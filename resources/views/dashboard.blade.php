@extends('layouts.app')

@section('title', 'Dashboard')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <div class="flex space-x-3">
            <a href="{{ route('customers.create') }}" 
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Add Customer
            </a>
            <a href="{{ route('imports.create') }}" 
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Import Data
            </a>
        </div>
    </div>
@endsection

@section('content')
    <!-- Search Bar -->
    <div class="mb-8">
        <form action="{{ route('dashboard.search') }}" method="GET" class="max-w-2xl">
            <div class="flex">
                <input type="text" name="search" 
                       value="{{ request('search') }}"
                       placeholder="Search customers by name, email, or organization..." 
                       class="flex-1 rounded-l-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                <button type="submit" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-r-md font-medium">
                    Search
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Customers</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($statistics['total_customers'] ?? 0) }}</dd>
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
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Added This Month</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($statistics['customers_this_month'] ?? 0) }}</dd>
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
                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Avg. per Week</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($statistics['avg_customers_per_week'] ?? 0, 1) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Customers -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Customers</h3>
                        <a href="{{ route('customers.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                            View all →
                        </a>
                    </div>

                    @if($customers && $customers->count() > 0)
                        <div class="space-y-3">
                            @foreach($customers as $customer)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
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
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">{{ $customer->created_at->diffForHumans() }}</p>
                                        <a href="{{ route('customers.show', $customer->slug) }}" 
                                           class="text-indigo-600 hover:text-indigo-500 text-sm">View</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.712-3.714M14 40v-4a9.971 9.971 0 01.712-3.714M28 16a4 4 0 11-8 0 4 4 0 018 0zm-8 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No customers yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by creating your first customer.</p>
                            <div class="mt-6">
                                <a href="{{ route('customers.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Add Customer
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('customers.create') }}" 
                       class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        Add New Customer
                    </a>
                    <a href="{{ route('imports.create') }}" 
                       class="block w-full bg-green-600 hover:bg-green-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        Import Customers
                    </a>
                    <a href="{{ route('exports.create') }}" 
                       class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        Export Data
                    </a>
                    <a href="{{ route('audit.index') }}" 
                       class="block w-full bg-purple-600 hover:bg-purple-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        View Audit Trail
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            @if(isset($recentActivity) && $recentActivity->count() > 0)
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        @foreach($recentActivity as $activity)
                            <div class="text-sm">
                                <p class="text-gray-900">{{ $activity->description }}</p>
                                <p class="text-gray-500 text-xs">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('audit.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm">
                            View all activity →
                        </a>
                    </div>
                </div>
            @endif

            <!-- Top Organizations -->
            @if(isset($topOrganizations) && count($topOrganizations) > 0)
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Top Organizations</h3>
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