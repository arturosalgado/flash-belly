<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSession extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'last_seen_at',
        'ended_at',
        'cards_studied',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
            'cards_studied' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function start(User $user): self
    {
        static::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->each(function (self $session): void {
                $session->update([
                    'ended_at' => $session->last_seen_at ?? now(),
                ]);
            });

        return static::create([
            'user_id' => $user->id,
            'started_at' => now(),
            'last_seen_at' => now(),
            'cards_studied' => 0,
        ]);
    }

    public static function finish(User $user): void
    {
        static::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->each(function (self $session): void {
                $session->update([
                    'ended_at' => now(),
                    'last_seen_at' => now(),
                ]);
            });
    }

    public static function current(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    public static function touchCurrent(User $user): void
    {
        $session = static::current($user) ?? static::start($user);

        if ($session->last_seen_at?->greaterThan(now()->subMinute())) {
            return;
        }

        $session->update(['last_seen_at' => now()]);
    }

    public static function recordCard(User $user): void
    {
        $session = static::current($user) ?? static::start($user);

        $session->update([
            'cards_studied' => $session->cards_studied + 1,
            'last_seen_at' => now(),
        ]);
    }

    public function lastedLabel(): string
    {
        $end = $this->ended_at ?? $this->last_seen_at ?? $this->started_at;
        $seconds = (int) round($this->started_at->diffInSeconds($end, absolute: true));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $label = match (true) {
            $hours > 0 && $minutes > 0 => "{$hours}h {$minutes}m",
            $hours > 0 => "{$hours}h",
            $minutes > 0 => "{$minutes}m",
            default => '< 1m',
        };

        if ($this->isLive()) {
            $label .= ' · in progress';
        }

        return $label;
    }

    public function isLive(): bool
    {
        return $this->ended_at === null
            && $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subMinutes(2));
    }
}
