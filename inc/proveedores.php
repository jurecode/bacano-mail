<?php
/* ============================================================
   MÓDULO DE CORREO — Capa de datos
   ------------------------------------------------------------
   La vista NUNCA habla con IMAP directamente: pide los datos a
   un "proveedor". Hoy funciona el proveedor de demostración;
   mañana se enchufa el de IMAP sin tocar ni una línea de la vista.

   Formato de un mensaje (contrato entre proveedor y vista):
   [
     'id'         => 'm-001',
     'carpeta'    => 'entrada',
     'de'         => ['nombre'=>'', 'email'=>'', 'avatar'=>''],
     'para'       => [ ['nombre'=>'','email'=>''] ],
     'cc'         => [ ... ],
     'asunto'     => '',
     'extracto'   => '',            // opcional: se calcula del cuerpo
     'cuerpo'     => '<p>…</p>',    // HTML
     'fecha'      => '2026-08-19T14:32:00-04:00',
     'leido'      => true|false,
     'destacado'  => false | '#F59E0B',
     'importante' => false,
     'silenciado' => false,
     'etiquetas'  => ['urgente'],
     'adjuntos'   => [ ['nombre'=>'', 'peso'=>12345, 'tipo'=>'pdf'] ],
   ]
   ============================================================ */

interface MjProveedor
{
  /** Todos los mensajes visibles (la vista filtra por carpeta) */
  public function mensajes(): array;

  /** Un mensaje puntual por id */
  public function mensaje(string $id): ?array;

  /** Nombre legible del proveedor (se muestra en el pie) */
  public function nombre(): string;
}

/* ------------------------------------------------------------
   Proveedor JSON / DEMO — activo por defecto
   ------------------------------------------------------------ */
class MjProveedorJson implements MjProveedor
{
  private array $datos = [];

  public function __construct(private string $archivo, private array $cfg = [])
  {
    if (is_readable($this->archivo)) {
      $json = json_decode((string) file_get_contents($this->archivo), true);
      if (is_array($json)) {
        $this->datos = array_map([$this, 'normalizar'], $json['mensajes'] ?? $json);
      }
    }
    usort($this->datos, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
  }

  public function mensajes(): array { return $this->datos; }

  public function mensaje(string $id): ?array
  {
    foreach ($this->datos as $m) { if ($m['id'] === $id) return $m; }
    return null;
  }

  public function nombre(): string { return 'Demostración (JSON)'; }

  /** Completa los campos que falten para que la vista nunca reviente */
  private function normalizar(array $m): array
  {
    $persona = static function ($p): array {
      if (is_string($p)) return ['nombre' => $p, 'email' => '', 'avatar' => ''];
      return [
        'nombre' => $p['nombre'] ?? '',
        'email'  => $p['email']  ?? '',
        'avatar' => $p['avatar'] ?? '',
      ];
    };

    $m += [
      'id' => uniqid('m-'), 'carpeta' => 'entrada', 'asunto' => '(sin asunto)',
      'cuerpo' => '', 'fecha' => date('c'), 'leido' => false, 'destacado' => false,
      'importante' => false, 'silenciado' => false, 'etiquetas' => [], 'adjuntos' => [],
      'de' => [], 'para' => [], 'cc' => [],
    ];

    // Fechas relativas en el JSON ("-45 minutes", "yesterday 09:15")
    if (!preg_match('/^\d{4}-/', (string) $m['fecha'])) {
      $ts = strtotime((string) $m['fecha']);
      $m['fecha'] = date('c', $ts ?: time());
    }

    $m['de']   = $persona($m['de']);
    $m['para'] = array_map($persona, (array) $m['para']);
    $m['cc']   = array_map($persona, (array) $m['cc']);

    if (empty($m['extracto'])) {
      $m['extracto'] = mj_recorte($m['cuerpo'], 180);
    }
    $m['adjuntos'] = array_map(static fn($a) => [
      'nombre' => $a['nombre'] ?? 'archivo',
      'peso'   => (int) ($a['peso'] ?? 0),
      'tipo'   => $a['tipo'] ?? pathinfo($a['nombre'] ?? '', PATHINFO_EXTENSION),
    ], (array) $m['adjuntos']);

    return $m;
  }
}

/* ------------------------------------------------------------
   Proveedor IMAP — ESQUELETO (pendiente de activar)
   ------------------------------------------------------------
   Cuando toque conectar el correo real:
     1) Verifica que exista la extensión imap  (php -m | grep imap)
     2) Completa 'origen.imap' en config.php
     3) Cambia 'origen.tipo' a 'imap'
     4) Implementa leer() más abajo devolviendo el mismo formato
        de arreglo documentado arriba.
   Mientras no esté implementado, el módulo cae a modo demo y
   avisa en pantalla, en vez de romper la página.
   ------------------------------------------------------------ */
class MjProveedorImap implements MjProveedor
{
  private $conexion = null;
  private ?string $fallo = null;
  private array $cache = [];

