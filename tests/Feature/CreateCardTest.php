<?php

namespace Tests\Feature;

use App\Filament\Resources\Cards\Pages\CreateCard;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class CreateCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_create_another_keeps_the_subject_and_clears_the_card_text(): void
    {
        $anatomy = Subject::create(['name' => 'Anatomía']);

        Livewire::test(CreateCard::class)
            ->fillForm([
                'subject_id' => $anatomy->id,
                'question' => '¿Qué hueso es este?',
                'answer' => 'Escafoides',
            ])
            ->call('createAnother')
            ->assertFormSet(function (array $state) use ($anatomy): array {
                Assert::assertSame($anatomy->id, (int) $state['subject_id']);
                Assert::assertTrue(blank($state['question'] ?? null));
                Assert::assertTrue(blank($state['answer'] ?? null));

                return [];
            });

        $this->assertDatabaseHas('cards', [
            'subject_id' => $anatomy->id,
            'question' => '¿Qué hueso es este?',
        ]);
    }

    public function test_the_subject_stays_until_the_user_picks_another(): void
    {
        $anatomy = Subject::create(['name' => 'Anatomía']);
        $simpsons = Subject::create(['name' => 'los simposons']);

        Livewire::test(CreateCard::class)
            ->fillForm([
                'subject_id' => $anatomy->id,
                'question' => 'Primera',
                'answer' => 'Uno',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateCard::class)
            ->assertFormSet(function (array $state) use ($anatomy): array {
                Assert::assertSame($anatomy->id, (int) $state['subject_id']);

                return [];
            })
            ->fillForm([
                'subject_id' => $simpsons->id,
                'question' => 'Segunda',
                'answer' => 'Dos',
            ])
            ->call('createAnother')
            ->assertFormSet(function (array $state) use ($simpsons): array {
                Assert::assertSame($simpsons->id, (int) $state['subject_id']);
                Assert::assertTrue(blank($state['question'] ?? null));

                return [];
            });

        Livewire::test(CreateCard::class)
            ->assertFormSet(function (array $state) use ($simpsons): array {
                Assert::assertSame($simpsons->id, (int) $state['subject_id']);

                return [];
            });
    }
}
