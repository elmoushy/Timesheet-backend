<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by token validation
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => [
                'required',
                'string',
                'min:16', // Minimum length for security
                'max:255', // Reasonable upper limit
            ],
            'device_id' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refresh_token.required' => 'Refresh token is required',
            'refresh_token.string' => 'Refresh token must be a string',
            'refresh_token.min' => 'Refresh token appears to be invalid (too short)',
            'refresh_token.max' => 'Refresh token appears to be invalid (too long)',
            'device_id.max' => 'Device ID cannot exceed 255 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'refresh_token' => 'refresh token',
            'device_id' => 'device ID',
        ];
    }
}
