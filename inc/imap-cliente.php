<?php
/* ============================================================
   BACANO.MAIL · Cliente IMAP por sockets
   ------------------------------------------------------------
   No necesita la extensión imap de PHP (que ya no viene de serie
   desde PHP 8.4). Sólo requiere OpenSSL, que sí está en todos los
   hostings. Lee la casilla; no la modifica.
   ============================================================ */

declare(strict_types=1);

class MjImap
{
    private $sock = null;
    private int $etiqueta = 0;
    public string $error = '';
    public array $registro = [];

    public function __construct(private array $conf) {}

    /* ---------- conexión ---------- */
    public function conectar(): bool
    {
        $host   = trim((string) ($this->conf['host'] ?? ''));
        $puerto = (int) ($this->conf['puerto'] ?? 993);
        $cif    = (string) ($this->conf['cifrado'] ?? 'ssl');
        $validar = !empty($this->conf['validar_certificado']);

        if ($host === '') { $this->error = 'Falta el servidor IMAP.'; return false; }

        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $validar, 'verify_peer_name' => $validar, 'SNI_enabled' => true,
        ]]);
        $destino = ($cif === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;

        $this->sock = @stream_socket_client($destino, $n, $e, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) { $this->error = "No se pudo conectar con $host:$puerto ($e)."; return false; }
        stream_set_timeout($this->sock, 20);

        $bienvenida = (string) fgets($this->sock, 2048);
        $this->registro[] = '< ' . trim($bienvenida);
        if (!str_starts_with($bienvenida, '* OK')) {
            $this->error = 'El servidor no dio la bienvenida.';
            return false;
        }

        if ($cif === 'tls') {
            $r = $this->orden('STARTTLS');
            if (!$r['ok'] || !@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->error = 'No se pudo cifrar la conexión (STARTTLS).';
                return false;
            }
        }
        return true;
    }

    public function entrar(): bool
    {
        $usuario = (string) ($this->conf['usuario'] ?? '');
        $clave   = (string) ($this->conf['clave'] ?? '');
        if ($usuario === '' || $clave === '') { $this->error = 'Faltan el usuario o la clave.'; return false; }

        $r = $this->orden('LOGIN ' . $this->citar($usuario) . ' ' . $this->citar($clave), true);
        if (!$r['ok']) {
            $this->error = 'El servidor rechazó el usuario o la clave.';
            return false;
        }
        return true;
    }

    /** Abre una carpeta y devuelve cuántos mensajes tiene. */
    public function abrir(string $carpeta = 'INBOX'): int
    {
        $r = $this->orden('SELECT ' . $this->citar($carpeta));
        if (!$r['ok']) { $this->error = "No se pudo abrir la carpeta $carpeta."; return 0; }
        return preg_match('/\* (\d+) EXISTS/i', $r['texto'], $m) ? (int) $m[1] : 0;
    }

    public function cerrar(): void
    {
        if ($this->sock) { @$this->orden('LOGOUT'); @fclose($this->sock); $this->sock = null; }
    }

    /** Carpetas del servidor, con su papel reconocido cuando se puede. */
    public function carpetas(): array
    {
        $r = $this->orden('LIST "" "*"');
        if (!$r['ok']) return [];

        $salida = [];
        foreach (preg_split('/\r?\n/', $r['texto']) as $linea) {
            if (!preg_match('/^\* LIST \(([^)]*)\)\s+\S+\s+(.+)$/i', trim($linea), $m)) continue;

            $banderas = strtolower($m[1]);
            $nombre   = trim($m[2], " \"");
            if (str_contains($banderas, '\noselect')) continue;

            $corto = strtolower(preg_replace('#^INBOX[./]#i', '', $nombre));
            $papel = match (true) {
                strcasecmp($nombre, 'INBOX') === 0        => 'entrada',
                str_contains($banderas, '\sent')          => 'enviados',
                str_contains($banderas, '\drafts')        => 'borrador',
                str_contains($banderas, '\trash')         => 'papelera',
                str_contains($banderas, '\junk')          => 'spam',
                str_contains($banderas, '\archive')       => 'archivo',
                in_array($corto, ['sent', 'sent items', 'enviados'], true) => 'enviados',
                in_array($corto, ['drafts', 'borradores'], true)           => 'borrador',
                in_array($corto, ['trash', 'papelera'], true)              => 'papelera',
                in_array($corto, ['junk', 'spam'], true)                   => 'spam',
                in_array($corto, ['archive', 'archivados'], true)          => 'archivo',
                default => '',
            };
            $salida[] = ['nombre' => $nombre, 'papel' => $papel];
        }
        return $salida;
    }

    /** Guarda una copia en una carpeta del servidor (los enviados, por ejemplo). */
    public function guardar(string $carpeta, string $mensaje, string $banderas = '\\Seen'): bool
    {
        $largo = strlen($mensaje);
        $etq   = 'a' . str_pad((string) (++$this->etiqueta), 3, '0', STR_PAD_LEFT);
        fwrite($this->sock, "$etq APPEND " . $this->citar($carpeta) . " ($banderas) {" . $largo . "}\r\n");

        $respuesta = (string) fgets($this->sock, 1024);
        if (!str_starts_with($respuesta, '+')) {
            return false;
        }
        fwrite($this->sock, $mensaje . "\r\n");

        while (($linea = fgets($this->sock, 1024)) !== false) {
            if (preg_match('/^' . $etq . ' (OK|NO|BAD)/i', $linea, $m)) {
                return strtoupper($m[1]) === 'OK';
            }
        }
        return false;
    }

    /* ---------- lectura ---------- */

    /** Cabeceras de los últimos $limite mensajes, del más nuevo al más antiguo. */
    public function cabeceras(int $total, int $limite = 50): array
    {
        if ($total < 1) return [];
        $desde = max(1, $total - $limite + 1);

        $r = $this->orden("FETCH $desde:$total (UID FLAGS INTERNALDATE "
                        . "BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE)])");
        if (!$r['ok']) { $this->error = 'No se pudieron leer las cabeceras.'; return []; }

        $mensajes = [];
        foreach (preg_split('/^\* \d+ FETCH /m', $r['texto']) as $trozo) {
            if (trim($trozo) === '') continue;

            $uid    = preg_match('/UID (\d+)/i', $trozo, $m) ? $m[1] : null;
            if ($uid === null) continue;
            $banderas = preg_match('/FLAGS \(([^)]*)\)/i', $trozo, $m) ? $m[1] : '';
            $cab      = $this->cabecerasDelTrozo($trozo);

            $mensajes[] = [
                'uid'        => (int) $uid,
                'de'         => $this->persona($cab['from'] ?? ''),
                'para'       => array_map([$this, 'persona'], $this->separar($cab['to'] ?? '')),
                'cc'         => array_map([$this, 'persona'], $this->separar($cab['cc'] ?? '')),
                'asunto'     => $this->decodificar($cab['subject'] ?? '') ?: '(sin asunto)',
                'fecha'      => $this->fecha($cab['date'] ?? ''),
                'leido'      => stripos($banderas, '\\Seen') !== false,
                'destacado'  => stripos($banderas, '\\Flagged') !== false ? '#F59E0B' : false,
                'respondido' => stripos($banderas, '\\Answered') !== false,
            ];
        }
        usort($mensajes, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return $mensajes;
    }

    /** Cuerpo de un mensaje (texto plano o HTML, ya decodificado). */
    public function cuerpo(int $uid): string
    {
        $r = $this->orden("UID FETCH $uid (BODY.PEEK[])");
        if (!$r['ok']) return '';

        $crudo = $r['texto'];

        // La respuesta trae el mensaje como "literal": {N} y a continuación
        // exactamente N bytes. Hay que cortar por N, o se cuela el ")" y la
        // línea de cierre del servidor.
        if (preg_match('/\{(\d+)\}\r?\n/', $crudo, $m, PREG_OFFSET_CAPTURE)) {
            $inicio = $m[0][1] + strlen($m[0][0]);
            return $this->extraerCuerpo(substr($crudo, $inicio, (int) $m[1][0]));
        }

        $corte = strpos($crudo, "\r\n");
        return $corte === false ? '' : $this->extraerCuerpo(substr($crudo, $corte + 2));
    }

    /* ---------- MIME ---------- */

    private function extraerCuerpo(string $crudo, int $nivel = 0): string
    {
        if ($nivel > 4) return '';                       // corta anidamientos absurdos

        [$cab, $cuerpo] = $this->partir($crudo);
        $tipo = strtolower($cab['content-type'] ?? 'text/plain');

        if (str_contains($tipo, 'multipart/') && preg_match('/boundary="?([^";\r\n]+)"?/i', $tipo, $m)) {
            $partes = explode('--' . $m[1], $cuerpo);
            array_shift($partes);                        // preámbulo: no es una parte

            $html = $texto = '';
            foreach ($partes as $parte) {
                $parte = ltrim($parte, "\r\n");
                if ($parte === '' || str_starts_with($parte, '--')) continue;   // epílogo

                [$pc, $pcuerpo] = $this->partir($parte);
                $ptipo = strtolower($pc['content-type'] ?? 'text/plain');

                if (str_contains($ptipo, 'multipart/')) {
                    $html = $html ?: $this->extraerCuerpo($parte, $nivel + 1);
                    continue;
                }
                if (str_contains($ptipo, 'attachment') || stripos($pc['content-disposition'] ?? '', 'attachment') === 0) {
                    continue;                            // los adjuntos no van en el cuerpo
                }

                $contenido = $this->desarmar($pcuerpo, $pc);
                if (str_contains($ptipo, 'text/html'))       { $html  = $html  ?: $contenido; }
                elseif (str_contains($ptipo, 'text/plain'))  { $texto = $texto ?: $contenido; }
            }

            if ($html !== '')  return $html;
            if ($texto !== '') return '<p>' . nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')) . '</p>';
            return '';
        }

        $contenido = $this->desarmar($cuerpo, $cab);
        return str_contains($tipo, 'text/html')
            ? $contenido
            : '<p>' . nl2br(htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    private function partir(string $crudo): array
    {
        $corte = strpos($crudo, "\r\n\r\n");
        if ($corte === false) { $corte = strpos($crudo, "\n\n"); }
        if ($corte === false) return [[], $crudo];

        $cabeceras = substr($crudo, 0, $corte);
        $cuerpo    = substr($crudo, $corte + ($crudo[$corte] === "\r" ? 4 : 2));
        return [$this->parsearCabeceras($cabeceras), $cuerpo];
    }

    private function parsearCabeceras(string $texto): array
    {
        $texto = preg_replace("/\r\n[ \t]+/", ' ', $texto);   // líneas continuadas
        $out = [];
        foreach (preg_split("/\r?\n/", (string) $texto) as $linea) {
            if (preg_match('/^([A-Za-z\-]+):\s*(.*)$/', $linea, $m)) {
                $out[strtolower($m[1])] = trim($m[2]);
            }
        }
        return $out;
    }

    private function cabecerasDelTrozo(string $trozo): array
    {
        $inicio = strpos($trozo, "\r\n");
        return $inicio === false ? [] : $this->parsearCabeceras(substr($trozo, $inicio));
    }

    private function desarmar(string $cuerpo, array $cab): string
    {
        $cod = strtolower(trim($cab['content-transfer-encoding'] ?? ''));
        if ($cod === 'base64')            { $cuerpo = (string) base64_decode($cuerpo, true); }
        elseif ($cod === 'quoted-printable') { $cuerpo = quoted_printable_decode($cuerpo); }

        if (preg_match('/charset="?([\w\-]+)"?/i', $cab['content-type'] ?? '', $m)) {
            $juego = strtoupper($m[1]);
            if ($juego !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $cuerpo = (string) @mb_convert_encoding($cuerpo, 'UTF-8', $juego);
            }
        }
        return trim($cuerpo);
    }

    /* ---------- utilidades ---------- */

    public function decodificar(string $s): string
    {
        if ($s === '') return '';
        $s = function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($s) : $s;
        return trim($s);
    }

    private function persona(string $bruto): array
    {
        $bruto = trim($this->decodificar($bruto));
        if ($bruto === '') return ['nombre' => '', 'email' => '', 'avatar' => ''];

        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $bruto, $m)) {
            return ['nombre' => trim($m[1], " \"'"), 'email' => trim($m[2]), 'avatar' => ''];
        }
        return ['nombre' => strtok($bruto, '@') ?: $bruto, 'email' => $bruto, 'avatar' => ''];
    }

    private function separar(string $lista): array
    {
        if (trim($lista) === '') return [];
        return array_map('trim', preg_split('/,(?![^<]*>)/', $lista) ?: []);
    }

    private function fecha(string $bruto): string
    {
        $t = strtotime($bruto);
        return date('c', $t ?: time());
    }

    private function citar(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    /** Envía una orden y devuelve ['ok'=>bool,'texto'=>string] */
    private function orden(string $orden, bool $secreto = false): array
    {
        $etq = 'a' . str_pad((string) (++$this->etiqueta), 3, '0', STR_PAD_LEFT);
        $this->registro[] = '> ' . ($secreto ? $etq . ' LOGIN ········' : $etq . ' ' . $orden);
        fwrite($this->sock, "$etq $orden\r\n");

        $texto = '';
        while (($linea = fgets($this->sock, 8192)) !== false) {
            $texto .= $linea;
            if (preg_match('/^' . $etq . ' (OK|NO|BAD)/i', $linea, $m)) {
                $this->registro[] = '< ' . trim($linea);
                return ['ok' => strtoupper($m[1]) === 'OK', 'texto' => $texto];
            }
        }
        return ['ok' => false, 'texto' => $texto];
    }
}

/**
 * Prueba de conexión para el instalador. Siempre por sockets: así funciona
 * con o sin la extensión imap, y el diálogo con el servidor queda a la vista.
 * Devuelve ['ok'=>bool,'mensaje'=>string,'registro'=>array]
 */
function mj_probar_imap(array $conf): array
{
    $imap = new MjImap($conf);

    if (!$imap->conectar()) {
        return ['ok' => false, 'mensaje' => $imap->error, 'registro' => $imap->registro];
    }
    if (!$imap->entrar()) {
        $imap->cerrar();
        return ['ok' => false, 'mensaje' => $imap->error, 'registro' => $imap->registro];
    }

    $total = $imap->abrir((string) ($conf['carpeta'] ?? 'INBOX'));
    $imap->cerrar();

    return ['ok' => true, 'registro' => $imap->registro,
            'mensaje' => "Conexión correcta: $total mensajes en la casilla."];
}
