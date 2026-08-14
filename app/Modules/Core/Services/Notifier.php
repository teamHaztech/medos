<?php

namespace App\Modules\Core\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Staff;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * SMS + Email delivery for MedOS.
 *
 * - sms()   : sends through a pluggable gateway (log | msg91 | twilio | generic).
 * - email() : sends through the Laravel mailer (SMTP via .env; falls back to the
 *             `log` mailer out of the box, so nothing breaks before SMTP is set).
 *
 * Config resolves per-hospital first (Hospital.config['sms'] / ['email']), then the
 * global config('medos.sms') / mail config. Secrets in Hospital.config are stored
 * encrypted (see AdminWebController::saveSmsSettings). Every attempt is written to
 * notifications_log via raw DB (the model casts `channel` to an enum that doesn't
 * include sms/email, so we insert raw). This never throws — a failed send must not
 * break a booking or a reminder.
 */
class Notifier
{
    // ---------------------------------------------------------------
    // Primitives
    // ---------------------------------------------------------------

    /** Send an SMS. Returns true if handed off to a gateway (or logged in dev). */
    public static function sms(?string $hospitalId, ?string $phone, string $message, string $type = 'sms', ?string $patientId = null): bool
    {
        $phone = self::normalisePhone($phone);
        if (! $phone) {
            return false;
        }

        $cfg = self::channelConfig($hospitalId, 'sms');
        $provider = strtolower($cfg['provider'] ?? config('medos.sms.provider', 'log'));

        $status = 'queued';
        $externalId = null;
        $error = null;

        try {
            [$status, $externalId, $error] = self::dispatchSms($provider, $cfg, $phone, $message);
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            Log::warning('[Notifier][SMS] send failed: ' . $e->getMessage());
        }

        self::logNotification($hospitalId, $patientId, 'sms', $type, [
            'to' => $phone, 'message' => $message, 'provider' => $provider,
        ], $status, $externalId, $error);

        return $status !== 'failed';
    }

    /** Send an email. Returns true if the mailer accepted it. */
    public static function email(?string $hospitalId, ?string $to, string $subject, string $htmlBody, string $type = 'email', ?string $patientId = null): bool
    {
        $to = trim((string) $to);
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cfg = self::channelConfig($hospitalId, 'email');
        $fromName = $cfg['from_name'] ?? config('mail.from.name', config('medos.name', 'MedOS'));

        $status = 'sent';
        $error = null;
        try {
            Mail::html($htmlBody, function ($m) use ($to, $subject, $fromName) {
                $m->to($to)->subject($subject);
                if ($fromName) {
                    $m->from(config('mail.from.address', 'no-reply@medos.local'), $fromName);
                }
            });
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            Log::warning('[Notifier][Email] send failed: ' . $e->getMessage());
        }

        self::logNotification($hospitalId, $patientId, 'email', $type, [
            'to' => $to, 'subject' => $subject,
        ], $status, null, $error);

        return $status === 'sent';
    }

    // ---------------------------------------------------------------
    // Convenience: patient appointment messages (SMS + Email together)
    // ---------------------------------------------------------------

