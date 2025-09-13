<?php

namespace App\Policies;

use App\Models\Clip;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClipPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }

    public function delete(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }
}