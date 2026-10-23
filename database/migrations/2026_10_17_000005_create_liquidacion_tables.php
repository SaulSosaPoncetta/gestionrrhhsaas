<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conceptos de liquidación (básico, antigüedad, aportes...)
        Schema::create('conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedInteger('codigo');
            $table->string('nombre');
            $table->string('tipo', 20);                         // remunerativo | no_remunerativo | descuento
            $table->string('forma', 30);                        // cómo se calcula (ver Concepto::FORMAS)
            $table->decimal('valor', 14, 4)->nullable();        // % / monto / divisor según la forma
            $table->string('base', 20)->nullable();             // basico | remunerativo | bruto
            $table->decimal('tope', 14, 2)->nullable();         // tope de base imponible (aportes)
            $table->json('parametros')->nullable();             // condiciones extra (presentismo, solo convenio...)
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->nullOnDelete();
            $table->boolean('automatico')->default(true);       // se aplica solo / solo con novedad
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
        });

        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('tipo', 10)->default('mensual');     // mensual | sac
            $table->string('descripcion')->nullable();
            $table->date('fecha_pago')->nullable();
            $table->string('lugar_pago')->nullable();
            // Art. 140 LCT inc. h): último depósito de aportes
            $table->string('deposito_periodo', 7)->nullable();  // 2026-09
            $table->date('deposito_fecha')->nullable();
            $table->string('deposito_banco')->nullable();
            $table->string('estado', 10)->default('borrador');  // borrador | cerrada
            $table->timestamp('calculada_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('cantidad_recibos')->default(0);
            $table->decimal('total_remunerativo', 16, 2)->default(0);
            $table->decimal('total_no_remunerativo', 16, 2)->default(0);
            $table->decimal('total_descuentos', 16, 2)->default(0);
            $table->decimal('total_neto', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'anio', 'mes', 'tipo']);
        });

        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            // Foto de los datos del empleado al liquidar (el recibo no cambia si después se edita el legajo)
            $table->unsignedInteger('legajo');
            $table->string('apellido_nombre');
            $table->string('cuil', 13);
            $table->date('fecha_ingreso');
            $table->string('categoria')->nullable();
            $table->string('puesto')->nullable();
            $table->string('convenio')->nullable();
            $table->string('modalidad')->nullable();
            $table->string('banco')->nullable();
            $table->string('cbu', 22)->nullable();
            $table->decimal('basico', 14, 2)->default(0);
            $table->unsignedSmallInteger('dias_trabajados')->default(0);
            $table->decimal('total_remunerativo', 14, 2)->default(0);
            $table->decimal('total_no_remunerativo', 14, 2)->default(0);
            $table->decimal('total_descuentos', 14, 2)->default(0);
            $table->decimal('neto', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['liquidacion_id', 'empleado_id']);
        });

        Schema::create('recibo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recibo_id')->constrained('recibos')->cascadeOnDelete();
            $table->foreignId('concepto_id')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->unsignedInteger('codigo');
            $table->string('nombre');
            $table->string('tipo', 20);
            $table->decimal('cantidad', 12, 2)->nullable();
            $table->string('unidad', 10)->nullable();
            $table->decimal('importe', 14, 2);
            $table->unsignedSmallInteger('orden')->default(0);
        });

        // Novedades manuales del período (anticipos, premios, embargos, horas corregidas...)
        Schema::create('novedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained('conceptos')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 2)->nullable();
            $table->decimal('importe', 14, 2)->nullable();
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->unique(['liquidacion_id', 'empleado_id', 'concepto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades');
        Schema::dropIfExists('recibo_items');
        Schema::dropIfExists('recibos');
        Schema::dropIfExists('liquidaciones');
        Schema::dropIfExists('conceptos');
    }
};
