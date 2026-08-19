<?php
/* ============================================================
   Pantalla del módulo de ejemplo.
   Reutiliza la configuración, el tema y los estilos del núcleo:
   no tienes que rehacer el diseño en cada módulo.
   ============================================================ */

require_once __DIR__ . '/../../inc/cargar.php';
require_once __DIR__ . '/../../inc/ayuda.php';
require_once __DIR__ . '/../../inc/iconos.php';

$cfg  = mj_config();
$base = mj_base($cfg);
$yo   = require __DIR__ . '/modulo.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= mj_e($yo['nombre']) ?> · <?= mj_e($cfg['marca']['nombre_full']) ?></title>
<link rel="stylesheet" href="<?= mj_e($base) ?>/assets/css/mail.css">
</head>
<body class="mj-body">

<div class="mjmail" data-tema="<?= mj_e($cfg['tema']['preset']) ?>" data-modo="<?= mj_e($cfg['tema']['modo']) ?>">
  <div class="mj-fondo" aria-hidden="true"><i></i><i></i><i></i></div>

  <div class="mj-ventana" style="grid-template-columns:minmax(0,1fr)">
    <section class="mj-lector">
      <header class="mj-lector-barra">
        <a class="mj-btn-txt" href="<?= mj_e($base) ?>/"><?= mj_icono('atras', 18) ?><span>Volver al correo</span></a>
      </header>
      <div class="mj-lector-scroll">
        <article class="mj-msg">
          <h1 class="mj-h1" style="margin-bottom:10px"><?= mj_e($yo['nombre']) ?></h1>
          <div class="mj-msg-cuerpo" style="border-top:0; margin-top:0">
            <p><?= mj_e($yo['descripcion']) ?></p>
            <p>Esta pantalla existe para mostrar el contrato de un módulo:</p>
            <ul>
              <li><code>modulo.php</code> declara id, nombre, icono y dirección.</li>
              <li>El instalador lo detecta solo y permite activarlo.</li>
              <li>Al activarlo aparece en el menú lateral del correo.</li>
              <li>Puede usar la configuración, el tema y los estilos del núcleo.</li>
            </ul>
            <p>Para crear el tuyo: copia esta carpeta, cámbiale el <code>id</code> y escribe tu pantalla.</p>
          </div>
        </article>
      </div>
    </section>
  </div>
</div>

</body>
</html>
