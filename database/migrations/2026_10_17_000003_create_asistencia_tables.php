<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Horarios de trabajo (un tramo por día de la semana)
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            // {"1":{"entrada":"08:00","salida":"17:00"}, ... "7":...}  1 = lunes, 7 = domingo
            $table->json('dias');
            $table->unsignedSmallInteger('tolerancia_minutos')->default(10);
            $table->unsignedSmallInteger('minimo_extra_minutos')->default(30);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->foreignId('horario_id')->nullable()->after('horas_semanales')
                ->constrained('horarios')->nullOnDelete();
        });

        // Feriados y días no laborables de la empresa
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('descripcion');
            $table->string('tipo', 20)->default('inamovible');
            $table->timestamps();

            $table->unique(['empresa_id', 'fecha']);
        });

        // Registros de entrada / salida
        Schema::create('fichadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->dateTime('fecha_hora');
            $table->string('tipo', 10);                       // entrada | salida
            $table->string('origen', 15)->default('manual');  // manual | importacion | reloj | web
            $table->string('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('editado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha_hora']);
            $table->index(['empresa_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichadas');
        Schema::dropIfExists('feriados');

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('horario_id');
        });

        Schema::dropIfExists('horarios');
    }
};
