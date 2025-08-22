@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Customer Imports</h1>
        <a href="{{ route('imports.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            New Import
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        @if($imports->count() > 0)
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($imports as $import)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $import->original_filename }}</div>
                                <div class="text-sm text-gray-500">{{ $import->file_size_human }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $import->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $import->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $import->status === 'processing' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $import->status === 'pending' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ ucfirst($import->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>Total: {{ $import->total_rows ?? 0 }}</div>
                                @if($import->processed_rows)
                                    <div class="text-gray-500">Processed: {{ $import->processed_rows }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $import->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <a href="{{ route('imports.show', $import) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                @if($import->status === 'processing')
                                    <button onclick="cancelImport({{ $import->id }})" class="text-red-600 hover:text-red-900">Cancel</button>
                                @endif
                                @if(in_array($import->status, ['completed', 'failed', 'cancelled']))
                                    <form method="POST" action="{{ route('imports.destroy', $import) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500 mb-4">No imports found</p>
                <a href="{{ route('imports.create') }}" class="text-blue-600 hover:text-blue-800">Create your first import</a>
            </div>
        @endif
    </div>

    @if($imports->hasPages())
        <div class="mt-4">
            {{ $imports->links() }}
        </div>
    @endif
</div>

<script>
function cancelImport(importId) {
    if (confirm('Are you sure you want to cancel this import?')) {
        fetch(`/imports/${importId}/cancel`, {
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
</script>
@endsection