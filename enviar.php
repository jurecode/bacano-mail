<?php
/* ============================================================
   BACANO.MAIL · Enviar un mensaje
   ------------------------------------------------------------
   Sólo responde a quien ya entró a la bandeja y trae el token
   de la sesión: así el formulario no se convierte en un relay
   abierto para spam.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';   // antes del config: la sesión manda
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/smtp.php';
require_once __DIR__ . '/inc/cuenta.php';
require_once __DIR__ . '/inc/contactos.php';
require_once __DIR__ . '/inc/mime.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = mj_config();

$responder = static function (bool $ok, string $mensaje, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
};

/* --- puerta --- */
if (!empty($cfg['acceso']['proteger']) && !mj_dentro()) {
    $responder(false, 'Tu sesión se cerró. Vuelve a entrar a la bandeja.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $responder(false, 'Método no permitido.', 405);
}
if (!mj_token_valido($_POST['token'] ?? null)) {
    $responder(false, 'La página estuvo demasiado tiempo abierta. Recárgala e inténtalo de nuevo.', 419);
}

/* --- lo que se quiere enviar --- */
$para   = trim((string) ($_POST['para'] ?? ''));
$cc     = trim((string) ($_POST['cc'] ?? ''));
$asunto = trim((string) ($_POST['asunto'] ?? '')) ?: '(sin asunto)';
$cuerpo = trim((string) ($_POST['cuerpo'] ?? ''));
$responde = trim((string) ($_POST['responde_a'] ?? ''), " <>");

if ($para === '')                                    $responder(false, 'Falta el destinatario.');
if (!filter_var($para, FILTER_VALIDATE_EMAIL))       $responder(false, 'La dirección "' . $para . '" no es válida.');
if ($cc !== '' && !filter_var($cc, FILTER_VALIDATE_EMAIL)) $responder(false, 'La dirección en copia no es válida.');
if ($cuerpo === '')                                  $responder(false, 'El mensaje está vacío.');

/* --- la cuenta configurada en instalar.php --- */
$smtp = $cfg['origen']['smtp'] ?? [];
$conf = [
    'servidor'  => (string) ($smtp['host'] ?? ''),
    'puerto'    => (int) ($smtp['puerto'] ?? 465),
    'seguridad' => ($smtp['cifrado'] ?? 'ssl') === 'tls' ? 'tls' : 'ssl',
    'usuario'   => (string) ($smtp['usuario'] ?? ''),
    'clave'     => (string) ($smtp['clave'] ?? ''),
    'remitente' => (string) ($smtp['desde'] ?? $smtp['usuario'] ?? ''),
    // el nombre que la persona configuró para su casilla, no el del producto
    'remitente_nombre' => (string) ($cfg['usuario']['nombre'] ?? $cfg['marca']['nombre'] ?? ''),
];

if ($conf['servidor'] === '' || $conf['usuario'] === '' || $conf['clave'] === '') {
    $responder(false, 'Falta configurar la cuenta de envío en instalar.php (servidor, usuario y clave SMTP).');
}

$firma = trim((string) ($cfg['cuenta_firma'] ?? ''));
if ($firma !== '') {
    $cuerpo .= "\n\n--\n" . $firma;
}

/* --- los archivos que se adjuntan --- */
$adjuntos = [];
$pesan    = 0;

foreach (mj_archivos_subidos('adjuntos') as $a) {
    if ($a['error'] === UPLOAD_ERR_INI_SIZE || $a['error'] === UPLOAD_ERR_FORM_SIZE) {
        $responder(false, '"' . $a['name'] . '" pasa del máximo que admite el servidor ('
                        . mj_limite_legible() . ').');
    }
    if ($a['error'] !== UPLOAD_ERR_OK) {
        $responder(false, 'No se pudo subir "' . $a['name'] . '".');
    }

    $pesan += $a['size'];
    if ($pesan > mj_limite_subida()) {
        $responder(false, 'Entre todos los archivos pasan de ' . mj_limite_legible()
                        . ', que es lo que admite el servidor.');
    }
    if (count($adjuntos) >= MJ_ADJUNTOS_N) {
        $responder(false, 'No se pueden mandar más de ' . MJ_ADJUNTOS_N . ' archivos a la vez.');
    }

    $adjuntos[] = [
        'nombre' => (string) $a['name'],
        'tipo'   => mj_tipo_archivo($a['tmp_name'], (string) $a['name']),
        'datos'  => (string) file_get_contents($a['tmp_name']),
    ];
}

/* --- la firma con logo obliga a mandar también una versión en HTML --- */
$extras = ['adjuntos' => $adjuntos];
$buzonFirma = mj_buzon_actual($cfg) ?: $conf['remitente'];

if (mj_logo_hay($buzonFirma)) {
    $logo = mj_logo($buzonFirma);
    if ($logo !== null) {
        $cid = 'firma' . bin2hex(random_bytes(8));
        $extras['html'] = mj_cuerpo_html($cuerpo, $cid);
        $extras['incrustadas'] = [[
            'cid'    => $cid,
            'nombre' => 'logo.' . $logo['ext'],
            'tipo'   => $logo['tipo'],
            'datos'  => $logo['datos'],
        ]];
    }
}

$r = mj_smtp_enviar($conf, $para, $asunto, $cuerpo, '', '', $responde, $extras);

// Copia en la carpeta de enviados del servidor, para que quede en la casilla
if ($r['ok']) {
    $imapConf = $cfg['origen']['imap'] ?? [];
    if (trim((string) ($imapConf['host'] ?? '')) !== '') {
        require_once __DIR__ . '/inc/imap-cliente.php';
        $imap = new MjImap($imapConf);
        if ($imap->conectar() && $imap->entrar()) {
            $carpeta = 'INBOX.Sent';
            foreach ($imap->carpetas() as $c) {
                if ($c['papel'] === 'enviados') { $carpeta = $c['nombre']; break; }
            }
            $nombreDe = (string) ($conf['remitente_nombre'] ?? '');

            // El mismo cuerpo que salió, con sus adjuntos: si la copia fuera
            // sólo el texto, en Enviados no quedaría lo que se mandó.
            $pieza = $r['mime'] ?? mj_mime_mensaje(['texto' => $cuerpo]);

            $sobre = "From: " . ($nombreDe !== '' ? '=?UTF-8?B?' . base64_encode($nombreDe) . '?= ' : '')
                   . '<' . $conf['remitente'] . ">\r\n"
                   . "To: <$para>\r\n"
                   . 'Subject: =?UTF-8?B?' . base64_encode($asunto) . "?=\r\n"
                   . 'Date: ' . date('r') . "\r\n"
                   // mismo identificador que el correo enviado: sin esto, la
                   // copia y la respuesta caen en conversaciones distintas
                   . 'Message-ID: <' . ($r['id_mensaje'] ?? '') . ">\r\n"
                   . ($responde !== '' ? "In-Reply-To: <$responde>\r\nReferences: <$responde>\r\n" : '')
                   . "MIME-Version: 1.0\r\n"
                   . implode("\r\n", $pieza['cabeceras']) . "\r\n\r\n"
                   . $pieza['cuerpo'];
            $imap->guardar($carpeta, $sobre);
            $imap->cerrar();
        }
    }
}

if ($r['ok'] && $cc !== '') {
    mj_smtp_enviar($conf, $cc, $asunto, $cuerpo, '', '', '', $extras);
}

// A quien se le escribe, queda en la agenda. Es lo último que se hace:
// si fallara el guardado, el mensaje ya salió y eso es lo que importa.
if ($r['ok']) {
    // La misma casilla que lee la agenda en la vista; si se calculara de
    // otra forma, se guardaría bajo una clave y se leería bajo otra.
    $buzon = mj_buzon_actual($cfg) ?: $conf['remitente'];
    mj_contactos_anotar_lista($buzon, $para);
    if ($cc !== '') { mj_contactos_anotar_lista($buzon, $cc); }
}

$responder($r['ok'], $r['ok'] ? 'Mensaje enviado a ' . $para : $r['mensaje']);
