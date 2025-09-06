<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SsoExchangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // No authentication required for SSO exchange
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'access_token' => [
                'required_without:id_token',
                'string',
                'min:100', // JWT tokens are typically much longer
                'max:4096', // Reasonable upper limit
            ],
            'device_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'client_version' => [
                'nullable',
                'string',
                'max:100',
            ],
            'nonce' => [
                'nullable',
                'string',
                'max:255',
            ],
            // Also accept id_token parameter
            'id_token' => [
                'required_without:access_token',
                'string',
                'min:100',
                'max:4096',
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
            'access_token.required_without' => 'Either Microsoft access token or ID token is required',
            'access_token.string' => 'Microsoft access token must be a string',
            'access_token.min' => 'Microsoft access token appears to be invalid (too short)',
            'access_token.max' => 'Microsoft access token appears to be invalid (too long)',
            'id_token.required_without' => 'Either Microsoft ID token or access token is required',
            'id_token.string' => 'Microsoft ID token must be a string',
            'id_token.min' => 'Microsoft ID token appears to be invalid (too short)',
            'id_token.max' => 'Microsoft ID token appears to be invalid (too long)',
            'device_id.max' => 'Device ID cannot exceed 255 characters',
            'client_version.max' => 'Client version cannot exceed 100 characters',
            'nonce.max' => 'Nonce cannot exceed 255 characters',
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
            'access_token' => 'Microsoft access token',
            'device_id' => 'device ID',
            'client_version' => 'client version',
            'nonce' => 'nonce',
        ];
    }
}
