@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Activity Log</h1>
        <div class="flex space-x-2">
            <a href="{{ route('audit.statistics') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                Statistics
            </a>
            <form method="POST" action="{{ route('audit.export') }}" class="inline">
                @csrf
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                    Export
                </button>
            </form>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('audit.search') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" 
                           placeholder="Customer name, email, or description"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="event" class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
                    <select name="event" id="event" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">All Events</option>
                        <option value="created" {{ request('event') === 'created' ? 'selected' : '' }}>Customer Created</option>
                        <option value="updated" {{ request('event') === 'updated' ? 'selected' : '' }}>Customer Updated</option>
                        <option value="deleted" {{ request('event') === 'deleted' ? 'selected' : '' }}>Customer Deleted</option>
                    </select>
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="flex space-x-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                        Search
                    </button>
                    <a href="{{ route('audit.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Clear
                    </a>
                </div>
                <a href="{{ route('audit.recent') }}" class="text-sm text-indigo-600 hover:text-indigo-800">View Recent Activity</a>
            </div>
        </form>
    </div>

    <!-- Activity List -->
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        @if($activities->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($activities as $activity)
                    <div class="p-6 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center
                                            {{ $activity->event === 'created' ? 'bg-green-100 text-green-600' : '' }}
                                            {{ $activity->event === 'updated' ? 'bg-blue-100 text-blue-600' : '' }}
                                            {{ $activity->event === 'deleted' ? 'bg-red-100 text-red-600' : '' }}">
                                            @if($activity->event === 'created')
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                                </svg>
                                            @elseif($activity->event === 'updated')
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                </svg>
                                            @elseif($activity->event === 'deleted')
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 012 0v4a1 1 0 11-2 0V7zM12 7a1 1 0 012 0v4a1 1 0 11-2 0V7z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $activity->description }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            @if($activity->subject)
                                                Customer: {{ $activity->subject?->full_name }}
                                                @if($activity->subject?->email)
                                                    ({{ $activity->subject->email }})
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                
                                @if($activity->properties && $activity->properties->count() > 0)
                                    <div class="mt-3">
                                        <details class="group">
                                            <summary class="cursor-pointer text-sm text-indigo-600 hover:text-indigo-800">
                                                View Details
                                            </summary>
                                            <div class="mt-2 p-3 bg-gray-50 rounded-md">
                                                @if($activity->properties->has('attributes') && $activity->properties->has('old'))
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                                        <div>
                                                            <h4 class="font-medium text-gray-900 mb-2">Before</h4>
                                                            <pre class="bg-white p-2 rounded border overflow-auto">{{ json_encode($activity->properties['old'], JSON_PRETTY_PRINT) }}</pre>
                                                        </div>
                                                        <div>
                                                            <h4 class="font-medium text-gray-900 mb-2">After</h4>
                                                            <pre class="bg-white p-2 rounded border overflow-auto">{{ json_encode($activity->properties['attributes'], JSON_PRETTY_PRINT) }}</pre>
                                                        </div>
                                                    </div>
                                                @else
                                                    <pre class="text-xs bg-white p-2 rounded border overflow-auto">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                                                @endif
                                            </div>
                                        </details>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex-shrink-0 text-right">
                                <p class="text-sm text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                                <p class="text-xs text-gray-400">{{ $activity->created_at->format('M d, Y H:i:s') }}</p>
                                @if($activity->properties && $activity->properties->has('ip_address'))
                                    <p class="text-xs text-gray-400">IP: {{ $activity->properties['ip_address'] }}</p>
                                @endif
                                <a href="{{ route('audit.activity', $activity->id) }}" class="text-xs text-indigo-600 hover:text-indigo-800">View</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No activity found</h3>
                <p class="mt-1 text-sm text-gray-500">No activity matches your search criteria.</p>
            </div>
        @endif
    </div>

    @if(method_exists($activities, 'hasPages') && $activities->hasPages())
        <div class="mt-4">
            {{ $activities->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection