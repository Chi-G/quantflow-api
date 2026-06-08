<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without:payload', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'payload' => ['required_without:file', 'array'],
            'payload.*.reference_number' => ['required_with:payload', 'string'],
            'payload.*.amount' => ['required_with:payload', 'numeric', 'min:0.01'],
            'payload.*.currency' => ['required_with:payload', 'string', 'size:3'],
            'payload.*.recipient_name' => ['required_with:payload', 'string'],
            'payload.*.recipient_account' => ['required_with:payload', 'string', 'size:10'],
            'payload.*.bank_code' => ['required_with:payload', 'string', 'min:3', 'max:6'],
        ];
    }
}
