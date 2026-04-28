<?php

namespace App\Modules\AIReceptionist\Services;

class EmergencyDetector
{
    /**
     * Emergency keyword lists by language (ISO 639-1).
     *
     * @var array<string, string[]>
     */
    private const KEYWORDS = [
        'en' => [
            'chest pain',
            'heart attack',
            'can\'t breathe',
            'cannot breathe',
            'cant breathe',
            'difficulty breathing',
            'not breathing',
            'unconscious',
            'passed out',
            'fainted',
            'seizure',
            'convulsion',
            'heavy bleeding',
            'severe bleeding',
            'bleeding heavily',
            'accident',
            'stroke',
            'choking',
            'poisoning',
            'overdose',
            'suicidal',
            'suicide',
            'anaphylaxis',
            'allergic reaction severe',
            'collapsed',
            'unresponsive',
            'head injury',
            'gunshot',
            'stabbed',
            'stabbing',
            'drowning',
            'electric shock',
            'burn severe',
            'severe burn',
        ],
        'hi' => [
            'seene mein dard',
            'chhati mein dard',
            'saans nahi aa rahi',
            'saans nahi',
            'behosh',
            'beyhosh',
            'dora pada',
            'bahut khoon',
            'khoon beh raha',
            'accident',
            'heart attack',
            'dil ka daura',
            'stroke',
            'zeher',
            'jal gaya',
        ],
        'ar' => [
            'ألم في الصدر',
            'نوبة قلبية',
            'لا أستطيع التنفس',
            'صعوبة في التنفس',
            'فاقد الوعي',
            'إغماء',
            'نوبة',
            'تشنج',
            'نزيف حاد',
            'نزيف شديد',
            'حادث',
            'سكتة دماغية',
            'اختناق',
            'تسمم',
            'حروق شديدة',
        ],
    ];

    /**
     * Detect if a message contains emergency indicators.
     *
     * This uses pure keyword/regex matching -- NO AI calls -- to be as fast as possible.
     *
     * @return array{is_emergency: bool, confidence: float, matched_keywords: string[], reason: string}
     */
    public function detect(string $message, ?string $language = null): array
    {
        $normalised = $this->normalise($message);
        $matched    = [];

        // Determine which keyword lists to check
        $languagesToCheck = $language
            ? [$language, 'en'] // Always check English as a fallback
            : array_keys(self::KEYWORDS);

        $languagesToCheck = array_unique($languagesToCheck);

        foreach ($languagesToCheck as $lang) {
            $keywords = self::KEYWORDS[$lang] ?? [];

            foreach ($keywords as $keyword) {
                $normalisedKeyword = $this->normalise($keyword);

                if (mb_strpos($normalised, $normalisedKeyword) !== false) {
                    $matched[] = $keyword;
                }
            }
        }

        // Also check the original (un-normalised) message for Arabic/Unicode keywords
        foreach (['ar'] as $lang) {
            if (!in_array($lang, $languagesToCheck)) {
                continue;
            }

            foreach (self::KEYWORDS[$lang] ?? [] as $keyword) {
                if (mb_strpos($message, $keyword) !== false && !in_array($keyword, $matched)) {
                    $matched[] = $keyword;
                }
            }
        }

        $matched = array_unique($matched);

        if (empty($matched)) {
            return [
                'is_emergency'     => false,
                'confidence'       => 0.0,
                'matched_keywords' => [],
                'reason'           => '',
            ];
        }

        // Confidence scales with number of matched keywords, capped at 1.0
        $confidence = min(1.0, 0.7 + (count($matched) - 1) * 0.1);

        return [
            'is_emergency'     => true,
            'confidence'       => $confidence,
            'matched_keywords' => $matched,
            'reason'           => 'Emergency keywords detected: ' . implode(', ', $matched),
        ];
    }

    /**
     * Normalise text for keyword matching: lowercase and collapse whitespace.
     */
    private function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
