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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('shipping_cp', 4);
            $table->string('shipping_address')->nullable();
            $table->bigInteger('shipping_cost_cents')->default(0);
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('total_cents');
            $table->string('payment_method')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_email');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_shipping_cost_cents_check CHECK (shipping_cost_cents >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_cents_check CHECK (subtotal_cents >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_cents_check CHECK (total_cents >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
