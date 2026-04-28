<?php

namespace App\Modules\Multilingual\Dictionaries;

class MedicalDictionary
{
    /**
     * Medical term translations.
     *
     * Structure: 'english_term' => [
     *     'hi' => 'Hindi (Devanagari)',
     *     'hi_latin' => 'Hindi (transliterated)',
     *     'ar' => 'Arabic',
     * ]
     */
    public const TERMS = [
        // Symptoms
        'fever' => ['hi' => 'बुखार', 'hi_latin' => 'bukhar', 'ar' => 'حمى'],
        'headache' => ['hi' => 'सिर दर्द', 'hi_latin' => 'sir dard', 'ar' => 'صداع'],
        'stomach pain' => ['hi' => 'पेट दर्द', 'hi_latin' => 'pet dard', 'ar' => 'ألم في المعدة'],
        'chest pain' => ['hi' => 'सीने में दर्द', 'hi_latin' => 'seene mein dard', 'ar' => 'ألم في الصدر'],
        'back pain' => ['hi' => 'पीठ दर्द', 'hi_latin' => 'peeth dard', 'ar' => 'ألم في الظهر'],
        'cough' => ['hi' => 'खांसी', 'hi_latin' => 'khansi', 'ar' => 'سعال'],
        'cold' => ['hi' => 'सर्दी', 'hi_latin' => 'sardi', 'ar' => 'زكام'],
        'sore throat' => ['hi' => 'गले में खराश', 'hi_latin' => 'gale mein kharash', 'ar' => 'التهاب الحلق'],
        'vomiting' => ['hi' => 'उल्टी', 'hi_latin' => 'ulti', 'ar' => 'قيء'],
        'nausea' => ['hi' => 'मतली', 'hi_latin' => 'matli', 'ar' => 'غثيان'],
        'diarrhea' => ['hi' => 'दस्त', 'hi_latin' => 'dast', 'ar' => 'إسهال'],
        'constipation' => ['hi' => 'कब्ज', 'hi_latin' => 'kabz', 'ar' => 'إمساك'],
        'dizziness' => ['hi' => 'चक्कर', 'hi_latin' => 'chakkar', 'ar' => 'دوخة'],
        'weakness' => ['hi' => 'कमज़ोरी', 'hi_latin' => 'kamzori', 'ar' => 'ضعف'],
        'fatigue' => ['hi' => 'थकान', 'hi_latin' => 'thakan', 'ar' => 'إرهاق'],
        'breathing difficulty' => ['hi' => 'सांस लेने में तकलीफ', 'hi_latin' => 'sans lene mein takleef', 'ar' => 'صعوبة في التنفس'],
        'joint pain' => ['hi' => 'जोड़ों का दर्द', 'hi_latin' => 'jodon ka dard', 'ar' => 'ألم المفاصل'],
        'swelling' => ['hi' => 'सूजन', 'hi_latin' => 'sujan', 'ar' => 'تورم'],
        'itching' => ['hi' => 'खुजली', 'hi_latin' => 'khujli', 'ar' => 'حكة'],
        'burning sensation' => ['hi' => 'जलन', 'hi_latin' => 'jalan', 'ar' => 'حرقة'],
        'rash' => ['hi' => 'दाने', 'hi_latin' => 'daane', 'ar' => 'طفح جلدي'],
        'bleeding' => ['hi' => 'खून बहना', 'hi_latin' => 'khoon behna', 'ar' => 'نزيف'],
        'pain' => ['hi' => 'दर्द', 'hi_latin' => 'dard', 'ar' => 'ألم'],
        'ear pain' => ['hi' => 'कान दर्द', 'hi_latin' => 'kaan dard', 'ar' => 'ألم في الأذن'],
        'eye pain' => ['hi' => 'आंख में दर्द', 'hi_latin' => 'aankh mein dard', 'ar' => 'ألم في العين'],
        'toothache' => ['hi' => 'दांत दर्द', 'hi_latin' => 'daant dard', 'ar' => 'ألم الأسنان'],
        'knee pain' => ['hi' => 'घुटने का दर्द', 'hi_latin' => 'ghutne ka dard', 'ar' => 'ألم الركبة'],

        // Conditions / Diseases
        'diabetes' => ['hi' => 'मधुमेह', 'hi_latin' => 'madhumeh', 'ar' => 'السكري'],
        'hypertension' => ['hi' => 'उच्च रक्तचाप', 'hi_latin' => 'ucch raktchap', 'ar' => 'ارتفاع ضغط الدم'],
        'high blood pressure' => ['hi' => 'हाई ब्लड प्रेशर', 'hi_latin' => 'high blood pressure', 'ar' => 'ضغط الدم المرتفع'],
        'low blood pressure' => ['hi' => 'लो ब्लड प्रेशर', 'hi_latin' => 'low blood pressure', 'ar' => 'ضغط الدم المنخفض'],
        'asthma' => ['hi' => 'दमा', 'hi_latin' => 'dama', 'ar' => 'الربو'],
        'allergy' => ['hi' => 'एलर्जी', 'hi_latin' => 'allergy', 'ar' => 'حساسية'],
        'infection' => ['hi' => 'संक्रमण', 'hi_latin' => 'sankraman', 'ar' => 'عدوى'],
        'fracture' => ['hi' => 'फ्रैक्चर', 'hi_latin' => 'fracture', 'ar' => 'كسر'],
        'heart disease' => ['hi' => 'हृदय रोग', 'hi_latin' => 'hriday rog', 'ar' => 'مرض القلب'],
        'kidney disease' => ['hi' => 'गुर्दे की बीमारी', 'hi_latin' => 'gurde ki bimari', 'ar' => 'مرض الكلى'],
        'liver disease' => ['hi' => 'लिवर की बीमारी', 'hi_latin' => 'liver ki bimari', 'ar' => 'مرض الكبد'],
        'thyroid' => ['hi' => 'थायराइड', 'hi_latin' => 'thyroid', 'ar' => 'الغدة الدرقية'],
        'anemia' => ['hi' => 'खून की कमी', 'hi_latin' => 'khoon ki kami', 'ar' => 'فقر الدم'],
        'pneumonia' => ['hi' => 'निमोनिया', 'hi_latin' => 'pneumonia', 'ar' => 'التهاب رئوي'],
        'malaria' => ['hi' => 'मलेरिया', 'hi_latin' => 'malaria', 'ar' => 'ملاريا'],
        'dengue' => ['hi' => 'डेंगू', 'hi_latin' => 'dengue', 'ar' => 'حمى الضنك'],
        'typhoid' => ['hi' => 'टाइफाइड', 'hi_latin' => 'typhoid', 'ar' => 'حمى التيفوئيد'],
        'tuberculosis' => ['hi' => 'टीबी', 'hi_latin' => 'TB', 'ar' => 'السل'],
        'cancer' => ['hi' => 'कैंसर', 'hi_latin' => 'cancer', 'ar' => 'سرطان'],

        // Body Parts
        'head' => ['hi' => 'सिर', 'hi_latin' => 'sir', 'ar' => 'رأس'],
        'chest' => ['hi' => 'छाती', 'hi_latin' => 'chaati', 'ar' => 'صدر'],
        'stomach' => ['hi' => 'पेट', 'hi_latin' => 'pet', 'ar' => 'معدة'],
        'heart' => ['hi' => 'दिल', 'hi_latin' => 'dil', 'ar' => 'قلب'],
        'lungs' => ['hi' => 'फेफड़े', 'hi_latin' => 'phephde', 'ar' => 'رئتين'],
        'kidney' => ['hi' => 'गुर्दा', 'hi_latin' => 'gurda', 'ar' => 'كلية'],
        'liver' => ['hi' => 'जिगर', 'hi_latin' => 'jigar', 'ar' => 'كبد'],

        // Medical Actions / Concepts
        'medicine' => ['hi' => 'दवाई', 'hi_latin' => 'dawai', 'ar' => 'دواء'],
        'treatment' => ['hi' => 'इलाज', 'hi_latin' => 'ilaj', 'ar' => 'علاج'],
        'surgery' => ['hi' => 'सर्जरी', 'hi_latin' => 'surgery', 'ar' => 'جراحة'],
        'blood test' => ['hi' => 'खून की जांच', 'hi_latin' => 'khoon ki jaanch', 'ar' => 'تحليل دم'],
        'x-ray' => ['hi' => 'एक्स-रे', 'hi_latin' => 'x-ray', 'ar' => 'أشعة سينية'],
        'ultrasound' => ['hi' => 'अल्ट्रासाउंड', 'hi_latin' => 'ultrasound', 'ar' => 'تصوير بالموجات فوق الصوتية'],
        'injection' => ['hi' => 'इंजेक्शन', 'hi_latin' => 'injection', 'ar' => 'حقنة'],
        'prescription' => ['hi' => 'नुस्खा', 'hi_latin' => 'nuskha', 'ar' => 'وصفة طبية'],
        'diagnosis' => ['hi' => 'निदान', 'hi_latin' => 'nidaan', 'ar' => 'تشخيص'],
        'appointment' => ['hi' => 'अपॉइंटमेंट', 'hi_latin' => 'appointment', 'ar' => 'موعد'],
        'hospital' => ['hi' => 'अस्पताल', 'hi_latin' => 'aspatal', 'ar' => 'مستشفى'],
        'doctor' => ['hi' => 'डॉक्टर', 'hi_latin' => 'doctor', 'ar' => 'طبيب'],
        'nurse' => ['hi' => 'नर्स', 'hi_latin' => 'nurse', 'ar' => 'ممرضة'],
        'patient' => ['hi' => 'मरीज़', 'hi_latin' => 'mareez', 'ar' => 'مريض'],
        'emergency' => ['hi' => 'इमरजेंसी', 'hi_latin' => 'emergency', 'ar' => 'طوارئ'],
        'ambulance' => ['hi' => 'एम्बुलेंस', 'hi_latin' => 'ambulance', 'ar' => 'سيارة إسعاف'],
    ];

