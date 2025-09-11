<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        switch ($action) {
            case 'approve':
                return [
                    'comments' => ['nullable', 'string', 'max:1000'],
                ];

            case 'reject':
                return [
                    'rejection_reason' => ['required', 'string', 'max:1000'],
                ];

            case 'return':
                return [
                    'return_reason' => ['required', 'string', 'max:1000'],
                ];

            default:
                return [];
        }
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Rejection reason is required when rejecting an expense',
            'return_reason.required' => 'Return reason is required when returning an expense for edit',
        ];
    }
}
