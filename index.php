<?php
/* ============================================================
   MÓDULO DE CORREO — Página completa (standalone)
   Abre  https://tu-sitio.cl/mails/  y listo.
   Toda la personalización vive en config.php
   ============================================================ */

/* ------------------------------------------------------------
   PHP 8 o superior. Debajo de esa versión el resto del programa
   ni siquiera se puede leer y el navegador muestra una página en
   blanco, sin explicación. Por eso esto va antes de todos los
   require y está escrito a propósito en PHP antiguo: así se
   ejecuta siempre y manda a la pantalla que sí lo cuenta.
   ------------------------------------------------------------ */
if (version_compare(PHP_VERSION, '8.0', '<')) {
    require __DIR__ . '/comprobar.php';
    exit;
}

require_once __DIR__ . '/inc/acceso.php';   // antes del config: la sesión manda
require __DIR__ . '/correo.php';

$cfg = mj_config();

// La casilla es privada: sin clave no se entra.
if (!mj_exigir_acceso($cfg)) { exit; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= mj_e($cfg['marca']['titulo_web']) ?></title>
<meta name="description" content="Correo de <?= mj_e($cfg['marca']['nombre_full']) ?>">
<meta name="robots" content="noindex, nofollow">
<?php if (!empty($cfg['marca']['favicon'])): ?>
<link rel="icon" href="<?= mj_e($cfg['marca']['favicon']) ?>">
<?php endif; ?>
</head>
<body class="mj-body">

<?php mj_correo(); ?>

</body>
</html>
