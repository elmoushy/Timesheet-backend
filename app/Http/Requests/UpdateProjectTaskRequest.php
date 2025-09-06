<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProjectTaskRequest extends FormRequest
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
            'is_pinned' => 'sometimes|boolean',
            'is_important' => 'sometimes|boolean',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:to-do,doing,done,blocked',
            'due_date' => 'sometimes|nullable|date',
            'estimated_hours' => 'sometimes|nullable|integer|min:0',
            'actual_hours' => 'sometimes|nullable|integer|min:0',
            'notes' => 'sometimes|nullable|string',
            'progress_points' => 'sometimes|nullable|integer|min:0|max:100',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'is_pinned.boolean' => 'The pinned field must be true or false.',
            'is_important.boolean' => 'The important field must be true or false.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'status.in' => 'The status must be one of: to-do, doing, done, blocked.',
            'due_date.date' => 'The due date must be a valid date.',
            'estimated_hours.integer' => 'The estimated hours must be an integer.',
            'estimated_hours.min' => 'The estimated hours must be at least 0.',
            'actual_hours.integer' => 'The actual hours must be an integer.',
            'actual_hours.min' => 'The actual hours must be at least 0.',
            'progress_points.integer' => 'The progress points must be an integer.',
            'progress_points.min' => 'The progress points must be at least 0.',
            'progress_points.max' => 'The progress points may not be greater than 100.',
        ];
    }
}
