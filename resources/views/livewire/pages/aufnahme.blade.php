<div class="flex flex-col gap-4" @if ($capture->isRunning()) wire:poll.4s @endif>
    <div class="kb-card">
        <p class="text-xs uppercase tracking-wide text-stone-500">{{ $capture->visit?->museum?->name ?? 'Ohne Museum' }} · {{ $capture->created_at->format('d.m.Y H:i') }}</p>
        <p class="mt-1 text-lg font-semibold">{{ $capture->artwork?->title ?? ($capture->recognition['title'] ?? 'Werk wird erkannt') }}</p>
        @if ($capture->artwork?->artist)
            <p class="text-sm text-stone-600">{{ $capture->artwork->artist->name }}{{ $capture->artwork->dating ? ', '.$capture->artwork->dating : '' }}</p>
        @elseif (filled($capture->recognition['artist'] ?? null))
            <p class="text-sm text-stone-600">{{ $capture->recognition['artist'] }}</p>
        @endif
        <div class="mt-2 flex items-center gap-2">
            <span class="rounded-full bg-stone-100 px-2 py-1 text-xs text-stone-600">{{ $capture->mode?->short() ?? 'Schnell' }}</span>
            @if ($capture->isRunning())
                <span class="inline-block h-3 w-3 animate-pulse rounded-full bg-accent"></span>
            @endif
            <p class="inline-block rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent">{{ $capture->progressLabel() }}</p>
        </div>
        @if ($capture->error_message)
            <p class="mt-2 text-sm text-red-700">{{ $capture->error_message }}</p>
        @endif
        @if ($notice)
            <p class="mt-2 text-sm text-red-700">{{ $notice }}</p>
        @endif
        @if ($capture->status === \App\Enums\CaptureStatus::Failed || ($capture->error_message && ! $capture->isRunning() && ! $capture->needs_confirmation && ! $capture->audioGuide?->hasAudio()))
            <button type="button" class="kb-button mt-3" wire:click="retry">Erneut versuchen</button>
        @endif
    </div>

    @if ($capture->needs_confirmation)
        <div class="kb-card flex flex-col gap-3">
            <p class="font-semibold">Welches Werk ist es?</p>
            <p class="text-sm text-stone-600">Die Erkennung ist unsicher{{ isset($capture->recognition['confidence']) ? ' ('.(int) round($capture->recognition['confidence'] * 100).' %)' : '' }}. Bitte bestätigen oder korrigieren.</p>
            @if (filled($capture->recognition['title'] ?? null))
                <button type="button" class="kb-button" wire:click="confirm(-1)">{{ $capture->recognition['title'] }}{{ filled($capture->recognition['artist'] ?? null) ? ' von '.$capture->recognition['artist'] : '' }}</button>
            @endif
            @foreach ($capture->recognition['alternatives'] ?? [] as $i => $alt)
                <button type="button" class="kb-button-secondary" wire:click="confirm({{ $i }})">{{ $alt['title'] ?? '' }}{{ filled($alt['artist'] ?? null) ? ' von '.$alt['artist'] : '' }}</button>
            @endforeach
            <form wire:submit="confirmManual" class="flex flex-col gap-2 border-t border-stone-200 pt-3">
                <label class="text-sm text-stone-600">Oder selbst eintragen</label>
                <input type="text" class="kb-input" wire:model="manualTitle" placeholder="Titel des Werks" required>
                <input type="text" class="kb-input" wire:model="manualArtist" placeholder="Künstlerin oder Künstler (optional)">
                <button type="submit" class="kb-button-secondary">So weitermachen</button>
            </form>
        </div>
    @endif

    @if ($capture->audioGuide)
        @php($guide = $capture->audioGuide)
        <div class="kb-card flex flex-col gap-3">
            @if ($guide->hasAudio())
                <div
                    x-data="{
                        player: null, playing: false, rate: 1, position: 0, duration: {{ (int) ($guide->duration_seconds ?? 0) }},
                        init() {
                            this.player = this.$refs.audio;
                            this.player.addEventListener('timeupdate', () => { this.position = this.player.currentTime });
                            this.player.addEventListener('loadedmetadata', () => { if (isFinite(this.player.duration)) this.duration = this.player.duration });
                            this.player.addEventListener('ended', () => { this.playing = false });
                        },
                        toggle() { this.playing ? this.player.pause() : this.player.play(); this.playing = ! this.playing },
                        back() { this.player.currentTime = Math.max(0, this.player.currentTime - 15) },
                        speed() { const rates = [0.8, 1, 1.2, 1.5]; this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length]; this.player.playbackRate = this.rate },
                        seek(event) { this.player.currentTime = event.target.value },
                        time(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') }
                    }"
                    class="flex flex-col gap-2"
                >
                    <audio x-ref="audio" src="{{ $guide->url() }}" preload="metadata"></audio>
                    <div class="flex items-center gap-3">
                        <button type="button" class="kb-button flex-1" @click="toggle()" x-text="playing ? 'Pause' : 'Anhören'"></button>
                        <button type="button" class="kb-button-secondary" @click="back()">15 s zurück</button>
                        <button type="button" class="kb-button-secondary" @click="speed()" x-text="rate + '×'"></button>
                    </div>
                    <input type="range" min="0" :max="duration" step="1" :value="position" @input="seek($event)" class="w-full">
                    <p class="text-xs text-stone-500"><span x-text="time(position)"></span> / <span x-text="time(duration)"></span></p>
                </div>
            @elseif ($segments->isNotEmpty() && ! $capture->isRunning())
                <div
                    x-data="{
                        text: @js($segments->pluck('text')->implode(' ')),
                        state: 'idle', rate: 1, supported: 'speechSynthesis' in window,
                        voice() {
                            const voices = window.speechSynthesis.getVoices();
                            return voices.find(v => v.lang === 'de-AT') || voices.find(v => v.lang.startsWith('de')) || null;
                        },
                        play() {
                            if (this.state === 'paused') { window.speechSynthesis.resume(); this.state = 'playing'; return; }
                            window.speechSynthesis.cancel();
                            const u = new SpeechSynthesisUtterance(this.text);
                            u.lang = 'de-AT'; u.rate = this.rate; const v = this.voice(); if (v) u.voice = v;
                            u.onend = () => { this.state = 'idle' }; u.onerror = () => { this.state = 'idle' };
                            window.speechSynthesis.speak(u); this.state = 'playing';
                        },
                        pause() { window.speechSynthesis.pause(); this.state = 'paused' },
                        stop() { window.speechSynthesis.cancel(); this.state = 'idle' },
                        speed() { const rates = [0.8, 1, 1.2, 1.5]; this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length]; if (this.state === 'playing') this.play() },
                        destroy() { window.speechSynthesis.cancel() }
                    }"
                    class="flex flex-col gap-2"
                >
                    <template x-if="supported">
                        <div class="flex items-center gap-3">
                            <button type="button" class="kb-button flex-1" @click="state === 'playing' ? pause() : play()" x-text="state === 'playing' ? 'Pause' : (state === 'paused' ? 'Weiter' : 'Vorlesen lassen')"></button>
                            <button type="button" class="kb-button-secondary" @click="stop()" x-show="state !== 'idle'">Von vorn</button>
                            <button type="button" class="kb-button-secondary" @click="speed()" x-text="rate + '×'"></button>
                        </div>
                    </template>
                    <p class="text-xs text-stone-500" x-show="supported">Handy-Stimme (am iPhone Siri), ohne Kosten. Für die Studio-Stimme unten den ausführlichen Guide erstellen.</p>
                    <p class="text-sm text-stone-600" x-show="! supported">Dieser Browser kann nicht vorlesen. Der Text steht unten zum Lesen.</p>
                </div>
            @else
                <p class="text-sm text-stone-600">Noch kein Audio. {{ $capture->isRunning() ? 'Die Stimme kommt gleich.' : 'Der Text steht unten zum Lesen.' }}</p>
            @endif

            <div class="flex items-center justify-between gap-2 border-t border-stone-200 pt-3 text-sm">
                <div class="flex gap-2">
                    <button type="button" class="rounded-full px-3 py-1 {{ $guide->feedback === 'up' ? 'bg-accent text-white' : 'bg-stone-100' }}" wire:click="feedback('up')" aria-label="Gut">👍</button>
                    <button type="button" class="rounded-full px-3 py-1 {{ $guide->feedback === 'down' ? 'bg-accent text-white' : 'bg-stone-100' }}" wire:click="feedback('down')" aria-label="Nicht gut">👎</button>
                </div>
                <div class="flex gap-1 text-xs">
                    @foreach (['too_easy' => 'Zu leicht', 'right' => 'Passt', 'too_hard' => 'Zu schwer'] as $value => $label)
                        <button type="button" class="rounded-full px-2 py-1 {{ $guide->difficulty_feedback === $value ? 'bg-accent text-white' : 'bg-stone-100' }}" wire:click="difficulty('{{ $value }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($capture->isQuick() && $capture->isDone() && $capture->artwork_id)
        <div class="kb-card flex flex-col gap-2">
            <p class="font-semibold">Mehr zu diesem Werk?</p>
            <p class="text-sm text-stone-600">Der ausführliche Guide recherchiert im Netz, prüft die Fakten und wird von der Studio-Stimme gesprochen. Dauert einige Minuten, kostet etwa 20 bis 40 Cent.</p>
            <button type="button" class="kb-button" wire:click="upgrade" wire:loading.attr="disabled">Ausführlichen Guide erstellen</button>
        </div>
    @endif

    @if ($capture->factSheet)
        @php($sheet = $capture->factSheet)
        @if (filled($sheet->key_facts))
            <div class="kb-card">
                <h2 class="text-sm font-semibold text-stone-700">Kurzfakten</h2>
                <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    @foreach ($sheet->key_facts as $fact)
                        @if (is_array($fact))
                            <dt class="text-stone-500">{{ $fact['label'] ?? '' }}</dt>
                            <dd>{{ $fact['value'] ?? '' }}</dd>
                        @else
                            <dd class="col-span-2">{{ $fact }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif
        @if (filled($sheet->key_statements))
            <div class="kb-card">
                <h2 class="text-sm font-semibold text-stone-700">Kernaussagen</h2>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($sheet->key_statements as $statement)
                        <li>{{ is_array($statement) ? ($statement['text'] ?? '') : $statement }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (filled($sheet->guest_ideas))
            <div class="kb-card">
                <h2 class="text-sm font-semibold text-stone-700">Für deine Gäste</h2>
                <dl class="mt-2 flex flex-col gap-2 text-sm">
                    @foreach (['opener' => 'Einstieg', 'question' => 'Frage an die Gruppe', 'anecdote' => 'Anekdote', 'vienna_link' => 'Wien-Bezug'] as $key => $label)
                        @if (filled($sheet->guest_ideas[$key] ?? null))
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-stone-500">{{ $label }}</dt>
                                <dd>{{ $sheet->guest_ideas[$key] }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @foreach ($sheet->guest_ideas as $key => $idea)
                        @if (is_int($key))
                            <dd>{{ is_array($idea) ? ($idea['text'] ?? '') : $idea }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif
        @if (filled($sheet->cross_references))
            <div class="kb-card">
                <h2 class="text-sm font-semibold text-stone-700">Querverweise</h2>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($sheet->cross_references as $ref)
                        <li>{{ is_array($ref) ? ($ref['text'] ?? '') : $ref }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    @if ($segments->isNotEmpty())
        <div class="kb-card">
            <button type="button" class="flex w-full items-center justify-between text-sm font-semibold text-stone-700" wire:click="$toggle('showScript')">
                <span>Text zum Mitlesen</span>
                <span class="text-stone-400">{{ $showScript ? 'Zu' : 'Auf' }}</span>
            </button>
            @if ($showScript)
                <div class="mt-3 flex flex-col gap-3 text-sm leading-relaxed">
                    @foreach ($segments as $segment)
                        <p class="{{ ($segment['role'] ?? 'narrator') === 'quote' ? 'border-l-2 border-accent pl-3 italic' : '' }}">{{ $segment['text'] }}</p>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if ($sources !== [])
        <div class="kb-card">
            <h2 class="text-sm font-semibold text-stone-700">Quellen</h2>
            <ul class="mt-2 flex flex-col gap-1 text-sm">
                @foreach ($sources as $source)
                    <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener" class="break-all text-accent underline">{{ $source['title'] ?? $source['url'] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="kb-card">
        <button type="button" class="flex w-full items-center justify-between text-sm font-semibold text-stone-700" wire:click="$toggle('showPhotos')">
            <span>Fotos ({{ $capture->photos->count() }})</span>
            <span class="text-stone-400">{{ $showPhotos ? 'Zu' : 'Auf' }}</span>
        </button>
        @if ($showPhotos)
            <ul class="mt-3 flex flex-col gap-3">
                @foreach ($capture->photos as $photo)
                    <li class="flex flex-col gap-2" wire:key="photo-{{ $photo->getKey() }}">
                        <img src="{{ $photo->url() }}" alt="" class="w-full rounded-xl object-contain" style="max-height: 60vh">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-stone-500">{{ $photo->width }} × {{ $photo->height }} px</span>
                            <select class="kb-input w-auto py-2 text-sm" wire:change="setType({{ $photo->getKey() }}, $event.target.value)">
                                @foreach ($photoTypes as $type)
                                    <option value="{{ $type->value }}" @selected($photo->type === $type)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($costCents > 0)
        <p class="text-center text-xs text-stone-400">Kosten dieser Aufnahme: {{ number_format($costCents / 100, 2, ',', '.') }} €</p>
    @endif

    <button type="button" class="kb-button-secondary text-red-700" wire:click="delete" wire:confirm="Aufnahme in den Papierkorb legen?">Aufnahme löschen</button>
</div>
