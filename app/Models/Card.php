<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    /**
     * Bounds for the running confidence score, so a card you keep getting
     * right cannot drift far above the rest of the deck, and one you keep
     * missing cannot sink so low that it takes ten answers to recover.
     */
    public const MAX_CONFIDENCE = 5;

    public const MIN_CONFIDENCE = -5;

    protected $fillable = [
        'subject_id',
        'question',
        'answer',
        'confidence',
        'reviews',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Weakest cards first, then the ones you have not seen in the longest time.
     * Unreviewed cards sort ahead of reviewed ones at the same confidence.
     */
    public function scopeWeakestFirst(Builder $query): Builder
    {
        return $query
            ->orderBy('confidence')
            ->orderByRaw('last_reviewed_at is null desc')
            ->orderBy('last_reviewed_at')
            ->orderBy('id');
    }

    /**
     * Record a review. +1 when confident, -1 when it needs repeating.
     */
    public function recordReview(int $delta): void
    {
        $this->confidence = max(
            self::MIN_CONFIDENCE,
            min(self::MAX_CONFIDENCE, $this->confidence + $delta),
        );
        $this->reviews++;
        $this->last_reviewed_at = now();
        $this->save();
    }

    public function isMaxed(): bool
    {
        return $this->confidence >= self::MAX_CONFIDENCE;
    }

    public function isFloored(): bool
    {
        return $this->confidence <= self::MIN_CONFIDENCE;
    }

    public function strength(): string
    {
        return match (true) {
            $this->reviews === 0 => 'new',
            $this->confidence < 0 => 'weak',
            $this->confidence < 3 => 'learning',
            default => 'strong',
        };
    }
}
