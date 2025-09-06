<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LogTimeSpentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hours' => 'required|numeric|min:0.1|max:24',
            'date' => 'nullable|date|before_or_equal:today',
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'hours.required' => 'Hours field is required.',
            'hours.numeric' => 'Hours must be a valid number.',
            'hours.min' => 'Hours must be at least 0.1.',
            'hours.max' => 'Hours cannot exceed 24 in a single day.',
            'date.date' => 'Date must be a valid date.',
            'date.before_or_equal' => 'Date cannot be in the future.',
            'description.max' => 'Description may not exceed 500 characters.',
        ];
    }
}
