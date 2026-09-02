<?php
/* ============================================================
   BACANO.MAIL · Mantener la sesión abierta
   ------------------------------------------------------------
   La sesión de PHP no sirve para esto: el servidor borra sus
   datos a los 24 minutos. Se guarda un vale con la casilla y su
   clave cifrada, y el navegador se queda con el token que lo
   abre. Es lo mismo que hace cualquier webmail con "recordarme".

   Quien tenga acceso al servidor y al archivo de clave puede
   descifrarlo: por eso la opción viene apagada y se avisa.
   ============================================================ */

declare(strict_types=1);

const MJ_RECUERDO_COOKIE = 'bacano_recuerdo';
const MJ_RECUERDO_DIAS   = 30;

function mj_recuerdo_dir(): string
{
    $dir = __DIR__ . '/../data/sesiones';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

/** Clave del servidor para cifrar. Se crea sola la primera vez. */
function mj_recuerdo_clave(): string
{
    $archivo = mj_recuerdo_dir() . '/clave.bin';
    if (!is_file($archivo)) {
        @file_put_contents($archivo, random_bytes(32));
        @chmod($archivo, 0600);
    }
    $clave = (string) @file_get_contents($archivo);
    return strlen($clave) === 32 ? $clave : str_repeat("\0", 32);
}

function mj_recuerdo_archivo(string $token): string
{
    return mj_recuerdo_dir() . '/' . hash('sha256', $token) . '.json';
}

/** Guarda el vale y devuelve el token que va al navegador. */
function mj_recuerdo_crear(string $usuario, string $clave): string
{
    if (!function_exists('openssl_encrypt')) {
        return '';
    }

    $token = bin2hex(random_bytes(32));
    $iv    = random_bytes(12);
    $tag   = '';

    $cifrada = openssl_encrypt($clave, 'aes-256-gcm', mj_recuerdo_clave(),
                               OPENSSL_RAW_DATA, $iv, $tag);
    if ($cifrada === false) {
        return '';
    }

    $ok = @file_put_contents(mj_recuerdo_archivo($token), json_encode([
        'usuario' => $usuario,
        'clave'   => base64_encode($cifrada),
        'iv'      => base64_encode($iv),
        'tag'     => base64_encode($tag),
        'expira'  => time() + MJ_RECUERDO_DIAS * 86400,
    ], JSON_UNESCAPED_UNICODE));

    if ($ok === false) {
        return '';
    }
    @chmod(mj_recuerdo_archivo($token), 0600);

    mj_recuerdo_limpiar();
    return $token;
}

/** Lee el vale del navegador. Devuelve ['usuario','clave'] o null. */
function mj_recuerdo_leer(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $archivo = mj_recuerdo_archivo($token);
    if (!is_readable($archivo)) {
        return null;
    }

    $d = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($d) || ($d['expira'] ?? 0) < time()) {
        @unlink($archivo);
        return null;
    }

    $clave = openssl_decrypt(
        base64_decode((string) $d['clave']), 'aes-256-gcm', mj_recuerdo_clave(),
        OPENSSL_RAW_DATA, base64_decode((string) $d['iv']), base64_decode((string) $d['tag'])
    );
    if ($clave === false) {
        @unlink($archivo);
        return null;
    }

    return ['usuario' => (string) $d['usuario'], 'clave' => $clave];
}

function mj_recuerdo_borrar(string $token): void
{
    if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
        @unlink(mj_recuerdo_archivo($token));
    }
}

/** Barre los vales caducados, de vez en cuando. */
function mj_recuerdo_limpiar(): void
{
    foreach (glob(mj_recuerdo_dir() . '/*.json') ?: [] as $archivo) {
        $d = json_decode((string) @file_get_contents($archivo), true);
        if (!is_array($d) || ($d['expira'] ?? 0) < time()) {
            @unlink($archivo);
        }
    }
}

/** Pone o quita la cookie del navegador. */
function mj_recuerdo_cookie(string $token): void
{
    setcookie(MJ_RECUERDO_COOKIE, $token, [
        'expires'  => $token === '' ? time() - 3600 : time() + MJ_RECUERDO_DIAS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
}
