<?php

namespace App\Policies;

use App\Models\Attempt;
use App\Models\User;

class AttemptPolicy
{
    /**
     * Attempts are private to the learner who started them.
     */
    public function view(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
