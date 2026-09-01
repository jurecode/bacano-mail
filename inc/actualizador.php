<?php
/* ============================================================
   ACTUALIZACIONES
   ------------------------------------------------------------
   Dos caminos, los dos conservan config.local.php:

   A) git pull        — si el hosting tiene git (lo más limpio)
   B) desde el panel  — descarga el ZIP del release de GitHub,
                        respalda lo actual y reemplaza los archivos

   La versión instalada vive en el archivo VERSION.
   ============================================================ */

/** Versión instalada */
function mj_version(): string
{
  static $v = null;
  if ($v === null) {
    $f = __DIR__ . '/../VERSION';
    $v = is_readable($f) ? trim((string) file_get_contents($f)) : '0.0.0';
  }
  return $v;
}

/** ¿Esta copia se instaló con git? */
function mj_es_git(): bool
{
  return is_dir(__DIR__ . '/../.git');
}

/**
 * Consulta la última versión publicada en GitHub.
 * Devuelve ['version','notas','zip','url','error'] (error vacío si todo bien).
 * Cachea 6 horas para no golpear la API en cada carga.
 */
function mj_buscar_version(array $cfg, bool $forzar = false): array
{
  $repo = trim($cfg['actualizaciones']['repositorio'] ?? '');
  $vacio = ['version' => '', 'notas' => '', 'zip' => '', 'url' => '', 'error' => ''];

  if ($repo === '' || !str_contains($repo, '/')) {
    return $vacio + ['error' => 'Falta indicar el repositorio (usuario/proyecto) en la configuración.'];
  }

  $cache = __DIR__ . '/../data/.cache-version.json';
  if (!$forzar && is_readable($cache) && (time() - filemtime($cache) < 6 * 3600)) {
    $c = json_decode((string) file_get_contents($cache), true);
    if (is_array($c)) return $c + $vacio;
  }

  $token = (string) ($cfg['actualizaciones']['token'] ?? '');
  $api   = 'https://api.github.com/repos/' . $repo . '/releases/latest';
  $json  = mj_pedir($api, null, 6, $estado, $token);   // consulta corta: no traba el panel

  if ($json === null) {
    $motivo = match (true) {
      $estado === 0 => 'Este servidor no pudo conectarse con GitHub.',
      $estado === 401 || $estado === 403 =>
        'GitHub rechazó el token: revísalo o quítalo si el repositorio es público.',
      $estado === 404 => mj_motivo_404($repo, $token),
      default => 'GitHub respondió con el código ' . $estado . '.',
    };
    return mj_cachear($cache, $vacio, $motivo);
  }

  $d = json_decode($json, true);
  if (!is_array($d) || empty($d['tag_name'])) {
    return mj_cachear($cache, $vacio, 'El repositorio aún no tiene ningún release publicado.');
  }

  $r = [
    'version' => ltrim((string) $d['tag_name'], 'vV'),
    'notas'   => mb_substr((string) ($d['body'] ?? ''), 0, 1200),
    'zip'     => (string) ($d['zipball_url'] ?? ''),
    'url'     => (string) ($d['html_url'] ?? ''),
    'error'   => '',
  ];
  @file_put_contents($cache, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  return $r;
}

/** Guarda el resultado (incluso si falló) para no reintentar en cada carga */
function mj_cachear(string $cache, array $vacio, string $error): array
{
  $r = $vacio;
  $r['error'] = $error;
  @file_put_contents($cache, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  return $r;
}

/** ¿Hay una versión más nueva que la instalada? */
function mj_hay_actualizacion(array $info): bool
{
  return $info['version'] !== '' && version_compare($info['version'], mj_version(), '>');
}

/**
 * GitHub responde 404 tanto si el repositorio no se ve como si no tiene
 * releases. Se pregunta por el repositorio para saber cuál de las dos es.
 */
function mj_motivo_404(string $repo, string $token): string
{
  mj_pedir('https://api.github.com/repos/' . $repo, null, 6, $verRepo, $token);

  if ($verRepo === 200) {
    return 'El repositorio se ve bien, pero todavía no tiene ningún release publicado. '
         . 'Si instalaste con git, actualiza con git pull.';
  }
  return $token === ''
    ? 'El repositorio es privado o no existe. Si es privado, agrega abajo un token de GitHub; '
      . 'si instalaste con git, actualiza con git pull.'
    : 'No se encontró el repositorio: revisa el nombre (usuario/proyecto) y que el token tenga acceso.';
}

/**
 * Petición HTTPS con curl o file_get_contents, lo que exista.
 * $estado recoge el código HTTP (0 si no se pudo ni conectar), para poder
 * distinguir "no hay internet" de "el repositorio es privado".
 */
function mj_pedir(string $url, ?string $destino = null, int $espera = 30,
                  ?int &$estado = null, string $token = ''): ?string
{
  $ua = 'bacano-mail-updater/' . mj_version();
  $estado = 0;

  $cabeceras = ['Accept: application/vnd.github+json'];
  if (trim($token) !== '') {
    $cabeceras[] = 'Authorization: Bearer ' . trim($token);
  }

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT        => $espera,
      CURLOPT_USERAGENT      => $ua,
      CURLOPT_HTTPHEADER     => $cabeceras,
    ]);
    $r      = curl_exec($ch);
    $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($r === false || $estado >= 400) return null;
  } else {
    if (!ini_get('allow_url_fopen')) return null;
    $ctx = stream_context_create(['http' => [
      'timeout'       => $espera,
      'ignore_errors' => true,
      'header'        => "User-Agent: {$ua}\r\n" . implode("\r\n", $cabeceras) . "\r\n",
    ]]);
    $r = @file_get_contents($url, false, $ctx);
    $cab = function_exists('http_get_last_response_headers')
      ? (http_get_last_response_headers() ?? [])
      : ($http_response_header ?? []);
    if (isset($cab[0]) && preg_match('#\s(\d{3})\s#', (string) $cab[0], $m)) {
      $estado = (int) $m[1];
    }
    if ($r === false || $estado >= 400) return null;
  }

  if ($destino !== null) {
    return @file_put_contents($destino, $r) !== false ? '' : null;
  }
  return (string) $r;
}

