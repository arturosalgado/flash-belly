<?php

namespace App\Livewire;

use App\Models\Card;
use App\Models\LoginSession;
use App\Models\Subject;
use App\Models\User;
use App\Services\CardImageFinder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
class Study extends Component
{
    #[Url]
    public ?string $subjectId = null;

    /**
     * Drill only the cards you have got wrong more often than right.
     */
    #[Url]
    public bool $weakOnly = false;

    /**
     * Pull cards at random from every subject, ignoring the subject filter.
     */
    #[Url]
    public bool $shuffleAll = false;

    /**
     * Show the answer as the prompt. Space then reveals the question.
     */
    #[Url]
    public bool $answerFirst = false;

    public ?int $cardId = null;

    public bool $revealed = false;

    public int $confidentCount = 0;

    public int $repeatCount = 0;

    /**
     * Cards passed over with right-click. Held back until the deck runs dry.
     *
     * @var array<int, int>
     */
    public array $skipped = [];

    /**
     * Wikimedia candidates for the card on screen. Not saved until one is chosen.
     *
     * @var array<int, array{url: string, thumb: string, title: string}>
     */
    public array $imageChoices = [];

    public ?string $imageSearchError = null;

    public function mount(): void
    {
        $this->loadNext();
    }

    #[Computed]
    public function card(): ?Card
    {
        return $this->cardId
            ? Card::with('subject')->find($this->cardId)
            : null;
    }

    #[Computed]
    public function subjects(): Collection
    {
        return Subject::withCount('cards')->orderBy('name')->get();
    }

    #[Computed]
    public function totalCards(): int
    {
        return Card::count();
    }

    /**
     * Scoped to the chosen subject but never to the weak filter, so the
     * breakdown still shows the whole deck while you drill part of it.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $cards = $this->subjectScope()->get(['confidence', 'reviews']);

        return [
            'total' => $cards->count(),
            'new' => $cards->where('reviews', 0)->count(),
            'weak' => $cards->where('reviews', '>', 0)->where('confidence', '<', 0)->count(),
            'learning' => $cards->where('reviews', '>', 0)->whereBetween('confidence', [0, 2])->count(),
            'strong' => $cards->where('confidence', '>=', 3)->count(),
        ];
    }

    public function reveal(): void
    {
        $this->revealed = true;
    }

    public function findImages(CardImageFinder $finder): void
    {
        $card = $this->card;

        if (! $card) {
            return;
        }

        $this->imageSearchError = null;
        $this->imageChoices = [];

        try {
            $this->imageChoices = $finder->options($card);

            if ($this->imageChoices === []) {
                $this->imageSearchError = 'No images found for this card.';
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->imageSearchError = $exception->getMessage() === 'ANTHROPIC_API_KEY is not configured.'
                ? 'ANTHROPIC_API_KEY is not configured.'
                : 'Could not fetch images.';
        }
    }

    public function chooseImage(string $url): void
    {
        $card = $this->card;

        if (! $card) {
            return;
        }

        $match = collect($this->imageChoices)->firstWhere('url', $url);

        if (! is_array($match) || ! app(CardImageFinder::class)->isWikimediaImage($url)) {
            return;
        }

        $card->update(['image_url' => $url]);
        unset($this->card);
        $this->imageChoices = [];
        $this->imageSearchError = null;
    }

    public function removeImage(): void
    {
        $this->card?->update(['image_url' => null]);
        unset($this->card);
    }

    /**
     * +1 when confident, -1 when it needs repeating later.
     */
    public function rate(int $delta): void
    {
        $card = $this->card;

        if (! $card) {
            return;
        }

        $card->recordReview($delta);

        $user = auth()->user();

        if ($user instanceof User) {
            LoginSession::recordCard($user);
        }

        $delta > 0
            ? $this->confidentCount++
            : $this->repeatCount++;

        $this->loadNext(excludeId: $card->id);
    }

    /**
     * Move on without touching the card's score.
     */
    public function skip(): void
    {
        if (! $this->cardId) {
            return;
        }

        $this->skipped[] = $this->cardId;

        $this->loadNext();
    }

    public function updatedSubjectId(): void
    {
        $this->skipped = [];

        $this->loadNext();
    }

    public function toggleWeakOnly(): void
    {
        $this->weakOnly = ! $this->weakOnly;
        $this->skipped = [];

        $this->loadNext();
    }

    public function toggleShuffleAll(): void
    {
        $this->shuffleAll = ! $this->shuffleAll;
        $this->skipped = [];

        $this->loadNext();
    }

    public function toggleAnswerFirst(): void
    {
        $this->answerFirst = ! $this->answerFirst;
        $this->revealed = false;
    }

    public function restartSession(): void
    {
        $this->confidentCount = 0;
        $this->repeatCount = 0;
        $this->skipped = [];

        $this->loadNext();
    }

    /**
     * Shuffle mode deliberately ignores the subject filter: the whole point
     * is to mix every deck together.
     */
    protected function subjectScope()
    {
        return Card::query()
            ->when(
                ! $this->shuffleAll && $this->subjectId,
                fn ($query) => $query->where('subject_id', $this->subjectId),
            );
    }

    protected function baseQuery()
    {
        return $this->subjectScope()
            ->when($this->weakOnly, fn ($query) => $query->where('confidence', '<', 0));
    }

    /**
     * Random across all decks when shuffling, weakest-first otherwise.
     */
    protected function orderedQuery()
    {
        return $this->shuffleAll
            ? $this->baseQuery()->inRandomOrder()
            : $this->baseQuery()->weakestFirst();
    }

    /**
     * Serve the weakest card next, skipping anything passed over this round and
     * the card just answered, so a card you pushed back does not reappear at once.
     */
    protected function loadNext(?int $excludeId = null): void
    {
        $exclude = array_values(array_unique(array_merge(
            $this->skipped,
            $excludeId ? [$excludeId] : [],
        )));

        $next = $this->orderedQuery()
            ->when($exclude, fn ($query) => $query->whereNotIn('id', $exclude))
            ->first();

        if (! $next) {
            // Deck exhausted: start the round over, still avoiding an immediate repeat.
            $this->skipped = [];

            $next = $this->orderedQuery()
                ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
                ->first()
                ?? $this->orderedQuery()->first();
        }

        $this->cardId = $next?->getKey();
        $this->revealed = false;
        $this->imageChoices = [];
        $this->imageSearchError = null;

        unset($this->card, $this->stats);
    }

    public function render(): View
    {
        return view('livewire.study');
    }
}
