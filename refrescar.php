<?php
/* ============================================================
   BACANO.MAIL · Volver a leer la bandeja
   ------------------------------------------------------------
   Devuelve las filas de la carpeta ya dibujadas por el mismo
   PHP que pinta la página, para que no haya dos versiones del
   mismo html. El navegador las pide cada tanto y las cambia si
   traen algo distinto.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = mj_config();

$responder = static function (array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!empty($cfg['acceso']['proteger']) && !mj_dentro()) {
    $responder(['ok' => false, 'mensaje' => 'Tu sesión se cerró.'], 401);
}

$prov = mj_proveedor($cfg);
$msgs = $prov->mensajes();

if (mj_fallo_imap() !== null) {
    $responder(['ok' => false, 'mensaje' => mj_fallo_imap()], 200);
}

$carpetas_ids = array_merge(
    array_column($cfg['carpetas'], 'id'),
    array_column($cfg['carpetas_propias'] ?? [], 'id')
);
if (method_exists($prov, 'carpetas_propias')) {
    foreach ($prov->carpetas_propias() as $c) { $carpetas_ids[] = $c['id']; }
}
$carpeta = mj_param('carpeta', $carpetas_ids, 'entrada');
$activo  = (string) ($_GET['m'] ?? '');

/* --- los mensajes de esa carpeta, agrupados como en la página --- */
$lista = array_values(array_filter($msgs, fn($m) => $m['carpeta'] === $carpeta));
if ($carpeta === 'destacado') {
    $lista = array_values(array_filter($msgs, fn($m) => $m['destacado'] || $m['importante']));
}
$lista = mj_agrupar_conversaciones($cfg, $lista);

/* --- se dibujan con la misma función que la página --- */
ob_start();
$grupo_previo = '';
foreach ($lista as $m) {
    mj_v_item($cfg, $m, $activo !== '' && $m['id'] === $activo, $carpeta, false);
}
$filas = (string) ob_get_clean();

$sinLeer = 0;
foreach ($msgs as $m) {
    if ($m['carpeta'] === 'entrada' && !$m['leido']) { $sinLeer++; }
}

$responder([
    'ok'      => true,
    'filas'   => $filas,
    'firma'   => sha1($filas),      // para no repintar si no cambió nada
    'cuantos' => count($lista),
    'sinLeer' => $sinLeer,
]);
