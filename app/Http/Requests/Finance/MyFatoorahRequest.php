<?php

namespace App\Http\Requests\Finance;

use App\Services\Payments\MyFatoorahCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class MyFatoorahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() && $this->user()->isActive() && $this->user()->can('myfatoorah.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        $name = $this->route('operation');
        if (! is_string($name)) {
            return [];
        }
        $operation = app(MyFatoorahCatalog::class)->get($name);
        $rules = $this->propertyRules($operation['schema']);
        $rules['idempotency_key'] = [$operation['mutates'] ? 'required' : 'nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'];
        $rules['customer_id'] = ['sometimes', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')];
        $rules['consent_reference'] = ['required_with:SourceOfFund.Token,RecurringModel', 'required_if:TokenType,mftoken', 'string', 'max:255'];
        if ($operation['name'] === 'payments.execute') {
            $rules['PaymentMethodId'][] = 'required_without:SessionId';
            $rules['SessionId'][] = 'required_without:PaymentMethodId';
        }
        if (isset($rules['Token'])) {
            $rules['Token'] = ['required', 'string', 'max:16384'];
        }
        if (isset($rules['SupplierCode']) && $operation['name'] === 'deposits.invoices') {
            $rules['SupplierCode'][] = 'required_if:Type,Supplier';
        }
        if ($this->input('OperationType') === 'CAPTURE') {
            $rules['Amount'][] = 'required';
        }
        if ($this->input('RecurringModel.RecurringType') === 'Custom') {
            $rules['RecurringModel.IntervalDays'][] = 'required';
        }
        foreach (['IntegrationUrls.Redirection', 'IntegrationUrls.Webhook', 'CallBackUrl', 'ErrorUrl', 'WebhookUrl'] as $field) {
            if (isset($rules[$field])) {
                $rules[$field][] = 'url:https';
            }
        }
        foreach (['Customer.Email', 'CustomerEmail'] as $field) {
            if (isset($rules[$field])) {
                $rules[$field][] = 'email';
                $rules[$field][] = 'required_if:NotificationOption,EMAIL,EML,ALL';
            }
        }
        foreach (['Email', 'ShippingConsignee.EmailAddress'] as $field) {
            if (isset($rules[$field])) {
                $rules[$field][] = 'email';
            }
        }
        if (isset($rules['Customer.Mobile'])) {
            $rules['Customer.Mobile'][] = 'required_if:NotificationOption,SMS,ALL';
            $rules['Customer.Mobile.CountryCode'][] = 'required_with:Customer.Mobile';
            $rules['Customer.Mobile.Number'][] = 'required_with:Customer.Mobile';
        }
        if (isset($rules['CustomerMobile'])) {
            $rules['CustomerMobile'][] = 'required_if:NotificationOption,SMS,ALL';
        }
        foreach (['FileUpload', 'LogoFile'] as $field) {
            if (isset($rules[$field.'.Buffer'])) {
                $rules[$field.'.Buffer'] = ['required_with:'.$field, 'string', 'max:6990508'];
            }
        }

        return $rules;
    }

    private function propertyRules(array $schema, string $prefix = ''): array
    {
        $rules = [];
        foreach ($schema['properties'] ?? [] as $name => $property) {
            $field = $prefix.$name;
            $required = in_array($name, $schema['required'] ?? [], true);
            $rules[$field] = $required ? [$prefix === '' ? 'required' : 'required_with:'.rtrim($prefix, '.')] : [];
            $type = $property['type'] ?? 'string';
            $rules[$field][] = match ($type) {
                'number' => 'numeric', 'object' => isset($property['properties']) && $property['properties'] !== [] ? 'array:'.implode(',', array_keys($property['properties'])) : 'array',
                'array' => 'array', 'integer' => 'integer', 'boolean' => 'boolean', default => 'string',
            };
            if (isset($property['enum'])) {
                $rules[$field][] = Rule::in($property['enum']);
            }
            if ($type === 'string') {
                $rules[$field][] = 'max:'.($property['maxLength'] ?? 4096);
            }
            if (in_array($type, ['number', 'integer'], true)) {
                $rules[$field][] = 'min:'.($property['minimum'] ?? 0);
                $rules[$field][] = 'max:'.($property['maximum'] ?? 99999999);
                if (in_array($name, ['Amount', 'InvoiceValue', 'TransferAmount', 'Quantity', 'UnitPrice'], true)) {
                    $rules[$field][] = 'gt:0';
                }
                if ($name === 'CommissionPercentage') {
                    $rules[$field][] = 'max:100';
                }
            }
            if ($type === 'object') {
                $rules = [...$rules, ...$this->propertyRules($property, $field.'.')];
            }
            if ($type === 'array') {
                $rules[$field][] = 'min:1';
                $rules[$field][] = 'max:100';
                if (($property['items']['type'] ?? '') === 'object') {
                    $rules[$field.'.*'] = ['array:'.implode(',', array_keys($property['items']['properties'] ?? []))];
                    $rules = [...$rules, ...$this->propertyRules($property['items'], $field.'.*.')];
                } else {
                    $rules[$field.'.*'] = [($property['items']['type'] ?? 'string') === 'integer' ? 'integer' : 'string'];
                }
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $operation = app(MyFatoorahCatalog::class)->get((string) $this->route('operation'));
            $allowed = [...array_keys($operation['schema']['properties']), 'idempotency_key', 'customer_id', 'consent_reference', 'locale'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, __('validation.unexpected_field'));
            }
            foreach (['FileUpload', 'LogoFile'] as $field) {
                if (! $this->has($field.'.Buffer') || $validator->errors()->has($field.'.Buffer')) {
                    continue;
                }
                $buffer = base64_decode((string) $this->input($field.'.Buffer'), true);
                $extension = strtolower(pathinfo((string) $this->input($field.'.FileName'), PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'xls', 'xlsx', 'pdf', 'doc', 'docx'];
                $types = [
                    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'bmp' => ['image/bmp', 'image/x-ms-bmp'], 'gif' => ['image/gif'],
                    'pdf' => ['application/pdf'], 'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'], 'doc' => ['application/msword', 'application/x-ole-storage'],
                    'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                ];
                $mime = is_string($buffer) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($buffer) : false;
                if ($buffer === false || strlen($buffer) > 5 * 1024 * 1024 || $buffer === '' || ! in_array($extension, $allowedExtensions, true) || ! in_array($mime, $types[$extension], true) || ! in_array($this->input($field.'.MediaType'), $types[$extension], true) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._ -]{0,199}$/D', (string) $this->input($field.'.FileName'))) {
                    $validator->errors()->add($field.'.Buffer', __('validation.file', ['attribute' => $validator->getDisplayableAttribute($field)]));
                }
            }
        }];
    }
}
