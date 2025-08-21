<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'integer|exists:customers,id',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'customer_ids.required' => 'Please select at least one customer.',
            'customer_ids.min' => 'Please select at least one customer.',
            'customer_ids.max' => 'Cannot delete more than 100 customers at once.',
            'customer_ids.*.exists' => 'One or more selected customers do not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('customer_ids')) {
                // Verify all customers belong to the authenticated user
                $user = auth()->user();
                $customerIds = $this->get('customer_ids', []);
                
                $ownedCount = \App\Models\Customer::whereIn('id', $customerIds)
                    ->where('user_id', $user->id)
                    ->count();
                
                if ($ownedCount !== count($customerIds)) {
                    $validator->errors()->add('customer_ids', 'You can only perform bulk operations on your own customers.');
                }
            }
        });
    }
}
