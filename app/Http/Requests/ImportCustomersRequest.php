<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportCustomersRequest extends FormRequest
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
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'has_headers' => 'boolean',
            'delimiter' => ['nullable', 'string', Rule::in([',', ';', '|'])],
            'encoding' => 'nullable|string|in:UTF-8,ISO-8859-1',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file to upload.',
            'file.mimes' => 'File must be a CSV or TXT file.',
            'file.max' => 'File size cannot exceed 10MB.',
            'delimiter.in' => 'Delimiter must be comma, semicolon, or pipe.',
            'encoding.in' => 'Encoding must be UTF-8 or ISO-8859-1.',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_headers' => $this->boolean('has_headers', true),
            'delimiter' => $this->input('delimiter', ','),
            'encoding' => $this->input('encoding', 'UTF-8'),
        ]);
    }
}
