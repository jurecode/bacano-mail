<?php
/* ============================================================
   BACANO.MAIL · Diagnóstico de lectura y marcas
   ------------------------------------------------------------
   Recorre la cadena completa contra tu propio servidor y muestra
   el diálogo IMAP en crudo: qué carpetas hay, qué UID tiene cada
   mensaje, qué banderas trae, y si el STORE queda guardado.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/imap-cliente.php';

$cfg = mj_config();
if (!mj_exigir_acceso($cfg)) { exit; }

$conf   = $cfg['origen']['imap'] ?? [];
$probar = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
$lineas = [];
$anotar = function (string $texto, string $tipo = '') use (&$lineas) {
    $lineas[] = ['t' => $texto, 'c' => $tipo];
};

$imap = new MjImap($conf);

if (!$imap->conectar()) {
    $anotar('No se pudo conectar: ' . $imap->error, 'mal');
} elseif (!$imap->entrar()) {
    $anotar('No se pudo entrar: ' . $imap->error, 'mal');
} else {
    $anotar('Conectado a ' . ($conf['host'] ?? '?') . ' como ' . ($conf['usuario'] ?? '?'), 'ok');

    /* --- carpetas --- */
    $carpetas = $imap->carpetas();
    $anotar('Carpetas del servidor (' . count($carpetas) . '):');
    foreach ($carpetas as $c) {
        $anotar('   · ' . $c['nombre'] . ($c['papel'] !== '' ? '  →  ' . $c['papel'] : '  →  (sin papel asignado)'));
    }

    /* --- INBOX --- */
    $entrada = 'INBOX';
    foreach ($carpetas as $c) { if ($c['papel'] === 'entrada') { $entrada = $c['nombre']; break; } }

    $total = $imap->abrir($entrada);
    $anotar("Abierta \"$entrada\": $total mensajes"
          . ($imap->solo_lectura ? '  ⚠ EN SÓLO LECTURA' : '  (escritura permitida)'),
            $imap->solo_lectura ? 'mal' : 'ok');

    /* --- últimos mensajes con su UID y banderas --- */
    $anotar('Últimos mensajes, tal como los ve el servidor:');
    foreach (array_slice($imap->cabeceras($total, 8), 0, 8) as $m) {
        $anotar(sprintf('   UID %-6s %-9s %s',
            $m['uid'],
            $m['leido'] ? 'leído' : 'NO leído',
            mb_substr($m['asunto'], 0, 44)));
    }

    /* --- prueba de marcado --- */
    if ($probar > 0) {
        $anotar('');
        $anotar("Prueba de marcado sobre el UID $probar:");
        $antes = $imap->banderas($probar);
        $anotar('   banderas antes : ' . ($antes === null ? '(ese UID no está en la carpeta)' : '"' . $antes . '"'));

        $ok = $imap->marcar($probar, '\\Seen');
        $anotar('   UID STORE      : ' . ($ok ? 'aceptado y verificado' : 'FALLÓ — ' . $imap->error), $ok ? 'ok' : 'mal');

        $despues = $imap->banderas($probar);
        $anotar('   banderas ahora : ' . ($despues === null ? '(sin respuesta)' : '"' . $despues . '"'));

        $imap->cerrar();
        $otro = new MjImap($conf);
        if ($otro->conectar() && $otro->entrar()) {
            $otro->abrir($entrada);
            $anotar('   en otra sesión : "' . (string) $otro->banderas($probar) . '"',
                    str_contains((string) $otro->banderas($probar), 'Seen') ? 'ok' : 'mal');
            $otro->cerrar();
        }
    } else {
        $imap->cerrar();
    }
}

$dialogo = $imap->registro;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico · BACANO.MAIL</title>
<meta name="robots" content="noindex, nofollow">
<style>
  :root{ color-scheme: dark }
  body{
    margin:0; padding:28px; background:#0b1220; color:#e8eaf0;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:13px; line-height:1.7;
  }
  h1{ font-family:system-ui,sans-serif; font-size:20px; margin:0 0 4px }
  p.sub{ font-family:system-ui,sans-serif; color:#93a0b8; margin:0 0 22px; font-size:13px }
  .caja{ padding:16px 18px; background:#111a2b; border:1px solid rgba(255,255,255,.10); border-radius:12px; margin-bottom:18px }
  .ok{ color:#6ee7a8 } .mal{ color:#fca5a5 }
  pre{ margin:0; white-space:pre-wrap; word-break:break-word; color:#93a0b8; font-size:12px; max-height:420px; overflow:auto }
  form{ font-family:system-ui,sans-serif; margin-bottom:18px }
  input{ padding:8px 10px; border-radius:8px; background:#0b1220; border:1px solid rgba(255,255,255,.16); color:#e8eaf0 }
  button{ padding:8px 16px; border:0; border-radius:8px; background:#3b82f6; color:#fff; cursor:pointer }
  a{ color:#93a0b8 }
</style>
</head>
<body>
  <h1>Diagnóstico de lectura</h1>
  <p class="sub">Qué ve y qué acepta tu servidor IMAP, sin intermediarios.</p>

  <form method="get">
    <label>Probar a marcar como leído el UID:
      <input type="number" name="uid" value="<?= $probar ?: '' ?>" placeholder="4" min="1">
    </label>
    <button type="submit">Probar</button>
  </form>

  <div class="caja">
    <?php foreach ($lineas as $l): ?>
      <div class="<?= mj_e($l['c']) ?>"><?= mj_e($l['t']) ?></div>
    <?php endforeach; ?>
  </div>

  <div class="caja">
    <strong style="font-family:system-ui,sans-serif">Diálogo con el servidor</strong>
    <pre><?= mj_e(implode("\n", $dialogo)) ?></pre>
  </div>

  <a href="./">← Volver a la bandeja</a>
</body>
</html>
