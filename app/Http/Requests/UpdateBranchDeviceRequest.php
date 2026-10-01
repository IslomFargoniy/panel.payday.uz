<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $device = $this->route('branch_device');
        $branchId = $this->branch_id ?? ($device instanceof \App\Models\Branch\BranchDevice ? $device->branch_id : ($device ? \App\Models\Branch\BranchDevice::find($device)?->branch_id : null));
        return $user && $branchId ? $user->hasBranchAccess((int) $branchId) : false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mac_address' => $this->mac_address ? trim($this->mac_address) : '',
            'name' => $this->name && trim($this->name) !== '' ? trim($this->name) : null,
            'device_id' => $this->device_id && trim($this->device_id) !== '' ? trim($this->device_id) : null,
            'encryption_key' => $this->encryption_key && trim($this->encryption_key) !== '' ? trim($this->encryption_key) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $device = $this->route('branch_device');
        $deviceId = $device instanceof \App\Models\Branch\BranchDevice ? $device->id : $device;

        return [
            'branch_id' => 'required|exists:branches,id',
            'mac_address' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('branch_devices')
                    ->ignore($deviceId)
                    ->where(function ($query) {
                        return $query->where('status', true);
                    }),
            ],
            'name' => 'nullable|string|max:255',
            'device_id' => [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('branch_devices')
                    ->ignore($deviceId)
                    ->where(function ($query) {
                        return $query->where('status', true)->whereNotNull('device_id');
                    }),
            ],
            'connection_type' => 'nullable|in:isup,http_listening',
            'encryption_key' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
        ];
    }

}
