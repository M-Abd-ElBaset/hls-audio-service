<!-- resources/views/player/track.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold mb-2">{{ $track->title }}</h1>
        @if($track->artist)
            <p class="text-gray-600 mb-6">by {{ $track->artist }}</p>
        @endif
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div id="waveform" class="mb-4"></div>
            
            <audio id="audio" class="w-full" controls></audio>
            
            <div class="mt-4 flex justify-between items-center">
                <div id="time-display" class="text-sm text-gray-600">0:00 / {{ gmdate('i:s', $track->duration_ms / 1000) }}</div>
                <div class="flex space-x-2">
                    <button id="play-btn" class="px-4 py-2 bg-blue-600 text-white rounded">Play</button>
                    <button id="pause-btn" class="px-4 py-2 bg-gray-600 text-white rounded">Pause</button>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Create Clip</h2>
            <form id="clip-form" action="{{ route('api.tracks.clips.store', $track) }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="start_ms" class="block text-sm font-medium text-gray-700">Start (seconds)</label>
                        <input type="number" step="0.1" min="0" max="{{ $track->duration_ms / 1000 }}" 
                               name="start_ms" id="start_ms" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="end_ms" class="block text-sm font-medium text-gray-700">End (seconds)</label>
                        <input type="number" step="0.1" min="0" max="{{ $track->duration_ms / 1000 }}" 
                               name="end_ms" id="end_ms" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700">Clip Name (optional)</label>
                    <input type="text" name="name" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Create Clip</button>
            </form>
            
            <div id="clip-result" class="mt-4 hidden">
                <h3 class="text-lg font-semibold mb-2">Clip Created Successfully!</h3>
                <p>Shareable URL: <a id="clip-url" href="#" class="text-blue-600 underline" target="_blank"></a></p>
            </div>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/wavesurfer.js@6"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize HLS.js
        const audio = document.getElementById('audio');
        const audioSrc = "{{ route('stream.track.master', ['track' => $track->uuid, 'token' => request()->query('token')]) }}";
        
        if (Hls.isSupported()) {
            const hls = new Hls();
            hls.loadSource(audioSrc);
            hls.attachMedia(audio);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                // Audio is ready to play
            });
        } else if (audio.canPlayType('application/vnd.apple.mpegurl')) {
            // Native HLS support (Safari)
            audio.src = audioSrc;
        }
        
        // Initialize Wavesurfer.js
        const wavesurfer = WaveSurfer.create({
            container: '#waveform',
            waveColor: '#4F46E5',
            progressColor: '#3730A3',
            cursorColor: '#3730A3',
            barWidth: 2,
            barRadius: 3,
            barGap: 2,
            height: 100,
            responsive: true,
        });
        
        // Load waveform data if available
        @if($waveformData)
            wavesurfer.load('', { peaks: @json($waveformData['peaks']), duration: {{ $track->duration_ms / 1000 }} });
        @endif
        
        // Connect wavesurfer to audio element
        wavesurfer.setMediaElement(audio);
        
        // Update time display
        audio.addEventListener('timeupdate', function() {
            const currentTime = formatTime(audio.currentTime);
            const duration = formatTime(audio.duration);
            document.getElementById('time-display').textContent = `${currentTime} / ${duration}`;
            
            // Update clip form max values
            document.getElementById('start_ms').max = audio.duration;
            document.getElementById('end_ms').max = audio.duration;
        });
        
        // Play/Pause buttons
        document.getElementById('play-btn').addEventListener('click', function() {
            audio.play();
        });
        
        document.getElementById('pause-btn').addEventListener('click', function() {
            audio.pause();
        });
        
        // Clip form submission
        document.getElementById('clip-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            // Convert seconds to milliseconds
            formData.set('start_ms', Math.round(formData.get('start_ms') * 1000));
            formData.set('end_ms', Math.round(formData.get('end_ms') * 1000));
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.clip_url) {
                    document.getElementById('clip-url').href = data.clip_url;
                    document.getElementById('clip-url').textContent = data.clip_url;
                    document.getElementById('clip-result').classList.remove('hidden');
                }
            })
            .catch(error => console.error('Error:', error));
        });
        
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
    });
</script>
@endpush