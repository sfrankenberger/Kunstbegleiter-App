<?php

namespace App\Models;

use App\Enums\GuideLength;
use App\Models\Concerns\LogsChanges;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

#[Fillable(['name', 'email', 'password', 'knowledge_profile', 'focus_areas', 'preferred_length', 'voice_preferences', 'monthly_budget_cents', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Ein Nutzer (Sebastian, Martha) mit eigenem Vorwissen-Profil und eigenem Lerngedaechtnis (docs/grundgeruest.md).
 * `is_admin` oeffnet das Panel /admin. Kein Papierkorb (nur zwei Nutzer), aber Verlauf.
 */
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsChanges, Notifiable, PasskeyAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'focus_areas' => 'array',
            'voice_preferences' => 'array',
            'preferred_length' => GuideLength::class,
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * Monatslimit in Cent: eigener Wert am Nutzer, sonst der aus config/museumguide.php.
     */
    public function monthlyLimitCents(): int
    {
        return $this->monthly_budget_cents ?? (int) config('museumguide.costs.monthly_limit_cents');
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /** @return HasMany<Capture, $this> */
    public function captures(): HasMany
    {
        return $this->hasMany(Capture::class);
    }

    /** @return HasMany<KnowledgeItem, $this> */
    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    /** @return HasMany<AiCall, $this> */
    public function aiCalls(): HasMany
    {
        return $this->hasMany(AiCall::class);
    }

    /**
     * Aktiver Besuch: der juengste, dessen Gueltigkeit noch nicht abgelaufen ist.
     */
    public function activeVisit(): ?Visit
    {
        return $this->visits()->where('valid_until', '>', now())->latest('valid_until')->first();
    }

    /**
     * Der gekoppelte Partner (aktive Kopplung), sonst null.
     */
    public function partner(): ?User
    {
        $pairing = Pairing::query()->active()->forUser($this)->first();

        return $pairing?->partnerOf($this);
    }
}
