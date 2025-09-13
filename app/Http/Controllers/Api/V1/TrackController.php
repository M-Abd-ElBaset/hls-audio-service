<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Support\Str;
use App\Http\Requests\StoreTrackRequest;
use App\Jobs\TranscodeAudioJob;
use App\Services\SignedUrlGenerator;

class TrackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tracks = Track::where('user_id', request()->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return response()->json($tracks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrackRequest $request)
    {
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();
        
        // Store original file
        $path = $file->store('originals/' . Str::random(2), 'local');
        
        $track = Track::create([
            'uuid' => Str::uuid(),
            'user_id' => $request->user()->id,
            'title' => $request->input('title', pathinfo($originalName, PATHINFO_FILENAME)),
            'artist' => $request->input('artist'),
            'original_path' => $path,
            'status' => 'pending',
        ]);
        
        // Dispatch transcode job
        TranscodeAudioJob::dispatch($track);
        
        return response()->json([
            'message' => 'Track uploaded successfully',
            'track_id' => $track->id,
            'uuid' => $track->uuid,
        ], 202);
    }

    /**
     * Display the specified resource.
     */
    public function show(Track $track)
    {
         $this->authorize('view', $track);
        
        $signedUrlGenerator = app(SignedUrlGenerator::class);
        $token = $signedUrlGenerator->generateTrackToken(
            $track, 
            request()->ip(),
            3600 // 1 hour expiration
        );
        
        $playbackUrl = route('stream.track.master', [
            'track' => $track->uuid,
            'token' => $token
        ]);
        
        return response()->json([
            'track' => $track->load('assets'),
            'signed_playback_url' => $playbackUrl,
        ]);
    }
}
