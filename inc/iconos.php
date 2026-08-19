<?php
/* ============================================================
   MÓDULO DE CORREO — Set de iconos SVG (trazo, 24x24)
   Uso:  echo mj_icono('bandeja');
   Para agregar uno nuevo: añade su <path> al arreglo.
   ============================================================ */

function mj_iconos_lista(): array
{
  return [
    // Rail
    'inicio'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.8V20h14V9.8"/><path d="M9.5 20v-5.5h5V20"/>',
    'panel'      => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="10" width="7" height="11" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    'balanza'    => '<path d="M12 3v18"/><path d="M7 21h10"/><path d="M4 7h16"/><path d="m7 7-3 6a3 3 0 0 0 6 0Z"/><path d="m17 7-3 6a3 3 0 0 0 6 0Z"/>',
    'sobre'      => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
    'objetivo'   => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
    'personas'   => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 19.5a6.2 6.2 0 0 1 12.4 0"/><path d="M16 5.4a3.2 3.2 0 0 1 0 6.2"/><path d="M17.6 14.4a6.2 6.2 0 0 1 3.6 5.1"/>',
    'tablero'    => '<rect x="5" y="4" width="14" height="17" rx="2.5"/><path d="M9 4.5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 4.5V6H9Z"/><path d="M9 11h6M9 15h4"/>',
    'moneda'     => '<circle cx="12" cy="12" r="9"/><path d="M14.8 9.2a3 3 0 0 0-2.8-1.7c-1.6 0-2.7.9-2.7 2.1 0 3 5.6 1.6 5.6 4.6 0 1.3-1.2 2.3-2.9 2.3a3.1 3.1 0 0 1-3-1.9"/><path d="M12 6v12"/>',
    'auto'       => '<path d="M5 16.5h14"/><path d="M6.5 16.5v2a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-2"/><path d="M20.5 16.5v2a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-2"/><path d="M3.5 16.5v-4l2-5A2 2 0 0 1 7.4 6h9.2a2 2 0 0 1 1.9 1.4l2 5v4Z"/><path d="M4 12.5h16"/><path d="M7 14.5h.01M17 14.5h.01"/>',
    'llave'      => '<circle cx="8" cy="14" r="4.5"/><path d="m11.2 10.8 8.3-8.3"/><path d="m16.5 5.5 2.5 2.5"/><path d="m14 8 2.5 2.5"/>',
    'casa'       => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.8V20h14V9.8"/><path d="M9.5 20v-6h5v6"/>',
    'bolsa'      => '<path d="M5 7.5h14l-1 12.5H6Z"/><path d="M8.5 10V6.5a3.5 3.5 0 0 1 7 0V10"/>',
    'caja'       => '<path d="M3.5 8 12 3.5 20.5 8v8L12 20.5 3.5 16Z"/><path d="M3.5 8 12 12.5 20.5 8"/><path d="M12 12.5v8"/>',
    'salud'      => '<path d="M4.5 12.5h3l1.5-4 2.5 8 2-5 1.5 2.5h4.5"/><path d="M20.5 9.5A4.6 4.6 0 0 0 12 7a4.6 4.6 0 0 0-8.5 2.5c0 4.5 8.5 10 8.5 10s8.5-5.5 8.5-10Z"/>',
    'libro'      => '<path d="M4 5.5A2 2 0 0 1 6 3.5h5v16H6a2 2 0 0 0-2 2Z"/><path d="M20 5.5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2Z"/>',
    'ajustes'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 8.1 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 4.2 14H4a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 4.2V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 19.8 10H20a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',

    // Carpetas
    'bandeja'    => '<path d="M3 13h4.5l1.5 2.5h6l1.5-2.5H21"/><path d="M5.2 5.5h13.6a2 2 0 0 1 1.9 1.4L21 13v4.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13l2.3-6.1a2 2 0 0 1 1.9-1.4Z"/>',
    'estrella'   => '<path d="m12 3.6 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.8l5.9-.9Z"/>',
    'enviar'     => '<path d="M21 3 10.5 13.5"/><path d="M21 3 14.5 21l-4-7.5L3 9.5Z"/>',
    'borrador'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
    'papelera'   => '<path d="M4 7h16"/><path d="M9.5 7V5.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5V7"/><path d="M6.5 7l.8 12a2 2 0 0 0 2 1.9h5.4a2 2 0 0 0 2-1.9L17.5 7"/><path d="M10.5 11v6M13.5 11v6"/>',
    'carpeta'    => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h3.2a2 2 0 0 1 1.6.8l1 1.4h7.2A2.5 2.5 0 0 1 21 9.7v7.8a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5Z"/>',
    'carpeta_mas'=> '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h3.2a2 2 0 0 1 1.6.8l1 1.4h7.2A2.5 2.5 0 0 1 21 9.7v7.8a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5Z"/><path d="M12 11.5v5M9.5 14h5"/>',

    // Acciones
    'buscar'     => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    'mas'        => '<path d="M12 5v14M5 12h14"/>',
    'responder'  => '<path d="M9 7 4 12l5 5"/><path d="M4 12h9.5a6.5 6.5 0 0 1 6.5 6.5V19"/>',
    'responder_todo' => '<path d="m8 7-5 5 5 5"/><path d="m13 7-5 5 5 5"/><path d="M8 12h7a5 5 0 0 1 5 5v1"/>',
    'reenviar'   => '<path d="m15 7 5 5-5 5"/><path d="M20 12h-9.5A6.5 6.5 0 0 0 4 18.5V19"/>',
    'calendario' => '<rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M3.5 10h17"/><path d="M8 3v4M16 3v4"/>',
    'archivar'   => '<rect x="3" y="4" width="18" height="4.5" rx="1.5"/><path d="M5 8.5V19a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5"/><path d="M10 12.5h4"/>',
    'clip'       => '<path d="M20 11.5 12.3 19a4.6 4.6 0 0 1-6.5-6.5l7.9-7.9a3.1 3.1 0 0 1 4.4 4.4l-7.9 7.9a1.6 1.6 0 1 1-2.2-2.2l7.1-7.1"/>',
    'silenciar'  => '<path d="M11 5 6.5 9H3v6h3.5L11 19Z"/><path d="m16 9.5 5 5M21 9.5l-5 5"/>',
    'spam'       => '<path d="M12 3.5 20.5 19h-17Z"/><path d="M12 9.5v4M12 16.5h.01"/>',
    'mover'      => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h2.9a2 2 0 0 1 1.6.8l.9 1.2h6.6A2.5 2.5 0 0 1 21 9.5v8A2.5 2.5 0 0 1 18.5 20h-12A2.5 2.5 0 0 1 4 17.5Z"/><path d="m12 11 3 2.5-3 2.5"/>',
    'copiar'     => '<rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M5.5 15A2.5 2.5 0 0 1 4 12.7V6.5A2.5 2.5 0 0 1 6.5 4h6.2A2.5 2.5 0 0 1 15 5.5"/>',
    'sobre_abrir'=> '<path d="M3 10.5 12 4l9 6.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="m3 10.5 9 6 9-6"/>',
    'cerrar'     => '<path d="m6 6 12 12M18 6 6 18"/>',
    'atras'      => '<path d="M15 5.5 8.5 12l6.5 6.5"/>',
    'adelante'   => '<path d="M9 5.5 15.5 12 9 18.5"/>',
    'menu'       => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'sol'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
    'luna'       => '<path d="M20 14.2A8.2 8.2 0 0 1 9.8 4 8.5 8.5 0 1 0 20 14.2Z"/>',
    'teclado'    => '<rect x="2.5" y="6" width="19" height="12" rx="2.5"/><path d="M6.5 10h.01M10 10h.01M13.5 10h.01M17 10h.01M8 14h8"/>',
    'check'      => '<path d="m5 12.5 4.5 4.5L19 7"/>',
    'refrescar'  => '<path d="M20 11.5A8 8 0 0 0 6.3 6.3L4 8.5"/><path d="M4 4v4.5h4.5"/><path d="M4 12.5a8 8 0 0 0 13.7 5.2L20 15.5"/><path d="M20 20v-4.5h-4.5"/>',
    'filtro'     => '<path d="M3.5 5.5h17l-6.5 7.6V19l-4 2v-7.9Z"/>',
    'usuario'    => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
  ];
}

/**
 * Devuelve el SVG de un icono.
 * @param string $nombre  clave del set
 * @param int    $tam     tamaño en px
 * @param string $clase   clases CSS extra
 */
function mj_icono(string $nombre, int $tam = 20, string $clase = ''): string
{
  static $set = null;
  if ($set === null) { $set = mj_iconos_lista(); }
  $d = $set[$nombre] ?? $set['sobre'];
  $c = trim('mj-i ' . $clase);
  return '<svg class="' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '" width="' . $tam . '" height="' . $tam . '"'
       . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
       . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}
