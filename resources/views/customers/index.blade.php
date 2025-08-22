@extends('layouts.app')

@section('title', 'Customers')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Customers</h1>
        <div class="flex space-x-3">
            <a href="{{ route('exports.create') }}" 
               class="btn btn-secondary">
                Export
            </a>
            <a href="{{ route('customers.create') }}" 
               class="btn btn-primary">
                Add Customer
            </a>
        </div>
    </div>
@endsection

@section('content')
    <!-- Filters -->
    <div class="dashboard-card mb-6">
        <form method="GET" action="{{ route('customers.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-group">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" 
                           value="{{ request('search') }}"
                           placeholder="Name, email, organization..."
                           class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="organization" class="form-label">Organization</label>
                    <input type="text" name="organization" id="organization" 
                           value="{{ request('organization') }}"
                           placeholder="Filter by organization..."
                           class="form-input">
                </div>

                <div class="form-group">
                    <label for="created_from" class="form-label">Created From</label>
                    <input type="date" name="created_from" id="created_from" 
                           value="{{ request('created_from') }}"
                           class="form-input">
                </div>

                <div class="form-group">
                    <label for="created_to" class="form-label">Created To</label>
                    <input type="date" name="created_to" id="created_to" 
                           value="{{ request('created_to') }}"
                           class="form-input">
                </div>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="btn btn-primary">
                    Apply Filters
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Statistics -->
    @if(isset($statistics))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card-title">Total Results</div>
                <div class="dashboard-card-value">{{ number_format($customers->total()) }}</div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 4a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm8 0a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1h-6a1 1 0 01-1-1V8zm0 4a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1h-6a1 1 0 01-1-1v-2z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card-title">Organizations</div>
                <div class="dashboard-card-value">{{ number_format($statistics['unique_organizations'] ?? 0) }}</div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card-title">This Page</div>
                <div class="dashboard-card-value">{{ $customers->count() }}</div>
            </div>
        </div>
    @endif

    <!-- Customers Table -->
    <div class="customer-table">
        @if($customers->count() > 0)
            <div class="customer-table-content">
                <table class="customer-table-table">
                    <thead class="customer-table-thead">
                        <tr>
                            <th class="customer-table-th">Customer</th>
                            <th class="customer-table-th">Organization</th>
                            <th class="customer-table-th">Contact</th>
                            <th class="customer-table-th">Created</th>
                            <th class="customer-table-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="customer-table-tbody">
                        @foreach($customers as $customer)
                            <tr class="customer-table-tr">
                                <td class="customer-table-td">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center mr-3">
                                            <span class="text-sm font-medium text-gray-700">
                                                {{ substr($customer->first_name, 0, 1) }}{{ substr($customer->last_name, 0, 1) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <a href="{{ route('customers.show', $customer->slug) }}" class="hover:text-blue-600">
                                                    {{ $customer->full_name }}
                                                </a>
                                            </div>
                                            @if($customer->job_title)
                                                <div class="text-xs text-gray-500">{{ $customer->job_title }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="customer-table-td">
                                    @if($customer->organization)
                                        <span class="status-badge status-pending">{{ $customer->organization }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="customer-table-td">
                                    <div class="text-sm text-gray-900">{{ $customer->email }}</div>
                                    @if($customer->phone)
                                        <div class="text-sm text-gray-500">{{ $customer->phone }}</div>
                                    @endif
                                </td>
                                <td class="customer-table-td">
                                    <div class="text-sm text-gray-900">{{ $customer->created_at->diffForHumans() }}</div>
                                    @if($customer->updated_at->ne($customer->created_at))
                                        <div class="text-xs text-gray-500">Updated {{ $customer->updated_at->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td class="customer-table-td">
                                    <div class="flex items-center space-x-3">
                                        <a href="{{ route('customers.show', $customer->slug) }}" 
                                           class="text-blue-600 hover:text-blue-900 font-medium text-sm">
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

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
                        <a href="{{ route('customers.index') }}" class="text-blue-600 hover:text-blue-500">clear filters</a>.
                    @else
                        Get started by creating your first customer.
                    @endif
                </p>
                @if(!request()->hasAny(['search', 'organization', 'created_from', 'created_to']))
                    <div class="mt-6">
                        <a href="{{ route('customers.create') }}" class="btn btn-primary">
                            Add Customer
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection