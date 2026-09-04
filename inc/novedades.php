<?php
/* ============================================================
   BACANO.MAIL · Novedades
   ------------------------------------------------------------
   Lo que va cambiando, contado para quien usa el correo. No es
   el CHANGELOG: ese está escrito para quien toca el código y
   habla de MIME, de UID y de cosas que aquí no importan.

   Para agregar una novedad, ponla arriba del todo. 'hacer' son
   los pasos concretos; si no hay nada que hacer, se deja fuera.
   ============================================================ */

declare(strict_types=1);

function mj_novedades(): array
{
    return [
        [
            'v'      => '1.14.0',
            'fecha'  => '2026-09-04',
            'icono'  => 'estrella',
            'titulo' => 'Esta misma sección: Novedades',
            'que'    => 'Cada vez que se actualice tu correo, aquí queda contado qué cambió y cómo usarlo.',
            'hacer'  => [
                'Cuando haya algo nuevo, verás un número junto a "Novedades" en el menú y un aviso sobre la bandeja.',
                'Los dos se apagan solos en cuanto entras a mirar; no hay que cerrar nada.',
                'Lo que ya viste no vuelve a marcarse, y cada casilla lleva su propia cuenta.',
            ],
        ],
        [
            'v'      => '1.13.0',
            'fecha'  => '2026-09-04',
            'icono'  => 'clip',
            'titulo' => 'Adjuntar archivos y poner tu logo en la firma',
            'que'    => 'El clip del compositor ya funciona, y tu firma puede llevar tu logo.',
            'hacer'  => [
                'Al escribir un correo, pulsa el clip y elige los archivos. Aparecen en una lista, con su peso, y cada uno tiene una × para sacarlo antes de enviar.',
                'Para el logo: Tu cuenta → Logo de la firma → Subir imagen. Admite PNG, JPG o GIF.',
                'El logo viaja dentro del correo, así que se ve aunque quien lo reciba tenga bloqueadas las imágenes de internet.',
            ],
            'ojo'    => 'El tamaño máximo lo pone el servidor. Si un archivo no entra, el aviso te dice el límite exacto.',
        ],
        [
            'v'      => '1.12.0',
            'fecha'  => '2026-09-01',
            'icono'  => 'ajustes',
            'titulo' => 'Los ajustes, dentro del correo',
            'que'    => '"Tu cuenta" ya no te saca a otra página: se abre en el mismo sitio donde está la bandeja.',
            'hacer'  => [
                'Pulsa el engranaje al pie del menú, junto a tu nombre.',
                'Ahí están tu nombre, tu firma, tu contraseña y tus casillas, todo junto.',
            ],
        ],
        [
            'v'      => '1.11.0',
            'fecha'  => '2026-09-01',
            'icono'  => 'llave',
            'titulo' => 'Cambiar tu contraseña, y usar dos casillas',
            'que'    => 'Puedes cambiar la contraseña del correo sin entrar al hosting, y tener dos casillas abiertas para saltar de una a otra.',
            'hacer'  => [
                'Tu cuenta → Contraseña. Te pide la de ahora, la nueva y su repetición.',
                'Para la segunda casilla: Tu cuenta → Agregar otra casilla. Después cambias entre ellas con un clic.',
            ],
            'ojo'    => 'Al cambiar la contraseña, acuérdate de actualizarla también en el celular y en cualquier otro programa que abra la casilla.',
        ],
        [
            'v'      => '1.10.0',
            'fecha'  => '2026-09-01',
            'icono'  => 'personas',
            'titulo' => 'Contactos',
            'que'    => 'Una agenda que se llena sola: cada vez que envías un correo, quien lo recibe queda anotado.',
            'hacer'  => [
                'Está en el menú, entre Enviados y Borradores.',
                'Puedes agregar contactos a mano, con teléfono y una nota (una causa, la empresa, lo que te sirva).',
                'Al escribir un destinatario, la dirección se completa sola con lo que hay en la agenda.',
            ],
        ],
        [
            'v'      => '1.8.0',
            'fecha'  => '2026-09-01',
            'icono'  => 'papelera',
            'titulo' => 'La papelera funciona como debe',
            'que'    => 'Al eliminar, el correo se va de Recibidos de verdad; antes reaparecía al recargar.',
            'hacer'  => [
                'Se elimina la conversación entera, no sólo el último mensaje.',
                'Dentro de la Papelera, el botón derecho ofrece "Eliminar permanentemente", con una confirmación antes de borrar sin vuelta atrás.',
            ],
        ],
        [
            'v'      => '1.7.0',
            'fecha'  => '2026-09-01',
            'icono'  => 'sobre_abrir',
            'titulo' => 'Conversaciones y correos leídos',
            'que'    => 'Los correos de un mismo asunto se agrupan en una conversación, como en Gmail, y el contador de no leídos baja al abrirlos.',
            'hacer'  => [
                'Una fila por conversación, con el número de mensajes que lleva.',
                'Al abrir una, se marcan como leídos todos sus mensajes, y queda así también en el celular.',
            ],
        ],
        [
            'v'      => '1.6.0',
            'fecha'  => '2026-09-01',
            'icono'  => 'usuario',
            'titulo' => 'Entrar y quedarse dentro',
            'que'    => 'Pantalla de acceso nueva, y "Mantener la sesión abierta" que de verdad mantiene la sesión.',
            'hacer'  => [
                'Marca la casilla al entrar y no tendrás que escribir la contraseña cada vez.',
                'No la actives en un computador compartido.',
            ],
        ],
    ];
}

/** La versión más reciente de la lista. */
function mj_novedades_ultima(): string
{
    $n = mj_novedades();
    return (string) ($n[0]['v'] ?? '');
}

function mj_novedades_archivo(string $correo): string
{
    return __DIR__ . '/../data/cuentas/' . sha1(strtolower(trim($correo))) . '-visto.txt';
}

/** Hasta qué versión ha mirado esta casilla. */
function mj_novedades_visto(string $correo): string
{
    $a = mj_novedades_archivo($correo);
    return is_readable($a) ? trim((string) file_get_contents($a)) : '';
}

/** Cuántas novedades no ha visto todavía. */
function mj_novedades_sin_ver(string $correo): int
{
    if ($correo === '') { return 0; }

    $visto = mj_novedades_visto($correo);
    if ($visto === '') { return count(mj_novedades()); }

    $n = 0;
    foreach (mj_novedades() as $x) {
        if (version_compare($x['v'], $visto, '>')) { $n++; }
    }
    return $n;
}

/** ¿Es nueva para esta casilla? */
function mj_novedad_nueva(array $x, string $visto): bool
{
    return $visto === '' || version_compare($x['v'], $visto, '>');
}

/** Deja constancia de que ya las miró. */
function mj_novedades_marcar(string $correo): void
{
    if ($correo === '') { return; }

    $carpeta = dirname(mj_novedades_archivo($correo));
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0750, true)) { return; }
    if (!is_file($carpeta . '/.htaccess')) {
        @file_put_contents($carpeta . '/.htaccess', "Require all denied\n");
    }
    @file_put_contents(mj_novedades_archivo($correo), mj_novedades_ultima());
}
