<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Services\SignedUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackController extends Controller
{
    public function index(Request $request)
    {
        $tracks = Track::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('tracks.index', compact('tracks'));
    }

    public function create()
    {
        return view('tracks.create');
    }

    public function store(Request $request)
    {
        // Validation is handled by StoreTrackRequest in API controller
        // For web uploads, we'll use the API endpoint
        return redirect()->route('tracks.index')
            ->with('error', 'Web upload not implemented. Use API for uploads.');
    }

    public function show(Track $track)
    {
        $this->authorize('view', $track);

        $signedUrlGenerator = app(SignedUrlGenerator::class);
        $token = $signedUrlGenerator->generateTrackToken(
            $track, 
            request()->ip(),
            3600 // 1 hour expiration
        );

        $signedPlaybackUrl = route('stream.track.master', [
            'track' => $track->uuid,
            'token' => $token
        ]);

        return view('tracks.show', compact('track', 'signedPlaybackUrl'));
    }
}