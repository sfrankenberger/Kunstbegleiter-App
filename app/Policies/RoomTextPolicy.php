<?php

namespace App\Policies;

use App\Models\RoomText;
use App\Models\User;

/**
 * Raumtexte sieht, aendert und loescht nur der Besitzer.
 */
class RoomTextPolicy
{
    public function view(User $user, RoomText $roomText): bool
    {
        return (int) $roomText->user_id === (int) $user->getKey();
    }

    public function update(User $user, RoomText $roomText): bool
    {
        return $this->view($user, $roomText);
    }

    public function delete(User $user, RoomText $roomText): bool
    {
        return $this->view($user, $roomText);
    }
}
