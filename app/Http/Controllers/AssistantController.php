<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\AvailabilityAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function __construct(private AvailabilityAssistant $assistant)
    {
    }

    public function index(): View
    {
        $history = auth()->user()
            ->aiConversations()
            ->latest('id')
            ->take(20)
            ->get()
            ->reverse()
            ->values();

        return view('assistant.index', [
            'history' => $history,
            'mode' => config('assistant.api_key') ? 'llm' : 'aturan',
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:300'],
        ], [
            'question.required' => 'Tulis pertanyaanmu dulu.',
            'question.max' => 'Pertanyaan terlalu panjang (maksimal 300 karakter).',
        ]);

        $limiterKey = 'assistant:user:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($limiterKey, 20)) {
            return response()->json([
                'message' => 'Batas pertanyaan asisten tercapai. Coba lagi sebentar.',
            ], 429);
        }

        RateLimiter::hit($limiterKey, 60);

        $result = $this->assistant->ask($request->user(), $data['question']);

        AiConversation::create([
            'user_id' => $request->user()->id,
            'question' => $data['question'],
            'answer' => $result['answer'],
            'mode' => $result['mode'],
        ]);

        return response()->json($result);
    }

    public function clear(): RedirectResponse
    {
        auth()->user()->aiConversations()->delete();

        return redirect()->route('assistant.index')->with('success', 'Riwayat percakapan dihapus.');
    }
}
