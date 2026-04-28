<?php

namespace App\Modules\Multilingual\Services;

use App\Modules\AIReceptionist\Services\AIService;
use App\Modules\Multilingual\Dictionaries\MedicalDictionary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    // ---------------------------------------------------------------
    // Pre-defined Localized Messages
    // ---------------------------------------------------------------

    public const MESSAGES = [
        'welcome' => [
            'en' => 'Welcome to :hospital! How can we help you today?',
            'hi' => ':hospital में आपका स्वागत है! आज हम आपकी कैसे मदद कर सकते हैं?',
            'ar' => 'مرحباً بكم في :hospital! كيف يمكننا مساعدتك اليوم؟',
        ],
        'appointment_confirmed' => [
            'en' => 'Your appointment with Dr. :doctor is confirmed for :date at :time.',
            'hi' => 'डॉ. :doctor के साथ आपकी अपॉइंटमेंट :date को :time बजे कन्फर्म है।',
            'ar' => 'تم تأكيد موعدك مع د. :doctor في :date الساعة :time.',
        ],
        'appointment_reminder' => [
            'en' => 'Reminder: You have an appointment with Dr. :doctor tomorrow at :time. Reply YES to confirm or CANCEL to cancel.',
            'hi' => 'याद दिलाना: कल :time बजे डॉ. :doctor के साथ आपकी अपॉइंटमेंट है। कन्फर्म करने के लिए YES या रद्द करने के लिए CANCEL लिखें।',
            'ar' => 'تذكير: لديك موعد مع د. :doctor غداً الساعة :time. أرسل YES للتأكيد أو CANCEL للإلغاء.',
        ],
        'queue_update' => [
            'en' => 'Queue update: You are number :position in line. Estimated wait time: :minutes minutes.',
            'hi' => 'कतार अपडेट: आप लाइन में :position नंबर पर हैं। अनुमानित प्रतीक्षा समय: :minutes मिनट।',
            'ar' => 'تحديث الطابور: أنت رقم :position في الصف. وقت الانتظار المتوقع: :minutes دقيقة.',
        ],
        'your_turn' => [
            'en' => 'It\'s your turn! Please proceed to :room.',
            'hi' => 'आपकी बारी आ गई है! कृपया :room में जाएं।',
            'ar' => 'حان دورك! يرجى التوجه إلى :room.',
        ],
        'prescription_ready' => [
            'en' => 'Your prescription is ready for pickup at the pharmacy.',
            'hi' => 'आपका नुस्खा फार्मेसी से लेने के लिए तैयार है।',
            'ar' => 'وصفتك الطبية جاهزة للاستلام من الصيدلية.',
        ],
        'feedback_request' => [
            'en' => 'Thank you for visiting :hospital! Please rate your experience from 1-5 (1=Poor, 5=Excellent).',
            'hi' => ':hospital आने के लिए धन्यवाद! कृपया अपने अनुभव को 1-5 में रेट करें (1=खराब, 5=उत्कृष्ट)।',
            'ar' => 'شكراً لزيارتكم :hospital! يرجى تقييم تجربتك من 1-5 (1=سيئ، 5=ممتاز).',
        ],
        'follow_up_reminder' => [
            'en' => 'Hi :name, this is a reminder for your follow-up visit on :date. Would you like to book an appointment?',
            'hi' => 'नमस्ते :name, यह आपकी :date को फॉलो-अप विज़िट की याद है। क्या आप अपॉइंटमेंट बुक करना चाहेंगे?',
            'ar' => 'مرحباً :name، هذا تذكير بزيارة المتابعة في :date. هل تود حجز موعد؟',
        ],
        'medication_reminder' => [
            'en' => 'Medication reminder: Please take :medication as prescribed by your doctor.',
            'hi' => 'दवा की याद: कृपया अपने डॉक्टर के निर्देशानुसार :medication लें।',
            'ar' => 'تذكير بالدواء: يرجى تناول :medication حسب وصفة طبيبك.',
        ],
        'lab_results_ready' => [
            'en' => 'Your lab results are ready. Please visit the hospital or contact your doctor.',
            'hi' => 'आपके लैब रिज़ल्ट तैयार हैं। कृपया अस्पताल आएं या अपने डॉक्टर से संपर्क करें।',
            'ar' => 'نتائج مختبرك جاهزة. يرجى زيارة المستشفى أو الاتصال بطبيبك.',
        ],
        'insurance_approved' => [
            'en' => 'Good news! Your insurance pre-authorization has been approved.',
            'hi' => 'अच्छी खबर! आपकी बीमा पूर्व-मंजूरी स्वीकृत हो गई है।',
            'ar' => 'أخبار سارة! تمت الموافقة على التفويض المسبق للتأمين الخاص بك.',
        ],
        'insurance_denied' => [
            'en' => 'Your insurance pre-authorization was not approved. Please contact the billing desk for payment options.',
            'hi' => 'आपकी बीमा पूर्व-मंजूरी स्वीकृत नहीं हुई। भुगतान विकल्पों के लिए कृपया बिलिंग डेस्क से संपर्क करें।',
            'ar' => 'لم تتم الموافقة على التفويض المسبق للتأمين. يرجى الاتصال بمكتب الفواتير لخيارات الدفع.',
        ],
        'bill_generated' => [
            'en' => 'Your bill of :amount has been generated. Bill number: :bill_number.',
            'hi' => ':amount का आपका बिल तैयार हो गया है। बिल नंबर: :bill_number।',
            'ar' => 'تم إنشاء فاتورتك بمبلغ :amount. رقم الفاتورة: :bill_number.',
        ],
        'thank_you' => [
            'en' => 'Thank you! We wish you good health.',
            'hi' => 'धन्यवाद! हम आपके अच्छे स्वास्थ्य की कामना करते हैं।',
            'ar' => 'شكراً لك! نتمنى لك دوام الصحة والعافية.',
        ],
    ];

    private AIService $aiService;
    private MedicalDictionary $dictionary;

    public function __construct(AIService $aiService, MedicalDictionary $dictionary)
    {
        $this->aiService = $aiService;
        $this->dictionary = $dictionary;
    }

    // ---------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------

    /**
     * Translate text between languages using AI.
     *
     * @param  string  $text  The text to translate.
     * @param  string  $from  Source language code (e.g., 'en').
     * @param  string  $to    Target language code (e.g., 'hi').
     * @return string         The translated text.
     */
    public function translate(string $text, string $from, string $to): string
    {
        if ($from === $to) {
            return $text;
        }

        // Check cache first
        $cacheKey = 'medos_translation:' . md5("{$from}:{$to}:{$text}");
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $languageNames = $this->languageNames();
            $fromName = $languageNames[$from] ?? $from;
            $toName = $languageNames[$to] ?? $to;

            $response = $this->aiService->chat(
                systemPrompt: "You are a medical translation assistant for a hospital system. "
                    . "Translate the following text from {$fromName} to {$toName}. "
                    . "Keep medical terminology accurate. Respond ONLY with the translated text, nothing else.",
                messages: [
                    ['role' => 'user', 'content' => $text],
                ],
            );

            $translated = trim($response);

            // Cache for 24 hours
            Cache::put($cacheKey, $translated, now()->addHours(24));

            return $translated;
        } catch (\Throwable $e) {
            Log::error('[MedOS] Translation failed', [
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            // Return original text as fallback
            return $text;
        }
    }

    /**
     * Look up a medical term translation from the dictionary.
     *
     * @param  string  $term      The medical term (in English).
     * @param  string  $language  Target language code.
     * @return string|null        The translated term, or null.
     */
    public function getMedicalTerm(string $term, string $language): ?string
    {
        return $this->dictionary->lookup($term, $language);
    }

    /**
     * Get a pre-defined localized message with parameter substitution.
     *
     * @param  string  $key       The message key (e.g., 'welcome', 'appointment_confirmed').
     * @param  string  $language  The language code.
     * @param  array   $params    Substitution parameters (e.g., ['hospital' => 'MedOS Clinic']).
     * @return string             The localized and parameterized message.
     */
    public function getLocalizedMessage(string $key, string $language, array $params = []): string
    {
        $defaultLang = config('medos.languages.default', 'en');

        // Try requested language first, then fall back to default
        $template = self::MESSAGES[$key][$language]
            ?? self::MESSAGES[$key][$defaultLang]
            ?? $key;

        // Replace :param placeholders
        foreach ($params as $param => $value) {
            $template = str_replace(":{$param}", $value, $template);
        }

        return $template;
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Map of language codes to human-readable names for AI prompts.
     */
    private function languageNames(): array
    {
        return [
            'en' => 'English',
            'hi' => 'Hindi',
            'ar' => 'Arabic',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'kn' => 'Kannada',
            'bn' => 'Bengali',
            'mr' => 'Marathi',
        ];
    }
}
