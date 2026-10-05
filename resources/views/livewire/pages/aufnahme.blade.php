<div class="flex flex-col gap-4" @if ($capture->isRunning()) wire:poll.4s @elseif ($imagesPending) wire:poll.5s @endif>
    @php($bullets = fn (mixed $v): array => is_array($v) ? array_values(array_filter(array_map(fn ($x) => is_array($x) ? ($x['text'] ?? '') : (string) $x, $v), 'filled')) : array_values(array_filter(preg_split('/(?<=[.!?])\s+/u', (string) $v) ?: [], 'filled')))
    <div class="kb-card flex gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-xs uppercase tracking-wide text-stone-500">{{ $capture->visit?->museum?->name ?? 'Ohne Museum' }} · {{ $capture->created_at->format('d.m.Y H:i') }}</p>
            <p class="mt-1 text-lg font-semibold">{{ $capture->artwork?->title ?? ($capture->recognition['title'] ?? 'Werk wird erkannt') }}</p>
            @if ($capture->artwork?->artist)
                <p class="text-sm text-stone-600">{{ $capture->artwork->artist->name }}{{ $capture->artwork->dating ? ', '.$capture->artwork->dating : '' }}</p>
            @elseif (filled($capture->recognition['artist'] ?? null))
                <p class="text-sm text-stone-600">{{ $capture->recognition['artist'] }}</p>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-2">
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
        @if ($capture->photos->first())
            <img src="{{ $capture->photos->first()->url() }}" alt="" class="h-24 w-24 shrink-0 rounded-xl object-cover">
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
        {{-- Player fest am unteren Rand ueber den Reitern. wire:ignore: Livewire zeichnet ihn nie neu, sonst stoppt die Wiedergabe --}}
        <div class="fixed inset-x-0 z-10 border-t border-stone-200 bg-white/95 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur" style="bottom: calc(3.6rem + env(safe-area-inset-bottom))" wire:ignore wire:key="player-{{ $guide->getKey() }}-{{ $guide->hasAudio() ? 'mp3' : 'browser' }}">
            <div class="mx-auto max-w-lg px-4 py-2">
            @if ($guide->hasAudio())
                <div
                    x-data="{
                        player: null, playing: false, rate: 1, position: 0, duration: {{ (int) ($guide->duration_seconds ?? 0) }}, canRoute: false,
                        init() {
                            this.player = this.$refs.audio;
                            this.canRoute = typeof this.player.webkitShowPlaybackTargetPicker === 'function';
                            this.player.addEventListener('timeupdate', () => { this.position = this.player.currentTime });
                            this.player.addEventListener('loadedmetadata', () => { if (isFinite(this.player.duration)) this.duration = this.player.duration });
                            this.player.addEventListener('play', () => { this.playing = true });
                            this.player.addEventListener('pause', () => { this.playing = false });
                            this.player.addEventListener('ended', () => { this.playing = false });
                            if ('mediaSession' in navigator) {
                                navigator.mediaSession.metadata = new MediaMetadata({ title: @js($capture->artwork?->title ?? 'Audioguide'), artist: @js($capture->artwork?->artist?->name ?? ''), album: 'Kunstbegleiter' });
                                navigator.mediaSession.setActionHandler('play', () => this.player.play());
                                navigator.mediaSession.setActionHandler('pause', () => this.player.pause());
                                navigator.mediaSession.setActionHandler('seekbackward', () => this.back());
                            }
                        },
                        toggle() { this.playing ? this.player.pause() : this.player.play() },
                        back() { this.player.currentTime = Math.max(0, this.player.currentTime - 15) },
                        speed() { const rates = [0.8, 1, 1.2, 1.5]; this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length]; this.player.playbackRate = this.rate },
                        route() { try { this.player.webkitShowPlaybackTargetPicker() } catch (e) {} },
                        seek(event) { this.player.currentTime = event.target.value },
                        time(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') }
                    }"
                    class="flex flex-col gap-1"
                >
                    <audio x-ref="audio" src="{{ $guide->url() }}" preload="metadata" x-webkit-airplay="allow"></audio>
                    <div class="flex items-center gap-2">
                        <button type="button" class="kb-button w-auto flex-1 py-2" @click="toggle()" x-text="playing ? 'Pause' : 'Anhören'"></button>
                        <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="back()">15 s</button>
                        <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="speed()" x-text="rate + '×'"></button>
                        <button type="button" class="kb-button-secondary w-auto px-3 py-2" @click="route()" x-show="canRoute" title="Ausgabe wählen (AirPods, Lautsprecher)" aria-label="Ausgabe wählen">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-stone-500">
                        <span x-text="time(position)" class="w-9"></span>
                        <input type="range" min="0" :max="duration" step="1" :value="position" @input="seek($event)" class="flex-1">
                        <span x-text="time(duration)" class="w-9 text-right"></span>
                    </div>
                </div>
            @elseif ($segments->isNotEmpty() && ! $capture->isRunning())
                <div
                    x-data="{
                        chunks: @js(array_values(array_filter(preg_split('/(?<=[.!?])\s+/u', $segments->pluck('text')->implode(' ')) ?: [], fn ($c) => trim($c) !== ''))),
                        state: 'idle', rate: 1, supported: 'speechSynthesis' in window, voices: [], voiceUri: '', index: 0, error: '', showVoices: false,
                        init() {
                            if (! this.supported) return;
                            try { this.voiceUri = localStorage.getItem('kb-voice') || '' } catch (e) {}
                            this.loadVoices();
                            window.speechSynthesis.addEventListener('voiceschanged', () => this.loadVoices());
                        },
                        loadVoices() {
                            const all = window.speechSynthesis.getVoices().filter(v => v.lang && v.lang.toLowerCase().startsWith('de'));
                            // iOS nennt Kompakt-, Erweitert- und Premium-Fassung gleich, die Qualitaet steht nur in der voiceURI
                            const quality = v => /premium/i.test(v.voiceURI + v.name) ? 'Premium' : (/enhanced|erweitert|verbessert/i.test(v.voiceURI + v.name) ? 'Erweitert' : (/compact/i.test(v.voiceURI) ? 'Kompakt' : ''));
                            const score = v => (/siri/i.test(v.name) ? 40 : 0) + (quality(v) === 'Premium' ? 30 : quality(v) === 'Erweitert' ? 20 : 0) + (v.lang === 'de-AT' ? 10 : 0) + (v.localService ? 1 : 0);
                            all.sort((a, b) => score(b) - score(a) || a.name.localeCompare(b.name));
                            this.voices = all.map(v => ({ voiceURI: v.voiceURI, name: v.name, lang: v.lang, label: v.name + (quality(v) ? ', ' + quality(v) : '') + ' (' + v.lang + ')' }));
                            if (! this.voices.some(v => v.voiceURI === this.voiceUri)) this.voiceUri = this.voices[0]?.voiceURI || '';
                        },
                        voice() { return window.speechSynthesis.getVoices().find(v => v.voiceURI === this.voiceUri) || null },
                        chooseVoice(uri) { this.voiceUri = uri; try { localStorage.setItem('kb-voice', uri) } catch (e) {} if (this.state !== 'idle') this.playFrom(this.index) },
                        play() {
                            if (this.state === 'paused') { window.speechSynthesis.resume(); this.state = 'playing'; return }
                            this.playFrom(0);
                        },
                        playFrom(start) {
                            // Alle Saetze auf einmal in die Warteschlange (iOS bricht einzelne lange Texte nach etwa einer Minute ab)
                            window.speechSynthesis.cancel();
                            this.error = ''; this.state = 'playing'; this.index = start;
                            const v = this.voice();
                            this.chunks.slice(start).forEach((text, offset) => {
                                const u = new SpeechSynthesisUtterance(text);
                                if (v) { u.voice = v; u.lang = v.lang } else { u.lang = 'de-AT' }
                                u.rate = this.rate;
                                u.onstart = () => { this.index = start + offset };
                                if (start + offset === this.chunks.length - 1) u.onend = () => { this.state = 'idle' };
                                u.onerror = (e) => { if (e.error !== 'interrupted' && e.error !== 'canceled') { this.error = 'Vorlesen abgebrochen (' + e.error + ').'; this.state = 'idle' } };
                                window.speechSynthesis.speak(u);
                            });
                        },
                        pause() { window.speechSynthesis.pause(); this.state = 'paused' },
                        stop() { this.state = 'idle'; window.speechSynthesis.cancel() },
                        back() { if (this.state !== 'idle') this.playFrom(Math.max(0, this.index - 1)) },
                        speed() { const rates = [0.8, 1, 1.2, 1.5]; this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length]; if (this.state === 'playing') this.playFrom(this.index) },
                        destroy() { window.speechSynthesis.cancel() }
                    }"
                    class="flex flex-col gap-1"
                >
                    <template x-if="supported">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <button type="button" class="kb-button w-auto flex-1 py-2" @click="state === 'playing' ? pause() : play()" x-text="state === 'playing' ? 'Pause' : (state === 'paused' ? 'Weiter' : 'Vorlesen')"></button>
                                <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="back()" :disabled="state === 'idle'">Satz zurück</button>
                                <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="speed()" x-text="rate + '×'"></button>
                                <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="showVoices = ! showVoices" x-show="voices.length > 1" aria-label="Stimme wählen">Stimme</button>
                            </div>
                            <div class="flex items-center justify-between text-xs text-stone-500">
                                <span x-text="state === 'idle' ? 'Handy-Stimme, Ausgabe wie in iOS eingestellt (AirPods, Lautsprecher)' : 'Satz ' + (index + 1) + ' von ' + chunks.length"></span>
                                <span class="text-red-700" x-text="error"></span>
                            </div>
                            <select class="kb-input py-2 text-sm" :value="voiceUri" @change="chooseVoice($event.target.value)" x-show="showVoices" x-cloak>
                                <template x-for="v in voices" :key="v.voiceURI">
                                    <option :value="v.voiceURI" :selected="v.voiceURI === voiceUri" x-text="v.label"></option>
                                </template>
                            </select>
                            <p class="text-xs text-stone-500" x-show="showVoices" x-cloak>Safari bekommt die Siri-Stimmen nicht, nur die Stimmen aus Einstellungen > Bedienungshilfen > Gesprochene Inhalte > Stimmen > Deutsch. Dort bei Anna, Petra oder Markus die Fassung "Premium" oder "Erweitert" laden, dann erscheint sie hier mit diesem Zusatz. Für eine wirklich gute Stimme: ausführlicher Guide (Studio-Stimme).</p>
                        </div>
                    </template>
                    <p class="text-sm text-stone-600" x-show="! supported">Dieser Browser kann nicht vorlesen. Der Text steht oben zum Lesen.</p>
                </div>
            @else
                <p class="text-sm text-stone-600">Noch kein Audio. {{ $capture->isRunning() ? 'Die Stimme kommt gleich.' : 'Der Text steht oben zum Lesen.' }}</p>
            @endif
            </div>
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
        @php($sections = array_filter((array) ($sheet->sections ?? []), fn ($v) => filled($v)))
        @if ($sections !== [])
            @foreach ([
                'artist' => ['Künstler', 'user'],
                'provenance' => ['Provenienz', 'archive'],
                'interpretation' => ['Deutung', 'bulb'],
                'epoch' => ['Epoche', 'clock'],
                'look' => ['Genau hinschauen', 'eye'],
                'more' => ['Außerdem', 'plus'],
            ] as $key => [$label, $icon])
                @if (filled($sections[$key] ?? null))
                    <div class="kb-card flex gap-3">
                        @if ($key === 'artist' && $capture->artwork?->artist?->portrait_url)
                            <img src="{{ $capture->artwork->artist->portrait_url }}" alt="" class="h-20 w-16 shrink-0 rounded-lg object-cover" loading="lazy" title="{{ $capture->artwork->artist->portrait_credit }}">
                        @else
                            <x-kb-icon :name="$icon" class="mt-0.5 h-5 w-5 text-accent" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-stone-700">{{ $label }}{{ $key === 'artist' && $capture->artwork?->artist ? ': '.$capture->artwork->artist->name : '' }}</h2>
                            @if ($key === 'artist' && is_array($profile = $capture->artwork?->artist?->profile) && $profile !== [])
                                @if (filled($profile['born'] ?? null) || filled($profile['died'] ?? null))
                                    <p class="mt-1 text-sm text-stone-600">{{ filled($profile['born'] ?? null) ? '* '.$profile['born'] : '' }}{{ filled($profile['born'] ?? null) && filled($profile['died'] ?? null) ? ' · ' : '' }}{{ filled($profile['died'] ?? null) ? '† '.$profile['died'] : '' }}</p>
                                @endif
                                @foreach (['life' => 'Leben', 'style' => 'Stil und Technik', 'reception' => 'Rezeption'] as $pk => $pl)
                                    @if (filled($profile[$pk] ?? null))
                                        <p class="mt-2 text-xs uppercase tracking-wide text-stone-500">{{ $pl }}</p>
                                        <ul class="mt-0.5 list-disc pl-4 text-sm leading-relaxed">
                                            @foreach ($bullets($profile[$pk]) as $point)<li>{{ $point }}</li>@endforeach
                                        </ul>
                                    @endif
                                @endforeach
                                @if (filled($profile['key_works'] ?? null))
                                    <p class="mt-2 text-xs uppercase tracking-wide text-stone-500">Wichtige Werke</p>
                                    <ul class="mt-0.5 list-disc pl-4 text-sm leading-relaxed">
                                        @foreach ($profile['key_works'] as $kw)
                                            @if (is_array($kw) && filled($kw['title'] ?? null))<li>{{ $kw['title'] }}{{ filled($kw['year'] ?? null) ? ', '.$kw['year'] : '' }}{{ filled($kw['location'] ?? null) ? ' ('.$kw['location'].')' : '' }}</li>@endif
                                        @endforeach
                                    </ul>
                                @endif
                                <p class="mt-2 text-xs uppercase tracking-wide text-stone-500">Zu diesem Werk</p>
                            @endif
                            <ul class="mt-1 list-disc pl-4 text-sm leading-relaxed">
                                @foreach ($bullets($sections[$key]) as $point)
                                    <li>{{ $point }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            @endforeach
            @if ($capture->artwork?->relatedWorks?->isNotEmpty())
                <div class="kb-card">
                    <h2 class="text-sm font-semibold text-stone-700">Vergleichswerke</h2>
                    <div class="-mx-4 mt-2 flex gap-3 overflow-x-auto px-4 pb-1">
                        @foreach ($capture->artwork->relatedWorks as $work)
                            <div class="w-40 shrink-0">
                                @if ($work->image_url)
                                    <img src="{{ $work->image_url }}" alt="" class="h-40 w-40 rounded-lg bg-stone-100 object-cover" loading="lazy" title="{{ $work->image_credit }}">
                                @else
                                    <div class="flex h-40 w-40 items-center justify-center rounded-lg bg-stone-100 text-xs text-stone-400">kein Bild</div>
                                @endif
                                <p class="mt-1 text-sm font-medium leading-tight">{{ $work->title }}</p>
                                <p class="text-xs text-stone-500">{{ $work->artist }}{{ $work->year ? ', '.$work->year : '' }}</p>
                                @if ($work->reason)<p class="mt-1 text-xs leading-snug text-stone-600">{{ $work->reason }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if (filled($sections['quote_text'] ?? null))
                <div class="kb-card flex gap-3 bg-accent-soft">
                    <x-kb-icon name="quote" class="mt-0.5 h-5 w-5 text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm italic leading-relaxed">„{{ $sections['quote_text'] }}“</p>
                        <p class="mt-1 text-xs text-stone-600">{{ $sections['quote_speaker'] ?? '' }}{{ filled($sections['quote_context'] ?? null) ? ', '.$sections['quote_context'] : '' }}</p>
                    </div>
                </div>
            @endif
            @if (filled($sections['anecdote'] ?? null))
                <div class="kb-card flex gap-3">
                    <x-kb-icon name="sparkles" class="mt-0.5 h-5 w-5 text-accent" />
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-stone-700">Anekdote</h2>
                        <ul class="mt-1 list-disc pl-4 text-sm leading-relaxed">
                            @foreach ($bullets($sections['anecdote']) as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @if (filled($sections['curator_text'] ?? null))
                <div class="kb-card flex gap-3">
                    <x-kb-icon name="academic" class="mt-0.5 h-5 w-5 text-accent" />
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-stone-700">Kuratorenstimme</h2>
                        <p class="mt-1 text-sm italic leading-relaxed">„{{ $sections['curator_text'] }}“</p>
                        @if (filled($sections['curator_name'] ?? null))<p class="mt-1 text-xs text-stone-600">{{ $sections['curator_name'] }}</p>@endif
                    </div>
                </div>
            @endif
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
        <div class="kb-card" x-data="{ open: @js($showScript) }">
            <button type="button" class="flex w-full items-center justify-between text-sm font-semibold text-stone-700" @click="open = ! open">
                <span>Text zum Mitlesen</span>
                <span class="text-stone-400" x-text="open ? 'Zu' : 'Auf'"></span>
            </button>
            <div class="mt-3 flex flex-col gap-3 text-sm leading-relaxed" x-show="open" x-cloak>
                    @foreach ($segments as $segment)
                        <p class="{{ ($segment['role'] ?? 'narrator') === 'quote' ? 'border-l-2 border-accent pl-3 italic' : '' }}">{{ $segment['text'] }}</p>
                    @endforeach
            </div>
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

    <div class="kb-card" x-data="{ open: @js($showPhotos) }">
        <button type="button" class="flex w-full items-center justify-between text-sm font-semibold text-stone-700" @click="open = ! open">
            <span>Fotos ({{ $capture->photos->count() }})</span>
            <span class="text-stone-400" x-text="open ? 'Zu' : 'Auf'"></span>
        </button>
        <ul class="mt-3 flex flex-col gap-3" x-show="open" x-cloak>
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
    </div>

    @if ($capture->isQuick() && $capture->isDone() && $capture->artwork_id)
        <div class="kb-card flex flex-col gap-2">
            <p class="font-semibold">Mehr zu diesem Werk?</p>
            <p class="text-sm text-stone-600">Der ausführliche Guide recherchiert im Netz, prüft die Fakten und wird von der Studio-Stimme gesprochen. Dauert einige Minuten, kostet etwa 20 bis 40 Cent.</p>
            <button type="button" class="kb-button" wire:click="upgrade" wire:loading.attr="disabled">Ausführlichen Guide erstellen</button>
        </div>
    @endif

    @if ($capture->audioGuide)
        @php($guide = $capture->audioGuide)
        <div class="kb-card">
            <p class="mb-2 text-sm font-semibold text-stone-700">War der Guide gut?</p>
            <div class="flex items-center justify-between gap-2 text-sm">
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

    <button type="button" class="kb-button-secondary text-red-700" wire:click="delete" wire:confirm="Aufnahme in den Papierkorb legen?">Aufnahme löschen</button>

    @if ($capture->audioGuide)
        <div class="h-24" aria-hidden="true"></div>
    @endif
</div>
