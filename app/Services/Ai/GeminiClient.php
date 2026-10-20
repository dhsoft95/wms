<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiClient;
use App\Data\Ai\AiResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GeminiClient implements AiClient
{
    public function summarise(string $systemInstruction, string $userContent): AiResult
    {
        $model   = config('ai.gemini.model', 'gemini-1.5-flash');
        $baseUrl = config('ai.gemini.base_url');
        $apiKey  = config('ai.gemini.api_key');
        $timeout = (int) config('ai.gemini.timeout', 30);

        $url = "{$baseUrl}/models/{$model}:generateContent";

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userContent]]],
            ],
            'generationConfig' => [
                'temperature'     => (float) config('ai.gemini.temperature', 0.2),
                'maxOutputTokens' => (int) config('ai.gemini.max_tokens', 600),
            ],
        ];

        $response = Http::withHeader('x-goog-api-key', $apiKey)
            ->timeout($timeout)
            ->retry(1, 1000)
            ->post($url, $payload);

        $response->throw();

        $body = $response->json();

        $text  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usage = $body['usageMetadata'] ?? [];

        return new AiResult(
            text:         trim($text),
            inputTokens:  (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
        );
    }
}
