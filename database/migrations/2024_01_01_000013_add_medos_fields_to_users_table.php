<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('hospital_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('hospitals')
                ->nullOnDelete();

            $table->string('role')
                ->default('receptionist')
                ->after('hospital_id');

            $table->uuid('staff_id')
                ->nullable()
                ->after('role');

            $table->string('phone')
                ->nullable()
                ->after('staff_id');

            $table->boolean('is_active')
                ->default(true)
                ->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropColumn([
                'hospital_id',
                'role',
                'staff_id',
                'phone',
                'is_active',
            ]);
        });
    }
};
