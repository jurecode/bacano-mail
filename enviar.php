<?php
/* ============================================================
   BACANO.MAIL · Enviar un mensaje
   ------------------------------------------------------------
   Sólo responde a quien ya entró a la bandeja y trae el token
   de la sesión: así el formulario no se convierte en un relay
   abierto para spam.
   ============================================================ */

declare(strict_types=1);

require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/acceso.php';
require_once __DIR__ . '/inc/smtp.php';

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
    'remitente_nombre' => (string) ($cfg['marca']['nombre'] ?? 'Correo'),
];

if ($conf['servidor'] === '' || $conf['usuario'] === '' || $conf['clave'] === '') {
    $responder(false, 'Falta configurar la cuenta de envío en instalar.php (servidor, usuario y clave SMTP).');
}

$r = mj_smtp_enviar($conf, $para, $asunto, $cuerpo);

if ($r['ok'] && $cc !== '') {
    mj_smtp_enviar($conf, $cc, $asunto, $cuerpo);
}

$responder($r['ok'], $r['ok'] ? 'Mensaje enviado a ' . $para : $r['mensaje']);
