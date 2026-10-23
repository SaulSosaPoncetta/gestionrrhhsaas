<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Áreas / departamentos (jerárquicas para el organigrama)
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('area_padre_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('nombre');
            $table->string('codigo', 20)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        // Puestos de trabajo
        Schema::create('puestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->text('requisitos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'area_id', 'nombre']);
        });

        // Convenios colectivos de trabajo
        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');                    // Ej: Empleados de Comercio
            $table->string('numero', 30)->nullable();    // Ej: CCT 130/75
            $table->string('sindicato')->nullable();     // Ej: FAECYS
            $table->decimal('horas_semanales', 5, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        // Categorías de cada convenio con su básico
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->cascadeOnDelete();
            $table->string('nombre');                    // Ej: Administrativo A
            $table->decimal('sueldo_basico', 14, 2)->default(0);
            $table->date('vigencia_desde')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['convenio_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('convenios');
        Schema::dropIfExists('puestos');
        Schema::dropIfExists('areas');
    }
};
