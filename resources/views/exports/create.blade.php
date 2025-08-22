@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('exports.index') }}" class="text-green-600 hover:text-green-800 mr-4">← Back</a>
            <h1 class="text-2xl font-bold text-gray-900">Export Customers</h1>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6">
            <form action="{{ route('exports.store') }}" method="POST">
                @csrf

                <div class="mb-6">
                    <label for="format" class="block text-sm font-medium text-gray-700 mb-2">
                        Export Format
                    </label>
                    <select name="format" id="format" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('format') border-red-500 @enderror">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="xlsx">Excel (XLSX)</option>
                        <option value="json">JSON</option>
                    </select>
                    @error('format')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Export Options</label>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_headers" value="1" checked class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Include column headers</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_notes" value="1" class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Include customer notes</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_timestamps" value="1" class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Include created/updated timestamps</span>
                        </label>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Filters</label>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="organization" class="block text-sm text-gray-600 mb-1">Organization</label>
                            <input type="text" 
                                   name="filters[organization]" 
                                   id="organization" 
                                   placeholder="Filter by organization"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        
                        <div>
                            <label for="job_title" class="block text-sm text-gray-600 mb-1">Job Title</label>
                            <input type="text" 
                                   name="filters[job_title]" 
                                   id="job_title" 
                                   placeholder="Filter by job title"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="created_from" class="block text-sm text-gray-600 mb-1">Created From</label>
                            <input type="date" 
                                   name="filters[created_from]" 
                                   id="created_from" 
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        
                        <div>
                            <label for="created_to" class="block text-sm text-gray-600 mb-1">Created To</label>
                            <input type="date" 
                                   name="filters[created_to]" 
                                   id="created_to" 
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        Search Query
                    </label>
                    <input type="text" 
                           name="filters[search]" 
                           id="search" 
                           placeholder="Search customers by name, email, or organization"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <p class="text-sm text-gray-500 mt-1">Leave empty to export all customers</p>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="{{ route('exports.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
                        Start Export
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">Export Information</h3>
                    <ul class="mt-2 text-sm text-green-700 space-y-1">
                        <li>• Large exports will be processed in the background</li>
                        <li>• You'll receive an email when the export is ready</li>
                        <li>• Export files are automatically deleted after 7 days</li>
                        <li>• Only your customer data will be exported</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection