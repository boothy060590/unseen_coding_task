@extends('layouts.app')

@section('title', 'Import Customers')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Import Customers</h1>
        <a href="{{ route('imports.index') }}" class="btn btn-secondary">
            ← Back to Imports
        </a>
    </div>
@endsection

@section('content')
    <div class="max-w-2xl mx-auto">
        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="alert alert-error mb-6">
                <div class="font-medium">Please fix the following errors:</div>
                <ul class="mt-2 list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="dashboard-card">
            <form action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label for="file" class="form-label">CSV File</label>
                    <input type="file" 
                           name="file" 
                           id="file" 
                           accept=".csv,.txt"
                           class="form-input @error('file') border-red-500 @enderror">
                    @error('file')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-gray-500 mt-1">Upload a CSV or TXT file with customer data. Maximum file size: 10MB</p>
                </div>

                <div class="form-group">
                    <label class="flex items-center">
                        <input type="checkbox" name="has_headers" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">File has headers</span>
                    </label>
                </div>

                <div class="form-group">
                    <label for="delimiter" class="form-label">Delimiter</label>
                    <select name="delimiter" id="delimiter" class="form-select">
                        <option value="," selected>Comma (,)</option>
                        <option value=";">Semicolon (;)</option>
                        <option value="|">Pipe (|)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="encoding" class="form-label">File Encoding</label>
                    <select name="encoding" id="encoding" class="form-select">
                        <option value="UTF-8" selected>UTF-8</option>
                        <option value="ISO-8859-1">ISO-8859-1</option>
                    </select>
                </div>

                <div class="form-group">
                    <h3 class="dashboard-card-title mb-3">Expected CSV Format</h3>
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
                    <a href="{{ route('imports.index') }}" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Start Import
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 alert alert-info">
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
@endsection