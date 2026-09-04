<?php
/* ============================================================
   BACANO.MAIL · El logo de la firma, para verlo en los ajustes
   ------------------------------------------------------------
   El archivo vive fuera de la web; esto lo sirve sólo a quien
   ha entrado, y sólo el suyo.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/cuenta.php';

$cfg = mj_config();

if (!empty($cfg['acceso']['proteger']) && !mj_dentro()) {
    http_response_code(401);
    exit;
}

$logo = mj_logo(mj_buzon_actual($cfg));
if ($logo === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $logo['tipo']);
header('Content-Length: ' . strlen($logo['datos']));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $logo['datos'];
