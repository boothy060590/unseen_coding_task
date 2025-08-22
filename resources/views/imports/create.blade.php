@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('imports.index') }}" class="text-blue-600 hover:text-blue-800 mr-4">← Back</a>
            <h1 class="text-2xl font-bold text-gray-900">Import Customers</h1>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6">
            <form action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-6">
                    <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                        CSV File
                    </label>
                    <input type="file" 
                           name="file" 
                           id="file" 
                           accept=".csv"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('file') border-red-500 @enderror">
                    @error('file')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-gray-500 mt-1">Upload a CSV file with customer data. Maximum file size: 10MB</p>
                </div>

                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="has_headers" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">File has headers</span>
                    </label>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Expected CSV Format</h3>
                    <div class="bg-gray-50 p-4 rounded-md">
                        <p class="text-sm text-gray-700 mb-2">Your CSV should contain the following columns:</p>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li><code class="bg-gray-200 px-1 rounded">first_name</code> - Required</li>
                            <li><code class="bg-gray-200 px-1 rounded">last_name</code> - Required</li>
                            <li><code class="bg-gray-200 px-1 rounded">email</code> - Required, must be unique</li>
                            <li><code class="bg-gray-200 px-1 rounded">phone</code> - Optional</li>
                            <li><code class="bg-gray-200 px-1 rounded">organization</code> - Optional</li>
                            <li><code class="bg-gray-200 px-1 rounded">job_title</code> - Optional</li>
                            <li><code class="bg-gray-200 px-1 rounded">birthdate</code> - Optional (YYYY-MM-DD format)</li>
                            <li><code class="bg-gray-200 px-1 rounded">notes</code> - Optional</li>
                        </ul>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="{{ route('imports.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Start Import
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Import Tips</h3>
                    <ul class="mt-2 text-sm text-blue-700 space-y-1">
                        <li>• Large files will be processed in the background</li>
                        <li>• You'll receive an email notification when complete</li>
                        <li>• Duplicate emails will be skipped automatically</li>
                        <li>• Invalid data rows will be logged for review</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection