<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFirmHolidayRequest extends FormRequest
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
            'comment' => $this->comment && trim($this->comment) !== '' ? trim($this->comment) : null,
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
            'firm_id' => 'required|exists:firms,id',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'firm_id.required' => 'The firm field is required.',
            'name.required' => 'The name field is required.',
            'date.required' => 'The date field is required.',
        ];
    }

}
