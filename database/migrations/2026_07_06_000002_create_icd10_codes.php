<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ICD-10 reference catalog (global, not hospital-scoped) for coded diagnoses on
 * the doctor consultation. Seeded with a curated set of common codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('icd10_codes')) {
            Schema::create('icd10_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 10)->index();
                $table->string('title');
                $table->string('category')->nullable();
                $table->timestamps();
                $table->index('title');
            });
        }

        if (DB::table('icd10_codes')->exists()) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($this->catalog() as [$code, $title, $cat]) {
            $rows[] = ['id' => (string) Str::uuid(), 'code' => $code, 'title' => $title, 'category' => $cat, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('icd10_codes')->insert($chunk);
        }
    }

    /** @return array<int,array{0:string,1:string,2:string}> */
    private function catalog(): array
    {
        return [
            // Infectious
            ['A09', 'Infectious gastroenteritis and colitis, unspecified', 'Infectious'],
            ['A15.0', 'Tuberculosis of lung', 'Infectious'],
            ['A90', 'Dengue fever', 'Infectious'],
            ['B34.9', 'Viral infection, unspecified', 'Infectious'],
            ['B54', 'Unspecified malaria', 'Infectious'],
            ['A01.0', 'Typhoid fever', 'Infectious'],
            ['B19.9', 'Unspecified viral hepatitis without hepatic coma', 'Infectious'],
            // Respiratory
            ['J00', 'Acute nasopharyngitis (common cold)', 'Respiratory'],
            ['J02.9', 'Acute pharyngitis, unspecified', 'Respiratory'],
            ['J03.90', 'Acute tonsillitis, unspecified', 'Respiratory'],
            ['J06.9', 'Acute upper respiratory infection, unspecified', 'Respiratory'],
            ['J18.9', 'Pneumonia, unspecified organism', 'Respiratory'],
            ['J20.9', 'Acute bronchitis, unspecified', 'Respiratory'],
            ['J45.909', 'Unspecified asthma, uncomplicated', 'Respiratory'],
            ['J44.9', 'Chronic obstructive pulmonary disease, unspecified', 'Respiratory'],
            ['R05', 'Cough', 'Respiratory'],
            ['J30.9', 'Allergic rhinitis, unspecified', 'Respiratory'],
            // Cardiovascular
            ['I10', 'Essential (primary) hypertension', 'Cardiovascular'],
            ['I25.10', 'Atherosclerotic heart disease of native coronary artery', 'Cardiovascular'],
            ['I48.91', 'Unspecified atrial fibrillation', 'Cardiovascular'],
            ['I50.9', 'Heart failure, unspecified', 'Cardiovascular'],
            ['I20.9', 'Angina pectoris, unspecified', 'Cardiovascular'],
            ['I21.9', 'Acute myocardial infarction, unspecified', 'Cardiovascular'],
            ['I63.9', 'Cerebral infarction, unspecified', 'Cardiovascular'],
            // Endocrine / metabolic
            ['E11.9', 'Type 2 diabetes mellitus without complications', 'Endocrine'],
            ['E10.9', 'Type 1 diabetes mellitus without complications', 'Endocrine'],
            ['E78.5', 'Hyperlipidemia, unspecified', 'Endocrine'],
            ['E03.9', 'Hypothyroidism, unspecified', 'Endocrine'],
            ['E05.90', 'Thyrotoxicosis, unspecified', 'Endocrine'],
            ['E66.9', 'Obesity, unspecified', 'Endocrine'],
            ['E86.0', 'Dehydration', 'Endocrine'],
            ['D50.9', 'Iron deficiency anemia, unspecified', 'Blood'],
            ['D64.9', 'Anemia, unspecified', 'Blood'],
            // Gastrointestinal
            ['K21.9', 'Gastro-esophageal reflux disease without esophagitis', 'Gastrointestinal'],
            ['K29.70', 'Gastritis, unspecified, without bleeding', 'Gastrointestinal'],
            ['K30', 'Functional dyspepsia', 'Gastrointestinal'],
            ['K59.00', 'Constipation, unspecified', 'Gastrointestinal'],
            ['K52.9', 'Noninfective gastroenteritis and colitis, unspecified', 'Gastrointestinal'],
            ['K80.20', 'Calculus of gallbladder without cholecystitis', 'Gastrointestinal'],
            ['K35.80', 'Unspecified acute appendicitis', 'Gastrointestinal'],
            ['B18.2', 'Chronic viral hepatitis C', 'Gastrointestinal'],
            // Genitourinary
            ['N39.0', 'Urinary tract infection, site not specified', 'Genitourinary'],
            ['N18.9', 'Chronic kidney disease, unspecified', 'Genitourinary'],
            ['N20.0', 'Calculus of kidney', 'Genitourinary'],
            ['N23', 'Unspecified renal colic', 'Genitourinary'],
            ['N40.0', 'Benign prostatic hyperplasia without LUTS', 'Genitourinary'],
            // Musculoskeletal
            ['M54.5', 'Low back pain', 'Musculoskeletal'],
            ['M54.2', 'Cervicalgia', 'Musculoskeletal'],
            ['M25.50', 'Pain in unspecified joint', 'Musculoskeletal'],
            ['M79.1', 'Myalgia', 'Musculoskeletal'],
            ['M17.9', 'Osteoarthritis of knee, unspecified', 'Musculoskeletal'],
            ['M06.9', 'Rheumatoid arthritis, unspecified', 'Musculoskeletal'],
            ['M81.0', 'Age-related osteoporosis without current fracture', 'Musculoskeletal'],
            // Neurological
            ['G43.909', 'Migraine, unspecified, not intractable', 'Neurological'],
            ['R51', 'Headache', 'Neurological'],
            ['G40.909', 'Epilepsy, unspecified, not intractable', 'Neurological'],
            ['R42', 'Dizziness and giddiness', 'Neurological'],
            ['G62.9', 'Polyneuropathy, unspecified', 'Neurological'],
            // Skin
            ['L30.9', 'Dermatitis, unspecified', 'Skin'],
            ['L20.9', 'Atopic dermatitis, unspecified', 'Skin'],
            ['L50.9', 'Urticaria, unspecified', 'Skin'],
            ['L03.90', 'Cellulitis, unspecified', 'Skin'],
            ['B35.9', 'Dermatophytosis, unspecified', 'Skin'],
            ['L70.0', 'Acne vulgaris', 'Skin'],
            // Mental health
            ['F41.9', 'Anxiety disorder, unspecified', 'Mental'],
            ['F32.9', 'Major depressive disorder, single episode, unspecified', 'Mental'],
            ['F51.01', 'Primary insomnia', 'Mental'],
            // ENT / Eye
            ['H66.90', 'Otitis media, unspecified', 'ENT'],
            ['H10.9', 'Unspecified conjunctivitis', 'Eye'],
            ['J32.9', 'Chronic sinusitis, unspecified', 'ENT'],
            ['H81.10', 'Benign paroxysmal vertigo, unspecified ear', 'ENT'],
            // Symptoms / general
            ['R50.9', 'Fever, unspecified', 'Symptoms'],
            ['R10.9', 'Unspecified abdominal pain', 'Symptoms'],
            ['R11.2', 'Nausea with vomiting, unspecified', 'Symptoms'],
            ['R07.9', 'Chest pain, unspecified', 'Symptoms'],
            ['R06.02', 'Shortness of breath', 'Symptoms'],
            ['R53.83', 'Other fatigue', 'Symptoms'],
            ['R63.0', 'Anorexia', 'Symptoms'],
            ['Z00.00', 'General adult medical examination without abnormal findings', 'General'],
            ['Z23', 'Encounter for immunization', 'General'],
            // Pregnancy / obstetrics
            ['O80', 'Encounter for full-term uncomplicated delivery', 'Obstetrics'],
            ['Z34.90', 'Encounter for supervision of normal pregnancy, unspecified', 'Obstetrics'],
            ['O21.9', 'Vomiting of pregnancy, unspecified', 'Obstetrics'],
            // Pediatric-common
            ['P07.30', 'Preterm newborn, unspecified weeks of gestation', 'Pediatric'],
            ['R62.51', 'Failure to thrive (child)', 'Pediatric'],
        ];
    }

    public function down(): void
    {
        Schema::dropIfExists('icd10_codes');
    }
};
