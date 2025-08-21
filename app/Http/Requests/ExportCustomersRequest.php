<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportCustomersRequest extends FormRequest
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
            'format' => 'required|in:csv,json',
            'filters' => 'nullable|array',
            'filters.search' => 'nullable|string|max:255',
            'filters.organization' => 'nullable|string|max:255',
            'filters.job_title' => 'nullable|string|max:255',
            'filters.created_from' => 'nullable|date',
            'filters.created_to' => 'nullable|date|after_or_equal:filters.created_from',
            'include_notes' => 'boolean',
            'include_audit_trail' => 'boolean',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'format.required' => 'Export format is required.',
            'format.in' => 'Export format must be CSV or JSON.',
            'filters.created_to.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_notes' => $this->boolean('include_notes', false),
            'include_audit_trail' => $this->boolean('include_audit_trail', false),
        ]);
    }
}
