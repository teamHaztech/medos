<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add ABHA fields to patients
        Schema::table('patients', function (Blueprint $table) {
            $table->string('abha_number', 14)->nullable()->unique()->after('phone_verified');
            $table->string('abha_address')->nullable()->after('abha_number'); // user@abdm format
            $table->boolean('abha_verified')->default(false)->after('abha_address');
            $table->json('abha_profile')->nullable()->after('abha_verified'); // cached ABDM profile
        });

        // ABHA Consent Management
        Schema::create('abha_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('abha_number', 14);
            $table->string('granted_to_type'); // doctor, hospital, lab, insurance
            $table->uuid('granted_to_id')->nullable(); // staff_id or hospital_id
            $table->string('granted_to_name');
            $table->string('purpose'); // consultation, referral, insurance, lab_report, prescription
            $table->string('data_scope'); // encounter, prescription, lab_results, full_history, billing
            $table->string('status')->default('requested'); // requested, granted, denied, revoked, expired
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['abha_number', 'status']);
            $table->index(['granted_to_id', 'status']);
            $table->index('hospital_id');
        });

        // ABHA Health Records — ABDM-compatible linked records
        Schema::create('abha_health_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('abha_number', 14);
            $table->string('record_type'); // encounter, prescription, lab_result, discharge_summary, diagnostic_report, immunization
            $table->uuid('source_id'); // encounter_id, order_id, bill_id etc.
            $table->string('source_type'); // App\Modules\Patient\Models\Encounter etc.
            $table->json('fhir_data')->nullable(); // FHIR-compatible structured data
            $table->string('status')->default('created'); // created, linked, pushed, verified
            $table->string('abdm_record_id')->nullable(); // ID from ABDM after push
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'record_type']);
            $table->index(['abha_number', 'record_type']);
            $table->index('hospital_id');
        });

        // ABHA Audit Log — every ABHA action logged
        Schema::create('abha_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('abha_number', 14)->nullable();
            $table->uuid('patient_id')->nullable();
            $table->uuid('performed_by')->nullable(); // user_id
            $table->string('action'); // create, link, verify, fetch_profile, push_record, consent_grant, consent_revoke, record_access
            $table->string('status'); // success, failed, pending
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index(['abha_number', 'action']);
            $table->index(['patient_id', 'action']);
            $table->index('hospital_id');
        });

        // Add ABHA linking to encounters
        Schema::table('encounters', function (Blueprint $table) {
            $table->string('abha_number', 14)->nullable()->after('doctor_id');
            $table->boolean('abha_linked')->default(false)->after('abha_number');
        });

        // Add ABHA to referrals
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('abha_number', 14)->nullable()->after('patient_id');
        });

        // Add ABHA to bills
        Schema::table('bills', function (Blueprint $table) {
            $table->string('abha_number', 14)->nullable()->after('patient_id');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) { $table->dropColumn('abha_number'); });
        Schema::table('referrals', function (Blueprint $table) { $table->dropColumn('abha_number'); });
        Schema::table('encounters', function (Blueprint $table) { $table->dropColumn(['abha_number', 'abha_linked']); });
        Schema::dropIfExists('abha_audit_logs');
        Schema::dropIfExists('abha_health_records');
        Schema::dropIfExists('abha_consents');
        Schema::table('patients', function (Blueprint $table) { $table->dropColumn(['abha_number', 'abha_address', 'abha_verified', 'abha_profile']); });
    }
};
