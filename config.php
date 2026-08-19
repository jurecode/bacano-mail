<?php
/* ============================================================
   MÓDULO DE CORREO — VALORES POR DEFECTO
   ------------------------------------------------------------
   ⚠️  NO EDITES ESTE ARCHIVO si vas a usar el instalador.

   instalar.php escribe tus ajustes en  config.local.php,  que
   manda por sobre este archivo. Así puedes actualizar el módulo
   (reemplazando esta carpeta) sin perder tu configuración.

   Este archivo sirve como:
     · valores de fábrica de una instalación nueva
     · documentación de TODAS las opciones disponibles
   ============================================================ */

return [

  /* ---------------------------------------------------------
     1. MARCA
     --------------------------------------------------------- */
  'marca' => [
    'nombre'      => 'BACANO.MAIL',   // Marca del rail (o usa 'logo')
    'nombre_corto'=> 'B.',            // Se muestra con el rail plegado
    'nombre_full' => 'Mi Empresa',
    'logo'        => '',              // Ruta/URL de imagen. Vacío = texto
    'url'         => '../',           // A dónde vuelve el usuario
    'titulo_web'  => 'Correo',        // <title> de la página
    'favicon'     => '',              // Ruta/URL del icono de la pestaña
  ],

  /* ---------------------------------------------------------
     2. PERFIL DE RUBRO
     Rellena menú, carpetas y etiquetas de una sola vez.
     Opciones: generico · automotora · inmobiliaria · tienda ·
               salud · educacion · servicios · juridico
     (ver inc/perfiles.php)
     --------------------------------------------------------- */
  'perfil' => 'generico',

  /* ---------------------------------------------------------
     3. RUTAS — vacío = se detecta sola (recomendado)
     --------------------------------------------------------- */
  'ruta_base' => '',

  /* ---------------------------------------------------------
     4. TEMA / APARIENCIA
     --------------------------------------------------------- */
  'tema' => [
    'preset'        => 'aurora',   // aurora · nieve · tinta · bosque
    'modo'          => 'auto',     // auto · claro · oscuro
    'permitir_cambio_modo' => true,
    'acento'        => '',         // Vacío = el del preset
    'fondo'         => 'malla',    // malla · solido · imagen
    'fondo_imagen'  => '',
    'animar_fondo'  => true,
    'radio'         => '22px',
    'ancho_maximo'  => '1560px',
    // Alto del módulo. Al incrustarlo bajo tu propia cabecera usa,
    // por ejemplo, 'calc(100svh - 64px)' para evitar doble scroll.
    'alto'          => '100svh',
    'ventana'       => true,       // false = a pantalla completa, sin marco
    'fuente_google' => 'Inter:wght@400;500;600;700&family=Inter+Tight:wght@700;800',
    'fuente_texto'  => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
    'fuente_titulo' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
    // Tipografía y color de la marca del rail.
    // El rail es oscuro: un color muy oscuro aquí queda ilegible.
    'fuente_marca'  => "'Inter Tight', 'Helvetica Neue', Helvetica, Arial, sans-serif",
    'color_marca'   => '',            // Vacío = blanco sobre el rail oscuro
    'densidad'      => 'comoda',   // comoda · compacta
  ],

  /* ---------------------------------------------------------
     5. USUARIO EN PANTALLA
     --------------------------------------------------------- */
  'usuario' => [
    'nombre' => 'Mi cuenta',
    'email'  => 'correo@midominio.cl',
    'avatar' => '',
  ],

  /* ---------------------------------------------------------
     6. INTERFAZ — enciende o apaga cada pieza
     --------------------------------------------------------- */
  'interfaz' => [
    'mostrar_rail'          => true,
    'rail_colapsable'       => true,
    'mostrar_carpetas'      => true,
    'mostrar_buscador'      => true,
    'mostrar_filtros'       => true,
    'mostrar_lector'        => true,
    'panel_lectura'         => 'derecha',  // derecha · abajo · oculto
    'mostrar_avatares'      => true,
    'mostrar_extracto'      => true,
    'lineas_extracto'       => 2,
    'mostrar_adjuntos'      => true,
    'seleccion_multiple'    => true,
    'menu_contextual'       => true,
    'boton_redactar'        => true,
    'atajos_teclado'        => true,
    'mostrar_ayuda_atajos'  => true,
    'notificaciones'        => true,
    'agrupar_por_fecha'     => true,
    'mensajes_por_pagina'   => 50,
    'auto_marcar_leido'     => true,
    'confirmar_eliminar'    => false,
  ],

  /* ---------------------------------------------------------
     7. MENÚ LATERAL (lo entrega el perfil; edítalo si quieres)
     --------------------------------------------------------- */
  'rail' => [
    ['icono' => 'inicio',     'texto' => 'Inicio',    'url' => '../'],
    ['icono' => 'panel',      'texto' => 'Panel',     'url' => '#'],
    ['icono' => 'sobre',      'texto' => 'Correo',    'url' => './', 'activo' => true],
    ['icono' => 'calendario', 'texto' => 'Agenda',    'url' => '#'],
    ['icono' => 'personas',   'texto' => 'Contactos', 'url' => '#'],
    ['icono' => 'tablero',    'texto' => 'Tareas',    'url' => '#'],
    ['icono' => 'carpeta',    'texto' => 'Archivos',  'url' => '#'],
  ],
  'rail_pie' => [
    ['icono' => 'ajustes', 'texto' => 'Ajustes', 'url' => '#'],
  ],

  /* ---------------------------------------------------------
     8. CARPETAS DEL SISTEMA
     'contador': no_leidos · total · no
     --------------------------------------------------------- */
  'carpetas' => [
    ['id' => 'entrada',   'nombre' => 'Recibidos',  'icono' => 'bandeja',  'contador' => 'no_leidos'],
    ['id' => 'destacado', 'nombre' => 'Destacados', 'icono' => 'estrella', 'contador' => 'no'],
    ['id' => 'enviados',  'nombre' => 'Enviados',   'icono' => 'enviar',   'contador' => 'no'],
    ['id' => 'borrador',  'nombre' => 'Borradores', 'icono' => 'borrador', 'contador' => 'total'],
    ['id' => 'archivo',   'nombre' => 'Archivados', 'icono' => 'archivar', 'contador' => 'no'],
    ['id' => 'spam',      'nombre' => 'Spam',       'icono' => 'spam',     'contador' => 'total'],
    ['id' => 'papelera',  'nombre' => 'Papelera',   'icono' => 'papelera', 'contador' => 'no'],
  ],

  // Carpetas propias (las entrega el perfil)
  'carpetas_propias' => [
    ['id' => 'proyectos', 'nombre' => 'Proyectos', 'icono' => 'carpeta', 'color' => '#2563EB'],
    ['id' => 'equipo',    'nombre' => 'Equipo',    'icono' => 'carpeta', 'color' => '#059669'],
  ],
  'mostrar_agregar_carpeta' => true,

  /* ---------------------------------------------------------
     9. ETIQUETAS (las entrega el perfil)
     --------------------------------------------------------- */
  'etiquetas' => [
    'importante'  => ['nombre' => 'Importante',  'color' => '#DC2626'],
    'seguimiento' => ['nombre' => 'Seguimiento', 'color' => '#7C3AED'],
    'facturacion' => ['nombre' => 'Facturación', 'color' => '#059669'],
    'personal'    => ['nombre' => 'Personal',    'color' => '#2563EB'],
  ],

  /* ---------------------------------------------------------
     10. BARRA DE ACCIONES DEL LECTOR
     --------------------------------------------------------- */
  'acciones_lector' => [
    ['id' => 'responder',      'texto' => 'Responder',        'icono' => 'responder'],
    ['id' => 'responder_todo', 'texto' => 'Responder a todos', 'icono' => 'responder_todo'],
    ['id' => 'reenviar',       'texto' => 'Reenviar',          'icono' => 'reenviar'],
    ['id' => 'eliminar',       'texto' => 'Eliminar',          'icono' => 'papelera'],
    ['id' => 'importante',     'texto' => 'Destacar',          'icono' => 'estrella'],
  ],

  /* ---------------------------------------------------------
     11. MENÚ CONTEXTUAL (clic derecho)
     --------------------------------------------------------- */
  'menu_contextual_items' => [
    ['id' => 'abrir',          'texto' => 'Abrir'],
    ['id' => 'responder',      'texto' => 'Responder'],
    ['id' => 'responder_todo', 'texto' => 'Responder a todos'],
    ['id' => 'reenviar',       'texto' => 'Reenviar'],
    ['id' => 'reenviar_adj',   'texto' => 'Reenviar como adjunto'],
    ['sep' => true],
    ['id' => 'no_leido',       'texto' => 'Marcar como no leído'],
    ['id' => 'spam',           'texto' => 'Mover a Spam'],
    ['id' => 'silenciar',      'texto' => 'Silenciar'],
    ['id' => 'eliminar',       'texto' => 'Eliminar'],
    ['id' => 'destacar',       'texto' => 'Destacar', 'tipo' => 'colores'],
    ['sep' => true],
    ['id' => 'archivar',       'texto' => 'Archivar'],
    ['id' => 'mover',          'texto' => 'Mover a',  'tipo' => 'carpetas'],
    ['id' => 'copiar',         'texto' => 'Copiar a', 'tipo' => 'carpetas'],
  ],

  'colores_estrella' => ['#F59E0B', '#EF4444', '#8B5CF6', '#3B82F6', '#10B981', '#6B7280'],

  /* ---------------------------------------------------------
     12. ORIGEN DE LOS MENSAJES
     tipo: demo | json | imap
     --------------------------------------------------------- */
  'origen' => [
    'tipo'    => 'demo',
    'archivo' => __DIR__ . '/data/demo.json',

    'imap' => [
      'host'                => '',
      'puerto'              => 993,
      'cifrado'             => 'ssl',   // ssl | tls | (vacío)
      'validar_certificado' => true,
      'usuario'             => '',
      'clave'               => '',
      'carpeta'             => 'INBOX',
      'limite'              => 50,
    ],

    'smtp' => [
      'host'    => '',
      'puerto'  => 465,
      'cifrado' => 'ssl',
      'usuario' => '',
      'clave'   => '',
      'desde'   => '',
    ],
  ],

  /* ---------------------------------------------------------
     13. FECHAS
     --------------------------------------------------------- */
  'formato' => [
    'zona_horaria' => 'America/Santiago',
    'hoy'          => 'H:i',
    'semana'       => 'D H:i',
    'anterior'     => 'd/m/Y',
    'completa'     => 'd/m/Y · H:i',
  ],

  /* ---------------------------------------------------------
     14. SEGURIDAD DEL CONTENIDO
     --------------------------------------------------------- */
  'seguridad' => [
    'sanitizar_html'            => true,
    'bloquear_imagenes_remotas' => true,
    'abrir_enlaces_nueva'       => true,
  ],

  /* ---------------------------------------------------------
     15. MÓDULOS
     La carpeta /modulos se descubre sola. Aquí solo se indica
     cuáles están activos en esta instalación.
     --------------------------------------------------------- */
  'modulos' => [
    'activos'         => [],     // ej: ['ejemplo', 'paginas']
    'mostrar_en_rail' => true,
  ],

  /* ---------------------------------------------------------
     16. ACTUALIZACIONES
     'repositorio' = usuario/proyecto en GitHub.
     Si instalaste con git, basta con "git pull" en el servidor.
     --------------------------------------------------------- */
  'actualizaciones' => [
    'repositorio' => 'TU-USUARIO/bacano-mail',
    'revisar'     => true,       // avisa en el panel cuando hay versión nueva
  ],

  /* ---------------------------------------------------------
     17. PANEL DE ADMINISTRACIÓN
     La clave se guarda cifrada (hash) en config.local.php
     cuando completas instalar.php.
     --------------------------------------------------------- */
  'admin' => [
    'clave_hash' => '',
    'instalado'  => false,
  ],

  /* ---------------------------------------------------------
     18. TEXTOS (traduce el módulo completo desde aquí)
     --------------------------------------------------------- */
  'textos' => [
    'correo'            => 'Correo',
    'buscar'            => 'Buscar en el correo…',
    'todos'             => 'Todos',
    'leidos'            => 'Leídos',
    'no_leidos'         => 'No leídos',
    'carpetas'          => 'Carpetas',
    'agregar_carpeta'   => 'Agregar carpeta',
    'redactar'          => 'Redactar',
    'para'              => 'Para',
    'cc'                => 'CC',
    'asunto'            => 'Asunto',
    'enviar'            => 'Enviar',
    'cancelar'          => 'Cancelar',
    'sin_mensajes'      => 'No hay mensajes aquí',
    'sin_mensajes_desc' => 'Cuando lleguen correos a esta carpeta, aparecerán en esta lista.',
    'sin_seleccion'     => 'Selecciona un mensaje',
    'sin_seleccion_desc'=> 'Elige un correo de la lista para leerlo aquí.',
    'sin_resultados'    => 'Sin resultados para tu búsqueda',
    'volver'            => 'Volver',
    'adjuntos'          => 'Adjuntos',
    'hoy'               => 'Hoy',
    'ayer'              => 'Ayer',
    'anteriores'        => 'Anteriores',
    'deshacer'          => 'Deshacer',
    'seleccionados'     => 'seleccionados',
    'atajos'            => 'Atajos de teclado',
    'imagenes_bloqueadas' => 'Se bloquearon las imágenes externas de este mensaje.',
    'mostrar_imagenes'  => 'Mostrar imágenes',
    'demo_aviso'        => 'Vista de demostración · los datos son de ejemplo',
  ],
];
