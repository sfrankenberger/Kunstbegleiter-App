<?php

namespace App\Livewire\Pages;

use App\Enums\GuideLength;
use App\Models\AiCall;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reiter "Profil": Vorwissen-Profil und Laenge (die einzige echte Eingabe in Etappe 1), Kosten des Monats,
 * Passkeys (App\Livewire\PasskeyManager), Abmelden. Stimmen und Kopplung kommen in Etappe 4 und 5.
 */
#[Layout('components.layouts.app', ['title' => 'Profil'])]
class Profil extends Component
{
    public string $knowledge_profile = '';

    public string $preferred_length = 'normal';

    public bool $saved = false;

    public function mount(): void
    {
        $user = $this->user();
        $this->knowledge_profile = (string) $user->knowledge_profile;
        $this->preferred_length = $user->preferred_length?->value ?? GuideLength::Normal->value;
    }

    public function save(): void
    {
        $data = $this->validate([
            'knowledge_profile' => ['nullable', 'string', 'max:2000'],
            'preferred_length' => ['required', Rule::enum(GuideLength::class)],
        ]);

        $this->user()->fill($data)->save();
        $this->saved = true;
    }

    public function render(): View
    {
        $user = $this->user();
        $limit = $user->monthlyLimitCents();
        $spent = AiCall::monthCents($user);

        return view('livewire.pages.profil', [
            'user' => $user,
            'lengths' => GuideLength::cases(),
            'spentCents' => $spent,
            'limitCents' => $limit,
            'percent' => $limit > 0 ? min(100, (int) round($spent / $limit * 100)) : 0,
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
