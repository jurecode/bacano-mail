<?php
/* ============================================================
   MÓDULO DE CORREO — Página completa (standalone)
   Abre  https://tu-sitio.cl/mails/  y listo.
   Toda la personalización vive en config.php
   ============================================================ */

require __DIR__ . '/correo.php';

$cfg = mj_config();
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
