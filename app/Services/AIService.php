<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    public function generateCV($data, $jobDescription = null)
    {
        $prompt = $this->buildPrompt($data, $jobDescription);

        $url = config('ai.url') . '?key=' . config('ai.key');

        $response = Http::post($url, [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ]);

        $result = $response->json();

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            Log::error('Gemini Error', $result);
            return null;
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    private function buildPrompt($data, $jobDescription = null)
    {
        $base = "
You are a professional resume writer.

Generate ONLY a professional summary (3-5 lines) and bullet achievements.

DO NOT return HTML page.
DO NOT include <html>, <head>, <body>.
Return ONLY plain text or simple paragraphs.

Candidate Details:
Name: {$data['name']}
Email: {$data['email']}

Skills: " . implode(', ', $data['skills']) . "

Education:
" . json_encode($data['educations']) . "

Experience:
" . json_encode($data['experiences']) . "

Instructions:
- Write strong professional summary
- Add 3–5 bullet achievements
- Keep it concise and impactful
";

        if ($jobDescription) {
            $base .= "\n\nOptimize this CV for the following job:\n{$jobDescription}";
        }

        return $base;
    }
}
