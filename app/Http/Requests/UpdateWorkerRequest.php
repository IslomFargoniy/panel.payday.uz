<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerRequest extends FormRequest
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
            'phone' => $this->phone && trim($this->phone) !== '' ? trim($this->phone) : null,
            'address' => $this->address && trim($this->address) !== '' ? trim($this->address) : null,
            'comment' => $this->comment && trim($this->comment) !== '' ? trim($this->comment) : null,
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
            'work_time' => 'required',
            'end_time' => 'required',
            'hour_price' => 'nullable|numeric|min:0',
            'fine_price' => 'nullable|numeric|min:0',
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', \Illuminate\Validation\Rule::unique('workers', 'phone')->ignore($this->route('worker'))],
            'address' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'status' => 'nullable',
            'avatar' => 'nullable|image|max:5120',
        ];
    }
}
