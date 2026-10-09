<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CardImageFinder
{
    public function __construct(private ClaudeClient $claude) {}

    /**
     * @return array<int, array{url: string, thumb: string, title: string}>
     */
    public function options(Card $card): array
    {
        $query = $this->searchQuery($card);

        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'FlashBelly/1.0 (https://sisadesel.com/study)',
            ])
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'generator' => 'search',
                'gsrsearch' => $query,
                'gsrnamespace' => 6,
                'gsrlimit' => 8,
                'prop' => 'imageinfo',
                'iiprop' => 'url|mime',
                'iiurlwidth' => 800,
            ])
            ->throw();

        $pages = $response->json('query.pages', []);

        if (! is_array($pages)) {
            return [];
        }

        $options = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $info = $page['imageinfo'][0] ?? null;

            if (! is_array($info) || ! str_starts_with((string) ($info['mime'] ?? ''), 'image/')) {
                continue;
            }

            $url = (string) ($info['thumburl'] ?? $info['url'] ?? '');

            if (! $this->isWikimediaImage($url)) {
                continue;
            }

            $options[] = [
                'url' => $url,
                'thumb' => $url,
                'title' => preg_replace('/^File:/', '', (string) ($page['title'] ?? 'Image')) ?: 'Image',
            ];

            if (count($options) === 6) {
                break;
            }
        }

        return $options;
    }

    public function searchQuery(Card $card): string
    {
        $text = $this->claude->messages([[
            'role' => 'user',
            'content' => <<<PROMPT
                Name the anatomical structure in this flashcard as a short English Wikimedia Commons search. Reply with the search words only, nothing else.

                Question: {$card->question}
                Answer: {$card->answer}
                PROMPT,
        ]], 40);

        $query = trim(Str::before($text, "\n"), " \t\"'`");

        if ($query === '' || strlen($query) > 120) {
            throw new RuntimeException('Claude did not return a search query.');
        }

        return $query;
    }

    public function isWikimediaImage(string $url): bool
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && ($host === 'upload.wikimedia.org' || str_ends_with($host, '.wikimedia.org'));
    }
}
