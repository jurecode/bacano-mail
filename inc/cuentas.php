<?php
/* ============================================================
   BACANO.MAIL · Cambiar de casilla con un clic
   ------------------------------------------------------------
   Guarda las casillas con las que se ha entrado desde ESTE
   navegador, para poder saltar de una a otra sin escribir la
   contraseña cada vez. Se cifran con la misma llave del servidor
   que usa "mantener la sesión abierta" (recuerdo.php).

   Guardar la contraseña de otra persona no es gratis: quien
   entre al servidor y a la llave puede descifrarla. Por eso una
   casilla sólo se guarda cuando se agrega a propósito, y se
   puede quitar de este equipo cuando se quiera.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/recuerdo.php';

const MJ_CUENTAS_COOKIE = 'bacano_cuentas';
const MJ_CUENTAS_DIAS   = 30;

function mj_cuentas_archivo(string $token): string
{
    return mj_recuerdo_dir() . '/cuentas-' . hash('sha256', $token) . '.json';
}

/** El token de este navegador; se crea al guardar la primera casilla. */
function mj_cuentas_token(bool $crear = false): string
{
    $token = (string) ($_COOKIE[MJ_CUENTAS_COOKIE] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        return $token;
    }
    if (!$crear) {
        return '';
    }
    $token = bin2hex(random_bytes(32));
    $_COOKIE[MJ_CUENTAS_COOKIE] = $token;     // vale ya en esta misma petición
    setcookie(MJ_CUENTAS_COOKIE, $token, [
        'expires'  => time() + MJ_CUENTAS_DIAS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    return $token;
}

/** Todo el archivo, tal cual. */
function mj_cuentas_leer(): array
{
    $token = mj_cuentas_token();
    if ($token === '') { return []; }

    $archivo = mj_cuentas_archivo($token);
    if (!is_readable($archivo)) { return []; }

    $d = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($d) || ($d['expira'] ?? 0) < time()) {
        @unlink($archivo);
        return [];
    }
    return is_array($d['cuentas'] ?? null) ? $d['cuentas'] : [];
}

function mj_cuentas_escribir(array $cuentas): bool
{
    $token = mj_cuentas_token(true);
    if ($token === '') { return false; }

    $archivo = mj_cuentas_archivo($token);

    if (!$cuentas) {                       // sin casillas no hace falta archivo
        @unlink($archivo);
        return true;
    }

    $ok = @file_put_contents($archivo, json_encode([
        'expira'  => time() + MJ_CUENTAS_DIAS * 86400,
        'cuentas' => array_values($cuentas),
    ], JSON_UNESCAPED_UNICODE));

    if ($ok === false) { return false; }
    @chmod($archivo, 0600);
    return true;
}

/** Las casillas guardadas, sin contraseñas: sólo para pintar el menú. */
function mj_cuentas_lista(): array
{
    return array_map(
        fn($c) => ['usuario' => (string) ($c['usuario'] ?? '')],
        array_filter(mj_cuentas_leer(), fn($c) => !empty($c['usuario']))
    );
}

/** Guarda (o actualiza) una casilla con su contraseña cifrada. */
function mj_cuenta_recordar(string $usuario, string $clave): bool
{
    if (!function_exists('openssl_encrypt')) { return false; }

    $usuario = strtolower(trim($usuario));
    if ($usuario === '') { return false; }

    $iv  = random_bytes(12);
    $tag = '';
    $cifrada = openssl_encrypt($clave, 'aes-256-gcm', mj_recuerdo_clave(),
                               OPENSSL_RAW_DATA, $iv, $tag);
    if ($cifrada === false) { return false; }

    $ficha = [
        'usuario'  => $usuario,
        'clave'    => base64_encode($cifrada),
        'iv'       => base64_encode($iv),
        'tag'      => base64_encode($tag),
        'agregada' => date('c'),
    ];

    $cuentas = mj_cuentas_leer();
    foreach ($cuentas as $i => $c) {
        if (strtolower((string) ($c['usuario'] ?? '')) === $usuario) {
            $ficha['agregada'] = (string) ($c['agregada'] ?? $ficha['agregada']);
            $cuentas[$i] = $ficha;
            return mj_cuentas_escribir($cuentas);
        }
    }
    $cuentas[] = $ficha;
    return mj_cuentas_escribir($cuentas);
}

/** La contraseña guardada de una casilla, o null. */
function mj_cuenta_clave(string $usuario): ?string
{
    $usuario = strtolower(trim($usuario));

    foreach (mj_cuentas_leer() as $c) {
        if (strtolower((string) ($c['usuario'] ?? '')) !== $usuario) { continue; }

        $clave = openssl_decrypt(
            base64_decode((string) $c['clave']), 'aes-256-gcm', mj_recuerdo_clave(),
            OPENSSL_RAW_DATA, base64_decode((string) $c['iv']), base64_decode((string) $c['tag'])
        );
        return $clave === false ? null : $clave;
    }
    return null;
}

/** Quita una casilla de este equipo. */
function mj_cuenta_olvidar(string $usuario): bool
{
    $usuario = strtolower(trim($usuario));
    $cuentas = mj_cuentas_leer();
    $quedan  = array_values(array_filter(
        $cuentas,
        fn($c) => strtolower((string) ($c['usuario'] ?? '')) !== $usuario
    ));

    if (count($quedan) === count($cuentas)) { return false; }
    return mj_cuentas_escribir($quedan);
}
