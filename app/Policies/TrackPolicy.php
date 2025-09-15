<?php
// app/Policies/TrackPolicy.php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrackPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Track $track)
    {
        return $user->id === $track->user_id;
    }

    public function update(User $user, Track $track)
    {
        return $user->id === $track->user_id;
    }

    public function delete(User $user, Track $track)
    {
        return $user->id === $track->user_id;
    }
}