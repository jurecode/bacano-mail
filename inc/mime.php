<?php
/* ============================================================
   BACANO.MAIL · Armar el cuerpo de un correo
   ------------------------------------------------------------
   Un correo con adjuntos o con firma en HTML deja de ser una
   caja de texto y pasa a ser un árbol de partes. Aquí se arma
   ese árbol, y el cliente SMTP sólo lo escribe.

       mixed ─┬─ related ─┬─ alternative ─┬─ text/plain
              │           │               └─ text/html
              │           └─ imagen incrustada (cid:)
              └─ adjuntos

   Las capas que no hacen falta no se ponen: un mensaje de sólo
   texto sigue siendo un text/plain pelado, como toda la vida.
   ============================================================ */

declare(strict_types=1);

const MJ_ADJUNTO_MAX   = 10485760;   // 10 MB por archivo
const MJ_ADJUNTOS_MAX  = 26214400;   // 25 MB entre todos
const MJ_ADJUNTOS_N    = 10;

/** Una parte con su cabecera y su contenido ya codificado. */
function mj_mime_parte(string $tipo, string $contenido, array $extra = []): string
{
    $cab = ['Content-Type: ' . $tipo, 'Content-Transfer-Encoding: base64'];
    foreach ($extra as $c) { $cab[] = $c; }

    return implode("\r\n", $cab) . "\r\n\r\n"
         . chunk_split(base64_encode($contenido), 76, "\r\n");
}

/** Envuelve varias partes en un multipart y devuelve [cabecera, cuerpo]. */
function mj_mime_multiparte(string $clase, array $partes): array
{
    $linde = 'mj' . bin2hex(random_bytes(12));
    $cuerpo = '';

    foreach ($partes as $p) {
        $cuerpo .= '--' . $linde . "\r\n" . $p . "\r\n";
    }
    $cuerpo .= '--' . $linde . "--\r\n";

    return ['multipart/' . $clase . '; boundary="' . $linde . '"', $cuerpo];
}

/**
 * Arma el mensaje completo.
 *
 *   'texto'        el cuerpo en texto plano (siempre va)
 *   'html'         versión en HTML, si la hay
 *   'incrustadas'  [['cid'=>, 'nombre'=>, 'tipo'=>, 'datos'=>], …]
 *   'adjuntos'     [['nombre'=>, 'tipo'=>, 'datos'=>], …]
 *
 * Devuelve ['cabeceras' => [...], 'cuerpo' => el cuerpo ya codificado].
 */
function mj_mime_mensaje(array $m): array
{
    $texto       = (string) ($m['texto'] ?? '');
    $html        = trim((string) ($m['html'] ?? ''));
    $incrustadas = $m['incrustadas'] ?? [];
    $adjuntos    = $m['adjuntos'] ?? [];

    // 1. El texto, y su gemelo en HTML si lo hay
    $planas = [];   // cabeceras del nivel de arriba, sin multipart de por medio

    if ($html === '') {
        $tipo   = 'text/plain; charset=UTF-8';
        $planas = ['Content-Transfer-Encoding: base64'];
        $cuerpo = chunk_split(base64_encode($texto), 76, "\r\n");
        $nucleo = "Content-Type: $tipo\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $cuerpo;
    } else {
        [$tipo, $cuerpo] = mj_mime_multiparte('alternative', [
            mj_mime_parte('text/plain; charset=UTF-8', $texto),
            mj_mime_parte('text/html; charset=UTF-8', $html),
        ]);
        $nucleo = "Content-Type: $tipo\r\n\r\n" . $cuerpo;
    }

    // 2. Las imágenes que el HTML llama por cid:
    if ($incrustadas) {
        $partes = [$nucleo];
        foreach ($incrustadas as $i) {
            $partes[] = mj_mime_parte(
                $i['tipo'] . '; name="' . mj_mime_nombre($i['nombre']) . '"',
                $i['datos'],
                [
                    'Content-ID: <' . $i['cid'] . '>',
                    'Content-Disposition: inline; filename="' . mj_mime_nombre($i['nombre']) . '"',
                ]
            );
        }
        [$tipo, $cuerpo] = mj_mime_multiparte('related', $partes);
        $planas = [];
        $nucleo = "Content-Type: $tipo\r\n\r\n" . $cuerpo;
    }

    // 3. Los archivos que se adjuntan
    if ($adjuntos) {
        $partes = [$nucleo];
        foreach ($adjuntos as $a) {
            $nombre  = mj_mime_nombre($a['nombre']);
            $partes[] = mj_mime_parte(
                ($a['tipo'] ?: 'application/octet-stream') . '; name="' . $nombre . '"',
                $a['datos'],
                ['Content-Disposition: attachment; filename="' . $nombre . '"']
            );
        }
        [$tipo, $cuerpo] = mj_mime_multiparte('mixed', $partes);
        $planas = [];
        $nucleo = "Content-Type: $tipo\r\n\r\n" . $cuerpo;
    }

    // Las cabeceras van aparte: quien escribe el mensaje las mezcla con las suyas
    [, $resto] = explode("\r\n\r\n", $nucleo, 2);
    return [
        'cabeceras' => array_merge(['Content-Type: ' . $tipo], $planas),
        'cuerpo'    => $resto,
    ];
}

