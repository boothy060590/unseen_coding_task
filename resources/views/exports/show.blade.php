@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('exports.index') }}" class="text-green-600 hover:text-green-800 mr-4">← Back</a>
            <h1 class="text-2xl font-bold text-gray-900">Export Details</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Export Information</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Filename</dt>
                        <dd class="text-sm text-gray-900">{{ $export->filename ?: 'customers_export_' . $export->created_at->format('Y_m_d_H_i') . '.' . strtolower($export->format ?? 'csv') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Format</dt>
                        <dd class="text-sm text-gray-900">{{ strtoupper($export->format ?? 'CSV') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $export->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $export->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $export->status === 'processing' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $export->status === 'pending' ? 'bg-gray-100 text-gray-800' : '' }}">
                                {{ ucfirst($export->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="text-sm text-gray-900">{{ $export->created_at->format('M d, Y H:i:s') }}</dd>
                    </div>
                    @if($export->completed_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Completed</dt>
                            <dd class="text-sm text-gray-900">{{ $export->completed_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                    @endif
                    @if($export->file_size)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">File Size</dt>
                            <dd class="text-sm text-gray-900">{{ $export->file_size_human }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Export Statistics</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total Records</dt>
                        <dd class="text-sm text-gray-900">{{ number_format($export->total_records ?? 0) }}</dd>
                    </div>
                    @if($export->filters)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Filters Applied</dt>
                            <dd class="text-sm text-gray-900">
                                @php
                                    $filters = is_string($export->filters) ? json_decode($export->filters, true) : $export->filters;
                                    $activeFilters = array_filter($filters ?? []);
                                @endphp
                                @if(empty($activeFilters))
                                    <span class="text-gray-500">None - All customers exported</span>
                                @else
                                    <ul class="space-y-1">
                                        @foreach($activeFilters as $key => $value)
                                            @if($value)
                                                <li class="text-xs bg-gray-100 px-2 py-1 rounded">{{ ucwords(str_replace('_', ' ', $key)) }}: {{ $value }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Options</dt>
                        <dd class="text-sm text-gray-900">
                            <ul class="space-y-1">
                                @if($export->include_headers ?? true)
                                    <li class="text-xs text-green-600">✓ Headers included</li>
                                @endif
                                @if($export->include_notes ?? false)
                                    <li class="text-xs text-green-600">✓ Notes included</li>
                                @endif
                                @if($export->include_timestamps ?? false)
                                    <li class="text-xs text-green-600">✓ Timestamps included</li>
                                @endif
                            </ul>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        @if($export->error_message)
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <h3 class="text-sm font-medium text-red-800">Error</h3>
                <p class="text-sm text-red-700 mt-1">{{ $export->error_message }}</p>
            </div>
        @endif

        @if($export->status === 'processing')
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="animate-spin h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-800">Export is currently processing...</p>
                        <button onclick="checkProgress()" class="text-sm text-yellow-600 hover:text-yellow-800 mt-1">Check Progress</button>
                    </div>
                </div>
            </div>
        @endif

        @if($export->status === 'completed' && $export->file_path)
            <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-800">Export completed successfully!</p>
                        <div class="mt-2">
                            <a href="{{ route('exports.download', $export) }}" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="mr-2 -ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                                Download Export
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex justify-between items-center">
            <div></div>
            
            @if(in_array($export->status, ['completed', 'failed', 'cancelled']))
                <form method="POST" action="{{ route('exports.destroy', $export) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700" onclick="return confirm('Are you sure you want to delete this export?')">
                        Delete Export
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
function checkProgress() {
    fetch(`{{ route('exports.progress', $export) }}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== '{{ $export->status }}') {
                location.reload();
            }
        });
}

// Auto-refresh for processing exports
@if($export->status === 'processing')
    setInterval(checkProgress, 5000);
@endif
</script>
@endsection