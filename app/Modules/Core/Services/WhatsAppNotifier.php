<?php

namespace App\Modules\Core\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Staff;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp notifications to patients for appointment events.
 * Uses the ChatController's session to send messages via the whatsapp-web.js bot.
 * For now, logs messages. When WhatsApp is connected, sends via API.
 */
class WhatsAppNotifier
{
    /**
     * Notify patient that their appointment is confirmed.
     */
    public static function appointmentBooked(Appointment $apt): void
    {
        $patient = Patient::find($apt->patient_id);
        $doctor = Staff::find($apt->doctor_id);
        if (!$patient?->phone) return;

        $date = Carbon::parse($apt->slot_start)->format('l, M d');
        $time = Carbon::parse($apt->slot_start)->format('g:i A');
        $token = $apt->notes ?? '';
        $lang = $patient->language_preference ?? 'en';

        $msg = match($lang) {
            'hi' => "✅ *अपॉइंटमेंट कन्फर्म*\n\n🩺 डॉक्टर: {$doctor?->name}\n📅 {$date} — {$time}\n🎫 टोकन: {$token}\n\nकृपया 10 मिनट पहले पहुंचें।",
            'ar' => "✅ *تم تأكيد الموعد*\n\n🩺 الطبيب: {$doctor?->name}\n📅 {$date} — {$time}\n🎫 التذكرة: {$token}\n\nيرجى الحضور قبل 10 دقائق.",
            default => "✅ *Appointment Confirmed*\n\n🩺 Doctor: {$doctor?->name}\n📅 {$date} at {$time}\n🎫 Token: {$token}\n\nPlease arrive 10 minutes early.",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Notify patient that their appointment was cancelled.
     */
    public static function appointmentCancelled(Appointment $apt, string $cancelledBy = 'hospital', ?string $reason = null): void
    {
        $patient = Patient::find($apt->patient_id);
        $doctor = Staff::find($apt->doctor_id);
        if (!$patient?->phone) return;

        $date = Carbon::parse($apt->slot_start)->format('l, M d');
        $time = Carbon::parse($apt->slot_start)->format('g:i A');
        $lang = $patient->language_preference ?? 'en';

        $reasonText = $reason ? "\nReason: {$reason}" : '';

        $msg = match($lang) {
            'hi' => "❌ *अपॉइंटमेंट रद्द*\n\n🩺 डॉक्टर: {$doctor?->name}\n📅 {$date} — {$time}\n\nद्वारा रद्द: {$cancelledBy}{$reasonText}\n\nनई अपॉइंटमेंट बुक करने के लिए \"hi\" भेजें।",
            'ar' => "❌ *تم إلغاء الموعد*\n\n🩺 الطبيب: {$doctor?->name}\n📅 {$date} — {$time}\n\nتم الإلغاء بواسطة: {$cancelledBy}{$reasonText}\n\nأرسل \"hi\" لحجز موعد جديد.",
            default => "❌ *Appointment Cancelled*\n\n🩺 Doctor: {$doctor?->name}\n📅 {$date} at {$time}\n\nCancelled by: {$cancelledBy}{$reasonText}\n\nSend \"hi\" to book a new appointment.",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Notify patient that their appointment was rescheduled.
     */
    public static function appointmentRescheduled(Appointment $apt, string $oldSlot): void
    {
        $patient = Patient::find($apt->patient_id);
        $doctor = Staff::find($apt->doctor_id);
        if (!$patient?->phone) return;

        $newDate = Carbon::parse($apt->slot_start)->format('l, M d');
        $newTime = Carbon::parse($apt->slot_start)->format('g:i A');
        $token = $apt->notes ?? '';
        $lang = $patient->language_preference ?? 'en';

        $msg = match($lang) {
            'hi' => "🔄 *अपॉइंटमेंट बदली गई*\n\n🩺 डॉक्टर: {$doctor?->name}\n❌ पुरानी: {$oldSlot}\n✅ नई: {$newDate} — {$newTime}\n🎫 टोकन: {$token}",
            'ar' => "🔄 *تم إعادة جدولة الموعد*\n\n🩺 الطبيب: {$doctor?->name}\n❌ القديم: {$oldSlot}\n✅ الجديد: {$newDate} — {$newTime}\n🎫 التذكرة: {$token}",
            default => "🔄 *Appointment Rescheduled*\n\n🩺 Doctor: {$doctor?->name}\n❌ Old: {$oldSlot}\n✅ New: {$newDate} at {$newTime}\n🎫 Token: {$token}",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Notify patient that their turn is coming up (queue update).
     */
    public static function queueUpdate(string $patientId, int $position, int $estimatedMinutes): void
    {
        $patient = Patient::find($patientId);
        if (!$patient?->phone) return;

        $lang = $patient->language_preference ?? 'en';

        $msg = match($lang) {
            'hi' => "📋 *कतार अपडेट*\n\nआप #{$position} नंबर पर हैं।\n⏱️ अनुमानित समय: ~{$estimatedMinutes} मिनट",
            'ar' => "📋 *تحديث الطابور*\n\nأنت رقم #{$position}.\n⏱️ الوقت المقدر: ~{$estimatedMinutes} دقيقة",
            default => "📋 *Queue Update*\n\nYou're #{$position} in line.\n⏱️ Estimated wait: ~{$estimatedMinutes} min",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Notify patient that doctor is ready (their turn).
     */
    public static function doctorReady(Appointment $apt): void
    {
        $patient = Patient::find($apt->patient_id);
        $doctor = Staff::find($apt->doctor_id);
        if (!$patient?->phone) return;

        $lang = $patient->language_preference ?? 'en';

        $msg = match($lang) {
            'hi' => "🔔 *आपकी बारी आ गई!*\n\n🩺 डॉक्टर {$doctor?->name} तैयार हैं।\nकृपया अभी अंदर आएं।",
            'ar' => "🔔 *حان دورك!*\n\n🩺 الدكتور {$doctor?->name} في انتظارك.\nيرجى الدخول الآن.",
            default => "🔔 *It's your turn!*\n\n🩺 Dr. {$doctor?->name} is ready for you.\nPlease come in now.",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Notify patient that consultation is complete with summary.
     */
    public static function consultationComplete(Appointment $apt, ?string $followUpDate = null): void
    {
        $patient = Patient::find($apt->patient_id);
        $doctor = Staff::find($apt->doctor_id);
        if (!$patient?->phone) return;

        $lang = $patient->language_preference ?? 'en';
        $followUp = $followUpDate ? "\n📅 Follow-up: {$followUpDate}" : '';

        $msg = match($lang) {
            'hi' => "✅ *परामर्श पूरा*\n\n🩺 डॉक्टर: {$doctor?->name}\n\nधन्यवाद! कृपया फार्मेसी और लैब काउंटर पर जाएं (यदि आवश्यक हो)।{$followUp}\n\nजल्दी स्वस्थ हों! 🙏",
            'ar' => "✅ *اكتملت الاستشارة*\n\n🩺 الطبيب: {$doctor?->name}\n\nشكراً لك! يرجى زيارة الصيدلية والمختبر (إذا لزم الأمر).{$followUp}\n\nنتمنى لك الشفاء العاجل! 🙏",
            default => "✅ *Consultation Complete*\n\n🩺 Doctor: {$doctor?->name}\n\nThank you! Please visit pharmacy and lab counters if needed.{$followUp}\n\nGet well soon! 🙏",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Notify patient about lab results.
     */
    public static function labResultsReady(string $patientId, string $testNames): void
    {
        $patient = Patient::find($patientId);
        if (!$patient?->phone) return;

        $lang = $patient->language_preference ?? 'en';

        $msg = match($lang) {
            'hi' => "🧪 *लैब रिपोर्ट तैयार*\n\nआपकी रिपोर्ट तैयार है: {$testNames}\n\nडॉक्टर जल्द समीक्षा करेंगे।",
            'ar' => "🧪 *نتائج المختبر جاهزة*\n\nنتائجك جاهزة: {$testNames}\n\nسيراجعها الطبيب قريباً.",
            default => "🧪 *Lab Results Ready*\n\nYour results are ready: {$testNames}\n\nYour doctor will review them shortly.",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Appointment reminder (24h or 2h before).
     */
    public static function appointmentReminder(Appointment $apt, string $timeframe = '24h'): void
    {
        $patient = Patient::find($apt->patient_id);
        $doctor = Staff::find($apt->doctor_id);
        if (!$patient?->phone) return;

        $date = Carbon::parse($apt->slot_start)->format('l, M d');
        $time = Carbon::parse($apt->slot_start)->format('g:i A');
        $token = $apt->notes ?? '';
        $lang = $patient->language_preference ?? 'en';

        $when = $timeframe === '2h' ? 'in 2 hours' : 'tomorrow';
        $whenHi = $timeframe === '2h' ? '2 घंटे में' : 'कल';

        $msg = match($lang) {
            'hi' => "⏰ *अपॉइंटमेंट रिमाइंडर*\n\nआपकी अपॉइंटमेंट {$whenHi} है:\n🩺 {$doctor?->name}\n📅 {$date} — {$time}\n🎫 {$token}\n\nकृपया समय पर पहुंचें।",
            default => "⏰ *Appointment Reminder*\n\nYour appointment is {$when}:\n🩺 {$doctor?->name}\n📅 {$date} at {$time}\n🎫 {$token}\n\nPlease arrive on time.",
        };

        self::send($patient->phone, $msg);
    }

    /**
     * Send message via WhatsApp.
     * Currently logs the message. When whatsapp-web.js bot is connected,
     * it will send via the bot's API or Meta Business API.
     */
    private static function send(string $phone, string $message): void
    {
        // Resolve the patient's hospital and respect its WhatsApp module toggle.
        $patient = \DB::table('patients')->where('phone', $phone)->first(['id', 'hospital_id']);
        $hospitalId = $patient->hospital_id ?? \DB::table('hospitals')->where('is_active', true)->value('id');

        $modules = \DB::table('hospitals')->where('id', $hospitalId)->value('modules_enabled');
        if ($modules) {
            $enabled = json_decode($modules, true) ?: [];
            if (! empty($enabled) && ! in_array('whatsapp', $enabled, true)) {
                return; // WhatsApp Integration disabled for this hospital.
            }
        }

        // Log all outgoing messages
        Log::channel('single')->info('[WhatsApp OUT] ' . $phone . ': ' . substr($message, 0, 100));

        // TODO: When WhatsApp bot is running, send via HTTP to the bot
        // Option 1: whatsapp-web.js bot exposes a local HTTP endpoint
        // Option 2: Meta Business API (production)
        // Option 3: Store in a queue table and let the bot poll it

        // For now, store in notifications_log for tracking
        try {
            \DB::table('notifications_log')->insert([
                'id' => \Str::uuid()->toString(),
                'hospital_id' => $hospitalId,
                'patient_id' => $patient->id ?? null,
                'channel' => 'whatsapp',
                'type' => 'appointment_notification',
                'content' => json_encode(['message' => $message, 'phone' => $phone]),
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] Failed to log notification: ' . $e->getMessage());
        }
    }
}