/** Archivos y carpetas que NUNCA se sobrescriben al actualizar */
function mj_protegidos(): array
{
  return [
    'config.local.php',   // tu configuración y tus claves
    'respaldos',          // respaldos anteriores
    '.git',               // si instalaste con git
    'data/.cache-version.json',
  ];
}

/** ¿Se puede actualizar desde el panel en este hosting? */
function mj_puede_actualizar(): array
{
  $motivos = [];
  if (!class_exists('ZipArchive'))       $motivos[] = 'falta la extensión zip';
  if (!is_writable(__DIR__ . '/..'))     $motivos[] = 'la carpeta no tiene permiso de escritura';
  if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) $motivos[] = 'el servidor no puede descargar archivos';
  return $motivos;
}

/**
 * Descarga el release y reemplaza los archivos.
 * Devuelve ['ok'=>bool, 'mensaje'=>string, 'respaldo'=>string]
 */
function mj_aplicar_actualizacion(array $info): array
{
  $raiz = realpath(__DIR__ . '/..');
  if ($motivos = mj_puede_actualizar()) {
    return ['ok' => false, 'mensaje' => 'No se puede actualizar desde aquí: ' . implode(', ', $motivos) . '.', 'respaldo' => ''];
  }
  if (empty($info['zip'])) {
    return ['ok' => false, 'mensaje' => 'El release no trae archivo descargable.', 'respaldo' => ''];
  }

  $tmp = $raiz . '/data/_update_' . bin2hex(random_bytes(4));
  @mkdir($tmp, 0755, true);
  $zip = $tmp . '/paquete.zip';

  $token = (string) ($cfg['actualizaciones']['token'] ?? '');
  if (mj_pedir($info['zip'], $zip, 30, $estado, $token) === null) {
    mj_borrar_dir($tmp);
    return ['ok' => false, 'mensaje' => 'No se pudo descargar el paquete.', 'respaldo' => ''];
  }

  $za = new ZipArchive();
  if ($za->open($zip) !== true) {
    mj_borrar_dir($tmp);
    return ['ok' => false, 'mensaje' => 'El paquete descargado no se pudo abrir.', 'respaldo' => ''];
  }
  $za->extractTo($tmp);
  $za->close();

  // GitHub empaqueta todo dentro de una carpeta con hash: la buscamos
  $origen = '';
  foreach ((array) glob($tmp . '/*', GLOB_ONLYDIR) as $d) {
    if (is_file($d . '/VERSION') && is_file($d . '/index.php')) { $origen = $d; break; }
  }
  if ($origen === '') {
    mj_borrar_dir($tmp);
    return ['ok' => false, 'mensaje' => 'El paquete no tiene la estructura esperada. No se cambió nada.', 'respaldo' => ''];
  }

  // Respaldo de lo actual antes de tocar nada
  $sello    = date('Y-m-d_His');
  $respaldo = $raiz . '/respaldos/' . $sello;
  @mkdir($respaldo, 0755, true);
  mj_copiar_dir($raiz, $respaldo, mj_protegidos());

  $copiados = mj_copiar_dir($origen, $raiz, mj_protegidos());
  mj_borrar_dir($tmp);
  @unlink($raiz . '/data/.cache-version.json');

  return [
    'ok'       => true,
    'mensaje'  => 'Actualizado a la versión ' . $info['version'] . '. Se reemplazaron ' . $copiados . ' archivos.',
    'respaldo' => 'respaldos/' . $sello,
  ];
}

/** Copia recursiva. Devuelve cuántos archivos copió. */
function mj_copiar_dir(string $de, string $a, array $excluir = [], string $rel = ''): int
{
  $n = 0;
  foreach ((array) scandir($de) as $item) {
    if ($item === '.' || $item === '..') continue;
    $ruta = ($rel === '' ? '' : $rel . '/') . $item;
    if (in_array($item, $excluir, true) || in_array($ruta, $excluir, true)) continue;

    $origen  = $de . '/' . $item;
    $destino = $a . '/' . $item;

    if (is_dir($origen)) {
      if (!is_dir($destino)) @mkdir($destino, 0755, true);
      $n += mj_copiar_dir($origen, $destino, $excluir, $ruta);
    } elseif (@copy($origen, $destino)) {
      $n++;
    }
  }
  return $n;
}

/** Borrado recursivo (solo para carpetas temporales del actualizador) */
function mj_borrar_dir(string $dir): void
{
  if (!is_dir($dir)) return;
  foreach ((array) scandir($dir) as $item) {
    if ($item === '.' || $item === '..') continue;
    $r = $dir . '/' . $item;
    is_dir($r) ? mj_borrar_dir($r) : @unlink($r);
  }
  @rmdir($dir);
}
