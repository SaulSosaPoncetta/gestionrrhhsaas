<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de licencia de cada empresa (vacaciones, enfermedad, matrimonio...)
        Schema::create('tipos_licencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo', 20)->nullable();
            $table->string('unidad', 10)->default('corridos');            // corridos | habiles
            $table->unsignedSmallInteger('dias_maximos')->nullable();     // por licencia
            $table->unsignedSmallInteger('maximo_anual')->nullable();     // por año calendario
            $table->boolean('con_goce')->default(true);
            $table->boolean('requiere_certificado')->default(false);
            $table->boolean('es_vacaciones')->default(false);
            $table->string('color', 20)->default('info');
            $table->string('descripcion')->nullable();                    // base legal / aclaración
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        Schema::create('licencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('tipo_licencia_id')->constrained('tipos_licencia')->restrictOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedSmallInteger('dias');
            $table->unsignedSmallInteger('periodo')->nullable();          // año al que corresponden las vacaciones
            $table->string('estado', 15)->default('pendiente');          // pendiente | aprobada | rechazada | cancelada
            $table->text('observaciones')->nullable();
            $table->string('certificado')->nullable();                    // ruta en disco privado
            $table->string('certificado_nombre')->nullable();
            $table->foreignId('solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_at')->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['empleado_id', 'fecha_desde', 'fecha_hasta']);
        });

        // Días de vacaciones que corresponden a cada empleado por año
        Schema::create('vacaciones_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedSmallInteger('dias_corresponden');
            $table->smallInteger('dias_ajuste')->default(0);              // + días extra / arrastre, - descuentos
            $table->boolean('manual')->default(false);                    // modificado a mano: no se recalcula
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones_periodos');
        Schema::dropIfExists('licencias');
        Schema::dropIfExists('tipos_licencia');
    }
};