  public function __construct(private array $conf, private array $cfg = []) {}

  public static function disponible(): bool
  {
    return function_exists('imap_open');
  }

  public function nombre(): string { return 'IMAP · ' . ($this->conf['host'] ?? ''); }

  /** Último error de conexión, para mostrarlo sin exponer la clave */
  public function fallo(): ?string { return $this->fallo; }

  /** Cadena de conexión: {host:993/imap/ssl}INBOX */
  public function cadena(?string $carpeta = null): string
  {
    $cif = $this->conf['cifrado'] ?? 'ssl';
    $val = !empty($this->conf['validar_certificado']) ? '' : '/novalidate-cert';
    return '{' . ($this->conf['host'] ?? '') . ':' . ($this->conf['puerto'] ?? 993)
         . '/imap' . ($cif ? '/' . $cif : '') . $val . '}'
         . ($carpeta ?? $this->conf['carpeta'] ?? 'INBOX');
  }

  /** Abre la conexión una sola vez por petición */
  private function abrir()
  {
    if ($this->conexion !== null) { return $this->conexion; }
    if (!self::disponible()) {
      $this->fallo = 'Este servidor no tiene la extensión imap de PHP.';
      return $this->conexion = false;
    }

    // imap_open emite warnings además de devolver false: se silencian y
    // se recoge el motivo con imap_last_error() para poder mostrarlo.
    $c = @imap_open(
      $this->cadena(),
      (string) ($this->conf['usuario'] ?? ''),
      (string) ($this->conf['clave'] ?? ''),
      0,
      1,
      ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
    );

    if ($c === false) {
      $this->fallo = imap_last_error() ?: 'No se pudo conectar a la casilla.';
      @imap_errors();          // vacía la cola para que no ensucie la siguiente
      return $this->conexion = false;
    }
    return $this->conexion = $c;
  }

  public function __destruct()
  {
    if ($this->conexion) { @imap_close($this->conexion); }
  }

  /**
   * Los últimos mensajes de la casilla, del más nuevo al más viejo.
   * Solo lee cabeceras: el cuerpo se pide al abrir un mensaje.
   */
  public function mensajes(): array
  {
    if ($this->cache) { return $this->cache; }

    $c = $this->abrir();
    if (!$c) { return []; }

    $total = @imap_num_msg($c);
    if (!$total) { return []; }

    $limite = max(1, (int) ($this->conf['limite'] ?? 50));
    $desde  = max(1, $total - $limite + 1);

    $salida = [];
    // De atrás hacia adelante: los más recientes primero
    for ($n = $total; $n >= $desde; $n--) {
      $cab = @imap_headerinfo($c, $n);
      if (!$cab) { continue; }

      $uid = @imap_uid($c, $n);
      $salida[] = [
        'id'         => 'imap-' . $uid,
        'carpeta'    => 'entrada',
        'de'         => $this->persona($cab->from[0] ?? null),
        'para'       => array_map([$this, 'persona'], (array) ($cab->to ?? [])),
        'cc'         => array_map([$this, 'persona'], (array) ($cab->cc ?? [])),
        'asunto'     => $this->texto($cab->subject ?? '(sin asunto)'),
        'extracto'   => '',                       // se completa abajo
        'cuerpo'     => '',                       // solo al abrir el mensaje
        'fecha'      => date('c', strtotime($cab->date ?? 'now')),
        'leido'      => ($cab->Unseen ?? 'U') !== 'U',
        'destacado'  => ($cab->Flagged ?? ' ') === 'F' ? '#F59E0B' : false,
        'importante' => ($cab->Flagged ?? ' ') === 'F',
        'silenciado' => false,
        'etiquetas'  => [],
        'adjuntos'   => $this->adjuntos($c, $n),
      ];
    }

    // El extracto necesita una parte del cuerpo: se pide solo la primera
    // parte de texto, que es barata comparada con el mensaje completo.
    foreach ($salida as $i => $m) {
      $uid = (int) substr($m['id'], 5);
      $salida[$i]['extracto'] = mj_recorte($this->cuerpoTexto($c, $uid), 180);
    }

    return $this->cache = $salida;
  }

