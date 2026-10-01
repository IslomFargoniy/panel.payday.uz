<?php

namespace App\Http\Requests;

use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $currentUser = $this->user();
        if (!$currentUser) {
            return false;
        }

        $targetUser = $this->route('user');
        $targetUserId = $targetUser instanceof User ? $targetUser->id : $targetUser;

        return $currentUser->hasRole('Admin') || $currentUser->id === (int) $targetUserId;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUser = $this->route('user');
        $targetUserId = $targetUser instanceof User ? $targetUser->id : $targetUser;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'digits_between:9,12',
                Rule::unique(User::class, 'phone')->ignore($targetUserId),
            ],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($targetUserId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
