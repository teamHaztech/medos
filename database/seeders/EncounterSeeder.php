<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EncounterSeeder extends Seeder
{
    /**
     * Seed the encounters table with 20 realistic encounters for City Care Hospital.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('encounters')->truncate();
        Schema::enableForeignKeyConstraints();

        $now   = Carbon::now();
        $today = $now->copy()->startOfDay();

        // Map specialty -> staff ID for deterministic doctor assignment
        $doctorMap = [
            'General Medicine' => SeedData::STAFF_RAJESH,
            'Pediatrics'       => SeedData::STAFF_PRIYA,
            'Cardiology'       => SeedData::STAFF_AMIT,
            'Gynecology'       => SeedData::STAFF_NEHA,
            'Orthopedics'      => SeedData::STAFF_SURESH,
            'Dermatology'      => SeedData::STAFF_ANJALI,
            'ENT'              => SeedData::STAFF_VIKRAM,
            'Dental'           => SeedData::STAFF_ARJUN,
            'General Medicine2'=> SeedData::STAFF_MEERA,
        ];

        // Patient IDs to cycle through
        $patientIds = [
            SeedData::PATIENT_01,
            SeedData::PATIENT_02,
            SeedData::PATIENT_03,
            SeedData::PATIENT_04,
            SeedData::PATIENT_05,
            SeedData::PATIENT_06,
            SeedData::PATIENT_07,
            SeedData::PATIENT_08,
            SeedData::PATIENT_09,
            SeedData::PATIENT_10,
        ];

        // ------------------------------------------------------------------
        // 20 encounters: 10 completed (past), 5 booked (today), 5 in_progress (today)
        // ------------------------------------------------------------------
        $encounters = [
            // --- COMPLETED (past dates) --- 10 encounters
            [
                'specialty'             => 'General Medicine',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'whatsapp',
                'triage_classification' => 'routine',
                'triage_score'          => 3,
                'days_ago'              => 7,
                'patient_idx'           => 0,
                'intake_data'           => [
                    'chief_complaint'     => 'fever and body ache',
                    'duration_days'       => 3,
                    'severity_indicators' => ['temperature 101F'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Patient reports fever of 3 days duration with generalized body ache. No cough or cold symptoms.',
                    'objective'  => 'Temp: 101.2F, BP: 120/80, HR: 88. Throat mildly congested.',
                    'assessment' => 'Viral fever with myalgia',
                    'plan'       => 'Paracetamol 650mg TDS x 3 days. ORS. Rest. Follow up if fever persists.',
                ],
                'diagnosis_codes'  => ['R50.9', 'M79.10'],
                'triage_reasoning' => ['score' => 3, 'reasoning' => 'Low-grade fever, no red flags, routine appointment suitable'],
            ],
            [
                'specialty'             => 'Pediatrics',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'whatsapp',
                'triage_classification' => 'routine',
                'triage_score'          => 2,
                'days_ago'              => 5,
                'patient_idx'           => 1,
                'intake_data'           => [
                    'chief_complaint'     => 'cough and runny nose in child',
                    'duration_days'       => 2,
                    'severity_indicators' => ['mild cough', 'clear nasal discharge'],
                    'who'                 => 'child',
                    'child_age'           => '5 years',
                ],
                'soap_notes' => [
                    'subjective' => 'Mother reports child has cough and runny nose for 2 days. No fever. Eating well.',
                    'objective'  => 'Temp: 98.6F, Clear nasal discharge. Lungs clear. No wheeze.',
                    'assessment' => 'Acute upper respiratory infection',
                    'plan'       => 'Cetirizine syrup 2.5ml OD x 5 days. Saline nasal drops. Steam inhalation.',
                ],
                'diagnosis_codes'  => ['J06.9'],
                'triage_reasoning' => ['score' => 2, 'reasoning' => 'Common cold symptoms in child, no distress signs'],
            ],
            [
                'specialty'             => 'Cardiology',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'walk_in',
                'triage_classification' => 'urgent',
                'triage_score'          => 7,
                'days_ago'              => 3,
                'patient_idx'           => 2,
                'intake_data'           => [
                    'chief_complaint'     => 'chest pain and breathlessness',
                    'duration_days'       => 1,
                    'severity_indicators' => ['chest tightness', 'breathlessness on exertion'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Patient complains of chest tightness since yesterday, worse on exertion. History of hypertension.',
                    'objective'  => 'BP: 150/95, HR: 96, SpO2: 97%. ECG: Sinus tachycardia, no ST changes.',
                    'assessment' => 'Hypertensive urgency with chest pain. Need to rule out ACS.',
                    'plan'       => 'ECG, Troponin, CBC ordered. Amlodipine 5mg increased to 10mg. Review in 48 hours.',
                ],
                'diagnosis_codes'  => ['R07.9', 'I10'],
                'triage_reasoning' => ['score' => 7, 'reasoning' => 'Chest pain with hypertension history, needs urgent evaluation'],
            ],
            [
                'specialty'             => 'Orthopedics',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'walk_in',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 5,
                'days_ago'              => 6,
                'patient_idx'           => 3,
                'intake_data'           => [
                    'chief_complaint'     => 'knee pain after fall',
                    'duration_days'       => 1,
                    'severity_indicators' => ['swelling', 'difficulty walking'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Patient fell while walking, landed on right knee. Swelling and pain since.',
                    'objective'  => 'Right knee swollen, tender. ROM limited. No deformity. X-ray: No fracture.',
                    'assessment' => 'Right knee contusion with soft tissue injury',
                    'plan'       => 'Ice packs, crepe bandage, Diclofenac gel. Rest 1 week. Review if not improving.',
                ],
                'diagnosis_codes'  => ['S80.01', 'M79.661'],
                'triage_reasoning' => ['score' => 5, 'reasoning' => 'Post-traumatic knee pain, needs X-ray to rule out fracture'],
            ],
            [
                'specialty'             => 'Gynecology',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'web',
                'triage_classification' => 'routine',
                'triage_score'          => 2,
                'days_ago'              => 10,
                'patient_idx'           => 4,
                'intake_data'           => [
                    'chief_complaint'     => 'irregular periods and lower abdominal pain',
                    'duration_days'       => 30,
                    'severity_indicators' => ['irregular cycles', 'mild pain'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Patient reports irregular menstrual cycles for 3 months. Mild lower abdominal discomfort.',
                    'objective'  => 'Abdomen soft, mild tenderness in lower quadrants. USG pelvis ordered.',
                    'assessment' => 'Irregular menstruation, PCOD suspected',
                    'plan'       => 'USG pelvis, hormonal profile. OCP for cycle regulation. Follow up with reports.',
                ],
                'diagnosis_codes'  => ['N92.1', 'E28.2'],
                'triage_reasoning' => ['score' => 2, 'reasoning' => 'Chronic menstrual irregularity, non-acute presentation'],
            ],
            [
                'specialty'             => 'Dermatology',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'whatsapp',
                'triage_classification' => 'routine',
                'triage_score'          => 1,
                'days_ago'              => 4,
                'patient_idx'           => 5,
                'intake_data'           => [
                    'chief_complaint'     => 'itchy rash on arms and back',
                    'duration_days'       => 5,
                    'severity_indicators' => ['itching', 'red patches'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Itchy red rash on both arms and upper back for 5 days. No new soaps or detergents.',
                    'objective'  => 'Erythematous papular rash on bilateral upper arms and upper back. No vesicles.',
                    'assessment' => 'Contact dermatitis',
                    'plan'       => 'Hydroxyzine 25mg HS. Calamine lotion. Avoid hot water. Review in 1 week.',
                ],
                'diagnosis_codes'  => ['L25.9'],
                'triage_reasoning' => ['score' => 1, 'reasoning' => 'Skin rash without systemic symptoms, routine visit'],
            ],
            [
                'specialty'             => 'ENT',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'kiosk',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 4,
                'days_ago'              => 2,
                'patient_idx'           => 6,
                'intake_data'           => [
                    'chief_complaint'     => 'right ear pain and reduced hearing',
                    'duration_days'       => 2,
                    'severity_indicators' => ['pain severity 6/10', 'hearing loss'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Right ear pain for 2 days with reduced hearing. No discharge. Had cold last week.',
                    'objective'  => 'Right TM bulging, erythematous. Left ear normal. No mastoid tenderness.',
                    'assessment' => 'Acute otitis media, right ear',
                    'plan'       => 'Amoxicillin 500mg TDS x 7 days. Xylometazoline nasal drops. Paracetamol SOS. Review in 5 days.',
                ],
                'diagnosis_codes'  => ['H66.91'],
                'triage_reasoning' => ['score' => 4, 'reasoning' => 'Acute ear infection with hearing loss, needs prompt treatment'],
            ],
            [
                'specialty'             => 'Dental',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'walk_in',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 4,
                'days_ago'              => 8,
                'patient_idx'           => 7,
                'intake_data'           => [
                    'chief_complaint'     => 'toothache and gum swelling',
                    'duration_days'       => 3,
                    'severity_indicators' => ['throbbing pain', 'swollen gums', 'difficulty eating'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Severe toothache in lower right molar for 3 days. Gum swelling. Unable to eat on that side.',
                    'objective'  => 'Lower right 2nd molar: deep caries, periapical tenderness. Gingival swelling.',
                    'assessment' => 'Dental abscess, lower right second molar',
                    'plan'       => 'Amoxicillin 500mg TDS x 5 days. Metronidazole 400mg TDS x 5 days. Ibuprofen 400mg SOS. Root canal planned.',
                ],
                'diagnosis_codes'  => ['K04.7', 'K05.10'],
                'triage_reasoning' => ['score' => 4, 'reasoning' => 'Dental abscess needs antibiotics and definitive treatment'],
            ],
            [
                'specialty'             => 'General Medicine2',
                'status'                => 'completed',
                'type'                  => 'follow_up',
                'channel'               => 'whatsapp',
                'triage_classification' => 'routine',
                'triage_score'          => 1,
                'days_ago'              => 14,
                'patient_idx'           => 8,
                'intake_data'           => [
                    'chief_complaint'     => 'diabetes follow-up',
                    'duration_days'       => null,
                    'severity_indicators' => [],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Follow-up visit for diabetes management. Patient compliant with medications. No hypoglycemic episodes.',
                    'objective'  => 'FBS: 134 mg/dL, HbA1c: 7.2%. BP: 130/82. Weight: 78kg.',
                    'assessment' => 'Type 2 DM, moderately controlled',
                    'plan'       => 'Continue Metformin 500mg BD. Add Glimepiride 1mg OD. Diet counseling. Repeat HbA1c in 3 months.',
                ],
                'diagnosis_codes'  => ['E11.65'],
                'triage_reasoning' => ['score' => 1, 'reasoning' => 'Routine follow-up, no acute concerns'],
            ],
            [
                'specialty'             => 'Orthopedics',
                'status'                => 'completed',
                'type'                  => 'consultation',
                'channel'               => 'web',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 5,
                'days_ago'              => 12,
                'patient_idx'           => 9,
                'intake_data'           => [
                    'chief_complaint'     => 'lower back pain radiating to left leg',
                    'duration_days'       => 7,
                    'severity_indicators' => ['pain severity 7/10', 'numbness in foot'],
                    'who'                 => 'self',
                ],
                'soap_notes' => [
                    'subjective' => 'Lower back pain for 1 week, radiating to left leg. Numbness in left foot. History of desk job.',
                    'objective'  => 'SLR positive left. L4-L5 tenderness. Reduced ankle reflex left. MRI ordered.',
                    'assessment' => 'Lumbar radiculopathy, likely disc herniation L4-L5',
                    'plan'       => 'MRI lumbar spine. Pregabalin 75mg BD. Physiotherapy referral. Avoid heavy lifting.',
                ],
                'diagnosis_codes'  => ['M54.16', 'M51.16'],
                'triage_reasoning' => ['score' => 5, 'reasoning' => 'Radiculopathy with neurological signs, needs imaging'],
            ],

            // --- BOOKED (today) --- 5 encounters
            [
                'specialty'             => 'General Medicine',
                'status'                => 'booked',
                'type'                  => 'consultation',
                'channel'               => 'whatsapp',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 4,
                'days_ago'              => 0,
                'patient_idx'           => 0,
                'intake_data'           => [
                    'chief_complaint'     => 'persistent dry cough',
                    'duration_days'       => 14,
                    'severity_indicators' => ['no fever', 'cough worse at night'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 4, 'reasoning' => 'Cough > 2 weeks needs evaluation to rule out TB or other causes'],
            ],
            [
                'specialty'             => 'Pediatrics',
                'status'                => 'booked',
                'type'                  => 'consultation',
                'channel'               => 'whatsapp',
                'triage_classification' => 'urgent',
                'triage_score'          => 7,
                'days_ago'              => 0,
                'patient_idx'           => 1,
                'intake_data'           => [
                    'chief_complaint'     => 'high fever in child',
                    'duration_days'       => 0,
                    'severity_indicators' => ['temperature 103F', 'irritable', 'not eating'],
                    'who'                 => 'child',
                    'child_age'           => '3 years',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 7, 'reasoning' => 'High fever in young child, irritable, needs urgent evaluation'],
            ],
            [
                'specialty'             => 'Cardiology',
                'status'                => 'booked',
                'type'                  => 'consultation',
                'channel'               => 'web',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 5,
                'days_ago'              => 0,
                'patient_idx'           => 4,
                'intake_data'           => [
                    'chief_complaint'     => 'palpitations and racing heart',
                    'duration_days'       => 3,
                    'severity_indicators' => ['palpitations', 'anxiety'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 5, 'reasoning' => 'Palpitations need cardiac evaluation, ECG recommended'],
            ],
            [
                'specialty'             => 'Gynecology',
                'status'                => 'booked',
                'type'                  => 'follow_up',
                'channel'               => 'whatsapp',
                'triage_classification' => 'routine',
                'triage_score'          => 2,
                'days_ago'              => 0,
                'patient_idx'           => 5,
                'intake_data'           => [
                    'chief_complaint'     => 'routine pregnancy check-up',
                    'duration_days'       => null,
                    'severity_indicators' => [],
                    'who'                 => 'self',
                    'pregnancy_weeks'     => 24,
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 2, 'reasoning' => 'Routine antenatal visit, no complications reported'],
            ],
            [
                'specialty'             => 'Dermatology',
                'status'                => 'booked',
                'type'                  => 'consultation',
                'channel'               => 'whatsapp',
                'triage_classification' => 'routine',
                'triage_score'          => 1,
                'days_ago'              => 0,
                'patient_idx'           => 7,
                'intake_data'           => [
                    'chief_complaint'     => 'excessive hair loss and dandruff',
                    'duration_days'       => 60,
                    'severity_indicators' => ['hair thinning', 'itchy scalp'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 1, 'reasoning' => 'Chronic hair concern, non-urgent, routine visit'],
            ],

            // --- IN PROGRESS (today) --- 5 encounters
            [
                'specialty'             => 'General Medicine2',
                'status'                => 'in_progress',
                'type'                  => 'consultation',
                'channel'               => 'walk_in',
                'triage_classification' => 'urgent',
                'triage_score'          => 7,
                'days_ago'              => 0,
                'patient_idx'           => 2,
                'intake_data'           => [
                    'chief_complaint'     => 'severe abdominal pain and vomiting',
                    'duration_days'       => 1,
                    'severity_indicators' => ['pain severity 8/10', 'multiple vomiting episodes', 'unable to eat'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 7, 'reasoning' => 'Severe abdominal pain with vomiting, needs urgent evaluation'],
            ],
            [
                'specialty'             => 'Pediatrics',
                'status'                => 'in_progress',
                'type'                  => 'consultation',
                'channel'               => 'walk_in',
                'triage_classification' => 'urgent',
                'triage_score'          => 8,
                'days_ago'              => 0,
                'patient_idx'           => 3,
                'intake_data'           => [
                    'chief_complaint'     => 'poor feeding and lethargy in infant',
                    'duration_days'       => 1,
                    'severity_indicators' => ['reduced feeding', 'sleepy', 'less active'],
                    'who'                 => 'child',
                    'child_age'           => '6 months',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 8, 'reasoning' => 'Infant with poor feeding and lethargy is a red flag, urgent assessment'],
            ],
            [
                'specialty'             => 'Orthopedics',
                'status'                => 'in_progress',
                'type'                  => 'consultation',
                'channel'               => 'walk_in',
                'triage_classification' => 'semi_urgent',
                'triage_score'          => 6,
                'days_ago'              => 0,
                'patient_idx'           => 6,
                'intake_data'           => [
                    'chief_complaint'     => 'wrist pain and swelling after fall',
                    'duration_days'       => 0,
                    'severity_indicators' => ['swelling', 'deformity suspected', 'unable to move wrist'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 6, 'reasoning' => 'Possible fracture, needs X-ray urgently'],
            ],
            [
                'specialty'             => 'ENT',
                'status'                => 'in_progress',
                'type'                  => 'emergency',
                'channel'               => 'walk_in',
                'triage_classification' => 'urgent',
                'triage_score'          => 7,
                'days_ago'              => 0,
                'patient_idx'           => 8,
                'intake_data'           => [
                    'chief_complaint'     => 'persistent nosebleed',
                    'duration_days'       => 0,
                    'severity_indicators' => ['bleeding for 30 minutes', 'not stopping with pressure'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 7, 'reasoning' => 'Uncontrolled epistaxis, needs ENT intervention urgently'],
            ],
            [
                'specialty'             => 'Dental',
                'status'                => 'in_progress',
                'type'                  => 'emergency',
                'channel'               => 'walk_in',
                'triage_classification' => 'urgent',
                'triage_score'          => 7,
                'days_ago'              => 0,
                'patient_idx'           => 9,
                'intake_data'           => [
                    'chief_complaint'     => 'severe toothache with facial swelling',
                    'duration_days'       => 2,
                    'severity_indicators' => ['facial swelling', 'fever', 'unable to open mouth fully'],
                    'who'                 => 'self',
                ],
                'soap_notes'       => null,
                'diagnosis_codes'  => null,
                'triage_reasoning' => ['score' => 7, 'reasoning' => 'Dental abscess with facial cellulitis, needs urgent drainage and antibiotics'],
            ],
        ];

        $encounterCounter = 0;

        foreach ($encounters as $enc) {
            $encounterCounter++;

            // Resolve doctor ID from specialty
            $doctorId = $doctorMap[$enc['specialty']] ?? SeedData::STAFF_RAJESH;

            $patientId = $patientIds[$enc['patient_idx']];

            $daysAgo   = $enc['days_ago'];
            $createdAt = $daysAgo > 0
                ? $today->copy()->subDays($daysAgo)->addHours(9 + ($encounterCounter % 8))->addMinutes($encounterCounter * 7 % 60)
                : $today->copy()->addHours(8 + ($encounterCounter % 6))->addMinutes($encounterCounter * 11 % 60);

            $encounterNumber = 'ENC-' . $today->format('Ymd') . '-' . str_pad($encounterCounter, 3, '0', STR_PAD_LEFT);

            DB::table('encounters')->insert([
                'id'                     => Str::uuid()->toString(),
                'hospital_id'            => SeedData::CITY_CARE_ID,
                'patient_id'             => $patientId,
                'doctor_id'              => $doctorId,
                'encounter_number'       => $encounterNumber,
                'type'                   => $enc['type'],
                'status'                 => $enc['status'],
                'channel'                => $enc['channel'],
                'intake_data'            => json_encode($enc['intake_data']),
                'triage_score'           => $enc['triage_score'],
                'triage_classification'  => $enc['triage_classification'],
                'triage_reasoning'       => $enc['triage_reasoning'] ? json_encode($enc['triage_reasoning']) : null,
                'soap_notes'             => $enc['soap_notes'] ? encrypt(json_encode($enc['soap_notes'])) : null,
                'diagnosis_codes'        => $enc['diagnosis_codes'] ? json_encode($enc['diagnosis_codes']) : null,
                'referral_to'            => null,
                'follow_up_date'         => $enc['status'] === 'completed' ? $createdAt->copy()->addDays(14)->toDateString() : null,
                'follow_up_notes'        => $enc['status'] === 'completed' ? 'Follow-up advised.' : null,
                'created_at'             => $createdAt,
                'updated_at'             => $createdAt,
            ]);
        }
    }
}
