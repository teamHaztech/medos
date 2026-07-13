<?php

namespace App\Modules\AIReceptionist\Services;

use Carbon\Carbon;

class NightMedicalAdviceService
{
    /**
     * Symptoms supported by the chatbot
     */
    protected array $symptomKeywords = [

        'stomach pain',
        'stomach ache',
        'abdominal pain',
        'abdomen pain',
        'gas',
        'gastric',
        'acidity',
        'acid reflux',
        'indigestion',

        'vomiting',
        'nausea',

        'diarrhea',
        'loose motion',

        'constipation',

        'fever',
        'high fever',
        'temperature',

        'headache',
        'migraine',

        'body pain',
        'body ache',
        'muscle pain',

        'cold',
        'cough',
        'sore throat',

        'dizziness',
        'weakness',

        'food poisoning',

        'dehydration',

        'ear pain',

        'tooth pain',

        'back pain',

        'joint pain',

        'allergy',

        'skin rash'
    ];

    /**
     * Emergency symptoms
     */
    protected array $emergencyKeywords = [

        'chest pain',

        'difficulty breathing',

        'cannot breathe',

        'shortness of breath',

        'unconscious',

        'loss of consciousness',

        'blood in vomit',

        'vomiting blood',

        'blood in stool',

        'black stool',

        'severe stomach pain',

        'severe abdominal pain',

        'heart attack',

        'stroke',

        'seizure',

        'fits',

        'severe burn',

        'electric shock',

        'major accident',

        'heavy bleeding',

        'cannot wake',

        'confused',

        'blue lips',

        'not breathing'
    ];

