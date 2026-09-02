<?php
/* ============================================================
   BACANO.MAIL · Agenda (quitar un contacto)
   ------------------------------------------------------------
   La agenda se llena sola al enviar; lo único que hace falta
   por aquí es poder sacar a alguien de ella.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';   // antes del config: la sesión manda
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/contactos.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = mj_config();

$responder = static function (bool $ok, string $mensaje, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
};

if (!empty($cfg['acceso']['proteger']) && !mj_dentro()) {
    $responder(false, 'Tu sesión se cerró. Vuelve a entrar a la bandeja.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $responder(false, 'Método no permitido.', 405);
}
if (!mj_token_valido($_POST['token'] ?? null)) {
    $responder(false, 'Recarga la página e inténtalo otra vez.', 419);
}

$buzon = mj_buzon_actual($cfg);
if ($buzon === '') {
    $responder(false, 'No se sabe de qué casilla es la agenda.', 409);
}

$accion = (string) ($_POST['accion'] ?? '');
$email  = (string) ($_POST['email'] ?? '');

if ($accion !== 'borrar') {
    $responder(false, 'Acción desconocida.');
}
if ($email === '') {
    $responder(false, 'Falta indicar el contacto.');
}

$hecho = mj_contacto_borrar($buzon, $email);
$responder($hecho, $hecho ? 'Contacto quitado' : 'Ese contacto ya no estaba en la agenda.');
