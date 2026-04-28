<?php

namespace App\Modules\Multilingual\Services;

use App\Modules\AIReceptionist\Services\AIService;
use Illuminate\Support\Facades\Log;

class LanguageDetector
{
    // ---------------------------------------------------------------
    // Unicode Script Ranges
    // ---------------------------------------------------------------

    private const SCRIPT_RANGES = [
        'devanagari' => '/[\x{0900}-\x{097F}]/u',
        'arabic'     => '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u',
        'tamil'      => '/[\x{0B80}-\x{0BFF}]/u',
        'telugu'     => '/[\x{0C00}-\x{0C7F}]/u',
        'kannada'    => '/[\x{0C80}-\x{0CFF}]/u',
        'bengali'    => '/[\x{0980}-\x{09FF}]/u',
    ];

    private const SCRIPT_TO_LANGUAGE = [
        'devanagari' => 'hi', // Could also be Marathi; we refine below
        'arabic'     => 'ar',
        'tamil'      => 'ta',
        'telugu'     => 'te',
        'kannada'    => 'kn',
        'bengali'    => 'bn',
    ];

    // ---------------------------------------------------------------
    // Hinglish Detection Keywords
    // ---------------------------------------------------------------

    private const HINGLISH_WORDS = [
        'mujhe', 'hai', 'kya', 'doctor', 'dard', 'bukhar', 'mera', 'meri',
        'pet', 'sir', 'haan', 'nahi', 'acha', 'theek', 'dawai', 'bimari',
        'rog', 'tabiyat', 'kaisi', 'kamzori', 'chakkar', 'ulti', 'dast',
        'khasi', 'zukam', 'sardi', 'gala', 'kaan', 'aankh', 'peeth',
        'ghutna', 'hath', 'pair', 'seena', 'sans', 'neend', 'bhookh',
        'pyas', 'sujan', 'khujli', 'jalan', 'sar', 'dawa', 'ilaj',
        'aspatal', 'bimarr', 'sehat', 'pareshani', 'takleef', 'karein',
        'dijiye', 'chahiye', 'batao', 'bataiye', 'dikhao', 'lagta',
        'hota', 'pehle', 'baad', 'abhi', 'kal', 'aaj', 'subah', 'raat',
        'khana', 'pani', 'appointment', 'milna', 'aana', 'jaana',
    ];

    // ---------------------------------------------------------------
    // Marathi Keyword Hints (Devanagari script + specific words)
    // ---------------------------------------------------------------

    private const MARATHI_WORDS = [
        'mala', 'aahe', 'kay', 'kasa', 'tuzi', 'majha', 'dukhat',
        'tap', 'arogya', 'aushadh', 'rugnalaya', 'upchar',
    ];

    // ---------------------------------------------------------------
    // Common English Words (for Latin script disambiguation)
    // ---------------------------------------------------------------

    private const ENGLISH_WORDS = [
        'the', 'is', 'am', 'are', 'was', 'were', 'have', 'has', 'had',
        'will', 'would', 'could', 'should', 'can', 'may', 'might',
        'my', 'your', 'his', 'her', 'its', 'our', 'their',
        'this', 'that', 'these', 'those', 'what', 'when', 'where',
        'how', 'who', 'why', 'which', 'hello', 'please', 'thank',
        'need', 'want', 'help', 'pain', 'fever', 'headache', 'feeling',
        'sick', 'appointment', 'schedule', 'medicine', 'hospital',
    ];

