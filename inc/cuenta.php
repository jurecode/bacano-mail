<?php
/* ============================================================
   BACANO.MAIL · Preferencias de cada casilla
   ------------------------------------------------------------
   El nombre con el que sales en los correos, y la firma. Se
   guardan por dirección, en data/cuentas/, así cada persona que
   entre tiene lo suyo.
   ============================================================ */

declare(strict_types=1);

function mj_cuenta_archivo(string $correo): string
{
    return __DIR__ . '/../data/cuentas/' . sha1(strtolower(trim($correo))) . '.json';
}

/** Lo que la persona tenga guardado, con valores por defecto sensatos. */
function mj_cuenta(string $correo): array
{
    $defecto = [
        'nombre' => ucwords(str_replace(['.', '_', '-'], ' ', strtok($correo, '@') ?: '')),
        'firma'  => '',
        'correo' => $correo,
    ];

    $archivo = mj_cuenta_archivo($correo);
    if (!is_readable($archivo)) {
        return $defecto;
    }
    $datos = json_decode((string) file_get_contents($archivo), true);
    return is_array($datos) ? $datos + $defecto : $defecto;
}

function mj_cuenta_guardar(string $correo, array $datos): bool
{
    $carpeta = dirname(mj_cuenta_archivo($correo));
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0750, true)) {
        return false;
    }
    if (!is_file($carpeta . '/.htaccess')) {
        @file_put_contents($carpeta . '/.htaccess', "Require all denied\n");
    }

    $limpio = [
        'correo' => $correo,
        'nombre' => trim(mb_substr((string) ($datos['nombre'] ?? ''), 0, 80)),
        'firma'  => trim(mb_substr((string) ($datos['firma'] ?? ''), 0, 500)),
    ];
    return file_put_contents(mj_cuenta_archivo($correo), json_encode($limpio, JSON_UNESCAPED_UNICODE)) !== false;
}

/** Aplica el nombre y la firma de la casilla al config. */
function mj_cuenta_aplicar(array $cfg): array
{
    if (!function_exists('mj_credenciales') || ($cred = mj_credenciales()) === null) {
        return $cfg;
    }
    $c = mj_cuenta($cred['usuario']);

    if ($c['nombre'] !== '') {
        $cfg['usuario']['nombre'] = $c['nombre'];
    }
    $cfg['usuario']['email'] = $cred['usuario'];
    $cfg['cuenta_firma']     = $c['firma'];
    return $cfg;
}

/* ------------------------------------------------------------
   Logo de la firma
   Se guarda junto a las preferencias, fuera de la web, y viaja
   incrustado en el correo: así no depende de que el sitio esté
   en pie ni de que el lector acepte imágenes remotas.
   ------------------------------------------------------------ */

const MJ_LOGO_MAX = 512000;          // 500 KB: es una firma, no un cartel

function mj_logo_archivo(string $correo): string
{
    return __DIR__ . '/../data/cuentas/' . sha1(strtolower(trim($correo))) . '-logo';
}

/** El logo guardado: ['datos','tipo','ext'] o null. */
function mj_logo(string $correo): ?array
{
    $base = mj_logo_archivo($correo);
    if (!is_readable($base . '.json')) { return null; }

    $d = json_decode((string) file_get_contents($base . '.json'), true);
    if (!is_array($d) || !is_readable($base . '.bin')) { return null; }

    return [
        'datos' => (string) file_get_contents($base . '.bin'),
        'tipo'  => (string) ($d['tipo'] ?? 'image/png'),
        'ext'   => (string) ($d['ext'] ?? 'png'),
    ];
}

/** ¿Hay logo, sin cargar la imagen entera? */
function mj_logo_hay(string $correo): bool
{
    return is_readable(mj_logo_archivo($correo) . '.json');
}

/**
 * Guarda un logo subido. Devuelve ['ok','mensaje'].
 * Sólo se aceptan formatos que cualquier lector de correo entiende.
 */
function mj_logo_guardar(string $correo, array $archivo): array
{
    $tipos = [
        IMAGETYPE_PNG  => ['image/png',  'png'],
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_GIF  => ['image/gif',  'gif'],
    ];

    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'mensaje' => 'No llegó la imagen. ¿Pesa demasiado?'];
    }
    if (($archivo['size'] ?? 0) > MJ_LOGO_MAX) {
        return ['ok' => false, 'mensaje' => 'La imagen pesa más de 500 KB. Usa una más liviana.'];
    }

    // El tipo se saca de la imagen, no de lo que diga el navegador
    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !isset($tipos[$info[2]])) {
        return ['ok' => false, 'mensaje' => 'Sólo se admiten imágenes PNG, JPG o GIF.'];
    }
    [$tipo, $ext] = $tipos[$info[2]];

    $carpeta = dirname(mj_logo_archivo($correo));
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0750, true)) {
        return ['ok' => false, 'mensaje' => 'No se pudo guardar: revisa los permisos de la carpeta data.'];
    }
    if (!is_file($carpeta . '/.htaccess')) {
        @file_put_contents($carpeta . '/.htaccess', "Require all denied\n");
    }

    $base = mj_logo_archivo($correo);
    $datos = (string) file_get_contents($archivo['tmp_name']);

    if (@file_put_contents($base . '.bin', $datos) === false) {
        return ['ok' => false, 'mensaje' => 'No se pudo guardar la imagen.'];
    }
    @file_put_contents($base . '.json', json_encode([
        'tipo' => $tipo, 'ext' => $ext, 'ancho' => $info[0], 'alto' => $info[1],
    ]));

    return ['ok' => true, 'mensaje' => 'Logo guardado'];
}

function mj_logo_borrar(string $correo): bool
{
    $base = mj_logo_archivo($correo);
    $habia = is_file($base . '.json');
    @unlink($base . '.json');
    @unlink($base . '.bin');
    return $habia;
}
