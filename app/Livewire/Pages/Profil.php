<?php

namespace App\Livewire\Pages;

use App\Enums\GuideLength;
use App\Enums\GuideMode;
use App\Models\AiCall;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reiter "Profil": Vorwissen-Profil und Laenge (die einzige echte Eingabe in Etappe 1), Kosten des Monats,
 * Passkeys (App\Livewire\PasskeyManager), Passwort aendern, Abmelden. Kosten sieht nur ein Admin (14.10.2026). Stimmen und Kopplung kommen in Etappe 4 und 5.
 */
#[Layout('components.layouts.app', ['title' => 'Profil'])]
class Profil extends Component
{
    public string $knowledge_profile = '';

    public string $preferred_length = 'normal';

    public string $default_mode = 'quick';

    public bool $saved = false;

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public bool $passwordSaved = false;

    public function mount(): void
    {
        $user = $this->user();
        $this->knowledge_profile = (string) $user->knowledge_profile;
        $this->preferred_length = $user->preferred_length?->value ?? GuideLength::Normal->value;
        $this->default_mode = $user->default_mode?->value ?? GuideMode::Quick->value;
    }

    public function save(): void
    {
        $data = $this->validate([
            'knowledge_profile' => ['nullable', 'string', 'max:2000'],
            'preferred_length' => ['required', Rule::enum(GuideLength::class)],
            'default_mode' => ['required', Rule::enum(GuideMode::class)],
        ]);

        $this->user()->fill($data)->save();
        $this->saved = true;
    }

    public function savePassword(): void
    {
        $this->validate(['new_password' => ['required', 'string', 'min:8', 'confirmed']], [
            'new_password.min' => 'Mindestens 8 Zeichen.',
            'new_password.confirmed' => 'Die beiden Eingaben stimmen nicht überein.',
        ]);

        $this->user()->forceFill(['password' => Hash::make($this->new_password)])->save();
        $this->reset('new_password', 'new_password_confirmation');
        $this->passwordSaved = true;
    }

    public function render(): View
    {
        $user = $this->user();
        $limit = $user->monthlyLimitCents();
        $spent = AiCall::monthCents($user);

        return view('livewire.pages.profil', [
            'user' => $user,
            'lengths' => GuideLength::cases(),
            'modes' => GuideMode::cases(),
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
