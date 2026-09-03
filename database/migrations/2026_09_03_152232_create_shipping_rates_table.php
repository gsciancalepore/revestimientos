<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('cp', 4);
            $table->bigInteger('costo_cents');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('cp');
        });

        DB::statement('ALTER TABLE shipping_rates ADD CONSTRAINT shipping_rates_costo_cents_check CHECK (costo_cents >= 0)');
        DB::statement('CREATE UNIQUE INDEX shipping_rates_cp_activo_partial_unique ON shipping_rates (cp) WHERE activo = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
