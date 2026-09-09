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
            'v'      => '1.23.1',
            'fecha'  => '2026-09-09',
            'icono'  => 'check',
            'titulo' => 'Retoques en el celular',
            'que'    => 'Detalles de la barra de abajo y de las pantallas de ajustes.',
            'hacer'  => [
                'El botón de escribir ya aparece en todas las pantallas, no sólo en la bandeja.',
                'La barra se aparta al bajar por la lista y vuelve al subir, para dejarte más pantalla.',
                'Al pulsar Actualizar gira sólo el icono, no la palabra.',
                'Se quitó "Atajos de teclado" del celular, donde no hay teclado.',
            ],
        ],
        [
            'v'      => '1.23.0',
            'fecha'  => '2026-09-09',
            'icono'  => 'refrescar',
            'titulo' => 'Barra del celular: Menú y Actualizar',
            'que'    => 'Los dos botones de abajo ahora sirven para algo estando dentro del correo.',
            'hacer'  => [
                'A la izquierda, "Menú": abre tus carpetas sin tener que buscar el botón de arriba.',
                'A la derecha, "Actualizar": trae el correo nuevo al momento, sin esperar al minuto.',
                'En el centro sigue el botón azul para escribir.',
                'El enlace al sitio pasó al menú de carpetas, arriba, junto a la X.',
            ],
        ],
        [
            'v'      => '1.22.0',
            'fecha'  => '2026-09-09',
            'icono'  => 'mas',
            'titulo' => 'Barra de abajo nueva en el celular',
            'que'    => 'El menú del celular pasa a ser una barra flotante con el botón de escribir en el centro.',
            'hacer'  => [
                'El botón azul del medio abre un correo nuevo: es el que más se usa y ahora se llega con el pulgar.',
                'En el computador no cambia nada.',
            ],
        ],
        [
            'v'      => '1.21.0',
            'fecha'  => '2026-09-09',
            'icono'  => 'clip',
            'titulo' => 'Los adjuntos que llegan, y los correos legibles',
            'que'    => 'Ahora se ven y se descargan los archivos que te mandan, y el texto de los correos se lee.',
            'hacer'  => [
                'Al final del mensaje aparece "Adjuntos" con cada archivo: pulsa uno y se descarga.',
                'El correo se muestra sobre fondo blanco, como lo escribió quien te lo manda. Antes sus colores oscuros quedaban invisibles sobre el fondo negro.',
                'Las tablas anchas ya no descuadran la pantalla en el celular: se desplazan dentro del mensaje.',
                'Al abrir un correo recién llegado ya no se queda el anterior en pantalla.',
            ],
        ],
        [
            'v'      => '1.20.0',
            'fecha'  => '2026-09-07',
            'icono'  => 'llave',
            'titulo' => 'La sesión ya no se cierra sola',
            'que'    => 'Entras una vez y sigues dentro 30 días, aunque cierres el navegador o apagues el computador. Como en Gmail.',
            'hacer'  => [
                'Cada vez que entras, la cuenta de los 30 días vuelve a empezar: si lo usas a diario, no verás nunca la pantalla de acceso.',
                'Antes te echaba a los 24 minutos sin tocar nada, y al cerrar el navegador siempre.',
            ],
            'ojo'    => 'Por lo mismo, si usas un computador prestado, cierra sesión al terminar: ahora quedaría abierta.',
        ],
        [
            'v'      => '1.19.0',
            'fecha'  => '2026-09-07',
            'icono'  => 'refrescar',
            'titulo' => 'El correo nuevo llega solo',
            'que'    => 'Ya no hace falta recargar la página con F5: la bandeja se actualiza sola cada minuto.',
            'hacer'  => [
                'Cuando llega algo, aparece arriba de la lista y te avisa con un mensajito.',
                'El botón de refrescar, arriba de la lista, ahora refresca de verdad; antes sólo giraba.',
                'Si tienes la pestaña en otra ventana no se consulta nada, para no gastar datos; al volver a ella mira enseguida.',
            ],
            'ojo'    => 'Los acentos de los asuntos también se arreglaron: "Notificación" ya no sale como "Notificaci??n".',
        ],
        [
            'v'      => '1.18.1',
            'fecha'  => '2026-09-07',
            'icono'  => 'atras',
            'titulo' => 'Devolver un correo desde la papelera, y un menú más corto',
            'que'    => 'Sacar un correo de la papelera ya estaba, pero escondido dentro de "Mover a". Ahora se ve.',
            'hacer'  => [
                'En la Papelera, el botón derecho ofrece "Restaurar a Recibidos". Lo mismo en Spam y en Archivados.',
                'El menú se quedó sólo con lo que hace algo: "Copiar a" y "Silenciar" no funcionaban, sólo avisaban de que no estaban disponibles.',
                'Ya no se repiten las opciones: si arriba tienes "Archivar", "Archivados" no vuelve a salir en "Mover a", y no se ofrece la carpeta donde ya estás.',
            ],
        ],
        [
            'v'      => '1.18.0',
            'fecha'  => '2026-09-07',
            'icono'  => 'check',
            'titulo' => 'El botón de eliminar de la barra ya elimina de verdad',
            'que'    => 'Cuando marcabas correos con la casilla y pulsabas la papelera de la barra, desaparecían de la pantalla pero seguían en el servidor: al recargar volvían todos.',
            'hacer'  => [
                'Ese botón no llegaba a mandar nada al servidor. Ahora sí, y el aviso espera a que el servidor confirme.',
                'Si estás en la Papelera, ese mismo botón borra para siempre y te pregunta antes.',
                'Eliminar con el botón derecho siempre funcionó: por eso a unos les pasaba y a otros no.',
            ],
        ],
        [
            'v'      => '1.17.2',
            'fecha'  => '2026-09-07',
            'icono'  => 'carpeta',
            'titulo' => 'Casillas con carpetas repetidas',
            'que'    => 'Si tu casilla tiene dos carpetas que sirven para lo mismo —por ejemplo Junk y spam—, ahora se distinguen bien.',
            'hacer'  => [
                'Antes las dos compartían identidad y una orden podía acabar en la carpeta equivocada. Ahora una se queda con el papel y la otra aparece como carpeta aparte, con su nombre.',
            ],
        ],
        [
            'v'      => '1.17.1',
            'fecha'  => '2026-09-07',
            'icono'  => 'papelera',
            'titulo' => 'El correo eliminado ya no vuelve al recargar',
            'que'    => 'Si tu casilla no tenía carpeta de papelera, o la tenía con otro nombre, el correo desaparecía de la pantalla pero seguía ahí. Ya no.',
            'hacer'  => [
                'Si falta la papelera, la aplicación la crea sola la primera vez que eliminas algo.',
                'Se reconocen también las carpetas creadas por otros programas: "Elementos eliminados", "Borrados", "Deleted Items" y demás.',
            ],
        ],
        [
            'v'      => '1.17.0',
            'fecha'  => '2026-09-07',
            'icono'  => 'check',
            'titulo' => 'Eliminar ya no puede mentirte',
            'que'    => 'Si el servidor no llega a eliminar un correo, ahora te lo dice y el correo vuelve a la lista, en vez de desaparecer y reaparecer al recargar.',
            'hacer'  => [
                'Antes la aplicación decía "Mensaje eliminado" en cuanto mandaba la orden, sin esperar respuesta. Ahora comprueba que el correo se fue de verdad.',
                'Si algo falla, verás el motivo exacto que dio tu servidor.',
                'En diagnostico.php se puede ver qué sabe hacer tu servidor y si quedaron correos marcados a medias.',
            ],
        ],
        [
            'v'      => '1.16.2',
            'fecha'  => '2026-09-07',
            'icono'  => 'llave',
            'titulo' => 'Al actualizar no se toca nada tuyo',
            'que'    => 'Tus contactos, tu firma, tu logo y tus casillas guardadas quedan fuera de la actualización y fuera de la copia de seguridad.',
            'hacer'  => [
                'La copia que se guarda antes de actualizar contiene sólo el programa. Antes copiaba también la llave que descifra tus contraseñas, y eso no hacía falta.',
            ],
        ],
        [
            'v'      => '1.16.1',
            'fecha'  => '2026-09-04',
            'icono'  => 'spam',
            'titulo' => 'Menos correos tuyos en la carpeta de spam',
            'que'    => 'Pequeños ajustes para que tus correos lleguen a la bandeja de quien los recibe.',
            'hacer'  => [
                'Al adjuntar archivos como .php, .zip o .exe, ahora se avisa: son los que más rechazan Gmail y compañía. Si el correo es importante, mejor mandar un enlace de descarga.',
                'En diagnostico.php hay una comprobación nueva de SPF, DKIM y DMARC, los tres registros que miran los filtros para decidir si tu dominio es de fiar.',
            ],
        ],
        [
            'v'      => '1.16.0',
            'fecha'  => '2026-09-04',
            'icono'  => 'carpeta_mas',
            'titulo' => 'Crear tus propias carpetas',
            'que'    => 'Ya puedes crear, renombrar y borrar carpetas. Antes eran de adorno: aparecían en el menú pero no guardaban nada.',
            'hacer'  => [
                'Pulsa "Agregar carpeta" en el menú, o el + junto a "Carpetas", y escribe el nombre.',
                'Para cambiarle el nombre o borrarla, pasa el cursor por encima: aparecen el lápiz y la papelera.',
                'Las carpetas se crean en tu casilla, así que las verás también en el celular y en cualquier otro programa de correo.',
                'Para guardar un correo dentro, botón derecho sobre él → Mover a → tu carpeta.',
            ],
            'ojo'    => 'Al borrar una carpeta se borran también los correos que tenga dentro.',
        ],
        [
            'v'      => '1.15.2',
            'fecha'  => '2026-09-04',
            'icono'  => 'spam',
            'titulo' => 'Si tu casilla no responde, ya no aparecen correos de ejemplo',
            'que'    => 'Cuando el servidor de correo no contestaba, la bandeja se llenaba con mensajes de muestra que venían con el programa. Parecían correos reales de gente desconocida.',
            'hacer'  => [
                'Si ves un aviso rojo diciendo que no se pudo abrir tu casilla, es que el servidor no respondió: pulsa Reintentar en un momento.',
                'Ningún correo se pierde ni se cruza: los mensajes viven en el servidor y aquí sólo se leen.',
            ],
        ],
        [
            'v'      => '1.15.1',
            'fecha'  => '2026-09-04',
            'icono'  => 'enviar',
            'titulo' => 'Tus correos salen a tu nombre',
            'que'    => 'El remitente ya no es el de la instalación: es la casilla con la que entraste.',
            'hacer'  => [
                'Si entras con tu dirección, los correos salen desde tu dirección, no desde otra.',
                'Para que aparezca tu nombre completo y no sólo el de tu correo, ponlo en Tu cuenta → Cómo te ven.',
            ],
        ],
        [
            'v'      => '1.15.0',
            'fecha'  => '2026-09-04',
            'icono'  => 'refrescar',
            'titulo' => 'Barra de carga al enviar con archivos',
            'que'    => 'Cuando adjuntas algo, ves cuánto lleva subido y el correo no se puede enviar dos veces.',
            'hacer'  => [
                'Al pulsar Enviar aparece una barra con el porcentaje. Mientras sube, el formulario queda bloqueado: no se puede cambiar nada ni cerrarlo por error.',
                'Si la conexión falla a medio camino, te avisa y el mensaje se queda como estaba, para volver a intentarlo.',
            ],
        ],
        [
            'v'      => '1.14.2',
            'fecha'  => '2026-09-04',
            'icono'  => 'check',
            'titulo' => 'Retoques',
            'que'    => 'Detalles pequeños que se veían mal.',
            'hacer'  => [
                'Las iniciales de los círculos de color ahora crecen con el círculo; antes quedaban diminutas en los grandes.',
                'El aviso de aquí arriba dice ahora que tu correo se actualizó, no sólo que hay cosas nuevas.',
            ],
        ],
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

/** La versión más reciente del catálogo. */
function mj_novedades_ultima(): string
{
    $n = mj_novedades();
    return (string) ($n[0]['v'] ?? '');
}

/**
 * La versión instalada. Es la que manda para avisar: si el aviso dependiera
 * del catálogo, una actualización sin entrada nueva pasaría en silencio, que
 * es justo lo que no se quiere.
 */
function mj_novedades_version(): string
{
    return function_exists('mj_version') ? mj_version() : mj_novedades_ultima();
}

/** ¿Se ha actualizado desde la última vez que miró? */
function mj_novedades_hay_update(string $correo): bool
{
    if ($correo === '') { return false; }
    $visto = mj_novedades_visto($correo);
    return $visto !== '' && version_compare(mj_novedades_version(), $visto, '>');
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
    // Se anota la versión instalada, no la del catálogo
    @file_put_contents(mj_novedades_archivo($correo), mj_novedades_version());
}
