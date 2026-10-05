<div class="flex flex-col gap-6 pt-6">
    <div class="text-center">
        <img src="/branding/icon-192.png" alt="" class="mx-auto h-20 w-20 rounded-2xl shadow">
        <p class="mt-3 text-sm text-stone-600">Dein persönlicher Audioguide im Museum.</p>
    </div>

    <form wire:submit="login" class="kb-card flex flex-col gap-4">
        <div>
            <label for="email" class="kb-label">E-Mail</label>
            <input id="email" type="email" wire:model="email" class="kb-input" autocomplete="username webauthn" inputmode="email" required autofocus>
            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="kb-label">Passwort</label>
            <input id="password" type="password" wire:model="password" class="kb-input" autocomplete="current-password" required>
            @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-700">
            <input type="checkbox" wire:model="remember" class="h-5 w-5 rounded border-stone-300 text-accent">
            Angemeldet bleiben
        </label>
        <button type="submit" class="kb-button" wire:loading.attr="disabled">
            <span wire:loading.remove>Anmelden</span>
            <span wire:loading>Einen Moment ...</span>
        </button>
    </form>

    <div
        x-data="{
            supported: false, busy: false, error: '',
            async lib() { return window.KunstPasskeys || await import('/js/passkeys.js?v={{ @filemtime(public_path('js/passkeys.js')) ?: 1 }}'); },
            async init() { try { this.supported = (await this.lib()).passkeySupported(); } catch (e) { this.supported = false; } },
            async signIn() {
                if (this.busy) return;
                this.busy = true; this.error = '';
                try {
                    const result = await (await this.lib()).loginWithPasskey();
                    window.location.href = result.redirect || '/jetzt';
                } catch (e) {
                    this.error = window.KunstPasskeys ? window.KunstPasskeys.passkeyErrorMessage(e) : (e.message || 'Das hat nicht geklappt.');
                    this.busy = false;
                }
            },
        }"
        x-show="supported" x-cloak class="flex flex-col gap-2"
    >
        <div class="flex items-center gap-3 text-xs text-stone-500"><span class="h-px flex-1 bg-stone-200"></span>oder<span class="h-px flex-1 bg-stone-200"></span></div>
        <button type="button" class="kb-button-secondary" x-on:click="signIn" x-bind:disabled="busy">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33" /></svg>
            <span x-show="!busy">Mit Passkey anmelden</span>
            <span x-show="busy" x-cloak>Einen Moment ...</span>
        </button>
        <p x-show="error" x-text="error" x-cloak class="text-sm text-red-700"></p>
    </div>
</div>
