<?php
/* ============================================================
   BACANO.MAIL · Agenda de contactos
   ------------------------------------------------------------
   Cada vez que se envía un mensaje, sus destinatarios quedan
   anotados aquí. Se guarda por casilla, en data/contactos/, así
   cada persona que entra ve su propia agenda y no la de otro.
   ============================================================ */

declare(strict_types=1);

function mj_contactos_archivo(string $buzon): string
{
    return __DIR__ . '/../data/contactos/' . sha1(strtolower(trim($buzon))) . '.json';
}

/**
 * La agenda de una casilla, de lo más reciente a lo más antiguo.
 * Cada contacto: email, nombre, envios, visto (fecha del último).
 */
function mj_contactos(string $buzon): array
{
    $archivo = mj_contactos_archivo($buzon);
    if (!is_readable($archivo)) { return []; }

    $datos = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($datos)) { return []; }

    $lista = [];
    foreach ($datos as $c) {
        $email = strtolower(trim((string) ($c['email'] ?? '')));
        if ($email === '') { continue; }
        $lista[] = [
            'email'  => $email,
            'nombre' => trim((string) ($c['nombre'] ?? '')),
            'envios' => max(1, (int) ($c['envios'] ?? 1)),
            'visto'  => (string) ($c['visto'] ?? ''),
        ];
    }

    usort($lista, fn($a, $b) => strcmp($b['visto'], $a['visto']));
    return $lista;
}

/** Guarda la agenda entera. */
function mj_contactos_guardar(string $buzon, array $lista): bool
{
    $carpeta = dirname(mj_contactos_archivo($buzon));
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0750, true)) { return false; }

    // La agenda es dato personal: que no se pueda pedir por la web.
    if (!is_file($carpeta . '/.htaccess')) {
        @file_put_contents($carpeta . '/.htaccess', "Require all denied\n");
    }

    return file_put_contents(
        mj_contactos_archivo($buzon),
        json_encode(array_values($lista), JSON_UNESCAPED_UNICODE)
    ) !== false;
}

/**
 * Anota un destinatario. Si ya estaba, suma un envío y se queda con el
 * nombre mejor: el que viene escrito gana al que se dedujo del correo.
 */
function mj_contacto_anotar(string $buzon, string $email, string $nombre = '', bool $deducido = false): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return false; }
    // La propia casilla no es un contacto
    if ($email === strtolower(trim($buzon))) { return false; }

    $nombre = trim(mb_substr($nombre, 0, 80));
    $lista  = mj_contactos($buzon);

    foreach ($lista as $i => $c) {
        if ($c['email'] === $email) {
            $lista[$i]['envios'] = $c['envios'] + 1;
            $lista[$i]['visto']  = date('c');
            // Un nombre escrito manda sobre el que se dedujo del correo;
            // uno deducido sólo rellena el hueco.
            if ($nombre !== '' && (!$deducido || $c['nombre'] === '')) {
                $lista[$i]['nombre'] = $nombre;
            }
            return mj_contactos_guardar($buzon, $lista);
        }
    }

    $lista[] = ['email' => $email, 'nombre' => $nombre, 'envios' => 1, 'visto' => date('c')];
    return mj_contactos_guardar($buzon, $lista);
}

/** Anota de una vez lo que venga en un campo "Para"/"CC". */
function mj_contactos_anotar_lista(string $buzon, string $campo): int
{
    $hechos = 0;
    foreach (mj_direcciones($campo) as $d) {
        if (mj_contacto_anotar($buzon, $d['email'], $d['nombre'], $d['deducido'])) { $hechos++; }
    }
    return $hechos;
}

/** Borra un contacto de la agenda. */
function mj_contacto_borrar(string $buzon, string $email): bool
{
    $email = strtolower(trim($email));
    $lista = mj_contactos($buzon);
    $queda = array_values(array_filter($lista, fn($c) => $c['email'] !== $email));

    if (count($queda) === count($lista)) { return false; }
    return mj_contactos_guardar($buzon, $queda);
}

/**
 * Separa "Ana <ana@x.cl>, pedro@y.cl" en direcciones con su nombre.
 * Sin nombre escrito, se deduce uno legible de la parte local.
 */
function mj_direcciones(string $campo): array
{
    $salida = [];
    foreach (preg_split('/[,;]+/', $campo) as $trozo) {
        $trozo = trim($trozo);
        if ($trozo === '') { continue; }

        $nombre = '';
        if (preg_match('/^(.*?)<([^>]+)>$/', $trozo, $m)) {
            $nombre = trim($m[1], " \t\"'");
            $trozo  = trim($m[2]);
        }
        if (!filter_var($trozo, FILTER_VALIDATE_EMAIL)) { continue; }

        $deducido = $nombre === '';
        if ($deducido) {
            $local  = strtok($trozo, '@') ?: '';
            $nombre = ucwords(str_replace(['.', '_', '-'], ' ', $local));
        }
        $salida[] = ['email' => $trozo, 'nombre' => $nombre, 'deducido' => $deducido];
    }
    return $salida;
}

/**
 * La casilla de quien está dentro. Si la sesión no la tiene (instalación
 * sin clave), se cae a la configurada en instalar.php.
 */
function mj_buzon_actual(array $cfg): string
{
    if (function_exists('mj_credenciales') && ($c = mj_credenciales()) !== null) {
        return (string) ($c['usuario'] ?? '');
    }
    return (string) ($cfg['origen']['imap']['usuario'] ?? '');
}
