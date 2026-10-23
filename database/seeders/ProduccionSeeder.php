<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos mínimos para producción: solo el superadmin de la plataforma.
 * Email y contraseña salen del .env (ADMIN_EMAIL / ADMIN_PASSWORD).
 * Si no hay contraseña en el .env, genera una al azar y la muestra en pantalla.
 * Al primer ingreso el sistema obliga a cambiarla.
 *
 *   php artisan db:seed --class=ProduccionSeeder --force
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@migestion.com.ar');
        $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);

        if (User::where('email', $email)->exists()) {
            $this->command?->warn("El superadmin {$email} ya existe: no se modificó.");
            return;
        }

        User::create([
            'name'                  => 'Superadmin',
            'email'                 => $email,
            'password'              => $password,
            'rol'                   => User::ROL_SUPERADMIN,
            'activo'                => true,
            'debe_cambiar_password' => true,
        ]);

        $this->command?->info("Superadmin creado: {$email}");
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->info("Contraseña provisoria: {$password}");
        }
        $this->command?->line('Al ingresar por primera vez el sistema pide cambiarla.');
    }
}
