<?php
/* ============================================================
   BACANO.MAIL · Descargar un adjunto
   ------------------------------------------------------------
   El archivo no se guarda en ningún sitio: se pide al servidor
   en el momento y se entrega. Sólo a quien entró, y sólo de su
   propia casilla.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/imap-cliente.php';

$cfg = mj_config();

if (!empty($cfg['acceso']['proteger']) && !mj_dentro()) {
    http_response_code(401);
    exit('Tu sesión se cerró.');
}

$id     = (string) ($_GET['id'] ?? '');
$indice = (int) ($_GET['n'] ?? -1);

if (!preg_match('/^imap-([a-z0-9]+)-(\d+)$/', $id, $c) || $indice < 0) {
    http_response_code(400);
    exit('Petición incompleta.');
}

$conf = $cfg['origen']['imap'] ?? [];
$imap = new MjImap($conf);
if (!$imap->conectar() || !$imap->entrar()) {
    http_response_code(502);
    exit('No se pudo conectar con la casilla.');
}

// Hay que abrir la carpeta donde vive el mensaje
$carpeta = (string) ($conf['carpeta'] ?? 'INBOX');
foreach (mj_carpetas_con_id($imap->carpetas()) as $x) {
    if ($x['id'] === $c[1]) { $carpeta = $x['nombre']; break; }
}
$imap->abrir($carpeta);

$a = $imap->adjunto((int) $c[2], $indice);
$imap->cerrar();

if ($a === null) {
    http_response_code(404);
    exit('Ese archivo ya no está en el mensaje.');
}

// El nombre va saneado: un salto de línea aquí permitiría colar cabeceras
$nombre = str_replace(["\r", "\n", '"'], '', $a['nombre']);

header('Content-Type: ' . ($a['tipo'] ?: 'application/octet-stream'));
header('Content-Length: ' . strlen($a['datos']));
header('Content-Disposition: attachment; filename="' . $nombre . '"; '
     . "filename*=UTF-8''" . rawurlencode($nombre));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
echo $a['datos'];
