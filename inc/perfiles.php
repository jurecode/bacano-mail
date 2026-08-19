<?php
/* ============================================================
   Correo — PERFILES DE RUBRO
   ------------------------------------------------------------
   Cada perfil rellena de una sola vez el menú lateral, las
   carpetas propias y las etiquetas, para no tener que
   inventarlas en cada instalación.

   Se elige en instalar.php (o en config: 'perfil' => 'clave').
   Se puede editar libremente después: el perfil solo entrega
   un punto de partida.
   ============================================================ */

function mj_perfiles(): array
{
  return [

    /* ---------------------------------------------------- */
    'generico' => [
      'nombre' => 'Genérico (cualquier empresa)',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',     'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',      'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',     'url' => './', 'activo' => true],
        ['icono' => 'calendario', 'texto' => 'Agenda',     'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Contactos',  'url' => '#'],
        ['icono' => 'tablero',    'texto' => 'Tareas',     'url' => '#'],
        ['icono' => 'carpeta',    'texto' => 'Archivos',   'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'proyectos', 'nombre' => 'Proyectos', 'icono' => 'carpeta', 'color' => '#2563EB'],
        ['id' => 'equipo',    'nombre' => 'Equipo',    'icono' => 'carpeta', 'color' => '#059669'],
      ],
      'etiquetas' => [
        'importante'  => ['nombre' => 'Importante',  'color' => '#DC2626'],
        'seguimiento' => ['nombre' => 'Seguimiento', 'color' => '#7C3AED'],
        'facturacion' => ['nombre' => 'Facturación', 'color' => '#059669'],
        'personal'    => ['nombre' => 'Personal',    'color' => '#2563EB'],
      ],
    ],

    /* ---------------------------------------------------- */
    'automotora' => [
      'nombre' => 'Automotora / concesionario',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',      'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',       'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',      'url' => './', 'activo' => true],
        ['icono' => 'auto',       'texto' => 'Vehículos',   'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Clientes',    'url' => '#'],
        ['icono' => 'calendario', 'texto' => 'Test drive',  'url' => '#'],
        ['icono' => 'llave',      'texto' => 'Postventa',   'url' => '#'],
        ['icono' => 'moneda',     'texto' => 'Ventas',      'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'cotizaciones', 'nombre' => 'Cotizaciones', 'icono' => 'carpeta', 'color' => '#2563EB'],
        ['id' => 'taller',       'nombre' => 'Taller',       'icono' => 'carpeta', 'color' => '#EA580C'],
      ],
      'etiquetas' => [
        'cotizacion' => ['nombre' => 'Cotización', 'color' => '#2563EB'],
        'financiado' => ['nombre' => 'Financiado', 'color' => '#059669'],
        'postventa'  => ['nombre' => 'Postventa',  'color' => '#EA580C'],
        'urgente'    => ['nombre' => 'Urgente',    'color' => '#DC2626'],
      ],
    ],

    /* ---------------------------------------------------- */
    'inmobiliaria' => [
      'nombre' => 'Inmobiliaria / corretaje',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',      'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',       'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',      'url' => './', 'activo' => true],
        ['icono' => 'casa',       'texto' => 'Propiedades', 'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Clientes',    'url' => '#'],
        ['icono' => 'calendario', 'texto' => 'Visitas',     'url' => '#'],
        ['icono' => 'llave',      'texto' => 'Arriendos',   'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'ventas',    'nombre' => 'Ventas',    'icono' => 'carpeta', 'color' => '#2563EB'],
        ['id' => 'arriendos', 'nombre' => 'Arriendos', 'icono' => 'carpeta', 'color' => '#7C3AED'],
      ],
      'etiquetas' => [
        'visita'    => ['nombre' => 'Visita',    'color' => '#7C3AED'],
        'oferta'    => ['nombre' => 'Oferta',    'color' => '#059669'],
        'documento' => ['nombre' => 'Documento', 'color' => '#2563EB'],
        'urgente'   => ['nombre' => 'Urgente',   'color' => '#DC2626'],
      ],
    ],

    /* ---------------------------------------------------- */
    'tienda' => [
      'nombre' => 'Tienda / e-commerce',
      'rail' => [
        ['icono' => 'inicio',   'texto' => 'Inicio',    'url' => '../'],
        ['icono' => 'panel',    'texto' => 'Panel',     'url' => '#'],
        ['icono' => 'sobre',    'texto' => 'Correo',    'url' => './', 'activo' => true],
        ['icono' => 'bolsa',    'texto' => 'Pedidos',   'url' => '#'],
        ['icono' => 'caja',     'texto' => 'Productos', 'url' => '#'],
        ['icono' => 'personas', 'texto' => 'Clientes',  'url' => '#'],
        ['icono' => 'moneda',   'texto' => 'Pagos',     'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'pedidos',    'nombre' => 'Pedidos',    'icono' => 'carpeta', 'color' => '#2563EB'],
        ['id' => 'devoluciones','nombre' => 'Devoluciones','icono' => 'carpeta','color' => '#DC2626'],
      ],
      'etiquetas' => [
        'pedido'    => ['nombre' => 'Pedido',    'color' => '#2563EB'],
        'devolucion'=> ['nombre' => 'Devolución','color' => '#DC2626'],
        'proveedor' => ['nombre' => 'Proveedor', 'color' => '#EA580C'],
        'pago'      => ['nombre' => 'Pago',      'color' => '#059669'],
      ],
    ],

    /* ---------------------------------------------------- */
    'salud' => [
      'nombre' => 'Clínica / consulta',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',     'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',      'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',     'url' => './', 'activo' => true],
        ['icono' => 'calendario', 'texto' => 'Agenda',     'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Pacientes',  'url' => '#'],
        ['icono' => 'salud',      'texto' => 'Fichas',     'url' => '#'],
        ['icono' => 'moneda',     'texto' => 'Convenios',  'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'derivaciones', 'nombre' => 'Derivaciones', 'icono' => 'carpeta', 'color' => '#0891B2'],
        ['id' => 'examenes',     'nombre' => 'Exámenes',     'icono' => 'carpeta', 'color' => '#7C3AED'],
      ],
      'etiquetas' => [
        'hora'      => ['nombre' => 'Hora',       'color' => '#0891B2'],
        'resultado' => ['nombre' => 'Resultado',  'color' => '#7C3AED'],
        'convenio'  => ['nombre' => 'Convenio',   'color' => '#059669'],
        'urgente'   => ['nombre' => 'Urgente',    'color' => '#DC2626'],
      ],
    ],

    /* ---------------------------------------------------- */
    'educacion' => [
      'nombre' => 'Colegio / academia',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',      'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',       'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',      'url' => './', 'activo' => true],
        ['icono' => 'calendario', 'texto' => 'Calendario',  'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Estudiantes', 'url' => '#'],
        ['icono' => 'libro',      'texto' => 'Cursos',      'url' => '#'],
        ['icono' => 'tablero',    'texto' => 'Tareas',      'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'apoderados', 'nombre' => 'Apoderados', 'icono' => 'carpeta', 'color' => '#2563EB'],
        ['id' => 'docentes',   'nombre' => 'Docentes',   'icono' => 'carpeta', 'color' => '#059669'],
      ],
      'etiquetas' => [
        'matricula'  => ['nombre' => 'Matrícula',  'color' => '#2563EB'],
        'reunion'    => ['nombre' => 'Reunión',    'color' => '#7C3AED'],
        'pago'       => ['nombre' => 'Pago',       'color' => '#059669'],
        'importante' => ['nombre' => 'Importante', 'color' => '#DC2626'],
      ],
    ],

    /* ---------------------------------------------------- */
    'servicios' => [
      'nombre' => 'Servicios profesionales / agencia',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',     'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',      'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',     'url' => './', 'activo' => true],
        ['icono' => 'tablero',    'texto' => 'Proyectos',  'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Clientes',   'url' => '#'],
        ['icono' => 'calendario', 'texto' => 'Agenda',     'url' => '#'],
        ['icono' => 'moneda',     'texto' => 'Facturas',   'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'propuestas', 'nombre' => 'Propuestas', 'icono' => 'carpeta', 'color' => '#7C3AED'],
        ['id' => 'clientes',   'nombre' => 'Clientes',   'icono' => 'carpeta', 'color' => '#2563EB'],
      ],
      'etiquetas' => [
        'propuesta'  => ['nombre' => 'Propuesta',  'color' => '#7C3AED'],
        'contrato'   => ['nombre' => 'Contrato',   'color' => '#2563EB'],
        'facturacion'=> ['nombre' => 'Facturación','color' => '#059669'],
        'urgente'    => ['nombre' => 'Urgente',    'color' => '#DC2626'],
      ],
    ],

    /* ---------------------------------------------------- */
    'juridico' => [
      'nombre' => 'Estudio jurídico',
      'rail' => [
        ['icono' => 'inicio',     'texto' => 'Inicio',     'url' => '../'],
        ['icono' => 'panel',      'texto' => 'Panel',      'url' => '#'],
        ['icono' => 'sobre',      'texto' => 'Correo',     'url' => './', 'activo' => true],
        ['icono' => 'balanza',    'texto' => 'Causas',     'url' => '#'],
        ['icono' => 'personas',   'texto' => 'Clientes',   'url' => '#'],
        ['icono' => 'calendario', 'texto' => 'Audiencias', 'url' => '#'],
        ['icono' => 'tablero',    'texto' => 'Documentos', 'url' => '#'],
      ],
      'carpetas_propias' => [
        ['id' => 'clientes', 'nombre' => 'Clientes',   'icono' => 'carpeta', 'color' => '#10413E'],
        ['id' => 'tribunal', 'nombre' => 'Tribunales', 'icono' => 'carpeta', 'color' => '#C18A85'],
      ],
      'etiquetas' => [
        'urgente'   => ['nombre' => 'Urgente',   'color' => '#DC2626'],
        'contrato'  => ['nombre' => 'Contrato',  'color' => '#2563EB'],
        'audiencia' => ['nombre' => 'Audiencia', 'color' => '#7C3AED'],
        'pago'      => ['nombre' => 'Pago',      'color' => '#059669'],
      ],
    ],
  ];
}

/** Aplica un perfil sobre la configuración (sin pisar lo que ya se editó a mano) */
function mj_aplicar_perfil(array $cfg, string $clave): array
{
  $p = mj_perfiles()[$clave] ?? null;
  if (!$p) return $cfg;
  $cfg['rail']             = $p['rail'];
  $cfg['carpetas_propias'] = $p['carpetas_propias'];
  $cfg['etiquetas']        = $p['etiquetas'];
  return $cfg;
}
