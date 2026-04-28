<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConversationSeeder extends Seeder
{
    /**
     * Seed the conversations table with 5 sample AI receptionist conversations.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('conversations')->truncate();
        Schema::enableForeignKeyConstraints();

        $now = Carbon::now();

        // Fetch first few encounters for linking
        $encounters = DB::table('encounters')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->limit(3)
            ->pluck('id')
            ->toArray();

        // ------------------------------------------------------------------
        // 1. Complete conversation in English (greeting -> confirmation)
        // ------------------------------------------------------------------
        $conv1Started = $now->copy()->subHours(3);
        DB::table('conversations')->insert([
            'id'                    => Str::uuid()->toString(),
            'hospital_id'           => SeedData::CITY_CARE_ID,
            'patient_id'            => SeedData::PATIENT_01,
            'encounter_id'          => $encounters[0] ?? null,
            'escalated_to_staff_id' => null,
            'channel'               => 'whatsapp',
            'status'                => 'completed',
            'current_state'         => 'confirmation',
            'language_detected'     => 'en',
            'messages'              => json_encode([
                [
                    'role'      => 'assistant',
                    'content'   => 'Welcome to City Care Hospital! I\'m your AI health assistant. How can I help you today? You can tell me your symptoms or book an appointment.',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'greeting'],
                    'timestamp' => $conv1Started->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Hi, I have been having fever and body ache for the past 3 days',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv1Started->copy()->addMinutes(1)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'I\'m sorry to hear that. Let me ask a few questions to better understand your condition. How high has your temperature been? Have you been taking any medication?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'intake'],
                    'timestamp' => $conv1Started->copy()->addMinutes(1)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Temperature has been around 101. I took paracetamol but it comes back.',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv1Started->copy()->addMinutes(2)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Thank you. Do you have any other symptoms like cough, cold, sore throat, or rash? Any known medical conditions or allergies?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'intake'],
                    'timestamp' => $conv1Started->copy()->addMinutes(2)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'No cough or cold. I am diabetic and take metformin. No allergies.',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv1Started->copy()->addMinutes(3)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Based on your symptoms, I\'d recommend a General Medicine consultation. Your condition is classified as routine priority. I have a slot available today with Dr. Meera Nair at 11:30 AM. Shall I book it for you?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'booking', 'triage' => 'routine', 'specialty' => 'General Medicine'],
                    'timestamp' => $conv1Started->copy()->addMinutes(3)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Yes please, book it.',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv1Started->copy()->addMinutes(4)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Your appointment has been booked! Here are the details:\n\nDoctor: Dr. Meera Nair\nDepartment: General Medicine\nDate: Today\nTime: 11:30 AM\nToken: GEN-005\n\nPlease arrive 15 minutes early. Bring your diabetes medications list. Get well soon!',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'confirmation', 'token' => 'GEN-005'],
                    'timestamp' => $conv1Started->copy()->addMinutes(4)->addSeconds(30)->toIso8601String(),
                ],
            ]),
            'ai_reasoning_trace' => json_encode([
                ['state' => 'greeting', 'action' => 'Sent welcome message'],
                ['state' => 'intake', 'action' => 'Collected symptoms: fever 101F, body ache, 3 days duration'],
                ['state' => 'intake', 'action' => 'Collected history: diabetic on metformin, no allergies'],
                ['state' => 'triage', 'action' => 'Classified as routine - viral fever symptoms, no red flags'],
                ['state' => 'booking', 'action' => 'Recommended General Medicine, found slot with Dr. Meera Nair at 11:30'],
                ['state' => 'confirmation', 'action' => 'Booking confirmed, token GEN-005 issued'],
            ]),
            'intent_detected'      => 'book_appointment',
            'escalation_reason'    => null,
            'handled_by_ai'        => true,
            'started_at'           => $conv1Started,
            'ended_at'             => $conv1Started->copy()->addMinutes(5),
            'created_at'           => $conv1Started,
            'updated_at'           => $conv1Started->copy()->addMinutes(5),
        ]);

        // ------------------------------------------------------------------
        // 2. Complete conversation in Hindi/Hinglish
        // ------------------------------------------------------------------
        $conv2Started = $now->copy()->subHours(5);
        DB::table('conversations')->insert([
            'id'                    => Str::uuid()->toString(),
            'hospital_id'           => SeedData::CITY_CARE_ID,
            'patient_id'            => SeedData::PATIENT_02,
            'encounter_id'          => $encounters[1] ?? null,
            'escalated_to_staff_id' => null,
            'channel'               => 'whatsapp',
            'status'                => 'completed',
            'current_state'         => 'confirmation',
            'language_detected'     => 'hi',
            'messages'              => json_encode([
                [
                    'role'      => 'assistant',
                    'content'   => 'City Care Hospital mein aapka swagat hai! Main aapka AI health assistant hoon. Aap mujhe apni takleef bata sakte hain ya appointment book kar sakte hain.',
                    'language'  => 'hi',
                    'metadata'  => ['state' => 'greeting'],
                    'timestamp' => $conv2Started->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Mera baccha 3 saal ka hai, usko subah se bahut tez bukhar hai',
                    'language'  => 'hi',
                    'metadata'  => [],
                    'timestamp' => $conv2Started->copy()->addMinutes(1)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Main samajh sakta hoon aapki chinta. Kuch sawal poochna chahta hoon. Bacche ka temperature kitna hai? Kya woh kuch kha pi raha hai? Koi rash ya dast toh nahi hai?',
                    'language'  => 'hi',
                    'metadata'  => ['state' => 'intake'],
                    'timestamp' => $conv2Started->copy()->addMinutes(1)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Temperature 103 hai. Kuch nahi kha raha, bahut chidchida ho raha hai. Rash nahi hai.',
                    'language'  => 'hi',
                    'metadata'  => [],
                    'timestamp' => $conv2Started->copy()->addMinutes(2)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Bacche ko tez bukhar hai aur woh irritable hai - yeh urgent hai. Main aapko aaj Pediatrics mein Dr. Priya Sharma ke paas 10:00 AM ka appointment de rahi hoon. Kya yeh theek hai?',
                    'language'  => 'hi',
                    'metadata'  => ['state' => 'booking', 'triage' => 'urgent', 'specialty' => 'Pediatrics'],
                    'timestamp' => $conv2Started->copy()->addMinutes(2)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Haan ji, book kar dijiye',
                    'language'  => 'hi',
                    'metadata'  => [],
                    'timestamp' => $conv2Started->copy()->addMinutes(3)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Appointment book ho gaya hai!\n\nDoctor: Dr. Priya Sharma\nVibhaag: Pediatrics\nDate: Aaj\nSamay: 10:00 AM\nToken: PED-001\n\nKripya 10 minute pehle pahunch jaayein. Bacche ko paracetamol syrup de sakte hain agar bukhar bahut tez ho. Jaldi theek ho jaaye!',
                    'language'  => 'hi',
                    'metadata'  => ['state' => 'confirmation', 'token' => 'PED-001'],
                    'timestamp' => $conv2Started->copy()->addMinutes(3)->addSeconds(30)->toIso8601String(),
                ],
            ]),
            'ai_reasoning_trace' => json_encode([
                ['state' => 'language_detection', 'action' => 'Detected Hindi/Hinglish, switching to Hindi responses'],
                ['state' => 'intake', 'action' => 'Child 3 years, fever 103F, irritable, not eating'],
                ['state' => 'triage', 'action' => 'Urgent - high fever in toddler with poor feeding and irritability'],
                ['state' => 'booking', 'action' => 'Urgent slot with Dr. Priya Sharma (Pediatrics) at 10:00 AM'],
                ['state' => 'confirmation', 'action' => 'Booking confirmed, token PED-001'],
            ]),
            'intent_detected'      => 'book_appointment',
            'escalation_reason'    => null,
            'handled_by_ai'        => true,
            'started_at'           => $conv2Started,
            'ended_at'             => $conv2Started->copy()->addMinutes(4),
            'created_at'           => $conv2Started,
            'updated_at'           => $conv2Started->copy()->addMinutes(4),
        ]);

        // ------------------------------------------------------------------
        // 3. Emergency detection conversation
        // ------------------------------------------------------------------
        $conv3Started = $now->copy()->subHours(1);
        DB::table('conversations')->insert([
            'id'                    => Str::uuid()->toString(),
            'hospital_id'           => SeedData::CITY_CARE_ID,
            'patient_id'            => SeedData::PATIENT_03,
            'encounter_id'          => $encounters[2] ?? null,
            'escalated_to_staff_id' => null,
            'channel'               => 'whatsapp',
            'status'                => 'completed',
            'current_state'         => 'emergency',
            'language_detected'     => 'en',
            'messages'              => json_encode([
                [
                    'role'      => 'assistant',
                    'content'   => 'Welcome to City Care Hospital! I\'m your AI health assistant. How can I help you today?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'greeting'],
                    'timestamp' => $conv3Started->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'My father is having severe chest pain and his left arm has gone numb. He is sweating heavily.',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv3Started->copy()->addSeconds(45)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'THIS IS AN EMERGENCY. The symptoms you describe (chest pain, left arm numbness, heavy sweating) could indicate a heart attack.\n\nPLEASE DO THE FOLLOWING IMMEDIATELY:\n1. Call 108 (ambulance) right now\n2. Have your father sit upright and stay calm\n3. If he has aspirin and is not allergic, give one tablet to chew\n4. Loosen any tight clothing\n5. Do NOT let him walk or exert himself\n\nI am alerting our emergency team at City Care Hospital. If you are nearby, our address is 42, MG Road, Koramangala, Bangalore.\n\nStay on the line - help is being arranged.',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'emergency', 'emergency_type' => 'cardiac', 'alert_sent' => true],
                    'timestamp' => $conv3Started->copy()->addMinutes(1)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Ok calling ambulance now. We are 10 minutes from the hospital.',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv3Started->copy()->addMinutes(2)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Good. Our emergency team has been notified and will be ready when you arrive. Keep your father comfortable and monitor his breathing. If he becomes unconscious, be prepared to start CPR. I have transferred this case to our duty doctor.',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'emergency', 'escalated' => true],
                    'timestamp' => $conv3Started->copy()->addMinutes(2)->addSeconds(30)->toIso8601String(),
                ],
            ]),
            'ai_reasoning_trace' => json_encode([
                ['state' => 'greeting', 'action' => 'Standard greeting sent'],
                ['state' => 'emergency', 'action' => 'EMERGENCY DETECTED: chest pain + left arm numbness + diaphoresis = suspected MI'],
                ['state' => 'emergency', 'action' => 'Emergency protocol activated, first aid instructions sent'],
                ['state' => 'emergency', 'action' => 'Hospital emergency team alerted, case escalated to duty doctor'],
            ]),
            'intent_detected'      => 'emergency',
            'escalation_reason'    => null,
            'handled_by_ai'        => true,
            'started_at'           => $conv3Started,
            'ended_at'             => $conv3Started->copy()->addMinutes(3),
            'created_at'           => $conv3Started,
            'updated_at'           => $conv3Started->copy()->addMinutes(3),
        ]);

        // ------------------------------------------------------------------
        // 4. Escalated conversation (AI unable to determine, handed to staff)
        // ------------------------------------------------------------------
        $conv4Started = $now->copy()->subHours(2);
        DB::table('conversations')->insert([
            'id'                    => Str::uuid()->toString(),
            'hospital_id'           => SeedData::CITY_CARE_ID,
            'patient_id'            => SeedData::PATIENT_04,
            'encounter_id'          => null,
            'escalated_to_staff_id' => SeedData::STAFF_RAJESH,
            'channel'               => 'whatsapp',
            'status'                => 'escalated',
            'current_state'         => 'escalated',
            'language_detected'     => 'en',
            'messages'              => json_encode([
                [
                    'role'      => 'assistant',
                    'content'   => 'Welcome to City Care Hospital! I\'m your AI health assistant. How can I help you today?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'greeting'],
                    'timestamp' => $conv4Started->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'I want to know about the surgery packages you have',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv4Started->copy()->addMinutes(1)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'I can help with booking consultations and understanding symptoms. For surgery package details and pricing, let me connect you with our front desk team who can give you accurate information. One moment please.',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'escalated', 'reason' => 'pricing_inquiry'],
                    'timestamp' => $conv4Started->copy()->addMinutes(1)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'Ok sure',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv4Started->copy()->addMinutes(2)->toIso8601String(),
                ],
                [
                    'role'      => 'system',
                    'content'   => 'Conversation escalated to Dr. Rajesh Kumar (Hospital Admin)',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'escalated', 'staff_id' => SeedData::STAFF_RAJESH],
                    'timestamp' => $conv4Started->copy()->addMinutes(2)->addSeconds(15)->toIso8601String(),
                ],
            ]),
            'ai_reasoning_trace' => json_encode([
                ['state' => 'greeting', 'action' => 'Standard greeting sent'],
                ['state' => 'intake', 'action' => 'User asking about surgery packages - not a symptom/booking flow'],
                ['state' => 'escalated', 'action' => 'Pricing inquiry outside AI scope, escalating to staff'],
            ]),
            'intent_detected'      => 'pricing_inquiry',
            'escalation_reason'    => 'Pricing inquiry outside AI scope',
            'handled_by_ai'        => false,
            'started_at'           => $conv4Started,
            'ended_at'             => null,
            'created_at'           => $conv4Started,
            'updated_at'           => $conv4Started->copy()->addMinutes(4),
        ]);

        // ------------------------------------------------------------------
        // 5. Active/in-progress conversation
        // ------------------------------------------------------------------
        $conv5Started = $now->copy()->subMinutes(10);
        DB::table('conversations')->insert([
            'id'                    => Str::uuid()->toString(),
            'hospital_id'           => SeedData::CITY_CARE_ID,
            'patient_id'            => SeedData::PATIENT_05,
            'encounter_id'          => null,
            'escalated_to_staff_id' => null,
            'channel'               => 'whatsapp',
            'status'                => 'active',
            'current_state'         => 'intake',
            'language_detected'     => 'en',
            'messages'              => json_encode([
                [
                    'role'      => 'assistant',
                    'content'   => 'Welcome to City Care Hospital! I\'m your AI health assistant. How can I help you today?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'greeting'],
                    'timestamp' => $conv5Started->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'I have been having stomach pain and loose motions since yesterday',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv5Started->copy()->addMinutes(2)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'I understand, that must be uncomfortable. Let me ask a few questions:\n\n1. How many loose stools have you had in the last 24 hours?\n2. Is there any blood in the stool?\n3. Do you have fever along with the stomach pain?\n4. Are you able to drink water/fluids?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'intake'],
                    'timestamp' => $conv5Started->copy()->addMinutes(2)->addSeconds(30)->toIso8601String(),
                ],
                [
                    'role'      => 'user',
                    'content'   => 'About 5-6 times. No blood. Mild fever. Yes I can drink water.',
                    'language'  => 'en',
                    'metadata'  => [],
                    'timestamp' => $conv5Started->copy()->addMinutes(5)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Thank you. A few more questions:\n\n1. Did you eat anything unusual or outside food recently?\n2. Do you have any known medical conditions or allergies?\n3. Is anyone else at home having similar symptoms?',
                    'language'  => 'en',
                    'metadata'  => ['state' => 'intake'],
                    'timestamp' => $conv5Started->copy()->addMinutes(5)->addSeconds(30)->toIso8601String(),
                ],
            ]),
            'ai_reasoning_trace' => json_encode([
                ['state' => 'greeting', 'action' => 'Standard greeting sent'],
                ['state' => 'intake', 'action' => 'Symptoms: abdominal pain + diarrhea, 1 day duration'],
                ['state' => 'intake', 'action' => 'Collecting additional info: 5-6 episodes, no blood, mild fever, tolerating fluids'],
                ['state' => 'intake', 'action' => 'Asking about food history, medical background, household contacts'],
            ]),
            'intent_detected'      => 'report_symptoms',
            'escalation_reason'    => null,
            'handled_by_ai'        => true,
            'started_at'           => $conv5Started,
            'ended_at'             => null,
            'created_at'           => $conv5Started,
            'updated_at'           => $now,
        ]);
    }
}
