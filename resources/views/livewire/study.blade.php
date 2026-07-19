@php
    $card = $this->card;
    $stats = $this->stats;
@endphp

<div
    class="shell"
    x-data
    @contextmenu.window="if (! $event.target.closest('a, select, button, input, textarea')) { $event.preventDefault(); $wire.skip() }"
>
    <div class="topbar">
        <a href="{{ url('/') }}" class="brand">Flash<span>cards</span></a>

        <div style="display: flex; gap: 0.4rem; align-items: center;">
            <span class="badge success">Confident: {{ $confidentCount }}</span>
            <span class="badge danger">Repeat: {{ $repeatCount }}</span>
            @if ($confidentCount || $repeatCount)
                <button type="button" class="ghost" style="padding: 0.35rem 0.7rem; font-size: 0.8rem;" wire:click="restartSession">
                    Reset
                </button>
            @endif
            <a href="/admin/cards" class="link">Manage</a>
        </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 0.625rem; margin-bottom: 1.25rem;">
        <div style="flex: 1 1 14rem; min-width: 12rem;">
            <select wire:model.live="subjectId">
                <option value="">All subjects ({{ $this->totalCards }} cards)</option>
                @foreach ($this->subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->cards_count }})</option>
                @endforeach
            </select>
        </div>

        <button
            type="button"
            class="{{ $weakOnly ? 'danger' : 'ghost' }}"
            wire:click="toggleWeakOnly"
            title="Drill only cards scoring below 0"
        >
            Weak only ({{ $stats['weak'] }})
        </button>
    </div>

    @if ($card)
        <div
            x-data
            @if ($revealed)
                @keydown.window.arrow-up.prevent="$wire.rate(1)"
                @keydown.window.arrow-down.prevent="$wire.rate(-1)"
            @else
                @keydown.window.space.prevent="$wire.reveal()"
                @keydown.window.enter.prevent="$wire.reveal()"
            @endif
            @keydown.window.arrow-right.prevent="$wire.skip()"
            wire:key="card-{{ $card->id }}-{{ $revealed ? 'a' : 'q' }}"
        >
            <div class="card">
                <div class="meta">
                    <span class="badge">{{ $card->subject?->name ?? 'No subject' }}</span>

                    <span class="badge {{ match ($card->strength()) {
                        'weak' => 'danger',
                        'learning' => 'warning',
                        'strong' => 'success',
                        default => '',
                    } }}">
                        {{ ucfirst($card->strength()) }} &middot;
                        {{ $card->confidence > 0 ? '+' : '' }}{{ $card->confidence }} pts{{ $card->isMaxed() ? ' (max)' : ($card->isFloored() ? ' (min)' : '') }}
                    </span>

                    <span class="badge">{{ $card->reviews }} {{ Str::plural('review', $card->reviews) }}</span>
                </div>

                <p class="question">{{ $card->question }}</p>

                @if ($revealed)
                    <p class="answer">{{ $card->answer }}</p>
                @endif

                <div class="controls">
                    @if (! $revealed)
                        <button type="button" class="primary" wire:click="reveal">
                            Show answer
                        </button>
                    @else
                        <button type="button" class="success" wire:click="rate(1)">
                            &uarr;&nbsp; I knew it (+1)
                        </button>
                        <button type="button" class="danger" wire:click="rate(-1)">
                            &darr;&nbsp; Repeat later (&minus;1)
                        </button>
                    @endif

                    <button type="button" class="ghost" wire:click="skip">
                        Skip
                    </button>
                </div>

                <p class="hint">
                    @if (! $revealed)
                        <kbd>Space</kbd> reveal &middot;
                    @else
                        <kbd>&uarr;</kbd> knew it &middot; <kbd>&darr;</kbd> repeat &middot;
                    @endif
                    <strong>Right-click</strong> or <kbd>&rarr;</kbd> to skip without changing the score
                </p>
            </div>
        </div>

        <div class="deck">
            <span class="badge">New: {{ $stats['new'] }}</span>
            <span class="badge danger">Weak: {{ $stats['weak'] }}</span>
            <span class="badge warning">Learning: {{ $stats['learning'] }}</span>
            <span class="badge success">Strong: {{ $stats['strong'] }}</span>
            @if ($skipped)
                <span class="badge">Skipped: {{ count($skipped) }}</span>
            @endif
        </div>
    @else
        <div class="card">
            <div class="empty">
                @if ($weakOnly)
                    <h2>No weak cards left</h2>
                    <p>
                        Nothing here is scoring below 0
                        @if ($subjectId) in this subject @endif
                        &mdash; the weak pile is clear.
                    </p>
                    <button type="button" class="primary" wire:click="toggleWeakOnly">
                        Study all cards
                    </button>
                @else
                    <h2>No cards to study</h2>
                    <p>
                        @if ($subjectId)
                            This subject has no cards yet. Pick another subject or add some.
                        @else
                            Add or import cards first, then come back here to study them.
                        @endif
                    </p>
                    <a href="/admin/cards" class="link">Go to cards</a>
                @endif
            </div>
        </div>
    @endif
</div>
