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
  public function __construct(private array $conf, private array $cfg = []) {}

  public static function disponible(): bool
  {
    return function_exists('imap_open');
  }

  public function nombre(): string { return 'IMAP · ' . ($this->conf['host'] ?? ''); }

  /** Cadena de conexión: {host:993/imap/ssl} */
  public function cadena(): string
  {
    $cif = $this->conf['cifrado'] ?? 'ssl';
    $val = !empty($this->conf['validar_certificado']) ? '' : '/novalidate-cert';
    return '{' . ($this->conf['host'] ?? '') . ':' . ($this->conf['puerto'] ?? 993)
         . '/imap' . ($cif ? '/' . $cif : '') . $val . '}' . ($this->conf['carpeta'] ?? 'INBOX');
  }

  public function mensajes(): array
  {
    // TODO (fase 2): imap_open + imap_search + imap_fetchbody
    //   → mapear cada correo al formato documentado arriba.
    return [];
  }

  public function mensaje(string $id): ?array { return null; }
}

/* ------------------------------------------------------------
   Fábrica: entrega el proveedor según config.php
   ------------------------------------------------------------ */
function mj_proveedor(array $cfg): MjProveedor
{
  $tipo    = $cfg['origen']['tipo'] ?? 'demo';
  $archivo = $cfg['origen']['archivo'] ?? __DIR__ . '/../data/demo.json';

  if ($tipo === 'imap' && MjProveedorImap::disponible()) {
    $imap = new MjProveedorImap($cfg['origen']['imap'] ?? [], $cfg);
    if ($imap->mensajes()) return $imap;   // aún vacío: cae a demo
  }
  return new MjProveedorJson($archivo, $cfg);
}
