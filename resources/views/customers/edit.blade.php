@extends('layouts.app')

@section('title', 'Edit ' . $customer->full_name)

@section('header')
    <div class="flex items-center space-x-4">
        <a href="{{ route('customers.show', $customer->slug) }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-3xl font-bold text-gray-900">Edit {{ $customer->full_name }}</h1>
    </div>
@endsection

@section('content')
    <div class="max-w-3xl mx-auto">
        <form action="{{ route('customers.update', $customer->slug) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <!-- Personal Information -->
            <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Personal Information</h3>
                        <p class="mt-1 text-sm text-gray-500">Basic information about the customer.</p>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First name *</label>
                                <input type="text" name="first_name" id="first_name" 
                                       value="{{ old('first_name', $customer->first_name) }}" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('first_name') border-red-500 @enderror">
                                @error('first_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last name *</label>
                                <input type="text" name="last_name" id="last_name" 
                                       value="{{ old('last_name', $customer->last_name) }}" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('last_name') border-red-500 @enderror">
                                @error('last_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="email" class="block text-sm font-medium text-gray-700">Email address *</label>
                                <input type="email" name="email" id="email" 
                                       value="{{ old('email', $customer->email) }}" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('email') border-red-500 @enderror">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone number</label>
                                <input type="tel" name="phone" id="phone" 
                                       value="{{ old('phone', $customer->phone) }}"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('phone') border-red-500 @enderror">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="birthdate" class="block text-sm font-medium text-gray-700">Date of birth</label>
                                <input type="date" name="birthdate" id="birthdate" 
                                       value="{{ old('birthdate', $customer->birthdate?->format('Y-m-d')) }}"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('birthdate') border-red-500 @enderror">
                                @error('birthdate')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Information -->
            <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Professional Information</h3>
                        <p class="mt-1 text-sm text-gray-500">Work-related information about the customer.</p>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-4">
                                <label for="organization" class="block text-sm font-medium text-gray-700">Organization</label>
                                <input type="text" name="organization" id="organization" 
                                       value="{{ old('organization', $customer->organization) }}"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('organization') border-red-500 @enderror">
                                @error('organization')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="job_title" class="block text-sm font-medium text-gray-700">Job title</label>
                                <input type="text" name="job_title" id="job_title" 
                                       value="{{ old('job_title', $customer->job_title) }}"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('job_title') border-red-500 @enderror">
                                @error('job_title')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Additional Information</h3>
                        <p class="mt-1 text-sm text-gray-500">Notes and additional details about the customer.</p>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" id="notes" rows="4" 
                                      class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md @error('notes') border-red-500 @enderror"
                                      placeholder="Any additional notes or information about this customer...">{{ old('notes', $customer->notes) }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-sm text-gray-500">Maximum 2000 characters.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Summary -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Important Note</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Changes to this customer will be logged in the audit trail. Make sure all information is accurate before saving.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('customers.show', $customer->slug) }}" 
                   class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" 
                        class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Customer
                </button>
            </div>
        </form>

        <!-- Additional Actions -->
        <div class="mt-8 pt-8 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Customer Actions</h3>
                    <p class="mt-1 text-sm text-gray-500">Additional actions you can perform on this customer.</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('audit.customer', $customer->slug) }}" 
                       class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        View Activity Log
                    </a>
                    <form method="POST" action="{{ route('customers.destroy', $customer->slug) }}" 
                          onsubmit="return confirm('Are you sure you want to delete {{ $customer->full_name }}? This action cannot be undone.')"
                          class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Delete Customer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Enhancement Script -->
    @push('scripts')
    <script>
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            e.target.value = value;
        });

        // Character counter for notes
        const notesField = document.getElementById('notes');
        const maxLength = 2000;
        
        // Create counter element
        const counter = document.createElement('div');
        counter.className = 'text-sm text-gray-500 mt-1 text-right';
        counter.id = 'notes-counter';
        notesField.parentNode.appendChild(counter);
        
        function updateCounter() {
            const remaining = maxLength - notesField.value.length;
            counter.textContent = `${remaining} characters remaining`;
            
            if (remaining < 100) {
                counter.className = 'text-sm text-red-500 mt-1 text-right';
            } else {
                counter.className = 'text-sm text-gray-500 mt-1 text-right';
            }
        }
        
        notesField.addEventListener('input', updateCounter);
        updateCounter(); // Initialize counter

        // Track changes for confirmation
        let hasChanges = false;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea');
        const originalValues = {};
        
        // Store original values
        inputs.forEach(input => {
            originalValues[input.name] = input.value;
        });
        
        // Track changes
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                hasChanges = Object.keys(originalValues).some(name => {
                    const element = form.querySelector(`[name="${name}"]`);
                    return element && element.value !== originalValues[name];
                });
            });
        });
        
        // Warn about unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Don't warn when submitting the form
        form.addEventListener('submit', function() {
            hasChanges = false;
        });
    </script>
    @endpush
@endsection