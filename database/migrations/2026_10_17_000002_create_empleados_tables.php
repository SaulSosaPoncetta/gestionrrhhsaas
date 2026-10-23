<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedInteger('legajo');

            // Datos personales
            $table->string('apellido');
            $table->string('nombre');
            $table->string('cuil', 13);
            $table->string('dni', 10);
            $table->date('fecha_nacimiento')->nullable();
            $table->char('sexo', 1)->nullable();               // M | F | X
            $table->string('estado_civil', 20)->nullable();
            $table->string('nacionalidad', 60)->nullable()->default('Argentina');
            $table->string('foto')->nullable();

            // Contacto
            $table->string('domicilio')->nullable();
            $table->string('localidad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('contacto_emergencia')->nullable();
            $table->string('telefono_emergencia', 50)->nullable();

            // Datos laborales
            $table->date('fecha_ingreso');
            $table->date('fecha_egreso')->nullable();
            $table->string('motivo_egreso', 30)->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('puesto_id')->nullable()->constrained('puestos')->nullOnDelete();
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->string('modalidad', 20)->default('indeterminado');
            $table->string('jornada', 20)->default('completa');
            $table->decimal('horas_semanales', 5, 2)->nullable();
            $table->decimal('sueldo_basico', 14, 2)->nullable();  // solo fuera de convenio

            // Pago y obra social
            $table->string('banco')->nullable();
            $table->string('cbu', 22)->nullable();
            $table->string('obra_social')->nullable();
            $table->string('codigo_obra_social', 10)->nullable();

            // Autogestión (paso 8)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'legajo']);
            $table->unique(['empresa_id', 'cuil']);
            $table->index(['empresa_id', 'apellido', 'nombre']);
        });

        Schema::create('empleado_familiares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('parentesco', 20);
            $table->string('apellido_nombre');
            $table->string('dni', 10)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->boolean('a_cargo')->default(true);
            $table->boolean('discapacidad')->default(false);
            $table->timestamps();
        });

        Schema::create('empleado_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('descripcion')->nullable();
            $table->string('archivo');                 // ruta en disco privado
            $table->string('nombre_original');
            $table->unsignedInteger('tamanio')->default(0);
            $table->date('vencimiento')->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_documentos');
        Schema::dropIfExists('empleado_familiares');
        Schema::dropIfExists('empleados');
    }
};
