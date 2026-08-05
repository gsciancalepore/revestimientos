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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('marca')->nullable();
            $table->string('codigo')->unique();
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('precio_cents');
            $table->unsignedBigInteger('precio_oferta_cents')->nullable();
            $table->string('unidad_venta');
            $table->decimal('m2_por_caja', 8, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('activo')->default(true);
            $table->jsonb('imagenes')->nullable();
            $table->jsonb('specs')->nullable();
            $table->timestamps();

            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
