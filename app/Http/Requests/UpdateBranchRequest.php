<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
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
            'address' => $this->address && trim($this->address) !== '' ? trim($this->address) : null,
            'comment' => $this->comment && trim($this->comment) !== '' ? trim($this->comment) : null,
            'telegram_group_id' => $this->telegram_group_id && trim($this->telegram_group_id) !== '' ? trim($this->telegram_group_id) : null,
            'latitude' => $this->latitude && trim($this->latitude) !== '' ? trim($this->latitude) : null,
            'longitude' => $this->longitude && trim($this->longitude) !== '' ? trim($this->longitude) : null,
            'hour_price' => $this->filled('hour_price') ? $this->hour_price : 0,
            'fine_price' => $this->filled('fine_price') ? $this->fine_price : 0,
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
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'work_time' => 'required',
            'end_time' => 'required',
            'hour_price' => 'nullable|numeric|min:0',
            'fine_price' => 'nullable|numeric|min:0',
            'status' => 'required',
            'telegram_group_id' => 'nullable|string',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
        ];
    }
}