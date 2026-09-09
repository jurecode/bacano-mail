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
    private ?array $capacidades = null;
    private array $adjuntosVistos = [];
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

    public bool $solo_lectura = false;

    /** Abre una carpeta y devuelve cuántos mensajes tiene. */
    public function abrir(string $carpeta = 'INBOX'): int
    {
        $r = $this->orden('SELECT ' . $this->citar($carpeta));
        if (!$r['ok']) { $this->error = "No se pudo abrir la carpeta $carpeta."; return 0; }

        // Si el servidor la abre en sólo lectura, ningún STORE se guardará
        $this->solo_lectura = stripos($r['texto'], '[READ-ONLY]') !== false;

        return preg_match('/\* (\d+) EXISTS/i', $r['texto'], $m) ? (int) $m[1] : 0;
    }

    /** Las banderas que tiene ahora mismo un mensaje, según el servidor. */
    public function banderas(int $uid): ?string
    {
        $r = $this->orden("UID FETCH $uid (FLAGS)");
        if (!$r['ok']) return null;
        return preg_match('/FLAGS \(([^)]*)\)/i', $r['texto'], $m) ? trim($m[1]) : null;
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
                // Por nombre, cuando el servidor no marca la carpeta. Cada
                // programa de correo crea las suyas con el nombre de su idioma,
                // así que una casilla vieja puede tener cualquiera de estos.
                in_array($corto, ['sent', 'sent items', 'sent messages', 'enviados',
                                  'elementos enviados', 'correo enviado'], true)  => 'enviados',
                in_array($corto, ['drafts', 'borradores', 'borrador'], true)      => 'borrador',
                in_array($corto, ['trash', 'papelera', 'borrados', 'deleted',
                                  'deleted items', 'deleted messages',
                                  'elementos eliminados', 'papelera de reciclaje'], true) => 'papelera',
                in_array($corto, ['junk', 'spam', 'junk e-mail', 'correo no deseado',
                                  'no deseado'], true)                            => 'spam',
                in_array($corto, ['archive', 'archivados', 'archivo'], true)      => 'archivo',
                default => '',
            };
            // Si el papel viene de la bandera del servidor es más fiable que
            // si se dedujo del nombre: hay casillas con dos carpetas que
            // parecen lo mismo (INBOX.Junk y INBOX.spam, por ejemplo).
            $porBandera = $papel !== '' && (
                strcasecmp($nombre, 'INBOX') === 0
                || preg_match('/\\\\(sent|drafts|trash|junk|archive)/i', $banderas) === 1
            );
            $salida[] = ['nombre' => $nombre, 'papel' => $papel, 'oficial' => $porBandera];
        }
        return $salida;
    }

    /**
     * Lo que este servidor dice saber hacer. Se pregunta una vez.
     * Importa sobre todo MOVE (mover de una carpeta a otra en un paso) y
     * UIDPLUS (purgar un mensaje concreto en vez de la carpeta entera).
     */
    public function capacidades(): array
    {
        if ($this->capacidades !== null) { return $this->capacidades; }

        $r = $this->orden('CAPABILITY');
        $lista = [];
        if ($r['ok'] && preg_match('/^\* CAPABILITY (.+)$/mi', $r['texto'], $m)) {
            $lista = array_map('strtoupper', preg_split('/\s+/', trim($m[1])) ?: []);
        }
        return $this->capacidades = $lista;
    }

    public function sabe(string $que): bool
    {
        return in_array(strtoupper($que), $this->capacidades(), true);
    }

    /** Los UID de la carpeta abierta que están marcados como borrados. */
    public function marcados_borrados(): array
    {
        $r = $this->orden('UID SEARCH DELETED');
        if ($r['ok'] && preg_match('/^\* SEARCH([0-9 ]*)$/mi', $r['texto'], $m)) {
            return array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1])) ?: [])));
        }
        return [];
    }

    /* --------------------------------------------------------
       Administrar carpetas
       -------------------------------------------------------- */

    /** Crea una carpeta y la deja suscrita, que si no algunos clientes no la ven. */
    public function crear(string $nombre): bool
    {
        $r = $this->orden('CREATE ' . $this->entrecomillar($nombre));
        if (!$r['ok']) {
            $this->error = $this->motivo($r['texto']) ?: 'El servidor no dejó crear la carpeta.';
            return false;
        }
        $this->orden('SUBSCRIBE ' . $this->entrecomillar($nombre));
        return true;
    }

    public function renombrar(string $de, string $a): bool
    {
        $r = $this->orden('RENAME ' . $this->entrecomillar($de) . ' ' . $this->entrecomillar($a));
        if (!$r['ok']) {
            $this->error = $this->motivo($r['texto']) ?: 'El servidor no dejó cambiar el nombre.';
            return false;
        }
        $this->orden('SUBSCRIBE ' . $this->entrecomillar($a));
        return true;
    }

    public function eliminar(string $nombre): bool
    {
        $this->orden('UNSUBSCRIBE ' . $this->entrecomillar($nombre));
        $r = $this->orden('DELETE ' . $this->entrecomillar($nombre));
        if (!$r['ok']) {
            $this->error = $this->motivo($r['texto']) ?: 'El servidor no dejó borrar la carpeta.';
            return false;
        }
        return true;
    }

    /** El separador de jerarquía que use este servidor: "." en Dovecot, "/" en otros. */
    public function separador(): string
    {
        $r = $this->orden('LIST "" ""');
        if ($r['ok'] && preg_match('/^\* LIST \([^)]*\)\s+"?([^"\s]+)"?/mi', $r['texto'], $m)) {
            return $m[1] === 'NIL' ? '.' : $m[1];
        }
        return '.';
    }

    /** Un nombre de carpeta, listo para meter en una orden. */
    private function entrecomillar(string $nombre): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $nombre) . '"';
    }

    /** El texto que devuelve el servidor cuando dice que no. */
    private function motivo(string $texto): string
    {
        if (preg_match('/^\S+\s+(?:NO|BAD)\s+(.+)$/mi', trim($texto), $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /** Marca banderas en un mensaje: leído, destacado… */
    public function marcar(int $uid, string $banderas = '\\Seen', bool $quitar = false): bool
    {
        if ($this->solo_lectura) {
            $this->error = 'El servidor abrió la carpeta en sólo lectura.';
            return false;
        }

        $signo = $quitar ? '-' : '+';
        $r = $this->orden("UID STORE $uid {$signo}FLAGS ($banderas)");
        if (!$r['ok']) {
            $this->error = 'El servidor rechazó la marca.';
            return false;
        }

        // Un STORE sobre un UID que no existe en esta carpeta también responde
        // OK y no hace nada: hay que releer para saber si quedó de verdad.
        $ahora = $this->banderas($uid);
        if ($ahora === null) {
            $this->error = 'El mensaje no está en esta carpeta (UID ' . $uid . ').';
            return false;
        }

        $puesta = stripos($ahora, trim($banderas, '\\')) !== false;
        if ($quitar ? $puesta : !$puesta) {
            $this->error = 'La marca no se guardó en el servidor.';
            return false;
        }
        return true;
    }

    /** Borra de verdad: marca \Deleted y purga. No hay vuelta atrás. */
    public function borrar(int $uid): bool
    {
        if ($this->solo_lectura) {
            $this->error = 'El servidor abrió la carpeta en sólo lectura.';
            return false;
        }
        if (!$this->orden("UID STORE $uid +FLAGS (\\Deleted)")['ok']) {
            $this->error = 'El servidor rechazó el borrado.';
            return false;
        }
        return $this->purgar($uid);
    }

    /** Mueve un mensaje a otra carpeta. Usa MOVE, o COPY + borrar si no está. */
    public function mover(int $uid, string $destino): bool
    {
        if ($this->solo_lectura) {
            $this->error = 'El servidor abrió la carpeta en sólo lectura.';
            return false;
        }

        if ($this->sabe('MOVE')) {
            $r = $this->orden('UID MOVE ' . $uid . ' ' . $this->citar($destino));
            if (!$r['ok']) {
                $this->error = $this->motivo($r['texto']) ?: 'El servidor rechazó mover el mensaje.';
                return false;
            }
            return $this->comprobar_ido($uid, false);
        }

        // Sin MOVE: copiar, marcar borrado y purgar. Los tres pasos tienen que
        // salir bien; si sólo sale el primero, el mensaje queda duplicado —en
        // la papelera y en su carpeta— y la persona ve que "no se eliminó".
        $c = $this->orden('UID COPY ' . $uid . ' ' . $this->citar($destino));
        if (!$c['ok']) {
            $this->error = $this->motivo($c['texto']) ?: 'El servidor no dejó copiar el mensaje.';
            return false;
        }
        if (!$this->orden("UID STORE $uid +FLAGS (\\Deleted)")['ok']) {
            $this->error = 'Se copió a la carpeta de destino, pero no se pudo quitar del origen.';
            return false;
        }
        if (!$this->purgar($uid)) {
            // El mensaje queda marcado como borrado: la mayoría de programas ya
            // no lo enseñan, pero conviene decirlo en vez de cantar victoria.
            $this->error = 'Se movió, pero el servidor no purgó el original: '
                         . 'puede seguir apareciendo hasta que se compacte la carpeta.';
            return false;
        }
        return $this->comprobar_ido($uid, true);
    }

    /**
     * ¿De verdad se fue de esta carpeta? Un "OK" no es prueba de nada: hay
     * servidores que contestan que sí y dejan el mensaje donde estaba, y
     * entonces la persona ve "Mensaje eliminado" y lo sigue teniendo delante.
     */
    private function comprobar_ido(int $uid, bool $valeMarcado): bool
    {
        $r = $this->orden("UID FETCH $uid (FLAGS)");
        $sigue = $r['ok'] && preg_match('/UID ' . $uid . '\b/i', $r['texto']);

        if (!$sigue) { return true; }

        // Por el camino antiguo —copiar, marcar y purgar— dejarlo marcado como
        // borrado es el resultado esperado: los programas ya no lo enseñan.
        // Tras un MOVE, en cambio, tiene que haber desaparecido.
        if ($valeMarcado && preg_match('/\\\\Deleted/i', $r['texto'])) { return true; }

        $this->error = 'El servidor aceptó la orden pero el mensaje sigue en la carpeta.';
        return false;
    }

    /**
     * Purga un mensaje concreto. Sin UIDPLUS hay que usar EXPUNGE a secas, que
     * se lleva TODO lo marcado como borrado en la carpeta: eso puede destruir
     * correo que otro programa dejó marcado. Sólo se hace si el único marcado
     * es el nuestro.
     */
    private function purgar(int $uid): bool
    {
        if ($this->sabe('UIDPLUS')) {
            return $this->orden("UID EXPUNGE $uid")['ok'];
        }

        $marcados = $this->marcados_borrados();
        if ($marcados && $marcados !== [$uid]) {
            $this->error = 'Este servidor no sabe purgar un mensaje suelto y hay otros '
                         . count($marcados) . ' marcados como borrados. No se purga nada '
                         . 'para no llevárselos por delante.';
            return false;
        }
        return $this->orden('EXPUNGE')['ok'];
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
                        . "BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)])");
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
                'id_mensaje' => trim((string) ($cab['message-id'] ?? ''), " <>"),
                'responde_a' => trim((string) ($cab['in-reply-to'] ?? ''), " <>"),
                'referencias'=> trim((string) ($cab['references'] ?? '')),
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
        return $this->leer($uid)['cuerpo'];
    }

    /** El mensaje entero: cuerpo ya en HTML y la lista de sus adjuntos. */
    public function leer(int $uid): array
    {
        $crudo = $this->crudo($uid);
        if ($crudo === '') { return ['cuerpo' => '', 'adjuntos' => []]; }

        $this->adjuntosVistos = [];
        $cuerpo = $this->extraerCuerpo($crudo);
        return ['cuerpo' => $cuerpo, 'adjuntos' => $this->adjuntosVistos];
    }

    /** Un adjunto concreto, para descargarlo. */
    public function adjunto(int $uid, int $indice): ?array
    {
        $todo = $this->leer($uid);
        foreach ($todo['adjuntos'] as $a) {
            if ($a['i'] === $indice) { return $a; }
        }
        return null;
    }

    /** El mensaje tal cual lo manda el servidor. */
    private function crudo(int $uid): string
    {
        $r = $this->orden("UID FETCH $uid (BODY.PEEK[])");
        if (!$r['ok']) return '';

        $crudo = $r['texto'];

        // La respuesta trae el mensaje como "literal": {N} y a continuación
        // exactamente N bytes. Hay que cortar por N, o se cuela el ")" y la
        // línea de cierre del servidor.
        if (preg_match('/\{(\d+)\}\r?\n/', $crudo, $m, PREG_OFFSET_CAPTURE)) {
            $inicio = $m[0][1] + strlen($m[0][0]);
            return substr($crudo, $inicio, (int) $m[1][0]);
        }

        $corte = strpos($crudo, "\r\n");
        return $corte === false ? '' : substr($crudo, $corte + 2);
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
                // Los adjuntos no van en el cuerpo, pero sí se apuntan: antes
                // se descartaban aquí y por eso no aparecían por ninguna parte.
                $nombre = $this->nombreDeParte($pc);
                $disp   = strtolower($pc['content-disposition'] ?? '');

                if ($nombre !== '' || str_starts_with($disp, 'attachment')) {
                    $datos = $this->desarmarBinario($pcuerpo, $pc);
                    $this->adjuntosVistos[] = [
                        'i'      => count($this->adjuntosVistos),
                        'nombre' => $nombre ?: 'archivo',
                        'tipo'   => trim(explode(';', $ptipo)[0]),
                        'peso'   => strlen($datos),
                        // Una imagen incrustada en la firma no es un archivo
                        // que la persona haya adjuntado: se marca aparte.
                        'inline' => str_starts_with($disp, 'inline') && isset($pc['content-id']),
                        'datos'  => $datos,
                    ];
                    continue;
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

    /**
     * Igual que desarmar(), pero sin tocar el contenido: un PDF o un JPG no
     * son texto y convertirles el juego de caracteres los rompe.
     */
    private function desarmarBinario(string $cuerpo, array $cab): string
    {
        $cod = strtolower(trim($cab['content-transfer-encoding'] ?? ''));
        if ($cod === 'base64')               { return (string) base64_decode($cuerpo, true); }
        if ($cod === 'quoted-printable')     { return quoted_printable_decode($cuerpo); }
        return $cuerpo;
    }

    /** El nombre del archivo, venga en el Content-Disposition o en el tipo. */
    private function nombreDeParte(array $cab): string
    {
        foreach (['content-disposition', 'content-type'] as $x) {
            $v = $cab[$x] ?? '';
            if ($v === '') { continue; }

            // filename*=UTF-8''nombre%20con%20acentos.pdf
            if (preg_match("/(?:file)?name\*=(?:([\w\-]+)'[^']*')?([^;\r\n]+)/i", $v, $m)) {
                return $this->decodificar(rawurldecode(trim($m[2], " \"'")));
            }
            if (preg_match('/(?:file)?name="?([^";\r\n]+)"?/i', $v, $m)) {
                return $this->decodificar(trim($m[1]));
            }
        }
        return '';
    }

    /* ---------- utilidades ---------- */

    public function decodificar(string $s): string
    {
        if ($s === '') return '';

        // Sólo se descodifica si trae palabras codificadas (=?UTF-8?B?…?=).
        // Con un asunto en UTF-8 crudo —que muchos servidores mandan tal cual—
        // mb_decode_mimeheader se come los acentos: "Notificación" salía
        // "Notificaci??n".
        if (str_contains($s, '=?') && function_exists('mb_decode_mimeheader')) {
            $s = mb_decode_mimeheader($s);
        }

        // Lo que no sea UTF-8 válido viene casi siempre en Latin-1
        if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
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
        $sueltas = 0;      // las respuestas del servidor, no la línea final

        while (($linea = fgets($this->sock, 8192)) !== false) {
            $texto .= $linea;

            if (preg_match('/^' . $etq . ' (OK|NO|BAD)/i', $linea, $m)) {
                $this->registro[] = '< ' . trim($linea);
                return ['ok' => strtoupper($m[1]) === 'OK', 'texto' => $texto];
            }

            // Lo que contesta el servidor también se apunta: sin esto el
            // diálogo enseña un "OK" y esconde las carpetas, las capacidades
            // y todo lo demás, que es justo lo que se va a mirar aquí.
            // Se recorta, que un FETCH trae mensajes enteros.
            if ($sueltas < 40) {
                $corta = trim($linea);
                if (mb_strlen($corta) > 240) { $corta = mb_substr($corta, 0, 240) . ' …'; }
                $this->registro[] = '< ' . $corta;
            } elseif ($sueltas === 40) {
                $this->registro[] = '< … (el resto de la respuesta no se apunta)';
            }
            $sueltas++;
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
