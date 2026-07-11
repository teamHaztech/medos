<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Payment ledger for bills — enables partial payments, payment history, and
 * accurate outstanding balances. Backfills existing single-payment bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bill_payments')) {
            Schema::create('bill_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('bill_id')->constrained('bills')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('method')->default('cash'); // cash, upi, card, insurance, bank, cheque
                $table->string('reference')->nullable();
                $table->uuid('received_by')->nullable();
                $table->string('received_by_name')->nullable();
                $table->dateTime('paid_at');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['hospital_id', 'paid_at']);
                $table->index('bill_id');
                $table->index('method');
            });
        }

        // Backfill: turn each already-paid bill into one ledger entry.
        if (Schema::hasColumn('bills', 'amount_paid') && ! DB::table('bill_payments')->exists()) {
            $bills = DB::table('bills')->where('amount_paid', '>', 0)->get();
            $now = now();
            foreach ($bills as $b) {
                DB::table('bill_payments')->insert([
                    'id'               => (string) Str::uuid(),
                    'hospital_id'      => $b->hospital_id,
                    'bill_id'          => $b->id,
                    'amount'           => $b->amount_paid,
                    'method'           => $b->payment_method ?? 'cash',
                    'reference'        => $b->payment_reference ?? null,
                    'received_by_name' => 'Migrated',
                    'paid_at'          => $b->paid_at ?? $b->updated_at ?? $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
