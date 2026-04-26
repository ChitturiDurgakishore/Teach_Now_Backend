<?php

namespace App\Services;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Prompts;
use App\Models\ResumeLimitAdmin;
use App\Models\ResumeLimit;

class AIService
{
    public function generateCV($data, $jobDescription = null)
    {
        Log::info('Generating CV with data', [
            'data' => $data,
            'job_description' => $jobDescription
        ]);
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

    //Resume generation limit checking function for AI
    public function checkAndConsume($userId)
    {
        Log::info('checkAndConsume called', [
            'user_id' => $userId ?? null
        ]);

        try {

            $month = now()->format('Y-m');
            Log::info('Month generated', ['month' => $month]);

            // 🔥 CHECK ADMIN LIMIT
            $limit = ResumeLimitAdmin::value('limit') ?? 5;
            Log::info('Limit fetched', ['limit' => $limit]);

            // 🔥 CHECK USAGE ROW
            $usage = ResumeLimit::firstOrCreate(
                [
                    'user_id' => $userId,
                    'month' => $month
                ],
                [
                    'count' => 0
                ]
            );

            Log::info('Usage record', ['usage' => $usage]);

            if ($usage->count >= $limit) {
                return [
                    'status' => false,
                    'message' => 'Monthly resume limit reached',
                    'limit' => $limit,
                    'used' => $usage->count
                ];
            }

            $usage->increment('count');

            Log::info('After increment', [
                'count' => $usage->count
            ]);

            return [
                'status' => true,
                'remaining' => $limit - $usage->count,
                'used' => $usage->count
            ];
        } catch (\Exception $e) {

            Log::error('checkAndConsume ERROR', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => false,
                'message' => 'Something went wrong in limit check'
            ];
        }
    }




    //================================================================================

    //Service function for AI to genearte job description based on the given data


    public function rewriteJob($data)
    {
        $prompt = \App\Models\Prompt::where('key', 'job_description_rewrite')
            ->where('is_active', true)
            ->first();

        if (!$prompt) {
            throw new \Exception('Prompt not found');
        }

        $finalPrompt = str_replace(
            ['{data}'],
            [json_encode($data)],
            $prompt->prompt
        );

        $apiKey = config('services.ai.key');
        $url = config('services.ai.url');

        $response = Http::post($url . '?key=' . $apiKey, [
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

        /*
    |--------------------------------------------------------------------------
    | 🔥 CLEAN MARKDOWN (IMPORTANT FIX)
    |--------------------------------------------------------------------------
    */
        $output = trim($output);

        // remove ```json or ```
        $output = preg_replace('/^```json/', '', $output);
        $output = preg_replace('/^```/', '', $output);
        $output = preg_replace('/```$/', '', $output);

        $output = trim($output);

        /*
    |--------------------------------------------------------------------------
    | 🔥 CONVERT TO ARRAY
    |--------------------------------------------------------------------------
    */
        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('AI returned invalid JSON: ' . $output);
        }

        return $decoded;
    }
}
