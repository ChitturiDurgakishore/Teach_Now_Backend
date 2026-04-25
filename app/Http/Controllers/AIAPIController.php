<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIService;
use App\Models\Prompts;

class AIAPIController extends Controller
{
    public function JdRewrite(Request $request)
    {
        try {

            $request->validate([
                'data' => 'required|array'
            ]);

            $aiService = app(\App\Services\AIService::class);

            $result = $aiService->rewriteJob($request->data);

            return response()->json([
                'status' => true,
                'output' => $result
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