  /** Un mensaje con su cuerpo completo */
  public function mensaje(string $id): ?array
  {
    if (strpos($id, 'imap-') !== 0) { return null; }
    $uid = (int) substr($id, 5);

    $c = $this->abrir();
    if (!$c) { return null; }

    foreach ($this->mensajes() as $m) {
      if ($m['id'] !== $id) { continue; }
      $m['cuerpo'] = $this->cuerpoHtml($c, $uid) ?: nl2br(mj_e($this->cuerpoTexto($c, $uid)));
      return $m;
    }
    return null;
  }

  /* ----------------------------------------------------------------
     Lectura del cuerpo
     ---------------------------------------------------------------- */

  /** Primera parte HTML del mensaje, decodificada */
  private function cuerpoHtml($c, int $uid): string
  {
    return $this->parte($c, $uid, 'HTML');
  }

  /** Primera parte de texto plano, decodificada */
  private function cuerpoTexto($c, int $uid): string
  {
    $txt = $this->parte($c, $uid, 'TEXT');
    return $txt !== '' ? $txt : strip_tags($this->parte($c, $uid, 'HTML'));
  }

  /**
   * Recorre la estructura MIME buscando la primera parte del tipo pedido.
   * Devuelve '' si el mensaje no la tiene.
   */
  private function parte($c, int $uid, string $tipo): string
  {
    $estructura = @imap_fetchstructure($c, $uid, FT_UID);
    if (!$estructura) { return ''; }

    $buscado = $tipo === 'HTML' ? 'HTML' : 'PLAIN';

    // Mensaje simple, sin partes
    if (empty($estructura->parts)) {
      $sub = strtoupper((string) ($estructura->subtype ?? 'PLAIN'));
      if ($sub !== $buscado) { return ''; }
      return $this->decodificar(
        (string) @imap_body($c, $uid, FT_UID | FT_PEEK),
        (int) ($estructura->encoding ?? 0),
        $estructura
      );
    }

    // Mensaje con partes: se recorren en orden, incluidas las anidadas
    $pila = [];
    foreach ($estructura->parts as $i => $parte) { $pila[] = [(string) ($i + 1), $parte]; }

    while ($pila) {
      [$numero, $parte] = array_shift($pila);

      if (!empty($parte->parts)) {
        foreach ($parte->parts as $j => $sub) {
          $pila[] = [$numero . '.' . ($j + 1), $sub];
        }
        continue;
      }

      $sub = strtoupper((string) ($parte->subtype ?? ''));
      $esAdjunto = !empty($parte->disposition) && strtoupper($parte->disposition) === 'ATTACHMENT';

      if ($sub === $buscado && !$esAdjunto) {
        return $this->decodificar(
          (string) @imap_fetchbody($c, $uid, $numero, FT_UID | FT_PEEK),
          (int) ($parte->encoding ?? 0),
          $parte
        );
      }
    }
    return '';
  }

