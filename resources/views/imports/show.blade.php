@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('imports.index') }}" class="text-blue-600 hover:text-blue-800 mr-4">← Back</a>
            <h1 class="text-2xl font-bold text-gray-900">Import Details</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Import Information</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Filename</dt>
                        <dd class="text-sm text-gray-900">{{ $import->original_filename }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">File Size</dt>
                        <dd class="text-sm text-gray-900">{{ $import->file_size_human }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $import->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $import->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $import->status === 'processing' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $import->status === 'pending' ? 'bg-gray-100 text-gray-800' : '' }}">
                                {{ ucfirst($import->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="text-sm text-gray-900">{{ $import->created_at->format('M d, Y H:i:s') }}</dd>
                    </div>
                    @if($import->completed_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Completed</dt>
                            <dd class="text-sm text-gray-900">{{ $import->completed_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Progress</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total Rows</dt>
                        <dd class="text-sm text-gray-900">{{ number_format($import->total_rows ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Processed Rows</dt>
                        <dd class="text-sm text-gray-900">{{ number_format($import->processed_rows ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Successful</dt>
                        <dd class="text-sm text-green-600">{{ number_format($import->successful_rows ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Failed</dt>
                        <dd class="text-sm text-red-600">{{ number_format($import->failed_rows ?? 0) }}</dd>
                    </div>
                    @if($import->total_rows > 0)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Progress</dt>
                            <dd>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($import->processed_rows / $import->total_rows) * 100 }}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ number_format(($import->processed_rows / $import->total_rows) * 100, 1) }}%</div>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        @if($import->error_message)
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <h3 class="text-sm font-medium text-red-800">Error</h3>
                <p class="text-sm text-red-700 mt-1">{{ $import->error_message }}</p>
            </div>
        @endif

        @if($import->status === 'processing')
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="animate-spin h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-800">Import is currently processing...</p>
                        <button onclick="checkProgress()" class="text-sm text-yellow-600 hover:text-yellow-800 mt-1">Check Progress</button>
                    </div>
                </div>
            </div>
        @endif

        @if($import->errors && $import->errors->count() > 0)
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Import Errors</h2>
                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Row</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($import->errors as $error)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $error->row_number }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600">{{ $error->error_message }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <details class="cursor-pointer">
                                            <summary class="text-blue-600 hover:text-blue-800">View Data</summary>
                                            <pre class="mt-2 text-xs bg-gray-100 p-2 rounded">{{ json_encode(json_decode($error->row_data), JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="flex justify-between items-center mt-6">
            <div class="flex space-x-4">
                @if($import->status === 'processing')
                    <button onclick="cancelImport()" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
                        Cancel Import
                    </button>
                @endif
            </div>
            
            @if(in_array($import->status, ['completed', 'failed', 'cancelled']))
                <form method="POST" action="{{ route('imports.destroy', $import) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700" onclick="return confirm('Are you sure you want to delete this import?')">
                        Delete Import
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
function checkProgress() {
    fetch(`{{ route('imports.progress', $import) }}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== '{{ $import->status }}') {
                location.reload();
            }
        });
}

function cancelImport() {
    if (confirm('Are you sure you want to cancel this import?')) {
        fetch(`{{ route('imports.cancel', $import) }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            }
        }).then(() => {
            location.reload();
        });
    }
}

// Auto-refresh for processing imports
@if($import->status === 'processing')
    setInterval(checkProgress, 5000);
@endif
</script>
@endsection