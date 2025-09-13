<?php
// app/Http/Controllers/Api/V1/ClipController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Clip;
use App\Services\SignedUrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clips = Clip::where('user_id', $request->user()->id)
            ->with('track')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return response()->json($clips);
    }
    
    public function show(Request $request, Clip $clip): JsonResponse
    {
        $this->authorize('view', $clip);
        
        $signedUrlGenerator = app(SignedUrlGenerator::class);
        $token = $signedUrlGenerator->generateClipToken(
            $clip, 
            $request->ip(),
            86400 // 24 hour expiration for clips
        );
        
        $playbackUrl = route('stream.clip.master', [
            'clip' => $clip->uuid,
            'token' => $token
        ]);
        
        return response()->json([
            'clip' => $clip->load('track'),
            'signed_playback_url' => $playbackUrl,
        ]);
    }
    
    public function destroy(Request $request, Clip $clip): JsonResponse
    {
        $this->authorize('delete', $clip);
        
        $clip->delete();
        
        return response()->json([
            'message' => 'Clip deleted successfully',
        ]);
    }
}