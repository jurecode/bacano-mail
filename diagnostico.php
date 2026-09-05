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

/* El dominio desde el que se envía: es el que miran los filtros */
$dominio_correo = (string) ($conf['usuario'] ?? '');
$dominio_correo = strstr($dominio_correo, '@') !== false
    ? substr(strrchr($dominio_correo, '@'), 1)
    : preg_replace('/^mail\./i', '', (string) ($conf['host'] ?? ''));

/**
 * Comprueba SPF, DKIM y DMARC. Sin ellos, un correo perfectamente legítimo
 * acaba en spam, y no hay nada que se pueda arreglar desde esta aplicación:
 * son registros DNS del dominio.
 */
function mj_dns_correo(string $dominio): array
{
    $out = [];
    if ($dominio === '' || !function_exists('dns_get_record')) {
        return [['t' => 'No se pudo consultar el DNS desde este servidor.', 'c' => 'ojo']];
    }
    $out[] = ['t' => 'Dominio: ' . $dominio, 'c' => ''];

    // dns_get_record no admite tiempo de espera y un resolutor que no
    // contesta cuelga la página entera hasta que PHP la mata. Se le pone un
    // presupuesto: en cuanto se pasa, se deja de preguntar.
    $arranque = microtime(true);
    $queda = static fn(): bool => (microtime(true) - $GLOBALS['mj_dns_t0']) < 6.0;
    $GLOBALS['mj_dns_t0'] = $arranque;

    $txt = static function (string $nombre) use ($queda): array {
        if (!$queda()) { return []; }
        $r = @dns_get_record($nombre, DNS_TXT) ?: [];
        return array_map(function ($x) {
            // Una clave DKIM pasa de 255 caracteres y viaja partida en trozos
            if (!empty($x['entries']) && is_array($x['entries'])) {
                return implode('', $x['entries']);
            }
            return (string) ($x['txt'] ?? '');
        }, $r);
    };

    // SPF: quién puede enviar en nombre del dominio
    $spf = array_values(array_filter($txt($dominio), fn($v) => stripos($v, 'v=spf1') === 0));
    $out[] = $spf
        ? ['t' => 'SPF ✓  ' . $spf[0], 'c' => 'bien']
        : ['t' => 'SPF ✗  No hay registro. Sin él, muchos servidores desconfían.', 'c' => 'mal'];

    // DKIM: la firma que demuestra que el correo no se manipuló
    $sel = ['default', 'dkim', 'mail'];   // cPanel usa "default"
    $hay = '';
    foreach ($sel as $x) {
        foreach ($txt($x . '._domainkey.' . $dominio) as $v) {
            if (stripos($v, 'v=DKIM1') !== false || stripos($v, 'p=') !== false) { $hay = $x; break 2; }
        }
    }
    $out[] = $hay
        ? ['t' => 'DKIM ✓  firmado con el selector "' . $hay . '"', 'c' => 'bien']
        : ['t' => 'DKIM ✗  No se encontró clave en los selectores habituales ('
               . implode(', ', $sel) . '). Puede ser que use otro nombre: compruébalo en '
               . 'cPanel → Correo electrónico → Autenticación de correo.', 'c' => 'ojo'];

    // DMARC: qué hacer cuando algo no cuadra
    $dmarc = array_values(array_filter($txt('_dmarc.' . $dominio), fn($v) => stripos($v, 'v=DMARC1') === 0));
    if (!$dmarc) {
        $out[] = ['t' => 'DMARC ✗  No hay registro.', 'c' => 'mal'];
    } elseif (preg_match('/p\s*=\s*none/i', $dmarc[0])) {
        $out[] = ['t' => 'DMARC ~  ' . $dmarc[0] . '  (p=none sólo observa; con el tiempo conviene subirlo a quarantine)', 'c' => 'ojo'];
    } else {
        $out[] = ['t' => 'DMARC ✓  ' . $dmarc[0], 'c' => 'bien'];
    }

    if (!$queda()) {
        $out[] = ['t' => 'El DNS de este servidor tarda demasiado en responder, así que puede '
                       . 'faltar alguna comprobación. Míralo desde fuera con mxtoolbox.com.', 'c' => 'ojo'];
    }
    return $out;
}

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
  .ok,.bien{ color:#6ee7a8 } .mal{ color:#fca5a5 } .ojo{ color:#fcd34d }
  pre{ margin:0; white-space:pre-wrap; word-break:break-word; color:#93a0b8; font-size:12px; max-height:420px; overflow:auto }
  form{ font-family:system-ui,sans-serif; margin-bottom:18px }
  input{ padding:8px 10px; border-radius:8px; background:#0b1220; border:1px solid rgba(255,255,255,.16); color:#e8eaf0 }
  button{ padding:8px 16px; border:0; border-radius:8px; background:#3b82f6; color:#fff; cursor:pointer }
  a{ color:#93a0b8 }
</style>
</head>
<body>
  <h1>Diagnóstico</h1>
  <p class="sub">Qué ve y qué acepta tu servidor, sin intermediarios.</p>

  <h2 style="font-family:system-ui,sans-serif;font-size:16px;margin:24px 0 4px">¿Llegarán tus correos?</h2>
  <p class="sub">Los tres registros que miran Gmail y los demás para decidir si un correo es de fiar.</p>
  <div class="caja">
    <?php foreach (mj_dns_correo($dominio_correo) as $x): ?>
      <div class="<?= mj_e($x['c']) ?>"><?= mj_e($x['t']) ?></div>
    <?php endforeach; ?>
  </div>

  <h2 style="font-family:system-ui,sans-serif;font-size:16px;margin:24px 0 4px">Lectura y marcas</h2>

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
