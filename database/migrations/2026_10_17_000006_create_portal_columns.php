<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Obliga a cambiar la contraseña en el próximo ingreso (accesos creados o reseteados por RRHH)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('password');
        });

        // Si el empleado puede fichar desde el portal web
        Schema::table('empleados', function (Blueprint $table) {
            $table->boolean('permite_fichaje_web')->default(false)->after('user_id');
        });

        // Conformidad del empleado con el recibo
        Schema::table('recibos', function (Blueprint $table) {
            $table->timestamp('conformado_at')->nullable()->after('observaciones');
            $table->string('conformado_ip', 45)->nullable()->after('conformado_at');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn(['conformado_at', 'conformado_ip']);
        });
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('permite_fichaje_web');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_password');
        });
    }
};
