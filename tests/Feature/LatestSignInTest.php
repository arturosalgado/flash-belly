<?php

namespace Tests\Feature;

use App\Filament\Widgets\LatestSignInWidget;
use App\Livewire\Study;
use App\Models\Card;
use App\Models\LoginSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class LatestSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_signing_in_and_out_records_how_long_the_visit_lasted(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-09 17:00:00');
        event(new Login('web', $user, false));

        Carbon::setTestNow('2026-10-09 17:34:00');
        event(new Logout('web', $user));

        $session = $user->latestLoginSession()->first();

        $this->assertNotNull($session);
        $this->assertTrue($session->started_at->equalTo(Carbon::parse('2026-10-09 17:00:00')));
        $this->assertTrue($session->ended_at->equalTo(Carbon::parse('2026-10-09 17:34:00')));
        $this->assertSame('34m', $session->lastedLabel());
    }

    public function test_a_new_sign_in_ends_the_previous_visit_at_the_last_activity(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-09 12:00:00');
        $first = LoginSession::start($user);

        Carbon::setTestNow('2026-10-09 12:40:00');
        $first->update(['last_seen_at' => now()]);

        Carbon::setTestNow('2026-10-09 18:00:00');
        event(new Login('web', $user, false));

        $first->refresh();

        $this->assertTrue($first->ended_at->equalTo(Carbon::parse('2026-10-09 12:40:00')));
        $this->assertSame('40m', $first->lastedLabel());
        $this->assertSame(2, $user->loginSessions()->count());
    }

    public function test_rating_a_card_counts_as_studied_and_skipping_does_not(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $subject = Subject::create(['name' => 'Anatomía']);
        Card::create([
            'subject_id' => $subject->id,
            'question' => 'Escafoides',
            'answer' => 'Hueso del carpo',
        ]);
        Card::create([
            'subject_id' => $subject->id,
            'question' => 'Semilunar',
            'answer' => 'Hueso del carpo',
        ]);

        Livewire::test(Study::class)
            ->call('rate', 1)
            ->call('skip');

        $this->assertSame(1, LoginSession::current($user)->cards_studied);
    }

    public function test_the_dashboard_widget_shows_the_latest_visit(): void
    {
        $user = User::factory()->create(['name' => 'Belly']);

        Carbon::setTestNow('2026-10-09 17:48:00');

        LoginSession::create([
            'user_id' => $user->id,
            'started_at' => Carbon::parse('2026-10-09 17:00:00'),
            'last_seen_at' => Carbon::parse('2026-10-09 17:34:00'),
            'ended_at' => Carbon::parse('2026-10-09 17:34:00'),
            'cards_studied' => 8,
        ]);

        $this->actingAs($user);

        Livewire::test(LatestSignInWidget::class)
            ->assertSee('Belly')
            ->assertSee('Oct 9, 2026 11:00 AM')
            ->assertSee('34m')
            ->assertSee('8')
            ->assertSee('Cards studied');
    }
}
