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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mac_address' => $this->mac_address ? trim($this->mac_address) : '',
            'name' => $this->name && trim($this->name) !== '' ? trim($this->name) : null,
            'device_id' => $this->device_id && trim($this->device_id) !== '' ? trim($this->device_id) : null,
            'encryption_key' => $this->encryption_key && trim($this->encryption_key) !== '' ? trim($this->encryption_key) : null,
            'connection_type' => $this->connection_type ?: 'http_listening',
        ]);
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
            'mac_address' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'connection_type' => 'nullable|in:isup,http_listening',
            'encryption_key' => 'nullable|string|max:255',
        ];
    }




}
