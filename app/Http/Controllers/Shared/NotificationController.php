<?php
namespace App\Http\Controllers\Shared;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
final class NotificationController extends Controller {
 public function index(Request $request): JsonResponse {
  $page=UserNotification::query()->where('user_id',$request->user()->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
  return ApiResponse::success(['items'=>$page->getCollection()->map(fn($row)=>$row->payload()),'meta'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'per_page'=>25,'total'=>$page->total()]]);
 }
 public function read(Request $request,string $notification): JsonResponse {
  $row=UserNotification::query()->where('user_id',$request->user()->id)->findOrFail($notification);
  $row->forceFill(['read_at'=>$row->read_at ?? now()])->save();
  return ApiResponse::success($row->payload());
 }
}
