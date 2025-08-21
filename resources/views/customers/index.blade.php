@extends('layouts.app')

@section('title', 'Customers')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Customers</h1>
        <div class="flex space-x-3">
            <a href="{{ route('exports.create') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Export
            </a>
            <a href="{{ route('customers.create') }}" 
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Add Customer
            </a>
        </div>
    </div>
@endsection

@section('content')
    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" action="{{ route('customers.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" 
                           value="{{ request('search') }}"
                           placeholder="Name, email, organization..."
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="organization" class="block text-sm font-medium text-gray-700">Organization</label>
                    <input type="text" name="organization" id="organization" 
                           value="{{ request('organization') }}"
                           placeholder="Filter by organization..."
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="created_from" class="block text-sm font-medium text-gray-700">Created From</label>
                    <input type="date" name="created_from" id="created_from" 
                           value="{{ request('created_from') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="created_to" class="block text-sm font-medium text-gray-700">Created To</label>
                    <input type="date" name="created_to" id="created_to" 
                           value="{{ request('created_to') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <div class="flex space-x-3">
                <button type="submit" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Apply Filters
                </button>
                <a href="{{ route('customers.index') }}" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Statistics -->
    @if(isset($statistics))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Results</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($customers->total()) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 4a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm8 0a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1h-6a1 1 0 01-1-1V8zm0 4a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1h-6a1 1 0 01-1-1v-2z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Organizations</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($statistics['unique_organizations'] ?? 0) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">This Page</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $customers->count() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Customers Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        @if($customers->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($customers as $customer)
                    <li class="hover:bg-gray-50">
                        <div class="px-4 py-4 flex items-center justify-between">
                            <div class="flex items-center min-w-0 flex-1">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ substr($customer->first_name, 0, 1) }}{{ substr($customer->last_name, 0, 1) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1 min-w-0">
                                    <div class="flex items-center space-x-3">
                                        <h3 class="text-sm font-medium text-gray-900 truncate">
                                            <a href="{{ route('customers.show', $customer->slug) }}" class="hover:text-indigo-600">
                                                {{ $customer->full_name }}
                                            </a>
                                        </h3>
                                        @if($customer->organization)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $customer->organization }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1">
                                        <p class="text-sm text-gray-500">{{ $customer->email }}</p>
                                        @if($customer->phone)
                                            <p class="text-sm text-gray-500">{{ $customer->phone }}</p>
                                        @endif
                                        @if($customer->job_title)
                                            <p class="text-xs text-gray-400">{{ $customer->job_title }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="hidden md:block text-sm text-gray-500">
                                    <p>Created {{ $customer->created_at->diffForHumans() }}</p>
                                    @if($customer->updated_at->ne($customer->created_at))
                                        <p class="text-xs">Updated {{ $customer->updated_at->diffForHumans() }}</p>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <a href="{{ route('customers.show', $customer->slug) }}" 
                                   class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                                    View
                                </a>
                                <a href="{{ route('customers.edit', $customer->slug) }}" 
                                   class="text-green-600 hover:text-green-900 font-medium text-sm">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('customers.destroy', $customer->slug) }}" 
                                      onsubmit="return confirm('Are you sure you want to delete this customer? This action cannot be undone.')"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 font-medium text-sm">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                {{ $customers->withQueryString()->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.712-3.714M14 40v-4a9.971 9.971 0 01.712-3.714M28 16a4 4 0 11-8 0 4 4 0 018 0zm-8 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No customers found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if(request()->hasAny(['search', 'organization', 'created_from', 'created_to']))
                        Try adjusting your search criteria or 
                        <a href="{{ route('customers.index') }}" class="text-indigo-600 hover:text-indigo-500">clear filters</a>.
                    @else
                        Get started by creating your first customer.
                    @endif
                </p>
                @if(!request()->hasAny(['search', 'organization', 'created_from', 'created_to']))
                    <div class="mt-6">
                        <a href="{{ route('customers.create') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Add Customer
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection