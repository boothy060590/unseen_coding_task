@extends('layouts.app')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Customer Exports</h1>
        <a href="{{ route('exports.create') }}" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
            New Export
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        @if($exports->count() > 0)
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Export</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($exports as $export)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $export->filename ?: 'customers_export_' . $export->created_at->format('Y_m_d_H_i') . '.csv' }}</div>
                                <div class="text-sm text-gray-500">{{ $export->format ?? 'CSV' }} format</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $export->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $export->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $export->status === 'processing' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $export->status === 'pending' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ ucfirst($export->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ number_format($export->total_records ?? 0) }} records
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $export->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <a href="{{ route('exports.show', $export) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                @if($export->status === 'completed' && $export->file_path)
                                    <a href="{{ route('exports.download', $export) }}" class="text-green-600 hover:text-green-900">Download</a>
                                @endif
                                @if(in_array($export->status, ['completed', 'failed', 'cancelled']))
                                    <form method="POST" action="{{ route('exports.destroy', $export) }}" class="inline">
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
                <p class="text-gray-500 mb-4">No exports found</p>
                <a href="{{ route('exports.create') }}" class="text-green-600 hover:text-green-800">Create your first export</a>
            </div>
        @endif
    </div>

    @if($exports->hasPages())
        <div class="mt-4">
            {{ $exports->links() }}
        </div>
    @endif
</div>
@endsection