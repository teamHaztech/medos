<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Service / charge master — the hospital's billable rate card.
        if (! Schema::hasTable('service_charges')) {
            Schema::create('service_charges', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('category')->default('other');
                $table->decimal('price', 12, 2)->default(0);
                $table->boolean('is_taxable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['hospital_id', 'category']);
                $table->index(['hospital_id', 'is_active']);
            });
        }

        // Patient advance / deposit ledger (signed: +collect, -adjust/-refund).
        if (! Schema::hasTable('patient_deposits')) {
            Schema::create('patient_deposits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->decimal('amount', 12, 2); // signed
                $table->string('type')->default('collect'); // collect | adjust | refund
                $table->string('method')->nullable();
                $table->string('reference')->nullable(); // e.g. bill id when adjusted
                $table->string('received_by_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['hospital_id', 'patient_id']);
            });
        }

        // Extra bill columns for discounts, tax, deposits, cancellation, standalone bills.
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                if (! Schema::hasColumn('bills', 'discount_reason')) {
                    $table->string('discount_reason')->nullable()->after('discount_amount');
                }
                if (! Schema::hasColumn('bills', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_amount');
                }
                if (! Schema::hasColumn('bills', 'deposit_applied')) {
                    $table->decimal('deposit_applied', 12, 2)->default(0)->after('insurance_covered');
                }
                if (! Schema::hasColumn('bills', 'bill_type')) {
                    $table->string('bill_type')->default('encounter')->after('bill_number');
                }
                if (! Schema::hasColumn('bills', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('bills', 'cancel_reason')) {
                    $table->string('cancel_reason')->nullable();
                }
            });
        }

        $this->seedSample();
    }

    private function seedSample(): void
    {
        if (! Schema::hasTable('service_charges') || DB::table('service_charges')->count() > 0) {
            return;
        }
        $hospital = DB::table('hospitals')->where('is_active', true)->first();
        if (! $hospital) {
            return;
        }
        $samples = [
            ['General Consultation', 'CONS-GEN', 'consultation', 300, false],
            ['Specialist Consultation', 'CONS-SPL', 'consultation', 600, false],
            ['General Ward — per day', 'ROOM-GEN', 'room', 1500, false],
            ['Private Room — per day', 'ROOM-PVT', 'room', 4000, false],
            ['ICU — per day', 'ROOM-ICU', 'room', 8000, false],
            ['Nursing Charges — per day', 'NURS-DAY', 'nursing', 500, false],
            ['Dressing (minor)', 'PROC-DRS', 'procedure', 250, true],
            ['Suturing', 'PROC-SUT', 'procedure', 800, true],
            ['Nebulization', 'PROC-NEB', 'procedure', 200, true],
            ['IV Cannula & Set', 'CONS-IV', 'consumable', 150, true],
            ['Oxygen — per hour', 'CONS-O2', 'consumable', 100, true],
            ['Registration Fee', 'MISC-REG', 'other', 50, false],
        ];
        $now = now();
        foreach ($samples as [$name, $code, $cat, $price, $tax]) {
            DB::table('service_charges')->insert([
                'id'          => Str::uuid()->toString(),
                'hospital_id' => $hospital->id,
                'name'        => $name,
                'code'        => $code,
                'category'    => $cat,
                'price'       => $price,
                'is_taxable'  => $tax,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_deposits');
        Schema::dropIfExists('service_charges');
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                foreach (['discount_reason', 'tax_rate', 'deposit_applied', 'bill_type', 'cancelled_at', 'cancel_reason'] as $c) {
                    if (Schema::hasColumn('bills', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
