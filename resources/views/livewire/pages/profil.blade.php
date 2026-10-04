<div class="flex flex-col gap-4">
    <div class="kb-card">
        <p class="font-semibold">{{ $user->name }}</p>
        <p class="text-sm text-stone-600">{{ $user->email }}</p>
    </div>

    <form wire:submit="save" class="kb-card flex flex-col gap-4">
        <div>
            <label for="knowledge_profile" class="kb-label">Vorwissen</label>
            <textarea id="knowledge_profile" wire:model="knowledge_profile" rows="4" class="kb-input" placeholder="z. B. Austria Guide, Schwerpunkt Wien um 1900, Barock gut, Gegenwartskunst weniger"></textarea>
            <p class="mt-1 text-xs text-stone-500">Wird jedem Audioguide mitgegeben, damit er auf Augenhöhe erzählt.</p>
            @error('knowledge_profile') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="preferred_length" class="kb-label">Länge der Audioguides</label>
            <select id="preferred_length" wire:model="preferred_length" class="kb-input">
                @foreach ($lengths as $length)
                    <option value="{{ $length->value }}">{{ $length->label() }}</option>
                @endforeach
            </select>
            @error('preferred_length') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="kb-button">Speichern</button>
        @if ($saved)
            <p class="text-center text-sm text-green-700">Gespeichert.</p>
        @endif
    </form>

    <div class="kb-card">
        <h2 class="font-semibold">Kosten diesen Monat</h2>
        <p class="mt-1 text-sm text-stone-600">{{ number_format($spentCents / 100, 2, ',', '.') }} € von {{ number_format($limitCents / 100, 2, ',', '.') }} €</p>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stone-200">
            <div class="h-2 rounded-full {{ $percent >= 80 ? 'bg-red-600' : 'bg-accent' }}" style="width: {{ $percent }}%"></div>
        </div>
        @if ($percent >= 80)
            <p class="mt-2 text-sm text-red-700">Du hast {{ $percent }} % deines Monatslimits verbraucht.</p>
        @endif
    </div>

    <div class="kb-card">
        <h2 class="font-semibold">Passkeys</h2>
        <livewire:passkey-manager />
    </div>

    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="kb-button-secondary">Abmelden</button>
    </form>
</div>
