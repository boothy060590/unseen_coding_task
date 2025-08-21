@extends('layouts.app')

@section('title', $customer->full_name)

@section('header')
    <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                <span class="text-lg font-medium text-gray-700">
                    {{ substr($customer->first_name, 0, 1) }}{{ substr($customer->last_name, 0, 1) }}
                </span>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $customer->full_name }}</h1>
                @if($customer->job_title && $customer->organization)
                    <p class="text-gray-600">{{ $customer->job_title }} at {{ $customer->organization }}</p>
                @elseif($customer->job_title)
                    <p class="text-gray-600">{{ $customer->job_title }}</p>
                @elseif($customer->organization)
                    <p class="text-gray-600">{{ $customer->organization }}</p>
                @endif
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('audit.customer', $customer->slug) }}" 
               class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                View Activity
            </a>
            <a href="{{ route('customers.edit', $customer->slug) }}" 
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Edit Customer
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Contact Information -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Contact Information</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Personal details and contact information.</p>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Full name</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $customer->full_name }}</dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Email address</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <a href="mailto:{{ $customer->email }}" class="text-indigo-600 hover:text-indigo-500">
                                    {{ $customer->email }}
                                </a>
                            </dd>
                        </div>
                        @if($customer->phone)
                            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Phone number</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                    <a href="tel:{{ $customer->phone }}" class="text-indigo-600 hover:text-indigo-500">
                                        {{ $customer->phone }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if($customer->organization)
                            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Organization</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $customer->organization }}</dd>
                            </div>
                        @endif
                        @if($customer->job_title)
                            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Job title</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $customer->job_title }}</dd>
                            </div>
                        @endif
                        @if($customer->birthdate)
                            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Date of birth</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $customer->birthdate->format('F j, Y') }}
                                    <span class="text-gray-500">({{ $customer->birthdate->age }} years old)</span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Notes -->
            @if($customer->notes)
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Notes</h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">Additional information and notes about this customer.</p>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <div class="prose max-w-none">
                            {!! nl2br(e($customer->notes)) !!}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Recent Activity -->
            @if(isset($auditTrail) && $auditTrail->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Activity</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">Latest changes and updates to this customer.</p>
                        </div>
                        <a href="{{ route('audit.customer', $customer->slug) }}" 
                           class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                            View all →
                        </a>
                    </div>
                    <div class="border-t border-gray-200">
                        <ul class="divide-y divide-gray-200">
                            @foreach($auditTrail->take(5) as $activity)
                                <li class="px-4 py-4">
                                    <div class="flex space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                @if($activity->event === 'created')
                                                    <svg class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                @elseif($activity->event === 'updated')
                                                    <svg class="h-4 w-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                                    </svg>
                                                @else
                                                    <svg class="h-4 w-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                    </svg>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-gray-900">{{ $activity->description }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{ $activity->created_at->diffForHumans() }}
                                                @if($activity->causer)
                                                    by {{ $activity->causer->full_name }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('customers.edit', $customer->slug) }}" 
                       class="block w-full bg-green-600 hover:bg-green-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        Edit Customer
                    </a>
                    <a href="mailto:{{ $customer->email }}" 
                       class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        Send Email
                    </a>
                    @if($customer->phone)
                        <a href="tel:{{ $customer->phone }}" 
                           class="block w-full bg-purple-600 hover:bg-purple-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                            Call Customer
                        </a>
                    @endif
                    <a href="{{ route('audit.customer', $customer->slug) }}" 
                       class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center px-4 py-2 rounded-md text-sm font-medium">
                        View Full Activity
                    </a>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Customer Details</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Customer ID</dt>
                        <dd class="text-sm text-gray-900">{{ $customer->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Slug</dt>
                        <dd class="text-sm text-gray-900 font-mono">{{ $customer->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $customer->created_at->format('F j, Y g:i A') }}
                            <div class="text-xs text-gray-500">{{ $customer->created_at->diffForHumans() }}</div>
                        </dd>
                    </div>
                    @if($customer->updated_at->ne($customer->created_at))
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="text-sm text-gray-900">
                                {{ $customer->updated_at->format('F j, Y g:i A') }}
                                <div class="text-xs text-gray-500">{{ $customer->updated_at->diffForHumans() }}</div>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white shadow rounded-lg p-6 border border-red-200">
                <h3 class="text-lg font-medium text-red-900 mb-4">Danger Zone</h3>
                <p class="text-sm text-red-700 mb-4">
                    Once you delete a customer, there is no going back. This action cannot be undone and will also remove all associated audit trail data.
                </p>
                <form method="POST" action="{{ route('customers.destroy', $customer->slug) }}" 
                      onsubmit="return confirm('Are you sure you want to delete {{ $customer->full_name }}? This action cannot be undone and will remove all associated data including audit trails.')"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Delete Customer
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection