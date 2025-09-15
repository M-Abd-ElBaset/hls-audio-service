<!-- resources/views/tracks/create.blade.php -->
<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h1 class="text-2xl font-bold mb-6">Upload Audio Track</h1>

                    <form action="{{ route('tracks.store') }}" method="POST" enctype="multipart/form-data" class="max-w-lg">
                        @csrf

                        <div class="mb-4">
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-2">Audio File</label>
                            <input type="file" name="file" id="file" accept=".mp3,.wav,.flac" required
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            @error('file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">MP3, WAV, or FLAC files up to 200MB. Maximum duration: 3 hours.</p>
                        </div>

                        <div class="mb-4">
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                            <input type="text" name="title" id="title" value="{{ old('title') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="artist" class="block text-sm font-medium text-gray-700 mb-2">Artist (Optional)</label>
                            <input type="text" name="artist" id="artist" value="{{ old('artist') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('artist')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('tracks.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Upload Track
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    document.getElementById('file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Set title from filename if not already set
            const titleInput = document.getElementById('title');
            if (!titleInput.value) {
                const filename = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
                titleInput.value = filename;
            }
        }
    });
</script>
@endpush