/** Nombre de archivo apto para una cabecera: sin comillas ni saltos. */
function mj_mime_nombre(string $nombre): string
{
    $nombre = str_replace(["\r", "\n", '"'], '', $nombre);
    $nombre = basename($nombre);
    return mb_substr($nombre, 0, 120) ?: 'archivo';
}

/**
 * Normaliza $_FILES['x'] para poder recorrerlo siempre igual, venga de un
 * campo suelto o de uno múltiple.
 */
function mj_archivos_subidos(string $campo): array
{
    $f = $_FILES[$campo] ?? null;
    if (!is_array($f) || !isset($f['name'])) { return []; }

    if (!is_array($f['name'])) {
        $f = array_map(fn($v) => [$v], $f);
    }

    $salida = [];
    foreach ($f['name'] as $i => $nombre) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
        $salida[] = [
            'name'     => (string) $nombre,
            'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''),
            'size'     => (int) ($f['size'][$i] ?? 0),
            'error'    => (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE),
        ];
    }
    return $salida;
}

/** El tipo real del archivo, no el que declare el navegador. */
function mj_tipo_archivo(string $ruta, string $nombre): string
{
    if (function_exists('finfo_open') && is_readable($ruta)) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $t  = $fi ? finfo_file($fi, $ruta) : false;
        // finfo_close quedó obsoleta en PHP 8.5: se libera sola
        if ($fi && PHP_VERSION_ID < 80500) { finfo_close($fi); }
        if (is_string($t) && $t !== '') { return $t; }
    }

    $mapa = [
        'pdf' => 'application/pdf', 'png' => 'image/png', 'gif' => 'image/gif',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
        'txt' => 'text/plain', 'csv' => 'text/csv', 'zip' => 'application/zip',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
        'docx'=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx'=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx'=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    return $mapa[$ext] ?? 'application/octet-stream';
}

/**
 * La versión en HTML del mensaje, con el logo al final de la firma.
 * Se parte por la raya de la firma para no meter la imagen en medio del texto.
 */
function mj_cuerpo_html(string $texto, string $cid): string
{
    $trozos = explode("\n--\n", $texto, 2);
    $cuerpo = htmlspecialchars($trozos[0], ENT_QUOTES, 'UTF-8');
    $firma  = isset($trozos[1]) ? htmlspecialchars($trozos[1], ENT_QUOTES, 'UTF-8') : '';

    $html = '<div style="font:14px/1.6 -apple-system,Segoe UI,Roboto,sans-serif;color:#111">'
          . nl2br($cuerpo);

    if ($firma !== '' || $cid !== '') {
        $html .= '<div style="margin-top:18px;padding-top:14px;border-top:1px solid #e5e5e5">';
        if ($cid !== '') {
            $html .= '<img src="cid:' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8')
                   . '" alt="" style="max-width:200px;height:auto;display:block;margin-bottom:10px">';
        }
        if ($firma !== '') {
            $html .= '<div style="color:#555;font-size:13px">' . nl2br($firma) . '</div>';
        }
        $html .= '</div>';
    }

    return $html . '</div>';
}

/**
 * Lo que de verdad se puede subir. PHP manda por encima de lo que diga esta
 * aplicación: si el hosting tiene upload_max_filesize en 2 MB, prometer 25
 * sólo consigue que el error llegue tarde y sin explicación.
 */
function mj_limite_subida(): int
{
    $aBytes = static function (string $v): int {
        $v = trim($v);
        if ($v === '') { return 0; }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    };

    $topes = array_filter([
        $aBytes((string) ini_get('upload_max_filesize')),
        $aBytes((string) ini_get('post_max_size')),
        MJ_ADJUNTOS_MAX,
    ]);
    return $topes ? (int) min($topes) : MJ_ADJUNTOS_MAX;
}

/** El tope en palabras, para decírselo a quien escribe. */
function mj_limite_legible(): string
{
    $b = mj_limite_subida();
    return $b >= 1048576
        ? round($b / 1048576) . ' MB'
        : round($b / 1024) . ' KB';
}
