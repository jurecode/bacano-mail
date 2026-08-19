<?php
/* ============================================================
   Correo — CARGA DE CONFIGURACIÓN
   ------------------------------------------------------------
   Orden de prioridad (gana el último):
     1. config.php          valores de fábrica
     2. perfil de rubro     inc/perfiles.php
     3. config.local.php    lo que escribió instalar.php
     4. variables de entorno  MJ_IMAP_CLAVE, MJ_SMTP_CLAVE …
     5. parámetros de mj_correo([...])
   ============================================================ */

require_once __DIR__ . '/perfiles.php';

/** Configuración completa y resuelta */
function mj_config(array $ov = []): array
{
  static $cache = null;

  if ($cache === null) {
    $cfg = require __DIR__ . '/../config.php';

    $local = __DIR__ . '/../config.local.php';
    $usr   = is_readable($local) ? (require $local) : [];
    if (!is_array($usr)) $usr = [];

    // El perfil se aplica antes de la config local: así lo que
    // hayas ajustado a mano en el instalador siempre manda.
    $perfil = $usr['perfil'] ?? $cfg['perfil'] ?? 'generico';
    $cfg = mj_aplicar_perfil($cfg, $perfil);

    $cfg = mj_fusionar($cfg, $usr);
    $cfg = mj_config_entorno($cfg);
    $cache = $cfg;
  }

  return $ov ? mj_fusionar($cache, $ov) : $cache;
}

/**
 * Las claves pueden venir del entorno del hosting en vez del
 * archivo. Si existe la variable, gana sobre lo guardado.
 *   MJ_IMAP_HOST  MJ_IMAP_USUARIO  MJ_IMAP_CLAVE
 *   MJ_SMTP_HOST  MJ_SMTP_USUARIO  MJ_SMTP_CLAVE
 */
function mj_config_entorno(array $cfg): array
{
  $mapa = [
    'MJ_IMAP_HOST'    => ['imap', 'host'],
    'MJ_IMAP_USUARIO' => ['imap', 'usuario'],
    'MJ_IMAP_CLAVE'   => ['imap', 'clave'],
    'MJ_IMAP_PUERTO'  => ['imap', 'puerto'],
    'MJ_SMTP_HOST'    => ['smtp', 'host'],
    'MJ_SMTP_USUARIO' => ['smtp', 'usuario'],
    'MJ_SMTP_CLAVE'   => ['smtp', 'clave'],
    'MJ_SMTP_PUERTO'  => ['smtp', 'puerto'],
  ];
  foreach ($mapa as $var => [$grupo, $clave]) {
    $v = getenv($var);
    if ($v !== false && $v !== '') $cfg['origen'][$grupo][$clave] = $v;
  }
  return $cfg;
}

/** ¿Ya pasó por el instalador? */
function mj_instalado(): bool
{
  return is_readable(__DIR__ . '/../config.local.php');
}

/** Escribe config.local.php (lo usa instalar.php) */
function mj_guardar_config(array $datos): bool
{
  $archivo = __DIR__ . '/../config.local.php';
  $tz = $datos['formato']['zona_horaria'] ?? 'UTC';
  try { $ahora = (new DateTimeImmutable('now', new DateTimeZone($tz)))->format('d/m/Y H:i'); }
  catch (Exception $e) { $ahora = date('d/m/Y H:i'); }

  $php = "<?php\n"
       . "/* ============================================================\n"
       . "   Configuración de ESTA instalación — generada por instalar.php\n"
       . "   " . $ahora . "\n"
       . "   Puedes editarla a mano; se respeta al volver a instalar.\n"
       . "   NO la subas a un repositorio público: puede tener claves.\n"
       . "   ============================================================ */\n\n"
       . "return " . mj_exportar($datos, 0) . ";\n";

  $ok = @file_put_contents($archivo, $php, LOCK_EX) !== false;
  if ($ok) @chmod($archivo, 0640);
  return $ok;
}

/** var_export con formato legible */
function mj_exportar($v, int $nivel = 0): string
{
  $tab = str_repeat('  ', $nivel + 1);
  if (is_array($v)) {
    $lista = array_is_list($v);
    $out = "[\n";
    foreach ($v as $k => $x) {
      $out .= $tab . ($lista ? '' : var_export($k, true) . ' => ') . mj_exportar($x, $nivel + 1) . ",\n";
    }
    return $out . str_repeat('  ', $nivel) . ']';
  }
  return var_export($v, true);
}

/** Fusión recursiva: las listas se reemplazan, los mapas se mezclan */
function mj_fusionar(array $base, array $nuevo): array
{
  foreach ($nuevo as $k => $v) {
    $base[$k] = (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !array_is_list($v))
      ? mj_fusionar($base[$k], $v) : $v;
  }
  return $base;
}
