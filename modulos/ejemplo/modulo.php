<?php
/* ============================================================
   MÓDULO DE EJEMPLO — copia esta carpeta para crear el tuyo
   ------------------------------------------------------------
   Este archivo solo describe el módulo. La pantalla va en
   index.php (o donde apunte 'url').

   'icono' usa el set de inc/iconos.php:
     inicio · panel · sobre · calendario · personas · tablero
     carpeta · caja · bolsa · casa · auto · llave · libro
     salud · moneda · balanza · objetivo · ajustes …
   ============================================================ */

return [
  'id'          => 'ejemplo',
  'nombre'      => 'Módulo de ejemplo',
  'descripcion' => 'Plantilla mínima para crear módulos nuevos (editor de páginas, agenda, catálogo, lo que necesites).',
  'version'     => '1.0.0',
  'icono'       => 'caja',
  'url'         => 'index.php',
  'rail'        => true,   // aparece en la barra lateral cuando está activo
];
