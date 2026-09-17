<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // HIG-19: la regla 116 fija `max:500` y la columna era `varchar(255)`.
        // Se repiten todos los atributos (nullable) para no perderlos.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_address', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_address', 255)->nullable()->change();
        });
    }
};
