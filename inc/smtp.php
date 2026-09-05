<?php
/* ============================================================
   BACANO.MAIL · Envío por SMTP
   ------------------------------------------------------------
   Cliente propio, sin librerías externas: sirve en cualquier
   hosting con PHP y OpenSSL. Los datos de la cuenta se guardan
   desde el panel, en data/ (nunca en el repositorio).
   ============================================================ */

declare(strict_types=1);

/** Diálogo SMTP. Devuelve ['ok'=>bool,'mensaje'=>string,'registro'=>array] */
function mj_smtp_enviar(array $c, string $para, string $asunto, string $cuerpo, string $responderA = '', string $nombreResponde = '', string $respondeA = '', array $extras = []): array
{
    require_once __DIR__ . '/mime.php';
    $registro = [];
    $falla = function (string $m) use (&$registro): array {
        return ['ok' => false, 'mensaje' => $m, 'registro' => $registro];
    };

    $servidor = trim((string) ($c['servidor'] ?? ''));
    $puerto   = (int) ($c['puerto'] ?? 465);
    $usuario  = trim((string) ($c['usuario'] ?? ''));
    $clave    = (string) ($c['clave'] ?? '');
    $desde    = trim((string) ($c['remitente'] ?? '')) ?: $usuario;
    // Sin nombre configurado, se deduce de la dirección. Poner aquí el de la
    // instalación haría que todas las casillas firmaran igual.
    $nombre   = trim((string) ($c['remitente_nombre'] ?? ''));
    if ($nombre === '') {
        $nombre = ucfirst(strtok($desde, '@') ?: $desde);
    }

    if ($servidor === '' || $usuario === '' || $clave === '') {
        return $falla('Faltan datos de la cuenta de correo.');
    }
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return $falla('La dirección de destino no es válida.');
    }

    /* --- conexión --- */
    $seguro   = ($c['seguridad'] ?? 'ssl') === 'ssl';
    $destino  = ($seguro ? 'ssl://' : 'tcp://') . $servidor . ':' . $puerto;
    $contexto = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'SNI_enabled'       => true,
    ]]);

    $sock = @stream_socket_client($destino, $errno, $error, 15, STREAM_CLIENT_CONNECT, $contexto);
    if (!$sock) {
        // hay hostings con certificados que no validan: se reintenta sin verificar
        $contexto = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $sock = @stream_socket_client($destino, $errno, $error, 15, STREAM_CLIENT_CONNECT, $contexto);
    }
    if (!$sock) {
        return $falla('No se pudo conectar con ' . $servidor . ':' . $puerto . ' (' . $error . ').');
    }
    stream_set_timeout($sock, 20);

    $leer = function () use ($sock, &$registro): array {
        $texto = '';
        while (($linea = fgets($sock, 1024)) !== false) {
            $texto .= $linea;
            if (strlen($linea) < 4 || $linea[3] !== '-') { break; }
        }
        $registro[] = '< ' . trim($texto);
        return [(int) substr(trim($texto), 0, 3), trim($texto)];
    };
    $decir = function (string $orden, bool $secreto = false) use ($sock, &$registro): void {
        $registro[] = '> ' . ($secreto ? '········' : $orden);
        fwrite($sock, $orden . "\r\n");
    };

    [$codigo] = $leer();
    if ($codigo !== 220) { fclose($sock); return $falla('El servidor no dio la bienvenida.'); }

    $saludo = 'madeja.' . preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'local');
    $decir('EHLO ' . $saludo);
    [$codigo, $capacidades] = $leer();
    if ($codigo !== 250) { fclose($sock); return $falla('El servidor rechazó el saludo.'); }

    /* --- STARTTLS cuando no es puerto seguro directo --- */
    if (!$seguro && stripos($capacidades, 'STARTTLS') !== false) {
        $decir('STARTTLS');
        [$codigo] = $leer();
        if ($codigo === 220) {
            $metodo = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!@stream_socket_enable_crypto($sock, true, $metodo)) {
                fclose($sock);
                return $falla('No se pudo cifrar la conexión (STARTTLS).');
            }
            $decir('EHLO ' . $saludo);
            $leer();
        }
    }

    /* --- autenticación --- */
    $decir('AUTH LOGIN');
    [$codigo] = $leer();
    if ($codigo !== 334) { fclose($sock); return $falla('El servidor no aceptó AUTH LOGIN.'); }

    $decir(base64_encode($usuario), true);
    [$codigo] = $leer();
    if ($codigo !== 334) { fclose($sock); return $falla('El servidor no aceptó el usuario.'); }

    $decir(base64_encode($clave), true);
    [$codigo, $texto] = $leer();
    if ($codigo !== 235) {
        fclose($sock);
        return $falla('Usuario o contraseña incorrectos. ' . $texto);
    }

    /* --- sobre --- */
    $decir('MAIL FROM:<' . $desde . '>');
    [$codigo, $texto] = $leer();
    if ($codigo !== 250) { fclose($sock); return $falla('El servidor rechazó el remitente. ' . $texto); }

    $decir('RCPT TO:<' . $para . '>');
    [$codigo, $texto] = $leer();
    if ($codigo !== 250 && $codigo !== 251) { fclose($sock); return $falla('El servidor rechazó el destinatario. ' . $texto); }

    /* --- contenido --- */
    $decir('DATA');
    [$codigo] = $leer();
    if ($codigo !== 354) { fclose($sock); return $falla('El servidor no aceptó el envío del texto.'); }

    $codificar = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';

    // Un solo identificador para el envío y para la copia en Enviados: es lo
    // que permite que la respuesta quede en la misma conversación.
    // El dominio del identificador tiene que ser el del remitente, no el del
    // servidor: los filtros comparan los dos y una discordancia suma puntos
    // para acabar en spam. mail.madejalex.cl → madejalex.cl.
    $dominio   = strstr($desde, '@') !== false ? substr(strrchr($desde, '@'), 1) : $servidor;
    $idMensaje = bin2hex(random_bytes(12)) . '@' . $dominio;

    // El cuerpo puede ser texto pelado o un árbol con firma en HTML y adjuntos
    $pieza = mj_mime_mensaje([
        'texto'       => $cuerpo,
        'html'        => (string) ($extras['html'] ?? ''),
        'incrustadas' => $extras['incrustadas'] ?? [],
        'adjuntos'    => $extras['adjuntos'] ?? [],
    ]);

    $cabeceras = array_merge([
        'Date: ' . date('r'),
        'From: ' . $codificar($nombre) . ' <' . $desde . '>',
        'To: <' . $para . '>',
        'Subject: ' . $codificar($asunto),
        'Message-ID: <' . $idMensaje . '>',
        'MIME-Version: 1.0',
    ], $pieza['cabeceras'], [
        'X-Mailer: Madeja',
    ]);
    if ($respondeA !== '') {
        $cabeceras[] = 'In-Reply-To: <' . $respondeA . '>';
        $cabeceras[] = 'References: <' . $respondeA . '>';
    }
    if ($responderA !== '' && filter_var($responderA, FILTER_VALIDATE_EMAIL)) {
        $cabeceras[] = 'Reply-To: ' . ($nombreResponde !== '' ? $codificar($nombreResponde) . ' ' : '')
                     . '<' . $responderA . '>';
    }

    $mensaje = implode("\r\n", $cabeceras) . "\r\n\r\n" . $pieza['cuerpo'];

    // Una línea que empiece por un punto cortaría el envío en seco
    $mensaje = preg_replace('/^\./m', '..', $mensaje);

    fwrite($sock, $mensaje . "\r\n.\r\n");
    $registro[] = '> (mensaje)';
    [$codigo, $texto] = $leer();

    $decir('QUIT');
    @fclose($sock);

    return $codigo === 250
        ? ['ok' => true, 'mensaje' => 'Correo enviado.', 'registro' => $registro,
           'id_mensaje' => $idMensaje, 'mime' => $pieza]
        : $falla('El servidor no aceptó el mensaje. ' . $texto);
}
