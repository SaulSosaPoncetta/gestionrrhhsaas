<?php

/*
|--------------------------------------------------------------------------
| Landing de Gestión RRHH
|--------------------------------------------------------------------------
| - contacto_email: a dónde llegan las solicitudes de demo. Se define en el
|   .env con LANDING_CONTACTO_EMAIL; si falta, usa MAIL_FROM_ADDRESS.
| - planes: se ofrecen según la cantidad de empleados de la empresa.
|   Editá acá los rangos, textos y precios. 'precio' => null muestra
|   "Consultar"; un número muestra "$ X / mes". 'hasta' => null = sin tope.
*/

return [

    'contacto_email' => env('LANDING_CONTACTO_EMAIL', env('MAIL_FROM_ADDRESS')),

    'planes' => [
        [
            'nombre'      => 'Micro',
            'desde'       => 1,
            'hasta'       => 10,
            'descripcion' => 'Para microempresas y comercios que empiezan a ordenar su personal.',
            'precio'      => null,
            'destacado'   => false,
        ],
        [
            'nombre'      => 'Pyme',
            'desde'       => 11,
            'hasta'       => 50,
            'descripcion' => 'Para pequeñas y medianas empresas con varias áreas y puestos.',
            'precio'      => null,
            'destacado'   => true,
        ],
        [
            'nombre'      => 'Empresa',
            'desde'       => 51,
            'hasta'       => 200,
            'descripcion' => 'Para empresas con estructura, convenios y supervisores.',
            'precio'      => null,
            'destacado'   => false,
        ],
        [
            'nombre'      => 'Corporativo',
            'desde'       => 201,
            'hasta'       => null,
            'descripcion' => 'Para organizaciones grandes. Lo armamos a medida.',
            'precio'      => null,
            'destacado'   => false,
        ],
    ],
];
