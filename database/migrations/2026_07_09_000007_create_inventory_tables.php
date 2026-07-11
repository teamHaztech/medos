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
        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('name');
                $table->string('code', 40)->nullable();
                $table->string('category', 30)->default('consumable'); // consumable|drug|surgical|linen|stationery|ppe|other
                $table->string('unit', 20)->default('piece');
                $table->integer('reorder_min')->default(0);
                $table->integer('reorder_max')->default(0);
                $table->integer('current_stock')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('item_id')->index();
                $table->string('type', 20);              // receipt|issue|adjustment
                $table->integer('quantity');             // signed delta (+in / -out)
                $table->string('batch_number', 60)->nullable();
                $table->date('expiry_date')->nullable()->index();
                $table->string('department')->nullable();
                $table->string('reference')->nullable();
                $table->string('performed_by_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if ($hospitalId && DB::table('inventory_items')->where('hospital_id', $hospitalId)->count() === 0) {
            $now = now();
            $seed = [
                ['Examination Gloves', 'GLOV', 'ppe', 'box', 20, 200, 80],
                ['Disposable Syringes 5ml', 'SYR5', 'consumable', 'box', 15, 150, 12],
                ['IV Cannula 20G', 'IVC20', 'surgical', 'piece', 50, 500, 220],
                ['Sterile Gauze', 'GAUZE', 'surgical', 'pack', 30, 300, 45],
                ['Face Masks', 'MASK', 'ppe', 'box', 25, 250, 18],
                ['Cotton Roll', 'COTT', 'consumable', 'roll', 10, 100, 60],
            ];
            foreach ($seed as [$name, $code, $cat, $unit, $min, $max, $stock]) {
                DB::table('inventory_items')->insert([
                    'id'            => (string) Str::uuid(),
                    'hospital_id'   => $hospitalId,
                    'name'          => $name,
                    'code'          => $code,
                    'category'      => $cat,
                    'unit'          => $unit,
                    'reorder_min'   => $min,
                    'reorder_max'   => $max,
                    'current_stock' => $stock,
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
    }
};
