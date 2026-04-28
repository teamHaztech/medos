<?php

namespace App\Modules\AIReceptionist\Enums;

enum Intent: string
{
    case BookAppointment       = 'book_appointment';
    case CancelAppointment     = 'cancel_appointment';
    case RescheduleAppointment = 'reschedule_appointment';
    case CheckStatus           = 'check_status';
    case GetLabResults         = 'get_lab_results';
    case BillingQuery          = 'billing_query';
    case EmergencyHelp         = 'emergency_help';
    case GeneralInquiry        = 'general_inquiry';
    case FollowUp              = 'follow_up';
    case SpeakToHuman          = 'speak_to_human';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::BookAppointment       => 'Book Appointment',
            self::CancelAppointment     => 'Cancel Appointment',
            self::RescheduleAppointment => 'Reschedule Appointment',
            self::CheckStatus           => 'Check Status',
            self::GetLabResults         => 'Get Lab Results',
            self::BillingQuery          => 'Billing Query',
            self::EmergencyHelp         => 'Emergency Help',
            self::GeneralInquiry        => 'General Inquiry',
            self::FollowUp              => 'Follow Up',
            self::SpeakToHuman          => 'Speak to Human',
        };
    }

    /**
     * Whether this intent should trigger the booking flow.
     */
    public function isBookingRelated(): bool
    {
        return in_array($this, [
            self::BookAppointment,
            self::RescheduleAppointment,
            self::FollowUp,
        ]);
    }

    /**
     * Whether this intent requires immediate escalation.
     */
    public function requiresEscalation(): bool
    {
        return in_array($this, [
            self::SpeakToHuman,
            self::EmergencyHelp,
        ]);
    }
}
