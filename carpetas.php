<?php
/* ============================================================
   BACANO.MAIL · Crear, renombrar y borrar carpetas
   ------------------------------------------------------------
   Las carpetas viven en el servidor de correo, no aquí: lo que
   se cree desde este panel aparece también en el celular.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = mj_config();

$responder = static function (bool $ok, string $mensaje, int $codigo = 200, array $extra = []): void {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje] + $extra, JSON_UNESCAPED_UNICODE);
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
if (!method_exists($prov, 'carpeta')) {
    $responder(false, 'La casilla real no está conectada.', 409);
}

$accion = (string) ($_POST['accion'] ?? '');
if (!in_array($accion, ['crear', 'renombrar', 'borrar'], true)) {
    $responder(false, 'Acción desconocida.');
}

$r = $prov->carpeta($accion, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['id'] ?? ''));
$responder($r['ok'], $r['mensaje'], 200, ['id' => $r['id'] ?? '']);
