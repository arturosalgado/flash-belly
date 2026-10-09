<?php

namespace Tests\Feature;

use App\Livewire\Study;
use App\Models\Card;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CardImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        config([
            'services.anthropic.key' => 'test-key',
            'services.anthropic.model' => 'claude-haiku-4-5-20251001',
        ]);
    }

    public function test_the_chosen_image_is_saved_and_shown_the_next_time_the_card_appears(): void
    {
        $thumb = 'https://upload.wikimedia.org/wikipedia/commons/thumb/transversus.png';

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'transversus abdominis muscle']],
            ]),
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '1' => [
                            'title' => 'File:Transversus.png',
                            'imageinfo' => [[
                                'thumburl' => $thumb,
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/transversus-full.png',
                                'mime' => 'image/png',
                            ]],
                        ],
                        '2' => [
                            'title' => 'File:Clip.webm',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/clip.webm',
                                'mime' => 'video/webm',
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $card = Card::create([
            'subject_id' => Subject::create(['name' => 'Abdomen'])->id,
            'question' => '¿Dónde se inserta el transverso del abdomen?',
            'answer' => 'En la línea alba, cresta del pubis y pecten del pubis.',
        ]);

        Livewire::test(Study::class)
            ->assertDontSee($thumb)
            ->call('findImages')
            ->assertSee($thumb)
            ->assertDontSee('clip.webm')
            ->call('chooseImage', 'https://evil.example/a.png')
            ->call('chooseImage', $thumb);

        $this->assertSame($thumb, $card->refresh()->image_url);

        Livewire::test(Study::class)
            ->assertSee($thumb, false);
    }

    public function test_a_rambling_reply_is_retried_and_pdfs_are_skipped(): void
    {
        $thumb = 'https://upload.wikimedia.org/wikipedia/commons/thumb/water.png';

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [['type' => 'text', 'text' => "I don't see an image attached. Please share the flashcard."]],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'Water and mineral salts']],
                ]),
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '1' => [
                            'title' => 'File:Chapter.pdf',
                            'imageinfo' => [[
                                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/chapter.pdf',
                                'mime' => 'application/pdf',
                            ]],
                        ],
                        '2' => [
                            'title' => 'File:Water.png',
                            'imageinfo' => [[
                                'thumburl' => $thumb,
                                'mime' => 'image/png',
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        Card::create([
            'subject_id' => Subject::create(['name' => 'Bioquímica'])->id,
            'question' => '¿Cuáles son las biomoléculas inorgánicas más importantes?',
            'answer' => 'Agua y sales minerales.',
        ]);

        Livewire::test(Study::class)
            ->call('findImages')
            ->assertSee($thumb)
            ->assertDontSee('chapter.pdf');
    }

    public function test_find_image_explains_when_claude_is_not_configured(): void
    {
        config(['services.anthropic.key' => null]);

        Card::create([
            'subject_id' => Subject::create(['name' => 'Abdomen'])->id,
            'question' => '¿Qué es el fémur?',
            'answer' => 'El hueso del muslo.',
        ]);

        Livewire::test(Study::class)
            ->call('findImages')
            ->assertSee('ANTHROPIC_API_KEY is not configured.');
    }
}
