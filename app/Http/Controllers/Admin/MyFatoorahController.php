<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\RunMyFatoorahOperation;
use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\MyFatoorahRequest;
use App\Http\Requests\Finance\MyFatoorahResolutionRequest;
use App\Http\Resources\Shared\MyFatoorahEntityResource;
use App\Http\Resources\Shared\MyFatoorahOperationResource;
use App\Models\MyFatoorahEntity;
use App\Models\MyFatoorahOperation;
use App\Services\Payments\MyFatoorahCatalog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class MyFatoorahController extends Controller
{
    /**
     * List MyFatoorah capabilities.
     *
     * Lists implemented workflows and local feature switches. Enabled means configured locally; availability still depends on merchant-country activation and API-key permissions.
     */
    public function capabilities(MyFatoorahCatalog $catalog): JsonResponse
    {
        return ApiResponse::success(['operations' => array_map(fn (array $item): array => [
            'name' => $item['name'], 'feature' => $item['feature'], 'enabled' => $item['enabled'],
            'method' => $item['http_method'], 'path' => '/api/v1/admin/integrations/myfatoorah/'.$item['route'],
            'idempotency_required' => $item['mutates'], 'documentation' => $item['source'],
        ], $catalog->all())]);
    }

    /**
     * Run a MyFatoorah workflow.
     *
     * Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.
     */
    public function execute(MyFatoorahRequest $request, RunMyFatoorahOperation $action): JsonResponse
    {
        return ApiResponse::success($action->execute($request->user(), (string) $request->route('operation'), $request->validated()));
    }

    /**
     * List MyFatoorah operations.
     *
     * Returns the administrative operation history, including uncertain outcomes needing review. Request bodies and credentials are never returned.
     */
    public function operations(): JsonResponse
    {
        return ApiResponse::paginated(MyFatoorahOperationResource::class, MyFatoorahOperation::query()->orderByDesc('id')->paginate(25));
    }

    /**
     * Get a MyFatoorah operation.
     *
     * Retrieves the durable result of a workflow by local operation ID. A sending or uncertain operation must be checked against provider status before any replacement operation.
     */
    public function operation(int $operationRecord): JsonResponse
    {
        return ApiResponse::success(new MyFatoorahOperationResource(MyFatoorahOperation::query()->findOrFail($operationRecord)));
    }

    /**
     * List MyFatoorah business records.
     *
     * Lists invoices, subscriptions, suppliers, shipments, sessions, refunds, transfers, settlements and disputes. needs_refresh indicates a signed webhook or command requires a fresh status inquiry. This is an administrative view and may contain sensitive customer or bank data.
     */
    public function entities(): JsonResponse
    {
        return ApiResponse::paginated(MyFatoorahEntityResource::class, MyFatoorahEntity::query()->orderByDesc('id')->paginate(25));
    }

    /**
     * Get a MyFatoorah business record.
     *
     * Returns its latest provider snapshot and signed webhook fields. Webhook hints do not overwrite authoritative status or change application wallet balances.
     */
    public function entity(int $entity): JsonResponse
    {
        return ApiResponse::success(new MyFatoorahEntityResource(MyFatoorahEntity::query()->findOrFail($entity)));
    }

    /**
     * Record manual reconciliation evidence.
     *
     * After checking the merchant portal or provider support, an authorized administrator records the confirmed outcome of an uncertain operation. This is an operator attestation, not automatic provider verification. It never submits another provider request or changes a wallet. The original idempotency key remains permanently consumed.
     */
    public function resolve(MyFatoorahResolutionRequest $request, int $operationRecord, AuditLoggerInterface $audit): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $operationRecord, $audit): MyFatoorahOperation {
            $record = MyFatoorahOperation::query()->whereKey($operationRecord)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'uncertain' && ! ($record->status === 'sending' && $record->updated_at->lt(now()->subMinutes(5)))) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            $data = $request->validated();
            $record->forceFill(['status' => $data['outcome'], 'result' => ['manual_resolution' => $data, 'resolved_by' => $request->user()->id]])->save();
            $audit->record(new AuditEntry(AuditAction::MYFATOORAH_OPERATION_RESOLVED, $record, actor: $request->user(), context: ['outcome' => $data['outcome']]));

            return $record;
        }, 5);

        return ApiResponse::success(new MyFatoorahOperationResource($record));
    }
}
