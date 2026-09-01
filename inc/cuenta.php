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
