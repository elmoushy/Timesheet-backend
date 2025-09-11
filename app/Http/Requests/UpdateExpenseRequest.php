<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Expense;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        // Only allow updates for draft and returned_for_edit status
        return $expense instanceof Expense &&
               in_array($expense->status, ['draft', 'returned_for_edit']) &&
               $expense->employee_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'expenses' => ['required', 'array', 'min:1'],
            'expenses.*.date' => ['required', 'date'],
            'expenses.*.type' => ['required', 'string', 'in:meal,taxi,hotel,other'],
            'expenses.*.currency' => ['required', 'string', 'in:USD,EUR,EGP,SAR,AED'],
            'expenses.*.amount' => ['required', 'numeric', 'min:0.01'],
            'expenses.*.currency_rate' => ['required', 'numeric', 'min:0.0001'],
            'expenses.*.description' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Title is required',
            'expenses.required' => 'At least one expense item is required',
            'expenses.array' => 'Expenses must be an array',
            'expenses.min' => 'At least one expense item is required',
            'expenses.*.date.required' => 'Date is required for each expense item',
            'expenses.*.date.date' => 'Date must be a valid date format',
            'expenses.*.type.required' => 'Type is required for each expense item',
            'expenses.*.type.in' => 'Type must be one of: meal, taxi, hotel, other',
            'expenses.*.currency.required' => 'Currency is required for each expense item',
            'expenses.*.currency.in' => 'Currency must be one of: USD, EUR, EGP, SAR, AED',
            'expenses.*.amount.required' => 'Amount is required for each expense item',
            'expenses.*.amount.numeric' => 'Amount must be a number',
            'expenses.*.amount.min' => 'Amount must be greater than 0',
            'expenses.*.currency_rate.required' => 'Currency rate is required for all currencies',
            'expenses.*.currency_rate.numeric' => 'Currency rate must be a number',
            'expenses.*.currency_rate.min' => 'Currency rate must be greater than 0',
            'expenses.*.description.required' => 'Description is required for each expense item',
            'expenses.*.description.string' => 'Description must be text',
            'expenses.*.description.max' => 'Description cannot exceed 1000 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * For backward compatibility, we can still handle JSON strings from old implementations
     * but now we primarily expect array data from the frontend
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('expenses') && is_string($this->expenses)) {
            try {
                $decoded = json_decode($this->expenses, true);
                if (is_array($decoded)) {
                    $this->merge([
                        'expenses' => $decoded,
                    ]);
                }
            } catch (\Exception $e) {
                // Let validation handle the error
            }
        }
    }
}
