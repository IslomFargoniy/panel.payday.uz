<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => 'required|exists:branches,id',
            'mac_address' => 'required|string',
            'name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'connection_type' => 'nullable|in:isup,http_listening',
            'encryption_key' => 'nullable|string|max:255',
        ];
    }
    public function messages(): array
    {
        return [
            'branch_id.required' => 'The branch field is required.',
            'mac_address.required' => 'The MAC address field is required.',
        ];
    }




}
