<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && $this->route('customer')?->user_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = $customer ? $customer->id : null;
        
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => $customerId 
                ? "required|email|max:255|unique:customers,email,{$customerId}"
                : 'required|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:50',
            'organization' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date|before:today',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'A customer with this email address already exists.',
            'birthdate.before' => 'Birthdate must be in the past.',
            'notes.max' => 'Notes cannot exceed 2000 characters.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'job_title' => 'job title',
        ];
    }
}