  /** Deshace el encoding de transporte y lleva todo a UTF-8 */
  private function decodificar(string $bruto, int $encoding, $parte): string
  {
    $txt = match ($encoding) {
      3       => (string) base64_decode($bruto, true),   // base64
      4       => quoted_printable_decode($bruto),        // quoted-printable
      default => $bruto,
    };

    // El charset viene en los parámetros de la parte
    $charset = 'UTF-8';
    foreach ((array) ($parte->parameters ?? []) as $p) {
      if (strtoupper((string) $p->attribute) === 'CHARSET') { $charset = (string) $p->value; }
    }
    if (strtoupper($charset) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
      $convertido = @mb_convert_encoding($txt, 'UTF-8', $charset);
      if ($convertido !== false) { $txt = $convertido; }
    }
    return $txt;
  }

  /* ----------------------------------------------------------------
     Cabeceras
     ---------------------------------------------------------------- */

  /** Adjuntos declarados en la estructura del mensaje */
  private function adjuntos($c, int $n): array
  {
    $estructura = @imap_fetchstructure($c, $n);
    if (!$estructura || empty($estructura->parts)) { return []; }

    $salida = [];
    foreach ($estructura->parts as $parte) {
      $disp = strtoupper((string) ($parte->disposition ?? ''));
      if ($disp !== 'ATTACHMENT' && $disp !== 'INLINE') { continue; }

      $nombre = '';
      foreach (array_merge((array) ($parte->dparameters ?? []), (array) ($parte->parameters ?? [])) as $p) {
        if (in_array(strtoupper((string) $p->attribute), ['FILENAME', 'NAME'], true)) {
          $nombre = $this->texto((string) $p->value);
          break;
        }
      }
      if ($nombre === '') { continue; }

      $salida[] = [
        'nombre' => $nombre,
        'peso'   => (int) ($parte->bytes ?? 0),
        'tipo'   => strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION)),
      ];
    }
    return $salida;
  }

  /** Decodifica una cabecera MIME (=?UTF-8?B?...?=) a texto legible */
  private function texto(string $bruto): string
  {
    $salida = '';
    foreach ((array) @imap_mime_header_decode($bruto) as $trozo) {
      $charset = strtoupper((string) $trozo->charset);
      $texto   = (string) $trozo->text;
      if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
        $conv = @mb_convert_encoding($texto, 'UTF-8', $charset);
        if ($conv !== false) { $texto = $conv; }
      }
      $salida .= $texto;
    }
    return trim($salida) ?: $bruto;
  }

  /** Objeto de dirección de imap_headerinfo → persona del módulo */
  private function persona($dir): array
  {
    if (!$dir) { return ['nombre' => '', 'email' => '', 'avatar' => '']; }
    $email = ($dir->mailbox ?? '') . '@' . ($dir->host ?? '');
    return [
      'nombre' => isset($dir->personal) ? $this->texto((string) $dir->personal) : $email,
      'email'  => $email,
      'avatar' => '',
    ];
  }
}

/* ------------------------------------------------------------
   Fábrica: entrega el proveedor según config.php
   ------------------------------------------------------------ */
function mj_proveedor(array $cfg): MjProveedor
{
  static $cache = null;
  if ($cache !== null) { return $cache; }

  $tipo    = $cfg['origen']['tipo'] ?? 'demo';
  $archivo = $cfg['origen']['archivo'] ?? __DIR__ . '/../data/demo.json';

  if ($tipo === 'imap') {
    $imap = new MjProveedorImap($cfg['origen']['imap'] ?? [], $cfg);

    // Se intenta de verdad antes de decidir: una casilla vacía es una
    // respuesta válida y no debe confundirse con un fallo de conexión.
    $imap->mensajes();

    if ($imap->fallo() === null) { return $cache = $imap; }
    $GLOBALS['mj_fallo_imap'] = $imap->fallo();
  }

  return $cache = new MjProveedorJson($archivo, $cfg);
}

/** Motivo por el que no se pudo leer la casilla real, si lo hubo */
function mj_fallo_imap(): ?string
{
  return $GLOBALS['mj_fallo_imap'] ?? null;
}
