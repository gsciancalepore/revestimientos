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
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_codigo');
            $table->string('marca')->nullable();
            $table->string('unidad_venta');
            $table->decimal('m2_por_caja', 8, 2)->nullable();
            $table->unsignedInteger('cantidad');
            $table->bigInteger('precio_unitario_cents');
            $table->bigInteger('subtotal_cents');
            $table->jsonb('specs')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_precio_unitario_cents_check CHECK (precio_unitario_cents >= 0)');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_subtotal_cents_check CHECK (subtotal_cents >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
