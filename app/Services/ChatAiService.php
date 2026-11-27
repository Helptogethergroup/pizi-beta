<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatAiService
{
    private $geminiApiKey;

    /** Set once no model answers, so one visitor message does not wait for the AI twice. */
    private bool $aiDown = false;

    public function __construct()
    {
        $this->geminiApiKey = env('GEMINI_API_KEY');
    }

    /**
     * The models to try, in order: the first one that answers wins. Set GEMINI_MODEL and GEMINI_FALLBACK_MODEL in
     * the env file to change them without a code change (Google retires old models and busy ones answer 503).
     */
    private function models(): array
    {
        return array_values(array_unique(array_filter([
            env('GEMINI_MODEL', 'gemini-3.6-flash'),
            env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash-lite'),
            'gemini-3.1-flash-lite', // a last resort: each model has its own free limit
        ])));
    }

    /**
     * Asks Gemini and returns the answer's text, or null when no model could give one. Each try has a short time
     * limit: a slow model must never leave the visitor watching the typing dots.
     */
    public function askGemini(array $body, int $seconds): ?string
    {
        if (!$this->geminiApiKey || $this->aiDown) {
            return null;
        }
        foreach ($this->models() as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
            try {
                // the key goes in a header, never in the address: addresses end up in error logs
                $send = fn (array $payload) => Http::withHeaders(['x-goog-api-key' => (string) $this->geminiApiKey])
                    ->connectTimeout(5)->timeout($seconds)->post($url, $payload);
                $response = $send($body);
                // some newer models do not accept the "no thinking" setting, and say only "invalid argument":
                // after any such refusal, ask again without it
                if ($response->status() === 400 && isset($body['generationConfig']['thinkingConfig'])) {
                    $plain = $body;
                    unset($plain['generationConfig']['thinkingConfig']);
                    $plain['generationConfig']['maxOutputTokens'] = 4096; // room for the thinking as well as the answer
                    $response = $send($plain);
                }
                $text = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;
                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
                Log::warning('Gemini: no answer from ' . $model . ', HTTP ' . $response->status() . ' ' . $response->json('error.message'));
                if (in_array($response->status(), [401, 403], true)) {
                    break; // the key itself is refused: another model will not help
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini: ' . $model . ' failed: ' . $e->getMessage());
            }
        }
        $this->aiDown = true;

        return null;
    }

    /** What the visitor reads when the AI cannot answer (bad key, Google down). Searching PGs still works. */
    private function unavailable(string $language): string
    {
        return $language === 'hi'
            ? 'माफ़ कीजिए, assistant अभी जवाब नहीं दे पा रहा। PG ढूंढने के लिए लिखिए, जैसे "PG in Noida under 10000", या Pizi.in पर search करें। 🙏'
            : 'Sorry, the assistant cannot answer that right now. To find a PG, type something like "PG in Noida under 10000", or search on Pizi.in. 🙏';
    }

    public function processMessage($message, $language = 'en', array $history = [], ?string $sessionId = null)
    {
        $search = app(ChatPgSearch::class);

        // the answer to our own "which area?" question
        try {
            $reply = $search->followUp($message, $language, $sessionId, fn (string $m) => $this->detectIntent($m, $language));
            if ($reply !== null) {
                return $reply;
            }
        } catch (\Throwable $e) {
            Log::error('PG search error: ' . $e->getMessage());
        }

        // a plain greeting: greet back and ask what they are looking for (no AI needed)
        if (preg_match('/^\s*(h+i+|he+y+|h+y+|hello+|helo+|hlo+|namaste|namaskar|नमस्ते|नमस्कार|हेलो|हाय|good\s+(morning|afternoon|evening))(\s+(bhai|sir|ji|there|pizi))?[\s!.,🙏👋]*$/iu', $message)) {
            return $language === 'hi'
                ? "नमस्ते! 👋 आपको किस city और area में PG चाहिए?\n\nऐसे लिख सकते हैं:\n• Karol Bagh me PG\n• Noida me girls PG 8000 tak"
                : "Hello! 👋 Which city and area do you want a PG in?\n\nYou can type, for example:\n• PG in Karol Bagh\n• Girls PG in Noida under 8000";
        }

        // First, use Gemini to understand the intent
        $intent = $this->detectIntent($message, $language);

        // Keyword safety-net only kicks in if Gemini's own classification call
        // failed (network error / bad JSON) — when Gemini DID respond, trust
        // its judgement. Previously this ran unconditionally and words like
        // "room", "stay", "accommodation" (common in general questions too)
        // hijacked every message into a property-search dump.
        $looksLikePgQuery = false;
        if (($intent['_source'] ?? 'gemini') === 'fallback') {
            $pgKeywords = ['pg ', ' pg', 'hostel', 'kiraye', 'coliving', 'paying guest', 'पीजी'];
            $lowerMsg = mb_strtolower($message);
            foreach ($pgKeywords as $kw) {
                if (str_contains($lowerMsg, $kw)) {
                    $looksLikePgQuery = true;
                    break;
                }
            }
        }

        if ($intent['type'] === 'pg_search' || $looksLikePgQuery) {
            // PG search: asks for the area first when none was given
            try {
                return $search->answer($search->read($message, $intent), $language, $sessionId);
            } catch (\Throwable $e) {
                Log::error('PG search error: ' . $e->getMessage());

                return $language === 'hi' ? 'कोई error आई। कृपया फिर से कोशिश करें।' : 'An error occurred. Please try again.';
            }
        }

        // It's a general question - use Gemini directly, with recent
        // conversation context so follow-up questions make sense
        return $this->getGeminiResponse($message, $language, $history);
    }

    private function detectIntent($message, $language)
    {
        $prompt = "Analyze this user message and determine if they're looking for PGs/hostels or asking a general question.

User message: \"$message\"

Write city and locality names in English letters. Respond ONLY with JSON (no markdown, no extra text):
{
  \"type\": \"pg_search\" or \"general\",
  \"keywords\": [list of relevant keywords],
  \"city\": \"city name if mentioned or null\",
  \"locality\": \"locality/sector/area/neighbourhood name if mentioned, e.g. 'Sector 58', 'Laxmi Nagar' — or null\",
  \"budget_min\": null or number,
  \"budget_max\": null or number,
  \"gender\": \"male\" or \"female\" or \"unisex\" or null,
  \"property_type\": \"pg\" or \"hostel\" or \"coliving\" or null
}";

        $text = $this->askGemini([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['maxOutputTokens' => 500, 'thinkingConfig' => ['thinkingBudget' => 0]],
        ], 8);

        if ($text !== null) {
            // Gemini kabhi kabhi ```json ... ``` mein wrap kar deta hai, usko clean karo
            $text = trim(preg_replace('/```json\s*|```\s*/', '', $text));
            $json = json_decode($text, true);

            if (is_array($json) && isset($json['type'])) {
                $json['_source'] = 'gemini';
                return $json;
            }

            Log::warning('Intent detection JSON parse failed. Raw text: ' . json_encode($text));
        }

        return ['type' => 'general', '_source' => 'fallback'];
    }

    private function getGeminiResponse($message, $language, array $history = [])
    {
        if (!$this->geminiApiKey) {
            Log::error('Gemini Error: GEMINI_API_KEY is not set');

            return $this->unavailable($language);
        }

        $systemPrompt = $language === 'hi'
            ? "आप Pizi.in का एक helpful AI assistant हैं। Pizi एक PG/hostel finder platform है। हमेशा user-friendly, short और informative जवाब दें। बहुत ज़रूरी: कभी भी किसी specific PG/property का नाम मत बताओ (चाहे Pizi का हो या किसी और platform का), कभी भी 99acres, NoBroker, MagicBricks जैसी किसी और वेबसाइट का ज़िक्र मत करो। PG इसी chat में खोजे जा सकते हैं: अगर कोई PG ढूंढ रहा हो, तो उनसे area, budget और boys/girls पूछो (website पर जाने को मत कहो)। listings कभी खुद मत बनाओ, और user के बताए बिना कोई city मत मानो। HARD RULE: हमेशा हिंदी में ही जवाब दो, चाहे पुरानी conversation में कोई भी भाषा इस्तेमाल हुई हो।"
            : "You are a helpful AI assistant for Pizi.in. Pizi is a PG/hostel finder platform. Always be friendly, concise and informative. IMPORTANT: Never mention specific property names, addresses, or listings — those must only come from Pizi's own database search. Never mention or recommend any other website or platform (like 99acres, NoBroker, MagicBricks, etc.). PGs can be searched right here in this chat: if someone is looking for one, ask them for the area, budget and boys/girls (do not send them to the website). Never invent listing details, and never assume a city they have not told you. HARD RULE: Always reply in English only, regardless of what language was used earlier in this conversation.";

        // Build multi-turn contents: system prompt, then recent conversation
        // history, then the new user message — so follow-up questions like
        // "what about under 10k?" understand what was asked before.
        $contents = [[
            'role' => 'user',
            'parts' => [['text' => $systemPrompt]],
        ]];

        foreach (array_slice($history, -8) as $turn) {
            $contents[] = [
                'role' => ($turn['sender'] ?? $turn['role'] ?? 'user') === 'bot' ? 'model' : 'user',
                'parts' => [['text' => (string) ($turn['message'] ?? $turn['text'] ?? '')]],
            ];
        }

        // Language reminder attached right next to the actual message (not
        // just at the very top) — AI models weigh recent context more, so a
        // rule stated once at the start can get drowned out by several turns
        // of history that happen to be in the other language.
        $langReminder = $language === 'hi' ? ' (कृपया सिर्फ हिंदी में जवाब दें।)' : ' (Please reply in English only.)';
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message . $langReminder]]];

        $text = $this->askGemini([
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
                // Without this, flash models burn most of the token budget on internal "thinking" and cut the
                // real answer off mid-sentence (finishReason: MAX_TOKENS).
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ], 15);

        return $text ?? $this->unavailable($language);
    }
}