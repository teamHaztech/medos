<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('patient_payable');
            $table->decimal('balance_due', 12, 2)->default(0)->after('amount_paid');
            $table->string('currency')->nullable()->after('payment_reference');
            $table->text('notes')->nullable()->after('currency');
            $table->datetime('issued_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'balance_due', 'currency', 'notes', 'issued_at']);
        });
    }
};
