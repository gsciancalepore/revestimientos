<?php

use App\Models\Product;
use App\Services\ProductSlugGenerator;
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
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $generator = app(ProductSlugGenerator::class);

        Product::query()->orderBy('id')->chunkById(200, function ($products) use ($generator): void {
            foreach ($products as $product) {
                $product->forceFill(['slug' => $generator->uniqueFor($product->name)])->save();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_slug_unique');
            $table->dropColumn('slug');
        });
    }
};
