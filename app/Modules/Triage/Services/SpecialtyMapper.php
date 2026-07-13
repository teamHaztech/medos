<?php

namespace App\Modules\Triage\Services;

class SpecialtyMapper
{
    /**
     * Keyword-to-specialty mapping. Each specialty has a set of trigger keywords
     * covering English and common transliterated Hindi/Arabic terms.
     */
    protected const SPECIALTY_KEYWORDS = [
        'cardiology' => [
            'chest pain', 'heart', 'blood pressure', 'bp', 'palpitation', 'cardiac',
            'seene mein dard', 'dil', 'alam fi alqalb',
        ],
        'orthopedics' => [
            'bone', 'joint', 'fracture', 'sprain', 'haddi', 'jodon', 'back pain',
            'kamar dard', 'arthritis', 'musculoskeletal', 'shoulder pain', 'knee pain',
        ],
        'pediatrics' => [
            // Note: age-based override happens in suggest() method
            'child', 'infant', 'newborn', 'bachcha', 'tifl',
        ],
        'gynecology' => [
            'pregnancy', 'pregnant', 'periods', 'menstrual', 'gynec', 'uterus',
            'ovary', 'pcos', 'garbh', 'hamal', 'mahawari', 'hayd',
        ],
        'dermatology' => [
            'skin', 'rash', 'acne', 'eczema', 'psoriasis', 'fungal', 'allergy skin',
            'khujli', 'daane', 'jild',
        ],
        'ophthalmology' => [
            'eye', 'vision', 'blindness', 'cataract', 'glaucoma',
            'aankh', 'nazar', 'ayn',
        ],
        'ent' => [
            'ear', 'nose', 'throat', 'sinus', 'tonsil', 'hearing', 'nasal',
            'kaan', 'naak', 'gala', 'udhun', 'anf', 'halq',
        ],
        'dental' => [
            'dental', 'tooth', 'teeth', 'gum', 'cavity', 'oral',
            'daant', 'asnan',
        ],
        'nutrition' => [
            'diet', 'nutrition', 'dietitian', 'dietician', 'nutritionist',
            'weight loss', 'weight gain', 'obesity', 'obese', 'overweight',
            'underweight', 'malnutrition', 'meal plan', 'food intake', 'eating habit',
            'khaana', 'aahaar', 'ghiza', 'wazan',
        ],
        'psychiatry' => [
            'mental health', 'anxiety', 'depression', 'stress', 'insomnia', 'panic',
            'psychiatric', 'bipolar', 'schizophrenia', 'mansik', 'iqtibas',
            'neend nahi', 'chinta',
        ],
        'gastroenterology' => [
            'stomach', 'digestion', 'gastric', 'ulcer', 'liver', 'hepatitis',
            'abdominal pain', 'vomiting', 'diarrhea', 'constipation', 'acidity',
            'pet dard', 'ulti', 'dast', 'qabz', 'maeda', 'alam fi albatn',
        ],
        'nephrology/urology' => [
            'kidney', 'urinary', 'bladder', 'dialysis', 'renal', 'prostate',
            'gurda', 'peshab', 'kulya', 'bawl',
        ],
        'neurology' => [
            'headache', 'migraine', 'seizure', 'nerve', 'numbness', 'paralysis',
            'sir dard', 'sudaa', 'daura', 'lakwa',
        ],
        'pulmonology' => [
            'breathing', 'asthma', 'copd', 'lung', 'cough chronic', 'tuberculosis', 'tb',
            'saans', 'phephda',
        ],
        'endocrinology' => [
            'diabetes', 'thyroid', 'hormone', 'sugar', 'insulin',
            'madhumeh', 'sukkar',
        ],
        'oncology' => [
            'cancer', 'tumor', 'lump', 'chemotherapy', 'biopsy',
            'sartan', 'rasauli',
        ],
        'general_medicine' => [
            'fever', 'cold', 'general', 'flu', 'weakness', 'fatigue', 'body pain',
            'bukhar', 'zukam', 'thakan', 'badan dard', 'humma',
            'follow up', 'checkup', 'vaccination',
        ],
    ];

    /**
     * Suggest a medical specialty based on chief complaint, age, and gender.
     */
    public function suggest(string $chiefComplaint, ?int $age = null, ?string $gender = null): string
    {
        $complaint = strtolower(trim($chiefComplaint));

        // Age-based override: children < 14 go to pediatrics unless complaint is clearly dental/eye/skin
        if ($age !== null && $age < 14) {
            $nonPediatricSpecialties = ['dental', 'ophthalmology', 'dermatology', 'ent'];
            $matched = $this->matchSpecialty($complaint);

            if (! in_array($matched, $nonPediatricSpecialties)) {
                return 'pediatrics';
            }

            return $matched;
        }

        return $this->matchSpecialty($complaint);
    }

    /**
     * Match complaint text against specialty keywords.
     */
    protected function matchSpecialty(string $complaint): string
    {
        $bestMatch = 'general_medicine';
        $bestMatchLength = 0;

        foreach (self::SPECIALTY_KEYWORDS as $specialty => $keywords) {
            if ($specialty === 'general_medicine') {
                continue; // Check last as fallback
            }

            foreach ($keywords as $keyword) {
                if (str_contains($complaint, $keyword) && strlen($keyword) > $bestMatchLength) {
                    $bestMatch = $specialty;
                    $bestMatchLength = strlen($keyword);
                }
            }
        }

        // If no specialty matched, check general_medicine keywords for confirmation
        if ($bestMatch === 'general_medicine' && $bestMatchLength === 0) {
            foreach (self::SPECIALTY_KEYWORDS['general_medicine'] as $keyword) {
                if (str_contains($complaint, $keyword)) {
                    return 'general_medicine';
                }
            }
        }

        return $bestMatch;
    }
}
