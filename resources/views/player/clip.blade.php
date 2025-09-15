<!-- resources/views/player/clip.blade.php -->
<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-2xl font-bold mb-2">{{ $clip->name ?? 'Clip' }}</h1>
                <p class="text-gray-600 mb-4">from "{{ $track->title }}" by {{ $track->artist ?? 'Unknown Artist' }}</p>
                
                <div id="waveform" class="mb-4"></div>
                
                <audio id="audio" class="w-full" controls></audio>
                
                <div class="mt-4 flex justify-between items-center">
                    <div id="time-display" class="text-sm text-gray-600">0:00 / {{ gmdate('i:s', $clip->getDurationMs() / 1000) }}</div>
                    <div class="flex space-x-2">
                        <button id="play-btn" class="px-4 py-2 bg-blue-600 text-white rounded">Play</button>
                        <button id="pause-btn" class="px-4 py-2 bg-gray-600 text-white rounded">Pause</button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-4">Share This Clip</h2>
                <div class="flex items-center space-x-2">
                    <input type="text" id="clip-url" value="{{ $signedPlaybackUrl }}" readonly 
                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button onclick="copyClipUrl()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Copy URL
                    </button>
                </div>
                <p class="mt-2 text-sm text-gray-500">This link will expire in 24 hours.</p>
            </div>
        </div>
    </div>

</x-app-layout>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/wavesurfer.js@6"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const audio = document.getElementById('audio');
        const audioSrc = "{{ $signedPlaybackUrl }}";
        
        if (Hls.isSupported()) {
            const hls = new Hls();
            hls.loadSource(audioSrc);
            hls.attachMedia(audio);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                // Audio is ready to play
            });
        } else if (audio.canPlayType('application/vnd.apple.mpegurl')) {
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
            wavesurfer.load('', { peaks: @json($waveformData['peaks']), duration: {{ $clip->getDurationMs() / 1000 }} });
        @endif
        
        // Connect wavesurfer to audio element
        wavesurfer.setMediaElement(audio);
        
        // Update time display
        audio.addEventListener('timeupdate', function() {
            const currentTime = formatTime(audio.currentTime);
            const duration = formatTime(audio.duration);
            document.getElementById('time-display').textContent = `${currentTime} / ${duration}`;
        });
        
        // Play/Pause buttons
        document.getElementById('play-btn').addEventListener('click', function() {
            audio.play();
        });
        
        document.getElementById('pause-btn').addEventListener('click', function() {
            audio.pause();
        });
        
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
    });
    
    function copyClipUrl() {
        const urlInput = document.getElementById('clip-url');
        urlInput.select();
        document.execCommand('copy');
        alert('Clip URL copied to clipboard!');
    }
</script>
@endpush