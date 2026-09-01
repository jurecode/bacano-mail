<?php
/* ============================================================
   BACANO.MAIL · Acciones sobre los mensajes
   ------------------------------------------------------------
   Marcar leído, destacar, archivar, mover y eliminar, contra el
   servidor IMAP. Sólo responde con la sesión abierta y el token
   de la página.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = mj_config();

$responder = static function (bool $ok, string $mensaje = '', int $codigo = 200): void {
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

$prov = mj_proveedor($cfg);
if (!method_exists($prov, 'accion')) {
    $responder(false, 'La casilla real no está conectada.', 409);
}

$accion = (string) ($_POST['accion'] ?? '');
$id     = (string) ($_POST['id'] ?? '');
$valor  = (string) ($_POST['valor'] ?? '');

if ($id === '') {
    $responder(false, 'Falta indicar el mensaje.');
}

$r = $prov->accion($accion, $id, $valor);
$responder($r['ok'], $r['mensaje']);
