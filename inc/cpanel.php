<?php
/* ============================================================
   BACANO.MAIL · Cambiar la contraseña del buzón
   ------------------------------------------------------------
   Por IMAP no se puede: el protocolo lee y escribe mensajes, no
   administra cuentas. Quien manda sobre las casillas es el panel
   del hosting, así que se le pide a cPanel por su UAPI.

   Hace falta un token de API de cPanel (Seguridad → Administrar
   tokens de API). Se guarda en config.local.php, que no se sube
   al repositorio, y da poder sobre TODAS las casillas del panel:
   si se filtra, hay que revocarlo desde cPanel.
   ============================================================ */

declare(strict_types=1);

/** ¿Está configurado el acceso al panel? */
function mj_cpanel_listo(array $cfg): bool
{
    $c = $cfg['cpanel'] ?? [];
    return trim((string) ($c['host'] ?? '')) !== ''
        && trim((string) ($c['usuario'] ?? '')) !== ''
        && trim((string) ($c['token'] ?? '')) !== '';
}

/**
 * Llama a la UAPI de cPanel.
 * Devuelve ['ok' => bool, 'mensaje' => string, 'datos' => mixed].
 */
function mj_cpanel_uapi(array $cfg, string $modulo, string $funcion, array $args): array
{
    $c    = $cfg['cpanel'] ?? [];
    $host = trim((string) ($c['host'] ?? ''));
    $host = preg_replace('#^https?://#i', '', $host);
    $host = rtrim((string) $host, '/');
    $puerto = (int) ($c['puerto'] ?? 2083) ?: 2083;

    $url = 'https://' . $host . ':' . $puerto . '/execute/'
         . rawurlencode($modulo) . '/' . rawurlencode($funcion);

    $cabeceras = [
        'Authorization: cpanel ' . trim((string) $c['usuario']) . ':' . trim((string) $c['token']),
    ];

    $cuerpo = http_build_query($args);
    $estado = 0;
    $crudo  = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'bacano-mail/' . (function_exists('mj_version') ? mj_version() : '1'),
            // Muchos hostings sirven :2083 con un certificado del propio panel
            CURLOPT_SSL_VERIFYPEER => !empty($c['validar_certificado']),
            CURLOPT_SSL_VERIFYHOST => !empty($c['validar_certificado']) ? 2 : 0,
        ]);
        $crudo  = curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fallo  = curl_error($ch);
        if (PHP_VERSION_ID < 80000) { curl_close($ch); }
        if ($crudo === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo hablar con cPanel. ' . $fallo, 'datos' => null];
        }
    } else {
        if (!ini_get('allow_url_fopen')) {
            return ['ok' => false, 'mensaje' => 'Este servidor no tiene cURL ni permite abrir URLs.', 'datos' => null];
        }
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $cabeceras)
                                 . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $cuerpo,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => !empty($c['validar_certificado']),
                'verify_peer_name' => !empty($c['validar_certificado']),
            ],
        ]);
        $crudo = @file_get_contents($url, false, $ctx);
        foreach ($http_response_header ?? [] as $l) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) { $estado = (int) $m[1]; }
        }
        if ($crudo === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo hablar con cPanel.', 'datos' => null];
        }
    }

    if ($estado === 401 || $estado === 403) {
        return ['ok' => false, 'mensaje' => 'cPanel rechazó el usuario o el token de API.', 'datos' => null];
    }

    $d = json_decode((string) $crudo, true);
    if (!is_array($d)) {
        return ['ok' => false, 'mensaje' => 'cPanel respondió algo que no se entiende (HTTP ' . $estado . ').', 'datos' => null];
    }
    if (!empty($d['errors'])) {
        return ['ok' => false, 'mensaje' => implode(' ', (array) $d['errors']), 'datos' => null];
    }
    if ((int) ($d['status'] ?? 0) !== 1) {
        return ['ok' => false, 'mensaje' => 'cPanel no completó la operación.', 'datos' => null];
    }

    return ['ok' => true, 'mensaje' => 'Listo.', 'datos' => $d['data'] ?? null];
}

/**
 * Cambia la contraseña de una casilla.
 * Se prueba con la parte local y el dominio por separado, que es lo que pide
 * la UAPI; si esa versión de cPanel espera la dirección entera, se reintenta.
 */
function mj_cpanel_cambiar_clave(array $cfg, string $correo, string $nueva): array
{
    if (!mj_cpanel_listo($cfg)) {
        return ['ok' => false, 'mensaje' => 'Falta configurar el acceso a cPanel en instalar.php.'];
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensaje' => 'La dirección no es válida.'];
    }

    [$local, $dominio] = explode('@', $correo, 2);

    $r = mj_cpanel_uapi($cfg, 'Email', 'passwd_pop', [
        'email'    => $local,
        'domain'   => $dominio,
        'password' => $nueva,
    ]);

    if (!$r['ok']) {
        $r2 = mj_cpanel_uapi($cfg, 'Email', 'passwd_pop', [
            'email'    => $correo,
            'password' => $nueva,
        ]);
        if ($r2['ok']) { $r = $r2; }
    }

    return ['ok' => $r['ok'], 'mensaje' => $r['ok'] ? 'Contraseña cambiada.' : $r['mensaje']];
}

/** Comprueba que el token sirve, sin tocar ninguna casilla. */
function mj_cpanel_probar(array $cfg): array
{
    if (!mj_cpanel_listo($cfg)) {
        return ['ok' => false, 'mensaje' => 'Faltan el servidor, el usuario o el token.'];
    }
    $r = mj_cpanel_uapi($cfg, 'Email', 'list_pops', ['api.chunk.enable' => 1, 'api.chunk.size' => 1]);
    return ['ok' => $r['ok'], 'mensaje' => $r['ok'] ? 'Conexión con cPanel correcta.' : $r['mensaje']];
}
