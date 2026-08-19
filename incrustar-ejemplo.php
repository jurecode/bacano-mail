<?php
/* ============================================================
   EJEMPLO · Cómo incrustar el correo en cualquier página web
   ------------------------------------------------------------
   Copia este patrón en tu propio archivo. Lo único obligatorio
   son las dos líneas marcadas con  ← .

   Sirve igual en un sitio de una automotora, una tienda, una
   clínica o una intranet: el rubro se elige en instalar.php.
   ============================================================ */

require __DIR__ . '/correo.php';        // ←  1. cargar el módulo

$cfg = mj_config();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Correo · <?= mj_e($cfg['marca']['nombre_full']) ?></title>
<style>
  body{ margin:0; font-family:system-ui,sans-serif; background:#eef1f6; }
  .cabecera{
    display:flex; align-items:center; justify-content:space-between;
    height:56px; padding:0 22px; background:#15171c; color:#fff;
  }
  .cabecera strong{ letter-spacing:.01em; font-size:15px; }
  .cabecera a{ color:#c9ced9; font-size:14px; text-decoration:none; }
  .cabecera a:hover{ color:#fff; }
</style>
</head>
<body>

  <!-- Tu propia cabecera, tu menú, lo que ya tenga el sitio -->
  <header class="cabecera">
    <strong><?= mj_e($cfg['marca']['nombre_full']) ?></strong>
    <a href="../">Volver al sitio</a>
  </header>

  <?php
  mj_correo([                            // ←  2. pintar el correo
    'tema' => [
      // Descuenta la altura de tu cabecera para que no haya doble scroll
      'alto'    => 'calc(100svh - 56px)',
      'ventana' => true,
    ],
    'interfaz' => [
      'mostrar_rail' => false,           // el sitio ya tiene su propio menú
    ],
  ]);
  ?>

</body>
</html>
