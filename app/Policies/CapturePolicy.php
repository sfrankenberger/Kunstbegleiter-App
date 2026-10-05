<?php

namespace App\Policies;

use App\Models\Capture;
use App\Models\User;

/**
 * Aufnahmen sieht der Besitzer; bei einem gemeinsamen Besuch auch der gekoppelte Partner (visits.shared_with_user_id).
 * Aendern und loeschen nur der Besitzer.
 */
class CapturePolicy
{
    public function view(User $user, Capture $capture): bool
    {
        if ((int) $capture->user_id === (int) $user->getKey()) {
            return true;
        }

        $visit = $capture->visit;

        return $visit !== null && (int) $visit->shared_with_user_id === (int) $user->getKey();
    }

    public function update(User $user, Capture $capture): bool
    {
        return (int) $capture->user_id === (int) $user->getKey();
    }

    public function delete(User $user, Capture $capture): bool
    {
        return $this->update($user, $capture);
    }
}