    private ?AIService $aiService;

    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService;
    }

    // ---------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------

    /**
     * Detect the language of the given text.
     *
     * Returns an associative array:
     *   ['language' => 'hi', 'confidence' => 0.92, 'script' => 'latin']
     */
    public function detect(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['language' => 'en', 'confidence' => 0.0, 'script' => 'latin'];
        }

        // Step 1: Script-based detection (high confidence for non-Latin scripts)
        $scriptLanguage = $this->detectFromScript($text);

        if ($scriptLanguage !== null) {
            $script = $this->identifyScript($text);

            // Refine Devanagari: could be Hindi or Marathi
            if ($scriptLanguage === 'hi') {
                $scriptLanguage = $this->refineDevanagari($text);
            }

            return [
                'language'   => $scriptLanguage,
                'confidence' => 0.95,
                'script'     => $script,
            ];
        }

        // Step 2: Latin script -- keyword frequency analysis
        $hinglishScore = $this->calculateHinglishScore($text);
        $englishScore  = $this->calculateEnglishScore($text);

        // Clear Hinglish
        if ($hinglishScore > 0.4 && $hinglishScore > $englishScore) {
            return [
                'language'   => 'hi',
                'confidence' => min(0.90, 0.5 + $hinglishScore),
                'script'     => 'latin',
            ];
        }

        // Clear English
        if ($englishScore > 0.4 && $englishScore > $hinglishScore) {
            return [
                'language'   => 'en',
                'confidence' => min(0.95, 0.5 + $englishScore),
                'script'     => 'latin',
            ];
        }

        // Step 3: Ambiguous -- check confidence threshold
        $threshold = config('medos.languages.detection_confidence_threshold', 0.8);
        $bestScore = max($hinglishScore, $englishScore);

        if ($bestScore < 0.3) {
            // Very ambiguous, delegate to AI
            return $this->detectWithAI($text);
        }

        // Low confidence heuristic result
        $bestLang = $hinglishScore >= $englishScore ? 'hi' : 'en';

        return [
            'language'   => $bestLang,
            'confidence' => 0.5 + ($bestScore * 0.3),
            'script'     => 'latin',
        ];
    }

    /**
     * Detect language from non-Latin script characters.
     *
     * Returns the language code or null if text is Latin/ambiguous.
     */
    public function detectFromScript(string $text): ?string
    {
        $scriptCounts = [];

        foreach (self::SCRIPT_RANGES as $script => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $count = count($matches[0] ?? []);

            if ($count > 0) {
                $scriptCounts[$script] = $count;
            }
        }

        if (empty($scriptCounts)) {
            return null;
        }

        // Use the script with the most character matches
        arsort($scriptCounts);
        $dominantScript = array_key_first($scriptCounts);

        return self::SCRIPT_TO_LANGUAGE[$dominantScript] ?? null;
    }

    /**
     * Detect if text is Hinglish (Hindi written in Latin script).
     */
    public function isHinglish(string $text): bool
    {
        return $this->calculateHinglishScore($text) > 0.3;
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Identify the primary script used in the text.
     */
    private function identifyScript(string $text): string
    {
        foreach (self::SCRIPT_RANGES as $script => $pattern) {
            if (preg_match($pattern, $text)) {
                return $script;
            }
        }

        return 'latin';
    }

    /**
     * Refine Devanagari detection: distinguish Hindi from Marathi.
     */
    private function refineDevanagari(string $text): string
    {
        $lower = mb_strtolower($text);

        $marathiHits = 0;
        foreach (self::MARATHI_WORDS as $word) {
            if (mb_strpos($lower, $word) !== false) {
                $marathiHits++;
            }
        }

        return $marathiHits >= 2 ? 'mr' : 'hi';
    }

    /**
     * Calculate the Hinglish keyword score for Latin-script text.
     *
     * Returns a float between 0.0 and 1.0.
     */
    private function calculateHinglishScore(string $text): float
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($text)));
        $totalWords = count($words);

        if ($totalWords === 0) {
            return 0.0;
        }

        $hinglishHits = 0;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z]/', '', $word);

            if ($clean !== '' && in_array($clean, self::HINGLISH_WORDS, true)) {
                $hinglishHits++;
            }
        }

        return $hinglishHits / $totalWords;
    }

    /**
     * Calculate the English keyword score for Latin-script text.
     *
     * Returns a float between 0.0 and 1.0.
     */
    private function calculateEnglishScore(string $text): float
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($text)));
        $totalWords = count($words);

        if ($totalWords === 0) {
            return 0.0;
        }

        $englishHits = 0;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z]/', '', $word);

            if ($clean !== '' && in_array($clean, self::ENGLISH_WORDS, true)) {
                $englishHits++;
            }
        }

        return $englishHits / $totalWords;
    }

    /**
     * Delegate language detection to the AI service for ambiguous text.
     */
    private function detectWithAI(string $text): array
    {
        if (!$this->aiService) {
            return ['language' => 'en', 'confidence' => 0.3, 'script' => 'latin'];
        }

        try {
            $supported = implode(', ', config('medos.languages.supported', ['en']));

            $response = $this->aiService->chat(
                systemPrompt: "You are a language detection service. Identify the language of the user's text. "
                    . "Supported languages: {$supported}. "
                    . "Respond ONLY with a JSON object: {\"language\": \"<code>\", \"confidence\": <0-1>, \"script\": \"<script>\"}. "
                    . "Use ISO 639-1 codes. Script should be: latin, devanagari, arabic, tamil, telugu, kannada, bengali.",
                messages: [
                    ['role' => 'user', 'content' => $text],
                ],
            );

            $decoded = json_decode($response, true);

            if (is_array($decoded) && isset($decoded['language'])) {
                return [
                    'language'   => $decoded['language'],
                    'confidence' => (float) ($decoded['confidence'] ?? 0.7),
                    'script'     => $decoded['script'] ?? 'latin',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[MedOS] AI language detection failed, defaulting to English', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback
        return ['language' => 'en', 'confidence' => 0.3, 'script' => 'latin'];
    }
}