    // ---------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------

    /**
     * Look up the translation of an English medical term in the given language.
     *
     * @param  string  $term      The English medical term (case-insensitive).
     * @param  string  $language  Target language code (e.g., 'hi', 'ar', 'hi_latin').
     * @return string|null        The translated term, or null if not found.
     */
    public function lookup(string $term, string $language): ?string
    {
        $key = mb_strtolower(trim($term));

        if (! isset(self::TERMS[$key])) {
            return null;
        }

        return self::TERMS[$key][$language] ?? null;
    }

    /**
     * Given a medical term in any language, return the English equivalent.
     *
     * Searches across all translations to find a match.
     *
     * @param  string  $term  The term in any supported language.
     * @return string|null    The English key, or null if not found.
     */
    public function reverseLookup(string $term): ?string
    {
        $needle = mb_strtolower(trim($term));

        // Direct English match
        if (isset(self::TERMS[$needle])) {
            return $needle;
        }

        // Search through translations
        foreach (self::TERMS as $englishTerm => $translations) {
            foreach ($translations as $translation) {
                if (mb_strtolower($translation) === $needle) {
                    return $englishTerm;
                }
            }
        }

        return null;
    }

    /**
     * Get all available terms as an array.
     */
    public function allTerms(): array
    {
        return self::TERMS;
    }

    /**
     * Get all supported languages in the dictionary.
     */
    public function supportedLanguages(): array
    {
        return ['en', 'hi', 'hi_latin', 'ar'];
    }
}
