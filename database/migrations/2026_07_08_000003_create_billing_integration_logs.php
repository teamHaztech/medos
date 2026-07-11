<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_integration_logs')) {
            return;
        }

        Schema::create('billing_integration_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id')->index();
            $table->uuid('bill_id')->index();
            $table->string('connector', 60);
            $table->string('event', 40);
            $table->string('status', 20);            // success | failed | skipped
            $table->integer('http_status')->nullable();
            $table->text('response')->nullable();
            $table->integer('attempts')->default(1);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_integration_logs');
    }
};
