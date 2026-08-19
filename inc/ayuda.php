<?php
/* ============================================================
   MÓDULO DE CORREO — Utilidades
   Funciones cortas usadas por las vistas. Todas con prefijo mj_
   ============================================================ */

/** Escape seguro para HTML */
function mj_e($v): string
{
  return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Ruta base del módulo detectada automáticamente (para assets) */
function mj_base(array $cfg): string
{
  if (!empty($cfg['ruta_base'])) {
    return rtrim($cfg['ruta_base'], '/');
  }
  $raiz = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
  $dir  = dirname(__DIR__);                       // .../mails
  $raiz = $raiz ? str_replace('\\', '/', $raiz) : '';
  $dir  = str_replace('\\', '/', $dir);
  if ($raiz !== '' && str_starts_with($dir, $raiz)) {
    $rel = substr($dir, strlen($raiz));
    return rtrim($rel, '/');
  }
  return '.';                                     // último recurso: relativo
}

/** Iniciales para el avatar de respaldo */
function mj_iniciales(string $nombre): string
{
  $nombre = trim($nombre);
  if ($nombre === '') return '?';
  $partes = preg_split('/\s+/u', $nombre) ?: [];
  $ini = mb_substr($partes[0], 0, 1, 'UTF-8');
  if (count($partes) > 1) {
    $ini .= mb_substr($partes[count($partes) - 1], 0, 1, 'UTF-8');
  }
  return mb_strtoupper($ini, 'UTF-8');
}

/** Color estable (mismo nombre = mismo color) para el avatar */
function mj_color_avatar(string $semilla): string
{
  $h = crc32(mb_strtolower($semilla, 'UTF-8')) % 360;
  return 'hsl(' . $h . ' 52% 46%)';
}

/** Zona horaria configurada */
function mj_tz(array $cfg): DateTimeZone
{
  static $tz = null;
  if ($tz === null) {
    $z = $cfg['formato']['zona_horaria'] ?? 'UTC';
    try { $tz = new DateTimeZone($z); } catch (Exception $e) { $tz = new DateTimeZone('UTC'); }
  }
  return $tz;
}

/** DateTime desde el ISO del mensaje */
function mj_fecha(string $iso, array $cfg): DateTimeImmutable
{
  try {
    return (new DateTimeImmutable($iso))->setTimezone(mj_tz($cfg));
  } catch (Exception $e) {
    return new DateTimeImmutable('now', mj_tz($cfg));
  }
}

/** Fecha corta al estilo cliente de correo: 14:32 · Mar 09:15 · 12/08/2026 */
function mj_fecha_corta(string $iso, array $cfg): string
{
  $f     = mj_fecha($iso, $cfg);
  $ahora = new DateTimeImmutable('now', mj_tz($cfg));
  $dias  = (int) $ahora->setTime(0, 0)->diff($f->setTime(0, 0))->format('%r%a');

  if ($dias === 0)  return mj_dia_es($f->format($cfg['formato']['hoy'] ?? 'H:i'));
  if ($dias === -1) return $cfg['textos']['ayer'] ?? 'Ayer';
  if ($dias > -7)   return mj_dia_es($f->format($cfg['formato']['semana'] ?? 'D H:i'));
  return $f->format($cfg['formato']['anterior'] ?? 'd/m/Y');
}

/** Fecha completa para el lector */
function mj_fecha_larga(string $iso, array $cfg): string
{
  return mj_dia_es(mj_fecha($iso, $cfg)->format($cfg['formato']['completa'] ?? 'd/m/Y · H:i'));
}

/** Traduce los nombres de día/mes que produce date() */
function mj_dia_es(string $txt): string
{
  return strtr($txt, [
    'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo',
    'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue',
    'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom',
    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
    'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
    'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre',
  ]);
}

/** Grupo del separador de fecha: hoy | ayer | anteriores */
function mj_grupo_fecha(string $iso, array $cfg): string
{
  $f     = mj_fecha($iso, $cfg);
  $ahora = new DateTimeImmutable('now', mj_tz($cfg));
  $dias  = (int) $ahora->setTime(0, 0)->diff($f->setTime(0, 0))->format('%r%a');
  if ($dias === 0)  return 'hoy';
  if ($dias === -1) return 'ayer';
  return 'anteriores';
}

/** Recorta texto sin cortar palabras */
function mj_recorte(string $txt, int $largo = 140): string
{
  // Los bloques y saltos se convierten en espacio para no pegar palabras
  $txt = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', ' ', $txt) ?? $txt;
  $txt = trim(preg_replace('/\s+/u', ' ', strip_tags($txt)) ?? '');
  if (mb_strlen($txt, 'UTF-8') <= $largo) return $txt;
  $corte = mb_substr($txt, 0, $largo, 'UTF-8');
  $esp   = mb_strrpos($corte, ' ', 0, 'UTF-8');
  return rtrim($esp ? mb_substr($corte, 0, $esp, 'UTF-8') : $corte, " ,.;:") . '…';
}

/** Bytes legibles */
function mj_peso(int $bytes): string
{
  $u = ['B', 'KB', 'MB', 'GB'];
  $i = 0;
  while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
  return ($i === 0 ? $bytes : number_format($bytes, 1, ',', '.')) . ' ' . $u[$i];
}

/**
 * Limpieza del HTML del cuerpo del mensaje.
 * Quita scripts/estilos/iframes y atributos on*, y opcionalmente
 * neutraliza las imágenes remotas (quedan en data-mj-src).
 */
function mj_html_seguro(string $html, array $cfg): string
{
  if (empty($cfg['seguridad']['sanitizar_html'])) return $html;

  $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
  $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#i', '', $html) ?? '';
  $html = preg_replace('#\son[a-z]+\s*=\s*"[^"]*"#i', '', $html) ?? '';
  $html = preg_replace("#\son[a-z]+\s*=\s*'[^']*'#i", '', $html) ?? '';
  $html = preg_replace('#\shref\s*=\s*("|\')\s*javascript:[^"\']*\1#i', ' href="#"', $html) ?? '';

  if (!empty($cfg['seguridad']['bloquear_imagenes_remotas'])) {
    $html = preg_replace('#<img([^>]*?)\ssrc=#i', '<img$1 data-mj-src=', $html) ?? '';
  }
  if (!empty($cfg['seguridad']['abrir_enlaces_nueva'])) {
    $html = preg_replace('#<a\s#i', '<a target="_blank" rel="noopener noreferrer" ', $html) ?? '';
  }
  return $html;
}

/** ¿El mensaje trae imágenes remotas bloqueadas? */
function mj_tiene_imagenes_bloqueadas(string $html): bool
{
  return (bool) preg_match('#data-mj-src=#i', $html);
}

/** Lista de personas → "Ana, Luis, Pedro" */
function mj_personas(array $lista, int $max = 4): string
{
  $n = array_map(fn($p) => $p['nombre'] ?: $p['email'], array_slice($lista, 0, $max));
  $extra = count($lista) - count($n);
  return implode(', ', $n) . ($extra > 0 ? ' +' . $extra : '');
}

/** Atributos data-* en una línea */
function mj_datos(array $pares): string
{
  $out = [];
  foreach ($pares as $k => $v) {
    if ($v === null || $v === false) continue;
    $out[] = 'data-' . $k . '="' . mj_e($v === true ? '1' : $v) . '"';
  }
  return implode(' ', $out);
}
