<?php

namespace App\Console\Commands;

use App\Modules\Core\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fire a test SMS and/or email through the Notifier so you can confirm the
 * gateway / SMTP settings are working. Logs to notifications_log like the real thing.
 *
 *   php artisan medos:test-notification --sms=9822162623 --email=you@x.com
 */
class TestNotification extends Command
{
    protected $signature = 'medos:test-notification {--sms= : phone to text} {--email= : email to send to} {--hospital= : hospital id (defaults to first active)}';

    protected $description = 'Send a test SMS and/or email through MedOS notifications';

    public function handle(): int
    {
        $hospitalId = $this->option('hospital') ?: DB::table('hospitals')->where('is_active', true)->value('id');
        $sms = $this->option('sms');
        $email = $this->option('email');

        if (! $sms && ! $email) {
            $this->error('Pass --sms=<phone> and/or --email=<address>.');
            return self::FAILURE;
        }

        if ($sms) {
            $ok = Notifier::sms($hospitalId, $sms, 'MedOS test SMS — if you got this, SMS delivery is working. ✅', 'test');
            $this->line(($ok ? '<info>SMS handed off</info>' : '<comment>SMS logged (no gateway wired)</comment>') . " → {$sms}");
        }
        if ($email) {
            $html = '<p>This is a <strong>MedOS test email</strong>. If you can read this, email delivery is working. ✅</p>';
            $ok = Notifier::email($hospitalId, $email, 'MedOS test email', $html, 'test');
            $this->line(($ok ? '<info>Email sent</info>' : '<comment>Email logged / failed — check MAIL_MAILER</comment>') . " → {$email}");
        }

        $this->info('Done. Check notifications_log (and your mail/SMS logs) for the entries.');

        return self::SUCCESS;
    }
}
