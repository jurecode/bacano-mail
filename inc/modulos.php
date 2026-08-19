<?php
/* ============================================================
   REGISTRO DE MÓDULOS
   ------------------------------------------------------------
   El correo es el primer módulo, pero la carpeta /modulos se
   descubre sola: cualquier carpeta con un archivo modulo.php
   aparece en el instalador y, si la activas, en el menú lateral.

   Para crear uno nuevo:

     modulos/mi-modulo/
       ├── modulo.php     ← metadatos (ver modulos/ejemplo)
       └── index.php      ← tu pantalla

   No hay que tocar el núcleo ni el correo.
   ============================================================ */

/** Todos los módulos encontrados en /modulos */
function mj_modulos(): array
{
  static $lista = null;
  if ($lista !== null) return $lista;

  $lista = [];
  $dir   = __DIR__ . '/../modulos';
  if (!is_dir($dir)) return $lista;

  foreach ((array) glob($dir . '/*/modulo.php') as $archivo) {
    $m = @require $archivo;
    if (!is_array($m) || empty($m['id'])) continue;

    $lista[$m['id']] = $m + [
      'nombre'      => $m['id'],
      'descripcion' => '',
      'version'     => '0.0.0',
      'icono'       => 'caja',
      'url'         => 'index.php',
      'rail'        => true,
      'carpeta'     => basename(dirname($archivo)),
    ];
  }
  ksort($lista);
  return $lista;
}

/** Los que están activos en esta instalación */
function mj_modulos_activos(array $cfg): array
{
  $activos = (array) ($cfg['modulos']['activos'] ?? []);
  return array_filter(mj_modulos(), fn($m) => in_array($m['id'], $activos, true));
}

/** Entradas de menú que aportan los módulos activos */
function mj_modulos_rail(array $cfg, string $base): array
{
  if (empty($cfg['modulos']['mostrar_en_rail'])) return [];

  $items = [];
  foreach (mj_modulos_activos($cfg) as $m) {
    if (empty($m['rail'])) continue;
    $items[] = [
      'icono'  => $m['icono'],
      'texto'  => $m['nombre'],
      'url'    => $base . '/modulos/' . $m['carpeta'] . '/' . ltrim($m['url'], '/'),
      'activo' => false,
    ];
  }
  return $items;
}
