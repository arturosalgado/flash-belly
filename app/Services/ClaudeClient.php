<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClaudeClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function messages(array $messages, int $maxTokens = 600): string
    {
        $apiKey = config('services.anthropic.key');

        if (! filled($apiKey)) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Claude API request failed.', previous: $exception);
        }

        $text = trim((string) $response->json('content.0.text', ''));

        if ($text === '') {
            throw new RuntimeException('Claude returned an empty response.');
        }

        return $text;
    }
}
