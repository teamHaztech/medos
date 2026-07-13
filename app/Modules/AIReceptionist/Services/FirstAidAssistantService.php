<?php

namespace App\Modules\AIReceptionist\Services;

use Carbon\Carbon;

class FirstAidAssistantService
{
    /**
     * Supported first-aid situations.
     */
    protected array $injuryKeywords = [

        'road accident',
        'bike accident',
        'car accident',
        'vehicle accident',

        'bleeding',
        'heavy bleeding',
        'blood loss',

        'cut',
        'deep cut',
        'wound',
        'open wound',

        'burn',
        'fire burn',
        'hot water burn',
        'chemical burn',
        'electric burn',

        'fracture',
        'broken bone',
        'bone fracture',

        'head injury',
        'head trauma',

        'unconscious',

        'choking',

        'not breathing',

        'snake bite',

        'dog bite',

        'cat bite',

        'poisoning',

        'electric shock',

        'heat stroke',

        'heat exhaustion',

        'fainting',

        'seizure',

        'eye injury',

        'chemical in eye',

        'nose bleed',

        'sprain',

        'dislocation'
    ];

    /**
     * Life-threatening situations.
     */
    protected array $emergencyKeywords = [

        'not breathing',

        'unconscious',

        'cardiac arrest',

        'heart stopped',

        'heavy bleeding',

        'blood everywhere',

        'severe burn',

        'multiple fractures',

        'head injury',

        'spinal injury',

        'stroke',

        'heart attack',

        'seizure',

        'electric shock',

        'drowning'
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether this message is requesting
     * first-aid guidance.
     */
    public function isFirstAidRequest(string $message): bool
    {
        $message = strtolower(trim($message));

        foreach ($this->injuryKeywords as $keyword) {

            if (str_contains($message, $keyword)) {

                return true;

            }

        }

        return false;
    }

    /**
     * Detect emergency situations.
     */
    protected function isEmergency(string $message): bool
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
     * Identify injury type.
     */
    protected function identifyInjury(string $message): ?string
    {
        $message = strtolower($message);

        $injuries = [

            'road_accident' => [
                'road accident',
                'bike accident',
                'car accident',
                'vehicle accident'
            ],

            'bleeding' => [
                'bleeding',
                'heavy bleeding',
                'blood loss'
            ],

            'cut' => [
                'cut',
                'deep cut',
                'wound',
                'open wound'
            ],

            'burn' => [
                'burn',
                'fire burn',
                'hot water burn',
                'chemical burn'
            ],

            'fracture' => [
                'fracture',
                'broken bone'
            ],

            'head_injury' => [
                'head injury',
                'head trauma'
            ],

            'choking' => [
                'choking'
            ],

            'snake_bite' => [
                'snake bite'
            ],

            'dog_bite' => [
                'dog bite',
                'cat bite'
            ],

            'poisoning' => [
                'poisoning'
            ],

            'electric_shock' => [
                'electric shock'
            ],

            'heat_stroke' => [
                'heat stroke',
                'heat exhaustion'
            ],

            'eye_injury' => [
                'eye injury',
                'chemical in eye'
            ],

            'nose_bleed' => [
                'nose bleed'
            ],

            'sprain' => [
                'sprain',
                'dislocation'
            ]
        ];

        foreach ($injuries as $type => $keywords) {

            foreach ($keywords as $keyword) {

                if (str_contains($message, $keyword)) {

                    return $type;

                }

            }

        }

        return null;
    }

    /**
     * Current timestamp.
     */
    protected function generatedAt(): string
    {
        return Carbon::now()->format('d M Y h:i A');
    }
        /**
     * Main assessment method.
     */
    public function assess(string $message, array $patient = []): string
    {
        $message = strtolower(trim($message));

        // Check for life-threatening emergency
        if ($this->isEmergency($message)) {

            return $this->emergencyResponse();

        }

        // Identify injury
        $injury = $this->identifyInjury($message);

        if ($injury === null) {

            return $this->unknownInjuryResponse();

        }

        // Determine severity
        $severity = $this->calculateSeverity(
            $injury,
            $patient
        );

        $response = [];

        $response[] = "🚑 First Aid Assistant";

        $response[] = "";

        $response[] = "Detected Injury: ".str_replace('_', ' ', ucfirst($injury));

        $response[] = "";

        $response[] = $this->severityAdvice($severity);

        $response[] = "";

        $response[] = "Please answer the following questions:";

        foreach ($this->followUpQuestions($injury) as $question) {

            $response[] = "• ".$question;

        }

        $response[] = "";

        $response[] = "While answering, follow the first-aid steps below.";

        return implode("\n", $response);
    }

    /**
     * Calculate injury severity.
     */
    protected function calculateSeverity(
        string $injury,
        array $patient = []
    ): string
    {
        $bleeding = !empty($patient['heavy_bleeding']);
        $unconscious = !empty($patient['unconscious']);
        $breathing = !empty($patient['difficulty_breathing']);
        $pain = (int)($patient['pain_score'] ?? 0);

        if ($unconscious) {
            return 'critical';
        }

        if ($breathing) {
            return 'critical';
        }

        if ($bleeding) {
            return 'critical';
        }

        if ($pain >= 8) {
            return 'severe';
        }

        if ($pain >= 5) {
            return 'moderate';
        }

        return 'minor';
    }

    /**
     * Severity guidance.
     */
    protected function severityAdvice(string $severity): string
    {
        return match ($severity) {

            'minor' =>
                "🟢 Severity: Minor\n\n".
                "The injury appears minor. Follow first-aid instructions carefully and monitor the person.",

            'moderate' =>
                "🟡 Severity: Moderate\n\n".
                "The injury should be assessed by a healthcare professional as soon as possible.",

            'severe' =>
                "🟠 Severity: Severe\n\n".
                "The injury is serious. Arrange immediate transport to the nearest emergency department.",

            'critical' =>
                "🔴 Severity: Critical\n\n".
                "This may be life-threatening. Call emergency medical services immediately and begin first aid if it is safe to do so.",

            default =>
                "Unable to determine severity."
        };
    }

    /**
     * Follow-up questions.
     */
    protected function followUpQuestions(string $injury): array
    {
        return match ($injury) {

            'road_accident' => [

                'Is the person conscious?',

                'Is the person breathing normally?',

                'Is there heavy bleeding?',

                'Is the person trapped inside the vehicle?',

                'Is there neck or back pain?'
            ],

            'bleeding' => [

                'Where is the bleeding?',

                'Is blood flowing continuously?',

                'Can direct pressure stop the bleeding?',

                'Is the person feeling dizzy?'
            ],

            'burn' => [

                'What caused the burn?',

                'Which body part is burned?',

                'Are there blisters?',

                'Approximately how large is the burn?'
            ],

            'fracture' => [

                'Which bone appears injured?',

                'Can the person move the limb?',

                'Is the bone visible?',

                'Is there swelling?'
            ],

            'head_injury' => [

                'Did the person lose consciousness?',

                'Are they vomiting?',

                'Do they have severe headache?',

                'Are they confused?'
            ],

            'choking' => [

                'Can the person speak?',

                'Can they cough?',

                'Can they breathe?'
            ],

            default => [

                'What happened?',

                'When did the injury occur?',

                'Is the person awake?',

                'Is there severe pain?'
            ]
        };
    }

    /**
     * Emergency response.
     */
    protected function emergencyResponse(): string
    {
        return
            "🚨 MEDICAL EMERGENCY\n\n".
            "The injury you described may be life-threatening.\n\n".
            "1. Call your local emergency medical services immediately.\n".
            "2. Ensure the area is safe before approaching the injured person.\n".
            "3. If the person is unconscious and not breathing normally, begin CPR only if you are trained.\n".
            "4. Control severe bleeding with firm direct pressure.\n".
            "5. Do not move someone with a suspected neck or spinal injury unless they are in immediate danger.\n\n".
            "Stay with the injured person until professional help arrives.";
    }

    /**
     * Unknown injury response.
     */
    protected function unknownInjuryResponse(): string
    {
        return
            "I couldn't identify the type of injury.\n\n".
            "Please describe:\n\n".
            "• What happened?\n".
            "• Is the person conscious?\n".
            "• Are they breathing normally?\n".
            "• Is there bleeding?\n".
            "• Where is the injury?\n\n".
            "Example:\n".
            "\"The patient fell from a bike and has a bleeding wound on the left leg.\"";
    }
        /**
     * Return first-aid instructions based on injury type.
     */
    protected function firstAidInstructions(string $injury): string
    {
        return match ($injury) {

            'road_accident' => $this->roadAccidentInstructions(),

            'bleeding' => $this->bleedingInstructions(),

            'cut' => $this->cutInstructions(),

            'burn' => $this->burnInstructions(),

            'fracture' => $this->fractureInstructions(),

            'head_injury' => $this->headInjuryInstructions(),

            default =>
                "Keep the injured person comfortable and seek medical attention if symptoms worsen."
        };
    }

    /**
     * Road Accident First Aid
     */
    protected function roadAccidentInstructions(): string
    {
        return
            "🚗 ROAD ACCIDENT FIRST AID\n\n".

            "1. Ensure the accident scene is safe before approaching.\n\n".

            "2. Call emergency medical services immediately.\n\n".

            "3. Check whether the injured person is conscious.\n\n".

            "4. Check if they are breathing normally.\n\n".

            "5. Control severe bleeding by applying firm pressure with a clean cloth or bandage.\n\n".

            "6. Do NOT remove a helmet unless the airway is blocked or the person is not breathing and you are trained to do so.\n\n".

            "7. Do NOT move the injured person if a neck or spinal injury is suspected unless they are in immediate danger.\n\n".

            "8. Keep the person warm using a blanket or jacket.\n\n".

            "9. Stay with the injured person until professional help arrives.";
    }

    /**
     * Heavy Bleeding
     */
    protected function bleedingInstructions(): string
    {
        return
            "🩸 HEAVY BLEEDING FIRST AID\n\n".

            "1. Wear gloves if available.\n\n".

            "2. Apply firm, direct pressure over the wound using a clean cloth, gauze, or bandage.\n\n".

            "3. Maintain continuous pressure.\n\n".

            "4. If blood soaks through the dressing, place another dressing on top without removing the first.\n\n".

            "5. Raise the injured limb only if there is no suspected fracture and it does not increase pain.\n\n".

            "6. Keep the injured person lying down if they feel faint.\n\n".

            "7. Seek emergency medical care immediately if bleeding is severe or cannot be controlled.";
    }

    /**
     * Cuts and Wounds
     */
    protected function cutInstructions(): string
    {
        return
            "✂ CUTS AND WOUNDS\n\n".

            "1. Wash your hands before touching the wound if possible.\n\n".

            "2. Rinse the wound gently with clean running water.\n\n".

            "3. Remove visible dirt carefully if it can be done safely.\n\n".

            "4. Apply gentle pressure if bleeding continues.\n\n".

            "5. Cover the wound with a sterile dressing or clean bandage.\n\n".

            "6. Change the dressing daily or when it becomes wet or dirty.\n\n".

            "7. Seek medical care if the wound is deep, heavily contaminated, caused by an animal or human bite, or if bleeding cannot be controlled.";
    }

    /**
     * Burns
     */
    protected function burnInstructions(): string
    {
        return
            "🔥 BURN FIRST AID\n\n".

            "1. Cool the burn under cool running water for about 20 minutes.\n\n".

            "2. Remove rings, watches, or tight clothing before swelling develops if they are not stuck to the skin.\n\n".

            "3. Cover the burn with a clean, non-stick dressing or clean cloth.\n\n".

            "4. Do NOT burst blisters.\n\n".

            "5. Do NOT apply toothpaste, butter, oil, powders, or ice directly to the burn.\n\n".

            "6. Seek urgent medical care for deep burns, electrical burns, chemical burns, burns larger than the person's hand, or burns involving the face, hands, feet, genitals, or major joints.";
    }

    /**
     * Fractures
     */
    protected function fractureInstructions(): string
    {
        return
            "🦴 FRACTURE FIRST AID\n\n".

            "1. Keep the injured area completely still.\n\n".

            "2. Do NOT try to straighten the bone.\n\n".

            "3. Immobilize the injured limb using a splint if trained and materials are available.\n\n".

            "4. Apply a cold pack wrapped in cloth for up to 20 minutes to reduce swelling.\n\n".

            "5. Elevate the injured limb if it does not increase pain and no spinal injury is suspected.\n\n".

            "6. Seek medical attention immediately.\n\n".

            "7. If bone is visible through the skin, cover it with a sterile dressing and call emergency services.";
    }

    /**
     * Head Injury
     */
    protected function headInjuryInstructions(): string
    {
        return
            "🤕 HEAD INJURY FIRST AID\n\n".

            "1. Keep the person still and calm.\n\n".

            "2. Do NOT shake the person.\n\n".

            "3. Monitor breathing and level of consciousness.\n\n".

            "4. Apply a cold pack wrapped in cloth to minor bumps.\n\n".

            "5. Watch for repeated vomiting, confusion, severe headache, seizures, increasing drowsiness, weakness, or unequal pupils.\n\n".

            "6. Do NOT give food, alcohol, or unnecessary medicines unless advised by a healthcare professional.\n\n".

            "7. Seek emergency medical care immediately if any warning signs develop or if the injury was severe.";
    }
        /**
     * Choking First Aid
     */
    protected function chokingInstructions(): string
    {
        return
            "🫁 CHOKING FIRST AID\n\n".

            "1. Ask the person if they are choking.\n\n".

            "2. If they can cough or speak, encourage them to continue coughing.\n\n".

            "3. If they cannot breathe, cough, or speak, call emergency medical services immediately.\n\n".

            "4. If you are trained, provide back blows and abdominal thrusts according to current first-aid guidelines until the object is expelled or help arrives.\n\n".

            "5. If the person becomes unconscious, begin CPR only if you are trained while another person calls emergency services.";
    }

    /**
     * CPR Guidance
     */
    protected function cprInstructions(): string
    {
        return
            "❤️ CPR GUIDANCE\n\n".

            "1. Check if the person is responsive.\n\n".

            "2. Check for normal breathing.\n\n".

            "3. Call emergency medical services immediately.\n\n".

            "4. If you are trained and the person is not breathing normally, begin CPR.\n\n".

            "5. Continue until the person starts breathing normally or trained medical personnel take over.\n\n".

            "If an Automated External Defibrillator (AED) is available, use it by following the device's voice prompts.";
    }

    /**
     * Electric Shock
     */
    protected function electricShockInstructions(): string
    {
        return
            "⚡ ELECTRIC SHOCK FIRST AID\n\n".

            "1. Do NOT touch the injured person until the electrical source has been switched off or it is safe to do so.\n\n".

            "2. Turn off the power source if possible.\n\n".

            "3. Call emergency medical services immediately.\n\n".

            "4. Check whether the person is conscious and breathing.\n\n".

            "5. Cover any burns with a clean, dry dressing.\n\n".

            "6. Even if the person appears well, they should be evaluated by a healthcare professional because internal injuries or heart rhythm problems can occur.";
    }

    /**
     * Chemical Burn
     */
    protected function chemicalBurnInstructions(): string
    {
        return
            "🧪 CHEMICAL BURN FIRST AID\n\n".

            "1. Avoid contact with the chemical yourself.\n\n".

            "2. Remove contaminated clothing carefully.\n\n".

            "3. Rinse the affected skin with plenty of clean running water for at least 20 minutes unless the product instructions say otherwise.\n\n".

            "4. Do not apply creams, ointments, or home remedies.\n\n".

            "5. Seek urgent medical care immediately.";
    }

    /**
     * Heat Stroke / Heat Exhaustion
     */
    protected function heatStrokeInstructions(): string
    {
        return
            "🌞 HEAT STROKE / HEAT EXHAUSTION\n\n".

            "1. Move the person to a cool, shaded place.\n\n".

            "2. Remove excess clothing.\n\n".

            "3. Cool the body with cool water, wet towels, or fans.\n\n".

            "4. If the person is awake and able to swallow safely, offer cool water in small sips.\n\n".

            "5. If the person becomes confused, loses consciousness, or stops sweating despite a high body temperature, treat it as a medical emergency and call emergency services immediately.";
    }

    /**
     * Fainting
     */
    protected function faintingInstructions(): string
    {
        return
            "😵 FAINTING FIRST AID\n\n".

            "1. Lay the person flat on their back if it is safe to do so.\n\n".

            "2. Raise the legs slightly if there is no suspected injury.\n\n".

            "3. Loosen tight clothing.\n\n".

            "4. Check breathing.\n\n".

            "5. If the person does not regain consciousness within a minute or two, or if they have difficulty breathing, call emergency medical services immediately.";
    }

    /**
     * Seizure
     */
    protected function seizureInstructions(): string
    {
        return
            "⚠ SEIZURE FIRST AID\n\n".

            "1. Stay calm and protect the person from nearby hazards.\n\n".

            "2. Cushion their head if possible.\n\n".

            "3. Do NOT restrain their movements.\n\n".

            "4. Do NOT place anything in their mouth.\n\n".

            "5. After the seizure stops, if they are breathing normally and there is no suspected spinal injury, place them in the recovery position.\n\n".

            "6. Call emergency medical services if the seizure lasts longer than five minutes, repeats without recovery, occurs in water, or the person is injured, pregnant, or has difficulty breathing afterwards.";
    }

    /**
     * Return first-aid instructions for advanced emergencies.
     */
    protected function advancedFirstAidInstructions(string $injury): string
    {
        return match ($injury) {

            'choking' =>
                $this->chokingInstructions(),

            'electric_shock' =>
                $this->electricShockInstructions(),

            'heat_stroke' =>
                $this->heatStrokeInstructions(),

            default =>
                "Continue monitoring the patient until professional medical help arrives.";

        };
    }
        /**
     * Snake Bite First Aid
     */
    protected function snakeBiteInstructions(): string
    {
        return
            "🐍 SNAKE BITE FIRST AID\n\n".

            "1. Move the person away from the snake if it is safe to do so.\n\n".

            "2. Keep the person calm and as still as possible.\n\n".

            "3. Keep the bitten limb below heart level if practical.\n\n".

            "4. Remove rings, bracelets, or tight clothing before swelling develops.\n\n".

            "5. Cover the bite with a clean dressing.\n\n".

            "6. Do NOT cut the wound.\n\n".

            "7. Do NOT suck the venom.\n\n".

            "8. Do NOT apply ice or chemicals.\n\n".

            "9. Seek emergency medical care immediately.";
    }

    /**
     * Dog/Cat Bite First Aid
     */
    protected function dogBiteInstructions(): string
    {
        return
            "🐶 DOG OR CAT BITE FIRST AID\n\n".

            "1. Wash the wound gently with soap and clean running water for several minutes.\n\n".

            "2. Apply gentle pressure if bleeding occurs.\n\n".

            "3. Cover the wound with a sterile dressing.\n\n".

            "4. Seek medical care as soon as possible because antibiotics or rabies assessment may be required.\n\n".

            "5. If the animal was unknown or behaving unusually, inform the healthcare provider.";
    }

    /**
     * Poisoning First Aid
     */
    protected function poisoningInstructions(): string
    {
        return
            "☠ POISONING FIRST AID\n\n".

            "1. Remove the person from the source of poisoning if it is safe.\n\n".

            "2. If the poison is on the skin, remove contaminated clothing and rinse the skin with plenty of water.\n\n".

            "3. If the poison entered the eyes, flush the eyes with clean running water for at least 15–20 minutes.\n\n".

            "4. Do NOT make the person vomit unless specifically instructed by a poison center or healthcare professional.\n\n".

            "5. Call emergency medical services or your local poison center immediately.\n\n".

            "6. If possible, keep the medicine container or chemical label available for responders.";
    }

    /**
     * Eye Injury First Aid
     */
    protected function eyeInjuryInstructions(): string
    {
        return
            "👁 EYE INJURY FIRST AID\n\n".

            "1. Do NOT rub the eye.\n\n".

            "2. If dust or a small particle is present, rinse gently with clean water.\n\n".

            "3. If a chemical entered the eye, flush continuously with clean running water for at least 20 minutes.\n\n".

            "4. If an object is embedded in the eye, do NOT remove it.\n\n".

            "5. Cover the eye lightly with a clean dressing without applying pressure.\n\n".

            "6. Seek immediate medical care.";
    }

    /**
     * Nosebleed First Aid
     */
    protected function noseBleedInstructions(): string
    {
        return
            "👃 NOSEBLEED FIRST AID\n\n".

            "1. Sit upright and lean slightly forward.\n\n".

            "2. Pinch the soft part of the nose continuously for 10–15 minutes.\n\n".

            "3. Breathe through the mouth.\n\n".

            "4. Avoid blowing the nose after the bleeding stops.\n\n".

            "5. Seek medical care if bleeding lasts longer than 20 minutes, follows a major injury, or is very heavy.";
    }

    /**
     * Sprain / Dislocation First Aid
     */
    protected function sprainInstructions(): string
    {
        return
            "🦵 SPRAIN / DISLOCATION FIRST AID\n\n".

            "1. Rest the injured joint.\n\n".

            "2. Apply a cold pack wrapped in cloth for up to 20 minutes.\n\n".

            "3. Use a compression bandage if appropriate and not too tight.\n\n".

            "4. Elevate the injured limb if possible.\n\n".

            "5. Do NOT try to force a dislocated joint back into place.\n\n".

            "6. Seek medical evaluation as soon as possible.";
    }

    /**
     * Warning signs requiring immediate emergency care.
     */
    protected function emergencyWarningSigns(): array
    {
        return [

            "Difficulty breathing",

            "Loss of consciousness",

            "Heavy uncontrolled bleeding",

            "Severe chest pain",

            "Repeated vomiting",

            "Seizures",

            "Blue lips or face",

            "Severe allergic reaction",

            "Major burns",

            "Suspected spinal injury"
        ];
    }

    /**
     * Format emergency warning section.
     */
    protected function emergencyWarningMessage(): string
    {
        $message = "🚨 SEEK IMMEDIATE EMERGENCY CARE IF:\n\n";

        foreach ($this->emergencyWarningSigns() as $warning) {

            $message .= "• {$warning}\n";

        }

        return trim($message);
    }

    /**
     * Determine whether hospital treatment is recommended.
     */
    protected function hospitalRecommendation(
        string $severity
    ): string {

        return match ($severity) {

            'critical' =>
                "🏥 Call emergency medical services immediately and transport the patient to the nearest emergency department.",

            'severe' =>
                "🏥 The patient should be taken to the nearest hospital immediately.",

            'moderate' =>
                "🏥 Arrange medical evaluation as soon as possible, preferably today.",

            default =>
                "🏥 Continue first aid and monitor the patient. Seek medical care if symptoms worsen."
        };
    }
        /**
     * Build the complete first-aid report.
     */
    public function generateReport(
        string $message,
        array $patient = []
    ): string {

        if ($this->isEmergency($message)) {

            return $this->emergencyResponse();

        }

        $injury = $this->identifyInjury($message);

        if (!$injury) {

            return $this->unknownInjuryResponse();

        }

        $severity = $this->calculateSeverity(
            $injury,
            $patient
        );

        $report = [];

        $report[] = "🚑 AI First Aid Assistant";

        $report[] = "";

        $report[] = "Generated: ".$this->generatedAt();

        $report[] = "";

        $report[] = $this->patientSummary(
            $injury,
            $severity,
            $patient
        );

        $report[] = "";

        $report[] = $this->severityAdvice(
            $severity
        );

        $report[] = "";

        $report[] = $this->getInstructions(
            $injury
        );

        $report[] = "";

        $report[] = $this->emergencyWarningMessage();

        $report[] = "";

        $report[] = $this->hospitalRecommendation(
            $severity
        );

        $report[] = "";

        $report[] = $this->disclaimer();

        $report[] = "";

        $report[] = $this->footer();

        return implode("\n", $report);
    }

    /**
     * Patient summary.
     */
    protected function patientSummary(
        string $injury,
        string $severity,
        array $patient
    ): string {

        $summary = [];

        $summary[] = "📋 Patient Summary";

        $summary[] = "Detected Injury : ".str_replace('_', ' ', ucfirst($injury));

        $summary[] = "Severity : ".ucfirst($severity);

        if (isset($patient['age'])) {

            $summary[] = "Age : ".$patient['age'];

        }

        if (!empty($patient['gender'])) {

            $summary[] = "Gender : ".$patient['gender'];

        }

        if (!empty($patient['conscious'])) {

            $summary[] = "Conscious : Yes";

        } else {

            $summary[] = "Conscious : No / Unknown";

        }

        return implode("\n", $summary);
    }

    /**
     * Return the appropriate first-aid instructions.
     */
    protected function getInstructions(
        string $injury
    ): string {

        return match ($injury) {

            'road_accident' => $this->roadAccidentInstructions(),

            'bleeding' => $this->bleedingInstructions(),

            'cut' => $this->cutInstructions(),

            'burn' => $this->burnInstructions(),

            'fracture' => $this->fractureInstructions(),

            'head_injury' => $this->headInjuryInstructions(),

            'choking' => $this->chokingInstructions(),

            'snake_bite' => $this->snakeBiteInstructions(),

            'dog_bite' => $this->dogBiteInstructions(),

            'poisoning' => $this->poisoningInstructions(),

            'electric_shock' => $this->electricShockInstructions(),

            'heat_stroke' => $this->heatStrokeInstructions(),

            'eye_injury' => $this->eyeInjuryInstructions(),

            'nose_bleed' => $this->noseBleedInstructions(),

            'sprain' => $this->sprainInstructions(),

            default =>
                "Keep the patient safe, monitor their condition, and seek medical attention if symptoms worsen."
        };
    }

    /**
     * Medical disclaimer.
     */
    protected function disclaimer(): string
    {
        return
            "⚠ DISCLAIMER\n\n".
            "This chatbot provides first-aid guidance only.\n".
            "It does not replace trained medical professionals.\n".
            "Always contact emergency medical services immediately for serious injuries or life-threatening conditions.";
    }

    /**
     * Footer.
     */
    protected function footer(): string
    {
        return
            "-----------------------------------------\n".
            "AI Hospital First Aid Assistant\n".
            "Generated at ".$this->generatedAt()."\n".
            "Version 1.0";
    }
}