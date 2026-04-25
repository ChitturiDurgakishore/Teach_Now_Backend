<?php

namespace App\Services;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Prompts;

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




    //================================================================================

    //Service function for AI to genearte job description based on the given data


    public function rewriteJob(array $data)
    {
        $prompt = \App\Models\Prompt::where('key', 'job_description_rewrite')
            ->where('is_active', true)
            ->first();

        if (!$prompt) {
            throw new \Exception('Prompt not found');
        }

        /*
    |--------------------------------------------------------------------------
    | 🔥 BUILD DYNAMIC INPUT TEXT
    |--------------------------------------------------------------------------
    */

        $inputText = "";

        foreach ($data as $key => $value) {

            if (!is_null($value) && $value !== '') {

                // make key readable (salary_min → Salary Min)
                $formattedKey = ucwords(str_replace('_', ' ', $key));

                $inputText .= "{$formattedKey}: {$value}\n";
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 🔥 FINAL PROMPT
    |--------------------------------------------------------------------------
    */

        $finalPrompt = str_replace(
            ['{data}'],
            [$inputText],
            $prompt->prompt
        );

        /*
    |--------------------------------------------------------------------------
    | 🔥 AI CALL
    |--------------------------------------------------------------------------
    */

        $apiKey = config('services.ai.key');
        $url = config('services.ai.url');

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->post($url . '?key=' . $apiKey, [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $finalPrompt]
                        ]
                    ]
                ]
            ]);

        if (!$response->successful()) {
            throw new \Exception('AI API failed: ' . $response->body());
        }

        $output = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$output) {
            throw new \Exception('Invalid AI response');
        }

        return $output;
    }
}
