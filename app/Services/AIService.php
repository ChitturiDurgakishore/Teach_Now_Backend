<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    public function generateCV($data, $jobDescription = null)
    {
        $prompt = $this->buildPrompt($data, $jobDescription);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('AI_API_KEY'),
            'Content-Type' => 'application/json'
        ])->post(env('AI_API_URL'), [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ]);

        return $response['choices'][0]['message']['content'] ?? null;
    }

    private function buildPrompt($data, $jobDescription = null)
    {
        $base = "
You are a professional resume writer.

Create a modern, ATS-friendly CV in CLEAN HTML FORMAT (no markdown).

Candidate Details:
Name: {$data['name']}
Email: {$data['email']}

Skills: " . implode(', ', $data['skills']->toArray()) . "

Education:
" . json_encode($data['educations']) . "

Experience:
" . json_encode($data['experiences']) . "

Instructions:
- Add a strong professional summary
- Convert experience into bullet points
- Highlight achievements (not just responsibilities)
- Use proper headings (Summary, Skills, Experience, Education)
- Keep it clean and readable
- Return ONLY HTML (no explanations)
";

        if ($jobDescription) {
            $base .= "\n\nOptimize this CV for the following job:\n{$jobDescription}";
        }

        return $base;
    }
}
