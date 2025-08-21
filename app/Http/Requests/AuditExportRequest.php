<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditExportRequest extends FormRequest
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
            'date_from' => 'required|date|before_or_equal:date_to',
            'date_to' => 'required|date|after_or_equal:date_from|before_or_equal:today',
            'events' => 'nullable|array',
            'events.*' => 'string|in:created,updated,deleted,viewed,exported,imported',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'integer|exists:customers,id',
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
            'date_from.required' => 'Start date is required.',
            'date_to.required' => 'End date is required.',
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'date_to.before_or_equal' => 'End date cannot be in the future.',
            'events.*.in' => 'Invalid event type selected.',
            'customer_ids.*.exists' => 'Selected customer does not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure date range is not too large (max 1 year)
            if ($this->date_from && $this->date_to) {
                $from = \Carbon\Carbon::parse($this->date_from);
                $to = \Carbon\Carbon::parse($this->date_to);
                
                if ($from->diffInDays($to) > 365) {
                    $validator->errors()->add('date_to', 'Date range cannot exceed 365 days.');
                }
            }
        });
    }
}
