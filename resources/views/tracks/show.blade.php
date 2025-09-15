<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">{{ $track->title }}</h1>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full 
                            @if($track->status === 'ready') bg-green-100 text-green-800
                            @elseif($track->status === 'processing') bg-yellow-100 text-yellow-800
                            @elseif($track->status === 'failed') bg-red-100 text-red-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst($track->status) }}
                        </span>
                    </div>

                    @if($track->artist)
                        <p class="text-lg text-gray-600 mb-4">by {{ $track->artist }}</p>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h2 class="text-lg font-semibold mb-2">Track Information</h2>
                            <dl class="grid grid-cols-2 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                <dd class="text-sm text-gray-900">
                                    @if($track->duration_ms)
                                        {{ gmdate('i:s', $track->duration_ms / 1000) }}
                                    @else
                                        -
                                    @endif
                                </dd>
                                
                                <dt class="text-sm font-medium text-gray-500">File Size</dt>
                                <dd class="text-sm text-gray-900">
                                    {{ number_format($track->getOriginalFileSize() / 1024 / 1024, 2) }} MB
                                </dd>
                                
                                <dt class="text-sm font-medium text-gray-500">Uploaded</dt>
                                <dd class="text-sm text-gray-900">{{ $track->created_at->format('M j, Y') }}</dd>
                                
                                <dt class="text-sm font-medium text-gray-500">Format</dt>
                                <dd class="text-sm text-gray-900">{{ strtoupper(pathinfo($track->original_path, PATHINFO_EXTENSION)) }}</dd>
                            </dl>
                        </div>

                        <div>
                            <h2 class="text-lg font-semibold mb-2">Streaming</h2>
                            @if($track->isReady())
                                <div class="space-y-2">
                                    <a href="{{ route('player.track', $track) }}" class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-bold py-2 px-4 rounded">
                                        Play in Player
                                    </a>
                                    <button onclick="copyToClipboard('{{ $signedPlaybackUrl }}')" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                        Copy Stream URL
                                    </button>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">
                                    @if($track->status === 'processing')
                                        Your track is being processed. This may take a few minutes.
                                    @elseif($track->status === 'failed')
                                        Processing failed: {{ $track->error_message }}
                                    @else
                                        Your track is queued for processing.
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    @if($track->isReady())
                        <div class="mt-8">
                            <h2 class="text-lg font-semibold mb-4">Clip Management</h2>
                            <a href="{{ route('player.track', $track) }}#create-clip" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Create Clip
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Stream URL copied to clipboard!');
        }, function() {
            alert('Failed to copy URL');
        });
    }
</script>
@endpush