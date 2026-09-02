<?php
/* ============================================================
   BACANO.MAIL · Armazón de las páginas sueltas
   ------------------------------------------------------------
   Las pantallas que viven fuera de la bandeja (los ajustes de
   la cuenta, por ejemplo) usan el mismo tema y la misma hoja de
   estilos que el correo. Así no hay un segundo diseño que
   mantener ni que se vaya pareciendo cada vez menos.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/vista.php';
require_once __DIR__ . '/ayuda.php';
require_once __DIR__ . '/actualizador.php';

/** Cabecera, tema y apertura de la columna central. */
function mj_pagina_abrir(array $cfg, string $titulo, string $bajada = ''): void
{
    $base = mj_base($cfg);
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= mj_e($titulo) ?> · <?= mj_e($cfg['marca']['nombre']) ?></title>
<meta name="robots" content="noindex, nofollow">
<?php if (!empty($cfg['marca']['favicon'])): ?>
<link rel="icon" href="<?= mj_e($cfg['marca']['favicon']) ?>">
<?php endif; ?>
<?php if (!empty($cfg['tema']['fuente_google'])): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= mj_e($cfg['tema']['fuente_google']) ?>&display=swap">
<?php endif; ?>
<link rel="stylesheet" href="<?= mj_e($base) ?>/assets/css/mail.css?v=<?= mj_e(mj_version()) ?>">
</head>
<body class="mj-body">

<div class="mjmail mj-suelta" style="<?= mj_estilo_tema($cfg) ?>" <?= mj_datos([
      'tema'     => $cfg['tema']['preset'],
      'modo'     => $cfg['tema']['modo'],
      'fondo'    => $cfg['tema']['fondo'],
      'densidad' => $cfg['tema']['densidad'],
    ]) ?>>

  <?php if ($cfg['tema']['fondo'] !== 'solido'): ?>
    <div class="mj-fondo" aria-hidden="true"><i></i><i></i><i></i></div>
  <?php endif; ?>

  <main class="mj-hoja">
    <header class="mj-hoja-cab">
      <a class="mj-hoja-atras" href="./" aria-label="Volver a la bandeja"><?= mj_icono('atras', 18) ?></a>
      <div>
        <h1 class="mj-hoja-t"><?= mj_e($titulo) ?></h1>
        <?php if ($bajada !== ''): ?><p class="mj-hoja-d"><?= mj_e($bajada) ?></p><?php endif; ?>
      </div>
    </header>
<?php }

/** Cierre de la columna y de la página. */
function mj_pagina_cerrar(): void
{ ?>
  </main>
</div>
<?php /* Estas páginas no llevan el JS del correo: no tienen nada que mover. */ ?>
</body>
</html>
<?php }

/** Una tarjeta del mismo aire que los modales del correo. */
function mj_pagina_tarjeta_abrir(string $titulo = '', string $ayuda = ''): void
{ ?>
    <section class="mj-tarjeta">
      <?php if ($titulo !== ''): ?>
        <header class="mj-tarjeta-cab">
          <h2 class="mj-tarjeta-t"><?= mj_e($titulo) ?></h2>
          <?php if ($ayuda !== ''): ?><p class="mj-tarjeta-d"><?= mj_e($ayuda) ?></p><?php endif; ?>
        </header>
      <?php endif; ?>
<?php }

function mj_pagina_tarjeta_cerrar(): void
{ ?>
    </section>
<?php }
