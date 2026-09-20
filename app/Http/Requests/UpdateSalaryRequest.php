<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryRequest extends FormRequest
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
            'user_id' => 'required',
            'worker_id' => 'required',
            'amount' => 'required',
            'minute' => 'required',
            'hour_price' => 'required',
            'real_amount' => 'required',
            'from' => 'required',
            'to' => 'required',
            'comment' => 'nullable|string|max:255',
        ];
    }

}