    /**
     * Simple OTC medicine database
     */
    protected array $medicineDatabase = [

        'fever' => [
            'medicine' => 'Paracetamol',
            'dose' => 'Follow the package directions or a healthcare professional’s advice.',
            'warning' => 'Avoid exceeding the recommended maximum daily dose. Use caution in liver disease.'
        ],

        'headache' => [
            'medicine' => 'Paracetamol',
            'dose' => 'Follow the package directions.',
            'warning' => 'Seek medical care if severe, sudden, or associated with weakness or confusion.'
        ],

        'acidity' => [
            'medicine' => 'Antacid',
            'dose' => 'Take according to the product label.',
            'warning' => 'Persistent symptoms require medical evaluation.'
        ],

        'gas' => [
            'medicine' => 'Simethicone',
            'dose' => 'Use according to the package instructions.',
            'warning' => 'Persistent abdominal pain needs medical assessment.'
        ],

        'diarrhea' => [
            'medicine' => 'ORS (Oral Rehydration Solution)',
            'dose' => 'Drink small amounts frequently according to the package instructions.',
            'warning' => 'Seek medical care if diarrhea is severe, bloody, or lasts more than 48 hours.'
        ],

        'constipation' => [
            'medicine' => 'Fiber supplement',
            'dose' => 'Use according to the product instructions.',
            'warning' => 'Persistent constipation or severe abdominal pain requires medical evaluation.'
        ],

        'cough' => [
            'medicine' => 'Cough Syrup',
            'dose' => 'Follow the package directions.',
            'warning' => 'Seek care if accompanied by difficulty breathing, chest pain, or coughing blood.'
        ],

        'cold' => [
            'medicine' => 'Steam Inhalation',
            'dose' => '10–15 minutes several times a day as tolerated.',
            'warning' => 'Seek medical advice if symptoms persist or worsen.'
        ],

        'sore throat' => [
            'medicine' => 'Warm Salt Water Gargle',
            'dose' => 'Gargle several times daily.',
            'warning' => 'Seek medical care for severe pain, difficulty swallowing, or high fever.'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the message appears
     * to request night medical advice.
     */
    public function isNightAdviceRequest(string $message): bool
    {
        $message = strtolower(trim($message));

        foreach ($this->symptomKeywords as $keyword) {

            if (str_contains($message, $keyword)) {
                return true;
            }

        }

        return false;
    }

    /**
     * Check if current time is night.
     */
    public function isNightTime(): bool
    {
        $hour = Carbon::now()->hour;

        return ($hour >= 21 || $hour <= 6);
    }

    /**
     * Detect emergency symptoms.
     */
    public function detectEmergency(string $message): bool
    {
        $message = strtolower($message);

        foreach ($this->emergencyKeywords as $keyword) {

            if (str_contains($message, $keyword)) {

                return true;

            }

        }

        return false;
    }
    /**
     * Main assessment entry point.
     */
    public function assess(string $message, array $patient = []): string
    {
        $message = strtolower(trim($message));

        // Emergency comes first
        if ($this->detectEmergency($message)) {
            return $this->emergencyResponse();
        }

        // Determine symptom category
        $symptom = $this->identifySymptom($message);

        if ($symptom === null) {
            return $this->unknownSymptomResponse();
        }

        // Safety checks
        $warnings = [];

        if ($this->isChild($patient)) {
            $warnings[] = "This advice is for children. A parent or guardian should supervise treatment.";
        }

        if ($this->isPregnant($patient)) {
            $warnings[] = "Because pregnancy may affect treatment options, consult a healthcare professional before taking any medicine.";
        }

        if ($this->hasAllergy($patient)) {
            $warnings[] = "You reported allergies. Avoid medicines that have previously caused allergic reactions.";
        }

        if ($this->hasChronicDisease($patient)) {
            $warnings[] = "Your chronic medical condition may affect which medicines are safe. Consult your doctor or pharmacist if unsure.";
        }

        $response = [];

        $response[] = "Based on the information provided, your symptoms appear to be related to **{$symptom}**.";

        $response[] = "";

        $response[] = $this->selfCareAdvice($symptom);

        $response[] = "";

        $response[] = $this->medicineAdvice($symptom);

        if (!empty($warnings)) {

            $response[] = "";

            $response[] = "⚠ Safety Information";

            foreach ($warnings as $warning) {
                $response[] = "• ".$warning;
            }
        }

        $response[] = "";

        $response[] = "Please answer these questions:";

        foreach ($this->followUpQuestions($symptom) as $question) {

            $response[] = "• ".$question;

        }

        $response[] = "";

        $response[] = "If your symptoms become worse tonight or you develop severe pain, chest pain, difficulty breathing, persistent vomiting, confusion, or bleeding, seek emergency medical care immediately.";

        $response[] = "";

        $response[] = "If your condition remains stable, consider booking the first available appointment tomorrow morning.";

        return implode("\n", $response);
    }

    /**
     * Determine the symptom category.
     */
    protected function identifySymptom(string $message): ?string
    {
        $map = [

            'stomach pain' => [
                'stomach pain',
                'stomach ache',
                'abdominal pain',
                'abdomen pain'
            ],

            'acidity' => [
                'gas',
                'gastric',
                'acidity',
                'acid reflux',
                'heartburn'
            ],

            'fever' => [
                'fever',
                'temperature',
                'high fever'
            ],

            'headache' => [
                'headache',
                'migraine'
            ],

            'vomiting' => [
                'vomiting',
                'vomit',
                'nausea'
            ],

            'diarrhea' => [
                'diarrhea',
                'loose motion'
            ],

            'constipation' => [
                'constipation'
            ],

            'cold' => [
                'cold'
            ],

            'cough' => [
                'cough'
            ],

            'sore throat' => [
                'sore throat'
            ],

            'body pain' => [
                'body pain',
                'body ache',
                'muscle pain'
            ],

            'food poisoning' => [
                'food poisoning'
            ],

            'dehydration' => [
                'dehydration'
            ],

            'back pain' => [
                'back pain'
            ],

            'joint pain' => [
                'joint pain'
            ]
        ];

        foreach ($map as $type => $keywords) {

            foreach ($keywords as $keyword) {

                if (str_contains($message, $keyword)) {
                    return $type;
                }

            }

        }

        return null;
    }

    /**
     * Check if patient is a child.
     */
    protected function isChild(array $patient): bool
    {
        return isset($patient['age']) && $patient['age'] < 12;
    }

    /**
     * Pregnancy check.
     */
    protected function isPregnant(array $patient): bool
    {
        return !empty($patient['pregnant']);
    }

    /**
     * Allergy check.
     */
    protected function hasAllergy(array $patient): bool
    {
        return !empty($patient['allergies']);
    }

    /**
     * Chronic disease check.
     */
    protected function hasChronicDisease(array $patient): bool
    {
        return !empty($patient['chronic_conditions']);
    }

    /**
     * Follow-up questions based on symptom.
     */
    protected function followUpQuestions(string $symptom): array
    {
        return match ($symptom) {

            'stomach pain' => [
                'Where exactly is the pain located?',
                'When did the pain start?',
                'Rate the pain from 1 to 10.',
                'Do you have fever?',
                'Are you vomiting?',
                'Have you noticed blood in your stool?'
            ],

            'fever' => [
                'What is your temperature?',
                'How long have you had the fever?',
                'Do you have chills?',
                'Do you have difficulty breathing?'
            ],

            'headache' => [
                'Is the headache sudden or gradual?',
                'Do you have blurred vision?',
                'Do you have vomiting?',
                'Do you have neck stiffness?'
            ],

            'vomiting' => [
                'How many times have you vomited?',
                'Can you keep fluids down?',
                'Is there blood in the vomit?',
                'Do you have abdominal pain?'
            ],

            default => [
                'How severe are your symptoms?',
                'When did they begin?',
                'Have they become worse?',
                'Have you taken any medicine already?'
            ]

        };
    }
       /**
     * Self-care advice for common symptoms.
     */
    protected function selfCareAdvice(string $symptom): string
    {
        return match ($symptom) {

            'stomach pain' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink small amounts of clean water frequently.\n".
                "• Avoid spicy, oily, and heavy meals.\n".
                "• Rest for a few hours.\n".
                "• Eat light foods such as rice, bananas, or toast if you are hungry.\n".
                "• Avoid alcohol and smoking.",

            'acidity' =>
                "🏠 Self-Care Advice\n\n".
                "• Avoid spicy foods.\n".
                "• Do not lie down immediately after eating.\n".
                "• Eat small frequent meals.\n".
                "• Drink plenty of water.\n".
                "• Avoid coffee and carbonated drinks.",

            'fever' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink plenty of fluids.\n".
                "• Get enough rest.\n".
                "• Wear light clothing.\n".
                "• Monitor your temperature every 4-6 hours.",

            'headache' =>
                "🏠 Self-Care Advice\n\n".
                "• Rest in a quiet, dark room.\n".
                "• Drink water.\n".
                "• Avoid bright screens.\n".
                "• Sleep if possible.",

            'vomiting' =>
                "🏠 Self-Care Advice\n\n".
                "• Sip small amounts of water frequently.\n".
                "• Avoid solid food until vomiting settles.\n".
                "• Start with bland foods when able to eat.\n".
                "• Avoid dairy and spicy food temporarily.",

            'diarrhea' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink Oral Rehydration Solution (ORS).\n".
                "• Drink plenty of water.\n".
                "• Eat bananas, rice, applesauce, and toast.\n".
                "• Avoid oily food and milk.",

            'constipation' =>
                "🏠 Self-Care Advice\n\n".
                "• Increase water intake.\n".
                "• Eat fruits and vegetables.\n".
                "• Increase fiber in your diet.\n".
                "• Walk for 20–30 minutes if able.",

            'cold' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink warm fluids.\n".
                "• Rest well.\n".
                "• Use steam inhalation carefully.\n".
                "• Gargle with warm salt water if needed.",

            'cough' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink warm water.\n".
                "• Honey may soothe a cough in adults and children over 1 year old.\n".
                "• Avoid smoking.\n".
                "• Rest.",

            'sore throat' =>
                "🏠 Self-Care Advice\n\n".
                "• Gargle with warm salt water.\n".
                "• Drink warm fluids.\n".
                "• Avoid very cold drinks.\n".
                "• Rest your voice.",

            'body pain' =>
                "🏠 Self-Care Advice\n\n".
                "• Rest.\n".
                "• Drink plenty of fluids.\n".
                "• Gentle stretching may help if appropriate.\n".
                "• Monitor for fever or worsening symptoms.",

            'food poisoning' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink ORS.\n".
                "• Eat light meals only.\n".
                "• Avoid dairy and spicy food.\n".
                "• Rest.",

            'dehydration' =>
                "🏠 Self-Care Advice\n\n".
                "• Drink ORS slowly.\n".
                "• Drink water regularly.\n".
                "• Avoid alcohol.\n".
                "• Stay in a cool environment.",

            'back pain' =>
                "🏠 Self-Care Advice\n\n".
                "• Rest briefly but avoid prolonged bed rest.\n".
                "• Apply a warm compress if it helps.\n".
                "• Avoid lifting heavy objects.",

            'joint pain' =>
                "🏠 Self-Care Advice\n\n".
                "• Rest the affected joint.\n".
                "• Apply a cold pack if swollen.\n".
                "• Elevate the limb if possible.",

            default =>
                "Rest, drink fluids, monitor your symptoms, and seek medical care if they worsen."
        };
    }

    /**
     * OTC medicine guidance.
     */
    protected function medicineAdvice(string $symptom): string
    {
        if (!isset($this->medicineDatabase[$symptom])) {

            return "💊 No general over-the-counter medicine recommendation is available for this symptom. Please consult a healthcare professional.";

        }

        $medicine = $this->medicineDatabase[$symptom];

        return
            "💊 Suggested OTC Option\n\n".
            "Medicine: ".$medicine['medicine']."\n\n".
            "How to use: ".$medicine['dose']."\n\n".
            "Warning: ".$medicine['warning'];
    }

    /**
     * Emergency response.
     */
    protected function emergencyResponse(): string
    {
        return
            "🚨 EMERGENCY WARNING\n\n".
            "Your symptoms may indicate a serious medical emergency.\n\n".
            "Please seek emergency medical care immediately.\n\n".
            "Until help arrives:\n".
            "• Stay with another person if possible.\n".
            "• Do not drive yourself if you feel faint or have severe symptoms.\n".
            "• If you have chest pain, difficulty breathing, severe bleeding, loss of consciousness, or seizures, contact emergency medical services immediately.\n\n".
            "This chatbot cannot safely manage these symptoms at home.";
    }

    /**
     * Unknown symptom response.
     */
    protected function unknownSymptomResponse(): string
    {
        return
            "I'm sorry, I couldn't clearly identify your symptoms.\n\n".
            "Please describe:\n\n".
            "• Where does it hurt?\n".
            "• When did it start?\n".
            "• How severe is it (1–10)?\n".
            "• Do you have fever?\n".
            "• Have you taken any medicine?\n\n".
            "Example:\n".
            "\"I have stomach pain since 10 PM and have vomited twice.\"";
    }
        /**
     * Calculate symptom severity.
     *
     * Expected patient array:
     * [
     *   'pain_score' => 1-10,
     *   'duration_hours' => int,
     *   'fever' => true/false,
     *   'vomiting_count' => int
     * ]
     */
    protected function calculateSeverity(string $symptom, array $patient = []): string
    {
        $pain = (int)($patient['pain_score'] ?? 0);
        $duration = (int)($patient['duration_hours'] ?? 0);
        $vomiting = (int)($patient['vomiting_count'] ?? 0);
        $fever = (bool)($patient['fever'] ?? false);

        if ($pain >= 8) {
            return 'severe';
        }

        if ($vomiting >= 5) {
            return 'severe';
        }

        if ($duration >= 48) {
            return 'moderate';
        }

        if ($fever && $pain >= 6) {
            return 'moderate';
        }

        if ($pain >= 4) {
            return 'moderate';
        }

        return 'mild';
    }

    /**
     * Generate advice based on severity.
     */
    protected function severityAdvice(string $severity): string
    {
        return match ($severity) {

            'mild' =>
                "🟢 Severity: Mild\n\n".
                "Your symptoms appear mild based on the information provided.\n".
                "Continue home care, stay hydrated, and monitor your symptoms overnight.",

            'moderate' =>
                "🟡 Severity: Moderate\n\n".
                "Your symptoms need medical evaluation within the next 24 hours.\n".
                "Continue self-care tonight and arrange a doctor's appointment as soon as possible.",

            'severe' =>
                "🔴 Severity: Severe\n\n".
                "Your symptoms may require urgent medical attention.\n".
                "Please visit the nearest emergency department immediately.",

            default =>
                "Unable to determine severity."
        };
    }

    /**
     * Determine whether an appointment should be recommended.
     */
    protected function shouldRecommendAppointment(
        string $severity,
        string $symptom,
        array $patient = []
    ): bool {

        if ($severity === 'moderate' || $severity === 'severe') {
            return true;
        }

        $duration = (int)($patient['duration_hours'] ?? 0);

        if ($duration >= 24) {
            return true;
        }

        return in_array($symptom, [
            'food poisoning',
            'back pain',
            'joint pain'
        ]);
    }

    /**
     * Morning appointment recommendation.
     */
    protected function morningAppointmentMessage(): string
    {
        return
            "📅 Morning Appointment Recommendation\n\n".
            "If your symptoms do not improve overnight, ".
            "book the earliest available appointment tomorrow morning.\n\n".
            "Bring:\n".
            "• Your current medicines\n".
            "• Previous medical reports (if available)\n".
            "• List of allergies\n".
            "• Temperature readings (if fever)";
    }

    /**
     * Home monitoring advice.
     */
    protected function monitoringAdvice(string $symptom): string
    {
        return
            "🏠 Monitor Your Condition\n\n".
            "Please keep track of:\n".
            "• Pain level every 2 hours\n".
            "• Body temperature (if fever)\n".
            "• Vomiting or diarrhea episodes\n".
            "• Ability to eat and drink\n".
            "• Any worsening symptoms";
    }

    /**
     * Red-flag symptoms.
     */
    protected function redFlagSymptoms(string $symptom): array
    {
        return match ($symptom) {

            'stomach pain' => [
                'Pain suddenly becomes severe',
                'Blood in stool',
                'Persistent vomiting',
                'High fever',
                'Abdominal swelling'
            ],

            'fever' => [
                'Confusion',
                'Difficulty breathing',
                'Seizures',
                'Persistent high fever',
                'Severe dehydration'
            ],

            'headache' => [
                'Sudden severe headache',
                'Blurred vision',
                'Weakness',
                'Loss of consciousness',
                'Repeated vomiting'
            ],

            'vomiting' => [
                'Blood in vomit',
                'Unable to drink water',
                'Signs of dehydration',
                'Severe abdominal pain'
            ],

            default => [
                'Difficulty breathing',
                'Chest pain',
                'Loss of consciousness',
                'Heavy bleeding'
            ];
    }

    /**
     * Format red-flag warning.
     */
    protected function redFlagMessage(string $symptom): string
    {
        $flags = $this->redFlagSymptoms($symptom);

        $message = "🚨 Seek Emergency Care Immediately If You Develop:\n\n";

        foreach ($flags as $flag) {
            $message .= "• {$flag}\n";
        }

        return trim($message);
    }

    /**
     * Determine whether symptoms are worsening.
     */
    protected function symptomsWorsening(array $patient): bool
    {
        return (bool)($patient['worsening'] ?? false);
    }

    /**
     * Escalate worsening symptoms.
     */
    protected function worseningAdvice(): string
    {
        return
            "⚠ Your symptoms appear to be getting worse.\n\n".
            "Please do not wait until morning.\n".
            "Visit the nearest emergency department or urgent care facility immediately.";
    }
         /**
     * Calculate symptom severity.
     *
     * Expected patient array:
     * [
     *   'pain_score' => 1-10,
     *   'duration_hours' => int,
     *   'fever' => true/false,
     *   'vomiting_count' => int
     * ]
     */
    protected function calculateSeverity(string $symptom, array $patient = []): string
    {
        $pain = (int)($patient['pain_score'] ?? 0);
        $duration = (int)($patient['duration_hours'] ?? 0);
        $vomiting = (int)($patient['vomiting_count'] ?? 0);
        $fever = (bool)($patient['fever'] ?? false);

        if ($pain >= 8) {
            return 'severe';
        }

        if ($vomiting >= 5) {
            return 'severe';
        }

        if ($duration >= 48) {
            return 'moderate';
        }

        if ($fever && $pain >= 6) {
            return 'moderate';
        }

        if ($pain >= 4) {
            return 'moderate';
        }

        return 'mild';
    }

    /**
     * Generate advice based on severity.
     */
    protected function severityAdvice(string $severity): string
    {
        return match ($severity) {

            'mild' =>
                "🟢 Severity: Mild\n\n".
                "Your symptoms appear mild based on the information provided.\n".
                "Continue home care, stay hydrated, and monitor your symptoms overnight.",

            'moderate' =>
                "🟡 Severity: Moderate\n\n".
                "Your symptoms need medical evaluation within the next 24 hours.\n".
                "Continue self-care tonight and arrange a doctor's appointment as soon as possible.",

            'severe' =>
                "🔴 Severity: Severe\n\n".
                "Your symptoms may require urgent medical attention.\n".
                "Please visit the nearest emergency department immediately.",

            default =>
                "Unable to determine severity."
        };
    }

    /**
     * Determine whether an appointment should be recommended.
     */
    protected function shouldRecommendAppointment(
        string $severity,
        string $symptom,
        array $patient = []
    ): bool {

        if ($severity === 'moderate' || $severity === 'severe') {
            return true;
        }

        $duration = (int)($patient['duration_hours'] ?? 0);

        if ($duration >= 24) {
            return true;
        }

        return in_array($symptom, [
            'food poisoning',
            'back pain',
            'joint pain'
        ]);
    }

    /**
     * Morning appointment recommendation.
     */
    protected function morningAppointmentMessage(): string
    {
        return
            "📅 Morning Appointment Recommendation\n\n".
            "If your symptoms do not improve overnight, ".
            "book the earliest available appointment tomorrow morning.\n\n".
            "Bring:\n".
            "• Your current medicines\n".
            "• Previous medical reports (if available)\n".
            "• List of allergies\n".
            "• Temperature readings (if fever)";
    }

    /**
     * Home monitoring advice.
     */
    protected function monitoringAdvice(string $symptom): string
    {
        return
            "🏠 Monitor Your Condition\n\n".
            "Please keep track of:\n".
            "• Pain level every 2 hours\n".
            "• Body temperature (if fever)\n".
            "• Vomiting or diarrhea episodes\n".
            "• Ability to eat and drink\n".
            "• Any worsening symptoms";
    }

    /**
     * Red-flag symptoms.
     */
    protected function redFlagSymptoms(string $symptom): array
    {
        return match ($symptom) {

            'stomach pain' => [
                'Pain suddenly becomes severe',
                'Blood in stool',
                'Persistent vomiting',
                'High fever',
                'Abdominal swelling'
            ],

            'fever' => [
                'Confusion',
                'Difficulty breathing',
                'Seizures',
                'Persistent high fever',
                'Severe dehydration'
            ],

            'headache' => [
                'Sudden severe headache',
                'Blurred vision',
                'Weakness',
                'Loss of consciousness',
                'Repeated vomiting'
            ],

            'vomiting' => [
                'Blood in vomit',
                'Unable to drink water',
                'Signs of dehydration',
                'Severe abdominal pain'
            ],

            default => [
                'Difficulty breathing',
                'Chest pain',
                'Loss of consciousness',
                'Heavy bleeding'
            ];
    }

    /**
     * Format red-flag warning.
     */
    protected function redFlagMessage(string $symptom): string
    {
        $flags = $this->redFlagSymptoms($symptom);

        $message = "🚨 Seek Emergency Care Immediately If You Develop:\n\n";

        foreach ($flags as $flag) {
            $message .= "• {$flag}\n";
        }

        return trim($message);
    }

    /**
     * Determine whether symptoms are worsening.
     */
    protected function symptomsWorsening(array $patient): bool
    {
        return (bool)($patient['worsening'] ?? false);
    }

    /**
     * Escalate worsening symptoms.
     */
    protected function worseningAdvice(): string
    {
        return
            "⚠ Your symptoms appear to be getting worse.\n\n".
            "Please do not wait until morning.\n".
            "Visit the nearest emergency department or urgent care facility immediately.";
    }
       /**
     * Validate whether an OTC medicine is appropriate
     * based on patient information.
     */
    protected function validateMedicineSafety(
        string $symptom,
        array $patient = []
    ): array {

        $warnings = [];

        if ($this->isChild($patient)) {
            $warnings[] =
                "Children may require different medicines and doses. Consult a pediatrician whenever possible.";
        }

        if ($this->isPregnant($patient)) {
            $warnings[] =
                "Pregnancy may affect medicine safety. Do not start new medicines without medical advice.";
        }

        if ($this->hasAllergy($patient)) {
            $warnings[] =
                "Avoid medicines that have previously caused allergic reactions.";
        }

        if (!empty($patient['kidney_disease'])) {
            $warnings[] =
                "Kidney disease can affect medicine selection and dosage.";
        }

        if (!empty($patient['liver_disease'])) {
            $warnings[] =
                "Liver disease may limit the safe use of some medicines.";
        }

        if (!empty($patient['diabetes'])) {
            $warnings[] =
                "Some cough syrups and liquid medicines contain sugar. Choose sugar-free options when appropriate.";
        }

        if (!empty($patient['high_blood_pressure'])) {
            $warnings[] =
                "Some cold medicines may increase blood pressure. Ask a pharmacist if unsure.";
        }

        return $warnings;
    }

    /**
     * Produce medicine safety section.
     */
    protected function medicineSafetySection(
        string $symptom,
        array $patient = []
    ): string {

        $warnings = $this->validateMedicineSafety(
            $symptom,
            $patient
        );

        if (empty($warnings)) {

            return
                "✅ No common medicine safety concerns were identified based on the information provided.";

        }

        $text = "⚠ Medicine Safety\n\n";

        foreach ($warnings as $warning) {

            $text .= "• {$warning}\n";

        }

        return trim($text);
    }

    /**
     * Generate a summary of the patient's condition.
     */
    protected function patientSummary(
        string $symptom,
        string $severity,
        array $patient = []
    ): string {

        $summary = [];

        $summary[] = "📋 Patient Summary";

        $summary[] = "";

        $summary[] = "Detected symptom: ".$symptom;

        $summary[] = "Severity: ".ucfirst($severity);

        if (isset($patient['age'])) {

            $summary[] = "Age: ".$patient['age'];

        }

        if (!empty($patient['pregnant'])) {

            $summary[] = "Pregnancy: Yes";

        }

        if (!empty($patient['allergies'])) {

            $summary[] = "Known allergies: ".implode(
                ', ',
                (array)$patient['allergies']
            );

        }

        if (!empty($patient['chronic_conditions'])) {

            $summary[] = "Chronic illnesses: ".implode(
                ', ',
                (array)$patient['chronic_conditions']
            );

        }

        return implode("\n", $summary);
    }

    /**
     * Determine whether the patient should be
     * monitored during the night.
     */
    protected function requiresNightMonitoring(
        string $severity
    ): bool {

        return in_array(
            $severity,
            [
                'moderate',
                'severe'
            ]
        );
    }

    /**
     * Night monitoring advice.
     */
    protected function nightMonitoringInstructions(): string
    {
        return
            "🌙 Night Monitoring\n\n".
            "• Check your symptoms every 2 hours.\n".
            "• Drink enough fluids.\n".
            "• Avoid alcohol.\n".
            "• Get adequate rest.\n".
            "• Keep your phone nearby.\n".
            "• If symptoms suddenly worsen, seek emergency care immediately.";
    }

    /**
     * General disclaimer.
     */
    protected function disclaimer(): string
    {
        return
            "⚠ Disclaimer\n\n".
            "This chatbot provides general health information only.\n".
            "It does not diagnose diseases or replace a licensed healthcare professional.\n".
            "Always seek immediate medical care if you develop severe or life-threatening symptoms.";
    }

    /**
     * Build the final response returned to ChatController.
     */
    protected function buildFinalResponse(
        string $symptom,
        array $patient = []
    ): string {

        $severity = $this->calculateSeverity(
            $symptom,
            $patient
        );

        $sections = [];

        $sections[] = $this->patientSummary(
            $symptom,
            $severity,
            $patient
        );

        $sections[] = $this->severityAdvice(
            $severity
        );

        $sections[] = $this->selfCareAdvice(
            $symptom
        );

        $sections[] = $this->medicineAdvice(
            $symptom
        );

        $sections[] = $this->medicineSafetySection(
            $symptom,
            $patient
        );

        $sections[] = $this->monitoringAdvice(
            $symptom
        );

        $sections[] = $this->redFlagMessage(
            $symptom
        );

        if ($this->requiresNightMonitoring($severity)) {

            $sections[] =
                $this->nightMonitoringInstructions();

        }

        if ($this->shouldRecommendAppointment(
            $severity,
            $symptom,
            $patient
        )) {

            $sections[] =
                $this->morningAppointmentMessage();

        }

        $sections[] = $this->disclaimer();

        return implode("\n\n", $sections);
    }
        /**
     * Check whether symptoms have lasted longer than expected.
     */
    protected function symptomsPersisting(array $patient): bool
    {
        return (($patient['duration_hours'] ?? 0) >= 48);
    }

    /**
     * Advice for persistent symptoms.
     */
    protected function persistentSymptomsAdvice(): string
    {
        return
            "⚠ Persistent Symptoms\n\n".
            "Your symptoms have continued for more than 48 hours.\n".
            "Please arrange an appointment with a doctor even if you are feeling slightly better.";
    }

    /**
     * Hydration reminder.
     */
    protected function hydrationReminder(): string
    {
        return
            "💧 Hydration Reminder\n\n".
            "Drink enough clean water unless your doctor has advised you to restrict fluids.";
    }

    /**
     * Rest reminder.
     */
    protected function restReminder(): string
    {
        return
            "😴 Rest Reminder\n\n".
            "Adequate sleep and rest help the body recover more quickly.";
    }

    /**
     * Food advice.
     */
    protected function dietAdvice(string $symptom): string
    {
        return match ($symptom) {

            'stomach pain',
            'food poisoning',
            'vomiting',
            'diarrhea' =>
                "🍽 Diet Advice\n\n".
                "• Eat soft foods.\n".
                "• Avoid oily and spicy meals.\n".
                "• Avoid alcohol.\n".
                "• Eat small meals.",

            'fever' =>
                "🍽 Diet Advice\n\n".
                "• Drink plenty of fluids.\n".
                "• Eat nutritious light meals.\n".
                "• Include fruits if tolerated.",

            default =>
                "🍽 Diet Advice\n\n".
                "Maintain a balanced diet and drink enough fluids."
        };
    }

    /**
     * Return current date and time.
     */
    protected function generatedAt(): string
    {
        return Carbon::now()->format('d M Y h:i A');
    }

    /**
     * Footer.
     */
    protected function footer(): string
    {
        return
            "----------------------------------------\n".
            "AI Night Medical Assistant\n".
            "Generated: ".$this->generatedAt()."\n".
            "For educational purposes only.";
    }

    /**
     * Build a complete report.
     */
    public function generateReport(
        string $message,
        array $patient = []
    ): string {

        if ($this->detectEmergency($message)) {
            return $this->emergencyResponse();
        }

        $symptom = $this->identifySymptom($message);

        if (!$symptom) {
            return $this->unknownSymptomResponse();
        }

        $severity = $this->calculateSeverity(
            $symptom,
            $patient
        );

        $report = [];

        $report[] = $this->patientSummary(
            $symptom,
            $severity,
            $patient
        );

        $report[] = $this->severityAdvice(
            $severity
        );

        $report[] = $this->selfCareAdvice(
            $symptom
        );

        $report[] = $this->dietAdvice(
            $symptom
        );

        $report[] = $this->medicineAdvice(
            $symptom
        );

        $report[] = $this->medicineSafetySection(
            $symptom,
            $patient
        );

        $report[] = $this->monitoringAdvice(
            $symptom
        );

        $report[] = $this->hydrationReminder();

        $report[] = $this->restReminder();

        if ($this->symptomsPersisting($patient)) {

            $report[] =
                $this->persistentSymptomsAdvice();

        }

        $report[] = $this->redFlagMessage(
            $symptom
        );

        if ($this->requiresNightMonitoring(
            $severity
        )) {

            $report[] =
                $this->nightMonitoringInstructions();

        }

        if ($this->shouldRecommendAppointment(
            $severity,
            $symptom,
            $patient
        )) {

            $report[] =
                $this->morningAppointmentMessage();

        }

        $report[] = $this->disclaimer();

        $report[] = $this->footer();

        return implode("\n\n", $report);
    }
}
    }
}

