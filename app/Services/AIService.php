<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    public function generateCV($data, $jobDescription = null)
    {
        $prompt = $this->buildPrompt($data, $jobDescription);

        // 🔥 Replace with your AI API (OpenAI or any)
        $response = Http::withHeaders([
            'Authorization' => 'Bearer YOUR_API_KEY'
        ])->post('https://api.openai.com/v1/chat/completions', [
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
        Create a professional ATS-friendly CV.

        Name: {$data['name']}
        Email: {$data['email']}

        Skills: " . implode(', ', $data['skills']->toArray()) . "

        Education:
        " . json_encode($data['educations']) . "

        Experience:
        " . json_encode($data['experiences']) . "
        ";

        if ($jobDescription) {
            $base .= "\nOptimize this CV for the following job:\n{$jobDescription}";
        }

        return $base;
    }
}
