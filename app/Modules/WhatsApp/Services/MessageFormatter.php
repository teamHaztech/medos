<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Billing\Models\Bill;

class MessageFormatter
{
    // ---------------------------------------------------------------
    // Appointment Formatting
    // ---------------------------------------------------------------

    /**
     * Format an appointment confirmation as an interactive button message.
     *
     * Returns array suitable for SendWhatsAppMessage job with type 'interactive_buttons'.
     *
     * @return array{type: string, content: array}
     */
    public function formatAppointmentConfirmation(Appointment $appointment): array
    {
        $appointment->loadMissing(['doctor', 'patient', 'hospital']);

        $doctorName = $appointment->doctor?->name ?? 'your doctor';
        $patientName = $appointment->patient?->name ?? 'Patient';
        $hospitalName = $appointment->hospital?->name ?? '';
        $date = $appointment->slot_start?->format('D, d M Y') ?? 'TBD';
        $time = $appointment->slot_start?->format('h:i A') ?? 'TBD';
        $reason = $appointment->reason ?? 'General Consultation';

        $body = $this->escapeMarkdown(
            "Your appointment is confirmed.\n\n"
            . "*Doctor:* {$doctorName}\n"
            . "*Date:* {$date}\n"
            . "*Time:* {$time}\n"
            . "*Reason:* {$reason}\n"
        );

        if ($hospitalName) {
            $body .= $this->escapeMarkdown("*Location:* {$hospitalName}\n");
        }

        $body .= "\nPlease arrive 15 minutes early.";

        return [
            'type'    => 'interactive_buttons',
            'content' => [
                'body'    => $body,
                'buttons' => [
                    ['id' => 'confirm_apt_' . $appointment->id, 'title' => 'Confirm'],
                    ['id' => 'reschedule_apt_' . $appointment->id, 'title' => 'Reschedule'],
                    ['id' => 'cancel_apt_' . $appointment->id, 'title' => 'Cancel'],
                ],
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Queue Formatting
    // ---------------------------------------------------------------

    /**
     * Format a queue position update as a plain text message.
     */
    public function formatQueueUpdate(QueueEntry $entry): string
    {
        $position = $entry->position ?? '?';
        $doctorName = $entry->doctor?->name ?? 'the doctor';

        // Estimate wait time: ~8 minutes per position ahead
        $estimatedMinutes = max(0, ($position - 1) * 8);

        $message = "Queue Update\n\n"
            . "You are *#{$position}* in line for *{$doctorName}*.\n";

        if ($estimatedMinutes > 0) {
            $message .= "Estimated wait: ~{$estimatedMinutes} minutes.\n";
        } else {
            $message .= "You are next! Please proceed to the consultation room.\n";
        }

        $message .= "\nWe will notify you when it's your turn.";

        return $message;
    }

    // ---------------------------------------------------------------
    // Slot Selection
    // ---------------------------------------------------------------

    /**
     * Format available time slots as an interactive list message.
     *
     * @param  array  $slots  Array of slot arrays, each with keys: id, start, end, doctor_name
     * @return array{type: string, content: array}
     */
    public function formatSlotOptions(array $slots): array
    {
        // Group slots by date
        $groupedByDate = [];

        foreach ($slots as $slot) {
            $date = isset($slot['start'])
                ? \Carbon\Carbon::parse($slot['start'])->format('D, d M')
                : 'Available';

            $groupedByDate[$date][] = $slot;
        }

        $sections = [];

        foreach ($groupedByDate as $date => $dateSlots) {
            $rows = [];

            foreach (array_slice($dateSlots, 0, 10) as $slot) { // Max 10 rows per section
                $startTime = isset($slot['start'])
                    ? \Carbon\Carbon::parse($slot['start'])->format('h:i A')
                    : '';

                $endTime = isset($slot['end'])
                    ? \Carbon\Carbon::parse($slot['end'])->format('h:i A')
                    : '';

                $doctorName = $slot['doctor_name'] ?? '';

                $rows[] = [
                    'id'          => 'slot_' . ($slot['id'] ?? uniqid()),
                    'title'       => $startTime . ($endTime ? " - {$endTime}" : ''),
                    'description' => $doctorName ? "Dr. {$doctorName}" : null,
                ];
            }

            $sections[] = [
                'title' => $date,
                'rows'  => $rows,
            ];
        }

        // Meta allows max 10 sections
        $sections = array_slice($sections, 0, 10);

        return [
            'type'    => 'interactive_list',
            'content' => [
                'body'        => 'Here are the available appointment slots. Please select a time that works for you:',
                'button_text' => 'View Slots',
                'sections'    => $sections,
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Billing
    // ---------------------------------------------------------------

    /**
     * Format a bill summary as a plain text message.
     */
    public function formatBillSummary(Bill $bill): string
    {
        $bill->loadMissing(['patient', 'encounter']);

        $currency = $bill->currency ?? 'INR';
        $total = number_format((float) $bill->total_amount, 2);
        $insurance = number_format((float) ($bill->insurance_covered ?? 0), 2);
        $due = number_format((float) ($bill->balance_due ?? $bill->total_amount), 2);
        $billNumber = $bill->bill_number ?? $bill->id;

        $message = "Bill Summary (#{$billNumber})\n\n";

        // Line items
        $lineItems = $bill->line_items ?? [];
        if (! empty($lineItems)) {
            foreach ($lineItems as $item) {
                $itemTotal = number_format(
                    ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                    2
                );
                $message .= "- {$item['description']}: {$currency} {$itemTotal}\n";
            }
            $message .= "\n";
        }

        $message .= "*Subtotal:* {$currency} {$total}\n";

        if ((float) $bill->insurance_covered > 0) {
            $message .= "*Insurance Coverage:* -{$currency} {$insurance}\n";
        }

        if ((float) ($bill->discount_amount ?? 0) > 0) {
            $discount = number_format((float) $bill->discount_amount, 2);
            $message .= "*Discount:* -{$currency} {$discount}\n";
        }

        $message .= "*Amount Due:* {$currency} {$due}\n";

        $status = $bill->payment_status?->value ?? 'pending';
        $message .= "*Status:* " . ucfirst($status);

        return $message;
    }

    // ---------------------------------------------------------------
    // Check-In
    // ---------------------------------------------------------------

    /**
     * Format a check-in prompt with a "Check In" button.
     *
     * @return array{type: string, content: array}
     */
    public function formatCheckInPrompt(Appointment $appointment): array
    {
        $appointment->loadMissing(['doctor']);

        $doctorName = $appointment->doctor?->name ?? 'your doctor';
        $time = $appointment->slot_start?->format('h:i A') ?? '';

        $body = "You have an appointment with *{$doctorName}*"
            . ($time ? " at *{$time}*" : '')
            . ".\n\nAre you at the hospital? Tap below to check in.";

        return [
            'type'    => 'interactive_buttons',
            'content' => [
                'body'    => $body,
                'buttons' => [
                    ['id' => 'checkin_' . $appointment->id, 'title' => 'Check In'],
                    ['id' => 'running_late_' . $appointment->id, 'title' => 'Running Late'],
                    ['id' => 'cancel_apt_' . $appointment->id, 'title' => 'Cancel'],
                ],
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Utility
    // ---------------------------------------------------------------

    /**
     * Escape text for WhatsApp's limited markdown.
     *
     * WhatsApp supports: *bold*, _italic_, ~strikethrough~, ```monospace```
     * This method ensures user-provided text doesn't accidentally trigger formatting.
     */
    public function escapeMarkdown(string $text): string
    {
        // Only escape markdown characters that are NOT intentionally used for formatting.
        // Since we control the templates, we pass through formatting characters.
        // For user-supplied content, call this to prevent injection.

        // Escape backticks (prevent monospace injection)
        $text = str_replace('```', '\`\`\`', $text);

        return $text;
    }
}
