<?php

namespace Tests\Feature;

use App\Livewire\Study;
use App\Models\Card;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudyPageTest extends TestCase
{
    use RefreshDatabase;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->subject = Subject::create(['name' => 'Membrana plasmática']);
    }

    protected function card(string $question, int $confidence = 0, int $reviews = 0): Card
    {
        return Card::create([
            'subject_id' => $this->subject->id,
            'question' => $question,
            'answer' => "Answer to {$question}",
            'confidence' => $confidence,
            'reviews' => $reviews,
        ]);
    }

    public function test_answer_first_shows_the_answer_until_space_reveals_the_question(): void
    {
        $card = Card::create([
            'subject_id' => $this->subject->id,
            'question' => '¿Dónde se inserta el transverso del abdomen?',
            'answer' => 'En la línea alba, cresta del pubis y pecten del pubis.',
        ]);

        Livewire::test(Study::class)
            ->call('toggleAnswerFirst')
            ->assertSet('answerFirst', true)
            ->assertSet('revealed', false)
            ->assertSee($card->answer)
            ->assertDontSee($card->question)
            ->assertSee('Show question')
            ->call('reveal')
            ->assertSee($card->question)
            ->call('toggleAnswerFirst')
            ->assertSet('answerFirst', false)
            ->assertSet('revealed', false)
            ->assertSee($card->question)
            ->assertDontSee($card->answer);
    }

    public function test_it_hides_the_answer_until_revealed(): void
    {
        $card = $this->card('¿Qué es la membrana plasmática?');

        Livewire::test(Study::class)
            ->assertSee($card->question)
            ->assertDontSee($card->answer)
            ->call('reveal')
            ->assertSee($card->answer);
    }

    public function test_up_arrow_adds_a_confidence_point(): void
    {
        $card = $this->card('Confident card');

        Livewire::test(Study::class)->call('rate', 1);

        $card->refresh();

        $this->assertSame(1, $card->confidence);
        $this->assertSame(1, $card->reviews);
        $this->assertNotNull($card->last_reviewed_at);
    }

    public function test_confidence_is_capped_at_five(): void
    {
        $card = $this->card('Well known', confidence: 5, reviews: 5);

        Livewire::test(Study::class)->call('rate', 1);

        $card->refresh();

        $this->assertSame(5, $card->confidence);
        $this->assertSame(6, $card->reviews, 'The review should still be recorded at the cap.');
    }

    public function test_the_cap_is_not_exceeded_when_climbing_to_it(): void
    {
        $card = $this->card('Climbing', confidence: 4);

        $component = Livewire::test(Study::class);
        $component->call('rate', 1);
        $component->call('rate', 1);

        $this->assertSame(5, $card->refresh()->confidence);
    }

    public function test_a_maxed_card_can_still_come_back_down(): void
    {
        $card = $this->card('Forgotten', confidence: 5, reviews: 5);

        Livewire::test(Study::class)->call('rate', -1);

        $this->assertSame(4, $card->refresh()->confidence);
    }

    public function test_confidence_is_floored_at_minus_five(): void
    {
        $card = $this->card('Never sticks', confidence: -5, reviews: 5);

        Livewire::test(Study::class)->call('rate', -1);

        $card->refresh();

        $this->assertSame(-5, $card->confidence);
        $this->assertSame(6, $card->reviews, 'The review should still be recorded at the floor.');
    }

    public function test_the_floor_is_not_exceeded_when_sinking_to_it(): void
    {
        $card = $this->card('Sinking', confidence: -4);

        $component = Livewire::test(Study::class);
        $component->call('rate', -1);
        $component->call('rate', -1);

        $this->assertSame(-5, $card->refresh()->confidence);
    }

    public function test_a_floored_card_can_climb_back_up(): void
    {
        $card = $this->card('Finally learned', confidence: -5, reviews: 5);

        Livewire::test(Study::class)->call('rate', 1);

        $this->assertSame(-4, $card->refresh()->confidence);
    }

    public function test_down_arrow_subtracts_a_confidence_point(): void
    {
        $card = $this->card('Weak card');

        Livewire::test(Study::class)->call('rate', -1);

        $card->refresh();

        $this->assertSame(-1, $card->confidence);
        $this->assertSame(1, $card->reviews);
    }

    public function test_it_serves_the_weakest_card_first(): void
    {
        $this->card('Strong', confidence: 5, reviews: 5);
        $weak = $this->card('Weak', confidence: -2, reviews: 3);
        $this->card('Medium', confidence: 1, reviews: 2);

        Livewire::test(Study::class)->assertSet('cardId', $weak->id);
    }

    public function test_it_does_not_serve_the_same_card_twice_in_a_row(): void
    {
        $first = $this->card('First');
        $this->card('Second');

        Livewire::test(Study::class)
            ->assertSet('cardId', $first->id)
            ->call('rate', -1)
            ->assertNotSet('cardId', $first->id);
    }

    public function test_a_single_card_is_served_again_after_rating(): void
    {
        $only = $this->card('Only card');

        Livewire::test(Study::class)
            ->call('rate', -1)
            ->assertSet('cardId', $only->id);
    }

    public function test_rating_resets_the_reveal_state_for_the_next_card(): void
    {
        $this->card('First');
        $this->card('Second');

        Livewire::test(Study::class)
            ->call('reveal')
            ->assertSet('revealed', true)
            ->call('rate', 1)
            ->assertSet('revealed', false);
    }

    public function test_it_tracks_session_counts(): void
    {
        $this->card('One');
        $this->card('Two');

        Livewire::test(Study::class)
            ->call('rate', 1)
            ->call('rate', -1)
            ->assertSet('confidentCount', 1)
            ->assertSet('repeatCount', 1)
            ->call('restartSession')
            ->assertSet('confidentCount', 0)
            ->assertSet('repeatCount', 0);
    }

    public function test_it_filters_by_subject(): void
    {
        $other = Subject::create(['name' => 'Otro tema']);
        $mine = $this->card('Mine');
        Card::create([
            'subject_id' => $other->id,
            'question' => 'Theirs',
            'answer' => 'Theirs',
            'confidence' => -10,
        ]);

        Livewire::test(Study::class)
            ->set('subjectId', (string) $this->subject->id)
            ->assertSet('cardId', $mine->id);
    }

    public function test_skipping_moves_on_without_changing_the_score(): void
    {
        $first = $this->card('First');
        $second = $this->card('Second');

        Livewire::test(Study::class)
            ->assertSet('cardId', $first->id)
            ->call('skip')
            ->assertSet('cardId', $second->id);

        $first->refresh();

        $this->assertSame(0, $first->confidence);
        $this->assertSame(0, $first->reviews);
        $this->assertNull($first->last_reviewed_at);
    }

    public function test_skipping_does_not_count_towards_the_session(): void
    {
        $this->card('One');
        $this->card('Two');

        Livewire::test(Study::class)
            ->call('skip')
            ->assertSet('confidentCount', 0)
            ->assertSet('repeatCount', 0);
    }

    public function test_skipping_walks_through_the_deck_before_repeating(): void
    {
        $a = $this->card('A');
        $b = $this->card('B');
        $c = $this->card('C');

        $component = Livewire::test(Study::class);
        $seen = [$component->get('cardId')];

        $component->call('skip');
        $seen[] = $component->get('cardId');

        $component->call('skip');
        $seen[] = $component->get('cardId');

        sort($seen);

        $this->assertSame([$a->id, $b->id, $c->id], $seen);
    }

    public function test_the_deck_recycles_once_every_card_is_skipped(): void
    {
        $this->card('A');
        $this->card('B');

        $component = Livewire::test(Study::class)
            ->call('skip')
            ->call('skip')
            ->call('skip');

        $this->assertNotNull($component->get('cardId'));
    }

    public function test_skipping_hides_a_revealed_answer(): void
    {
        $this->card('First');
        $this->card('Second');

        Livewire::test(Study::class)
            ->call('reveal')
            ->assertSet('revealed', true)
            ->call('skip')
            ->assertSet('revealed', false);
    }

    public function test_the_home_page_shows_the_study_interface(): void
    {
        $card = $this->card('¿Qué es la membrana plasmática?');

        $this->get('/')
            ->assertOk()
            ->assertSee($card->question)
            ->assertSee('Show answer');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        auth()->logout();

        $this->get('/')->assertRedirect('/admin/login');
    }

    public function test_weak_mode_only_serves_cards_below_zero(): void
    {
        $this->card('Strong', confidence: 4, reviews: 4);
        $this->card('Neutral', confidence: 0, reviews: 2);
        $this->card('Brand new');
        $weak = $this->card('Weak', confidence: -2, reviews: 3);

        $component = Livewire::test(Study::class)->call('toggleWeakOnly');

        $this->assertTrue($component->get('weakOnly'));
        $component->assertSet('cardId', $weak->id);

        // Rating it up out of the weak range leaves nothing to drill.
        $component->call('rate', 1)->call('rate', 1);

        $component->assertSet('cardId', null)->assertSee('No weak cards left');
    }

    public function test_weak_mode_combines_with_the_subject_filter(): void
    {
        $other = Subject::create(['name' => 'Otro tema']);
        Card::create([
            'subject_id' => $other->id,
            'question' => 'Weak but another subject',
            'answer' => 'x',
            'confidence' => -4,
            'reviews' => 4,
        ]);
        $mine = $this->card('Weak and mine', confidence: -1, reviews: 2);

        Livewire::test(Study::class)
            ->set('subjectId', (string) $this->subject->id)
            ->call('toggleWeakOnly')
            ->assertSet('cardId', $mine->id);
    }

    public function test_weak_mode_can_be_turned_back_off(): void
    {
        $strong = $this->card('Strong', confidence: 5, reviews: 5);

        Livewire::test(Study::class)
            ->call('toggleWeakOnly')
            ->assertSet('cardId', null)
            ->call('toggleWeakOnly')
            ->assertSet('weakOnly', false)
            ->assertSet('cardId', $strong->id);
    }

    public function test_the_deck_breakdown_ignores_the_weak_filter(): void
    {
        $this->card('Strong', confidence: 5, reviews: 5);
        $this->card('Weak', confidence: -2, reviews: 2);
        $this->card('New one');

        $component = Livewire::test(Study::class)->call('toggleWeakOnly');

        $stats = $component->instance()->stats;

        $this->assertSame(3, $stats['total'], 'Breakdown should still describe the whole deck.');
        $this->assertSame(1, $stats['weak']);
        $this->assertSame(1, $stats['strong']);
        $this->assertSame(1, $stats['new']);
    }

    public function test_toggling_weak_mode_clears_skipped_cards(): void
    {
        $this->card('Weak one', confidence: -1, reviews: 1);
        $this->card('Weak two', confidence: -2, reviews: 1);

        Livewire::test(Study::class)
            ->call('skip')
            ->assertCount('skipped', 1)
            ->call('toggleWeakOnly')
            ->assertCount('skipped', 0);
    }

    public function test_shuffle_ignores_the_selected_subject(): void
    {
        $other = Subject::create(['name' => 'Otro tema']);
        $theirs = Card::create([
            'subject_id' => $other->id,
            'question' => 'From another subject',
            'answer' => 'x',
        ]);

        // Subject pinned to one that has no cards at all.
        $component = Livewire::test(Study::class)
            ->set('subjectId', (string) $this->subject->id)
            ->assertSet('cardId', null)
            ->call('toggleShuffleAll');

        $this->assertTrue($component->get('shuffleAll'));
        $component->assertSet('cardId', $theirs->id);
    }

    public function test_shuffle_does_not_always_serve_the_weakest_card(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->card("Card {$i}", confidence: $i - 5, reviews: 2);
        }

        $seen = [];

        for ($i = 0; $i < 12; $i++) {
            $seen[] = Livewire::test(Study::class)
                ->call('toggleShuffleAll')
                ->get('cardId');
        }

        $this->assertGreaterThan(
            1,
            count(array_unique($seen)),
            'Shuffle should not deterministically serve the same card.',
        );
    }

    public function test_shuffle_combines_with_weak_only(): void
    {
        $other = Subject::create(['name' => 'Otro tema']);
        $this->card('Mine but strong', confidence: 4, reviews: 4);
        Card::create([
            'subject_id' => $other->id,
            'question' => 'Theirs and strong',
            'answer' => 'x',
            'confidence' => 3,
            'reviews' => 3,
        ]);
        $weakElsewhere = Card::create([
            'subject_id' => $other->id,
            'question' => 'Theirs and weak',
            'answer' => 'x',
            'confidence' => -3,
            'reviews' => 3,
        ]);

        Livewire::test(Study::class)
            ->set('subjectId', (string) $this->subject->id)
            ->call('toggleShuffleAll')
            ->call('toggleWeakOnly')
            ->assertSet('cardId', $weakElsewhere->id);
    }

    public function test_turning_shuffle_off_restores_the_subject_filter(): void
    {
        $other = Subject::create(['name' => 'Otro tema']);
        Card::create([
            'subject_id' => $other->id,
            'question' => 'Theirs',
            'answer' => 'x',
        ]);
        $mine = $this->card('Mine');

        Livewire::test(Study::class)
            ->set('subjectId', (string) $this->subject->id)
            ->call('toggleShuffleAll')
            ->call('toggleShuffleAll')
            ->assertSet('shuffleAll', false)
            ->assertSet('cardId', $mine->id);
    }

    public function test_toggling_shuffle_clears_skipped_cards(): void
    {
        $this->card('One');
        $this->card('Two');

        Livewire::test(Study::class)
            ->call('skip')
            ->assertCount('skipped', 1)
            ->call('toggleShuffleAll')
            ->assertCount('skipped', 0);
    }

    public function test_it_handles_an_empty_deck(): void
    {
        Livewire::test(Study::class)
            ->assertSet('cardId', null)
            ->assertSee('No cards to study');
    }
}