    public static function appointmentConfirmation(Appointment $apt): void
    {
        $patient = Patient::find($apt->patient_id);
        if (! $patient) {
            return;
        }
        $doctor = Staff::find($apt->doctor_id);
        $when = Carbon::parse($apt->slot_start);
        $date = $when->format('l, M d');
        $time = $when->format('g:i A');
        $token = $apt->notes ?? '';

        $sms = "Appointment confirmed with {$doctor?->name} on {$date} at {$time}. Token: {$token}. Please arrive 10 min early. — " . self::hospitalName($apt->hospital_id);
        self::sms($apt->hospital_id, $patient->phone, $sms, 'appointment_confirmation', $patient->id);

        if ($patient->email) {
            $html = self::emailShell('Appointment Confirmed', "
                <p>Dear {$patient->name},</p>
                <p>Your appointment is confirmed.</p>
                <table style='border-collapse:collapse'>
                    <tr><td style='padding:4px 12px 4px 0;color:#64748b'>Doctor</td><td><strong>{$doctor?->name}</strong></td></tr>
                    <tr><td style='padding:4px 12px 4px 0;color:#64748b'>Date</td><td>{$date}</td></tr>
                    <tr><td style='padding:4px 12px 4px 0;color:#64748b'>Time</td><td>{$time}</td></tr>
                    <tr><td style='padding:4px 12px 4px 0;color:#64748b'>Token</td><td>{$token}</td></tr>
                </table>
                <p style='color:#64748b;font-size:13px'>Please arrive 10 minutes early.</p>
            ", $apt->hospital_id);
            self::email($apt->hospital_id, $patient->email, 'Appointment Confirmed — ' . self::hospitalName($apt->hospital_id), $html, 'appointment_confirmation', $patient->id);
        }
    }

    public static function appointmentReminder(Appointment $apt, string $timeframe = '24h'): void
    {
        $patient = Patient::find($apt->patient_id);
        if (! $patient) {
            return;
        }
        $doctor = Staff::find($apt->doctor_id);
        $when = Carbon::parse($apt->slot_start);
        $whenText = $timeframe === '2h' ? 'in 2 hours' : 'tomorrow';

        $sms = "Reminder: your appointment with {$doctor?->name} is {$whenText} ({$when->format('M d, g:i A')}). Token: " . ($apt->notes ?? '') . ". — " . self::hospitalName($apt->hospital_id);
        self::sms($apt->hospital_id, $patient->phone, $sms, 'appointment_reminder', $patient->id);

        if ($patient->email) {
            $html = self::emailShell('Appointment Reminder', "
                <p>Dear {$patient->name},</p>
                <p>This is a reminder that your appointment with <strong>{$doctor?->name}</strong> is {$whenText}, on {$when->format('l, M d')} at {$when->format('g:i A')}.</p>
                <p style='color:#64748b;font-size:13px'>Please arrive on time.</p>
            ", $apt->hospital_id);
            self::email($apt->hospital_id, $patient->email, 'Appointment Reminder — ' . self::hospitalName($apt->hospital_id), $html, 'appointment_reminder', $patient->id);
        }
    }

    // ---------------------------------------------------------------
    // SMS gateway drivers
    // ---------------------------------------------------------------

    /** @return array{0:string,1:?string,2:?string} [status, externalId, error] */
    private static function dispatchSms(string $provider, array $cfg, string $phone, string $message): array
    {
        return match ($provider) {
            'msg91'   => self::sendMsg91($cfg, $phone, $message),
            'twilio'  => self::sendTwilio($cfg, $phone, $message),
            'generic' => self::sendGeneric($cfg, $phone, $message),
            default   => self::sendLog($phone, $message),   // 'log' — dev / not-configured
        };
    }

    private static function sendLog(string $phone, string $message): array
    {
        Log::channel('single')->info('[SMS OUT] ' . $phone . ': ' . Str::limit($message, 140));

        return ['queued', null, null]; // marked queued: no real gateway wired yet
    }

    private static function sendMsg91(array $cfg, string $phone, string $message): array
    {
        $key = self::secret($cfg, 'api_key');
        $sender = $cfg['sender_id'] ?? 'MEDOS';
        if (! $key) {
            return self::sendLog($phone, $message);
        }
        $resp = Http::timeout(10)->withHeaders(['authkey' => $key])
            ->post('https://control.msg91.com/api/v5/flow/', [
                'sender' => $sender,
                'mobiles' => ltrim($phone, '+'),
                'message' => $message,
            ]);

        return $resp->successful()
            ? ['sent', $resp->json('request_id') ?? null, null]
            : ['failed', null, 'MSG91 HTTP ' . $resp->status() . ': ' . Str::limit($resp->body(), 200)];
    }

    private static function sendTwilio(array $cfg, string $phone, string $message): array
    {
        $sid = $cfg['account_sid'] ?? null;
        $token = self::secret($cfg, 'auth_token');
        $from = $cfg['from'] ?? null;
        if (! $sid || ! $token || ! $from) {
            return self::sendLog($phone, $message);
        }
        $resp = Http::timeout(10)->asForm()->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $phone, 'From' => $from, 'Body' => $message,
            ]);

        return $resp->successful()
            ? ['sent', $resp->json('sid') ?? null, null]
            : ['failed', null, 'Twilio HTTP ' . $resp->status() . ': ' . Str::limit($resp->body(), 200)];
    }

