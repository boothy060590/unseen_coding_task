@extends('layouts.app')

@section('title', 'Dashboard')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <div class="flex space-x-3">
            <a href="{{ route('customers.create') }}"
               class="btn btn-primary">
                Add Customer
            </a>
            <a href="{{ route('imports.create') }}"
               class="btn btn-success">
                Import Data
            </a>
        </div>
    </div>
@endsection

@section('content')
    <!-- Search and Filter Bar -->
    <div class="mb-8">
        <!-- Search Container -->
        <div class="mb-6">
            <form action="{{ route('dashboard.search') }}" method="GET" class="max-w-2xl">
                <div class="search-container">
                    <div class="search-icon">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" name="search"
                           value="{{ request('search') }}"
                           placeholder="Search customers by name, email, or organization..."
                           class="search-input">
                    <button type="submit"
                            class="btn btn-primary absolute right-0 top-0 h-full rounded-l-none">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Filters -->
        <div class="filter-container">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="form-label text-xs">Organization</label>
                    <input type="text" 
                           data-filter="organization"
                           placeholder="Filter by organization..."
                           value="{{ request('organization') }}"
                           class="filter-input">
                </div>
                <div>
                    <label class="form-label text-xs">Job Title</label>
                    <input type="text" 
                           data-filter="jobTitle"
                           placeholder="Filter by job title..."
                           value="{{ request('job_title') }}"
                           class="filter-input">
                </div>
                <div>
                    <label class="form-label text-xs">Email Domain</label>
                    <input type="text" 
                           data-filter="email"
                           placeholder="Filter by email domain..."
                           value="{{ request('email_domain') }}"
                           class="filter-input">
                </div>
                <div>
                    <label class="form-label text-xs">Name</label>
                    <input type="text" 
                           data-filter="fullName"
                           placeholder="Filter by name..."
                           class="filter-input">
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
            <div class="dashboard-card-title">Total Customers</div>
            <div class="dashboard-card-value">{{ number_format($statistics['total_customers'] ?? 0) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="dashboard-card-title">Added This Month</div>
            <div class="dashboard-card-value">{{ number_format($statistics['monthly_growth'] ?? 0) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 4a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm8 0a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1h-6a1 1 0 01-1-1V8zm0 4a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1h-6a1 1 0 01-1-1v-2z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="dashboard-card-title">Organizations</div>
            <div class="dashboard-card-value">{{ number_format($statistics['organisation_count'] ?? 0) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="dashboard-card-title">Added this Week</div>
            <div class="dashboard-card-value">{{ number_format($statistics['weekly_growth'] ?? 0, 1) }}</div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Customers -->
        <div class="lg:col-span-2">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Recent Customers</h3>
                    <a href="{{ route('customers.index') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                        View all →
                    </a>
                </div>

                @if($customers && $customers->count() > 0)
                    <div class="space-y-3" data-filterable>
                        @foreach($customers as $customer)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors" 
                                 data-filterable-row 
                                 data-organization="{{ strtolower($customer->organization ?? '') }}"
                                 data-job-title="{{ strtolower($customer->job_title ?? '') }}"
                                 data-email="{{ strtolower(substr(strrchr($customer->email, '@'), 1)) }}"
                                 data-full-name="{{ strtolower($customer->full_name) }}">
                                <div class="flex items-center space-x-3">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ substr($customer->first_name, 0, 1) }}{{ substr($customer->last_name, 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $customer->full_name }}</p>
                                        <p class="text-sm text-gray-500">{{ $customer->email }}</p>
                                        @if($customer->organization)
                                            <p class="text-xs text-gray-400">{{ $customer->organization }}</p>
                                        @endif
                                        @if($customer->job_title)
                                            <p class="text-xs text-gray-400">{{ $customer->job_title }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">{{ $customer->created_at->diffForHumans() }}</p>
                                    <a href="{{ route('customers.show', $customer->slug) }}"
                                       class="text-blue-600 hover:text-blue-500 text-sm">View</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 text-sm text-gray-500 text-center" data-results-count>
                        Showing {{ $customers->count() }} customers
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.712-3.714M14 40v-4a9.971 9.971 0 01.712-3.714M28 16a4 4 0 11-8 0 4 4 0 018 0zm-8 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No customers yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating your first customer.</p>
                        <div class="mt-6">
                            <a href="{{ route('customers.create') }}" class="btn btn-primary">
                                Add Customer
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="dashboard-card">
                <h3 class="dashboard-card-title mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('customers.create') }}"
                       class="btn btn-primary w-full">
                        Add New Customer
                    </a>
                    <a href="{{ route('imports.create') }}"
                       class="btn btn-success w-full">
                        Import Customers
                    </a>
                    <a href="{{ route('exports.create') }}"
                       class="btn btn-secondary w-full">
                        Export Data
                    </a>
                    <a href="{{ route('audit.index') }}"
                       class="btn btn-secondary w-full">
                        View Audit Trail
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            @if(isset($recentActivity) && $recentActivity->count() > 0)
                <div class="dashboard-card">
                    <h3 class="dashboard-card-title mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        @foreach($recentActivity as $activity)
                            <div class="text-sm">
                                <p class="text-gray-900">{{ $activity->description }}</p>
                                <p class="text-gray-500 text-xs">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('audit.index') }}" class="text-blue-600 hover:text-blue-500 text-sm">
                            View all activity →
                        </a>
                    </div>
                </div>
            @endif

            <!-- Top Organizations -->
            @if(isset($topOrganizations) && count($topOrganizations) > 0)
                <div class="dashboard-card">
                    <h3 class="dashboard-card-title mb-4">Top Organizations</h3>
                    <div class="space-y-3">
                        @foreach($topOrganizations as $org)
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-900">{{ $org->organization ?: 'No Organization' }}</span>
                                <span class="text-sm text-gray-500">{{ $org->count }} customers</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
