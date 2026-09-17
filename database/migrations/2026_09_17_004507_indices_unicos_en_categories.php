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
        // HIG-23: las reglas 49 y 50 exigen unicidad y hasta acá solo existía en
        // la capa HTTP. Si el entorno ya trae duplicados (en staging las
        // migraciones son un step manual), se falla legible nombrándolos en vez
        // del error crudo de Postgres.
        $slugs = $this->duplicados('slug');
        $nombres = $this->duplicados('name');

        if ($slugs !== [] || $nombres !== []) {
            throw new RuntimeException(
                'No se pueden crear los índices únicos de categories por duplicados preexistentes'
                .' — slugs: ['.implode(', ', $slugs).']'
                .' — nombres: ['.implode(', ', $nombres).']'
            );
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);
        });
    }

    /**
     * @return list<string>
     */
    private function duplicados(string $columna): array
    {
        /** @var list<string> $valores */
        $valores = DB::table('categories')
            ->select($columna)
            ->groupBy($columna)
            ->havingRaw('count(*) > 1')
            ->pluck($columna)
            ->all();

        return $valores;
    }
};