    /** A generic JSON POST gateway — {url, api_key} with {to, message} body. */
    private static function sendGeneric(array $cfg, string $phone, string $message): array
    {
        $url = $cfg['url'] ?? null;
        if (! $url) {
            return self::sendLog($phone, $message);
        }
        $key = self::secret($cfg, 'api_key');
        $req = Http::timeout(10);
        if ($key) {
            $req = $req->withToken($key);
        }
        $resp = $req->post($url, ['to' => $phone, 'message' => $message]);

        return $resp->successful()
            ? ['sent', $resp->json('id') ?? null, null]
            : ['failed', null, 'Gateway HTTP ' . $resp->status()];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Merge per-hospital channel config over the global defaults. */
    private static function channelConfig(?string $hospitalId, string $channel): array
    {
        $out = (array) (config("medos.{$channel}") ?? []);
        if ($hospitalId) {
            $raw = DB::table('hospitals')->where('id', $hospitalId)->value('config');
            $cfg = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
            if (! empty($cfg[$channel]) && is_array($cfg[$channel])) {
                $out = array_merge($out, $cfg[$channel]);
            }
        }

        return $out;
    }

    /** Decrypt a secret stored in Hospital.config (plaintext env values pass through). */
    private static function secret(array $cfg, string $key): ?string
    {
        $val = $cfg[$key] ?? null;
        if (! $val) {
            return null;
        }
        try {
            return decrypt($val);
        } catch (\Throwable $e) {
            return $val; // not an encrypted value (e.g. from env)
        }
    }

    /** Normalise an Indian mobile to E.164-ish (+91XXXXXXXXXX). */
    private static function normalisePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($digits, '+')) {
            return $digits;
        }
        $digits = ltrim($digits, '0');
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }
        if (strlen($digits) > 10) {
            return '+' . $digits;
        }

        return $digits ?: null;
    }

    private static function hospitalName(?string $hospitalId): string
    {
        return $hospitalId
            ? (DB::table('hospitals')->where('id', $hospitalId)->value('name') ?? 'the hospital')
            : 'the hospital';
    }

    private static function emailShell(string $title, string $bodyHtml, ?string $hospitalId): string
    {
        $hospital = self::hospitalName($hospitalId);

        return "<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#0f172a'>
            <div style='background:#0f1e33;color:#fff;padding:18px 24px;border-radius:10px 10px 0 0'>
                <div style='font-size:18px;font-weight:700'>{$hospital}</div>
                <div style='font-size:13px;color:#8ba2bc'>{$title}</div>
            </div>
            <div style='border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;padding:22px 24px'>
                {$bodyHtml}
                <p style='color:#94a3b8;font-size:11px;margin-top:22px'>This message was sent by {$hospital} via MedOS. Please do not reply to this email.</p>
            </div>
        </div>";
    }

    private static function logNotification(?string $hospitalId, ?string $patientId, string $channel, string $type, array $content, string $status, ?string $externalId, ?string $error): void
    {
        try {
            DB::table('notifications_log')->insert([
                'id'          => Str::uuid()->toString(),
                'hospital_id' => $hospitalId ?? DB::table('hospitals')->where('is_active', true)->value('id'),
                'patient_id'  => $patientId,
                'channel'     => $channel,
                'type'        => $type,
                'content'     => json_encode($content),
                'status'      => $status,
                'external_id' => $externalId,
                'error'       => $error ? Str::limit($error, 500) : null,
                'sent_at'     => in_array($status, ['sent', 'queued'], true) ? now() : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notifier] log insert failed: ' . $e->getMessage());
        }
    }
}
