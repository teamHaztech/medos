<?php

namespace App\Modules\Core\Enums;

enum UserRole: string
{
    case SuperAdmin    = 'super_admin';
    case HospitalAdmin = 'hospital_admin';
    case Doctor        = 'doctor';
    case Nurse         = 'nurse';
    case Receptionist  = 'receptionist';
    case Pharmacist    = 'pharmacist';
    case LabTech       = 'lab_tech';
    case BillingStaff  = 'billing_staff';

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin    => 'Super Admin',
            self::HospitalAdmin => 'Hospital Admin',
            self::Doctor        => 'Doctor',
            self::Nurse         => 'Nurse',
            self::Receptionist  => 'Receptionist',
            self::Pharmacist    => 'Pharmacist',
            self::LabTech       => 'Lab Technician',
            self::BillingStaff  => 'Billing Staff',
        };
    }

    /**
     * Determine if the role is a clinical role.
     */
    public function isClinical(): bool
    {
        return in_array($this, [
            self::Doctor,
            self::Nurse,
            self::LabTech,
            self::Pharmacist,
        ]);
    }
}
