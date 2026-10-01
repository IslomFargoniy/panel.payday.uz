<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user && $this->worker_id && !$user->hasRole('Admin')) {
            return $user->hasWorkerAccess((int) $this->worker_id);
        }
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
            'worker_id' => 'required|exists:workers,id',
            'amount' => 'required|numeric|gt:0',
            'comment' => 'nullable|string|max:255',
        ];
    }
}
