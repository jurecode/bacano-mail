<?php
/* ============================================================
   MÓDULO DE CORREO — INSTALADOR Y PANEL DE CONFIGURACIÓN
   ------------------------------------------------------------
   1ª vez  → asistente de instalación (creas tu clave de acceso)
   Después → panel de ajustes protegido por esa clave

   Guarda todo en config.local.php. Nunca toca config.php, así
   puedes actualizar el módulo sin perder tu configuración.
   ============================================================ */

require_once __DIR__ . '/inc/cargar.php';
require_once __DIR__ . '/inc/ayuda.php';
require_once __DIR__ . '/inc/modulos.php';
require_once __DIR__ . '/inc/actualizador.php';

session_start();

$cfg        = mj_config();
$instalado  = mj_instalado() && !empty($cfg['admin']['clave_hash']);
$autenticado= !empty($_SESSION['mj_admin']);
$avisos     = [];
$errores    = [];

/* ------------------------------------------------------------
   Esquema del formulario: describe los campos y cómo guardarlos
   ------------------------------------------------------------ */
$SECCIONES = [

  'Identidad del sitio' => [
    'marca.nombre_full' => ['t' => 'texto', 'l' => 'Nombre de la empresa o sitio', 'p' => 'Mi Empresa'],
    'marca.nombre'      => ['t' => 'texto', 'l' => 'Marca del menú lateral', 'a' => 'Se muestra arriba en la barra oscura', 'p' => 'BACANO.MAIL'],
    'marca.nombre_corto'=> ['t' => 'texto', 'l' => 'Marca plegada', 'a' => 'Versión de 1 a 3 letras, para cuando el menú está angosto', 'p' => 'B.'],
    'marca.logo'        => ['t' => 'texto', 'l' => 'Logo (ruta o URL)', 'a' => 'Vacío = se usa el texto corto', 'p' => 'assets/img/logo.png'],
    'marca.url'         => ['t' => 'texto', 'l' => 'Enlace de vuelta al sitio', 'p' => '../'],
    'marca.titulo_web'  => ['t' => 'texto', 'l' => 'Título de la pestaña', 'p' => 'Correo'],
    'marca.favicon'     => ['t' => 'texto', 'l' => 'Icono de la pestaña (ruta o URL)', 'p' => 'assets/img/favicon.png'],
    'usuario.nombre'    => ['t' => 'texto', 'l' => 'Nombre del usuario', 'p' => 'Mi cuenta'],
    'usuario.email'     => ['t' => 'texto', 'l' => 'Correo del usuario', 'p' => 'correo@midominio.cl'],
    'usuario.avatar'    => ['t' => 'texto', 'l' => 'Foto del usuario (ruta o URL)', 'a' => 'Vacío = iniciales'],
  ],

  'Rubro' => [
    'perfil' => ['t' => 'perfil', 'l' => 'Perfil del negocio',
                 'a' => 'Rellena el menú lateral, las carpetas y las etiquetas. Puedes editarlos después en config.local.php'],
  ],

  'Apariencia' => [
    'tema.preset'   => ['t' => 'select', 'l' => 'Tema', 'o' => [
                          'aurora' => 'Aurora — degradado de colores',
                          'nieve'  => 'Nieve — blanco sobrio',
                          'tinta'  => 'Tinta — oscuro permanente',
                          'bosque' => 'Bosque — verde y tierra',
                        ]],
    'tema.modo'     => ['t' => 'select', 'l' => 'Modo', 'o' => [
                          'auto' => 'Automático (según el equipo)', 'claro' => 'Claro', 'oscuro' => 'Oscuro']],
    'tema.acento'   => ['t' => 'color', 'l' => 'Color de acento', 'a' => 'Vacío = el del tema'],
    'tema.fondo'    => ['t' => 'select', 'l' => 'Fondo', 'o' => [
                          'malla' => 'Degradado de color', 'solido' => 'Color plano', 'imagen' => 'Imagen']],
    'tema.fondo_imagen' => ['t' => 'texto', 'l' => 'Imagen de fondo (URL)', 'a' => 'Solo si elegiste "Imagen"'],
    'tema.densidad' => ['t' => 'select', 'l' => 'Densidad', 'o' => ['comoda' => 'Cómoda', 'compacta' => 'Compacta']],
    'tema.radio'    => ['t' => 'texto',  'l' => 'Redondeo de la ventana', 'p' => '22px'],
    'tema.ancho_maximo' => ['t' => 'texto', 'l' => 'Ancho máximo', 'p' => '1560px'],
    'tema.alto'     => ['t' => 'texto',  'l' => 'Alto', 'a' => 'Si lo incrustas bajo tu cabecera: calc(100svh - 64px)', 'p' => '100svh'],
    'tema.fuente_google' => ['t' => 'texto', 'l' => 'Fuente de Google', 'a' => 'Vacío = tipografía del sistema', 'p' => 'Inter:wght@400;500;600;700'],
    'tema.fuente_marca'  => ['t' => 'texto', 'l' => 'Tipografía de la marca', 'p' => "'Inter Tight', Helvetica, Arial, sans-serif"],
    'tema.color_marca'   => ['t' => 'texto', 'l' => 'Color de la marca', 'a' => 'Vacío = blanco. El menú lateral es oscuro: un color muy oscuro no se leería', 'p' => '#ffffff'],
    'tema.ventana'  => ['t' => 'bool', 'l' => 'Mostrar como ventana flotante'],
    'tema.animar_fondo' => ['t' => 'bool', 'l' => 'Animar el fondo'],
    'tema.permitir_cambio_modo' => ['t' => 'bool', 'l' => 'Botón de modo claro/oscuro'],
  ],

  'Interfaz' => [
    'interfaz.panel_lectura'      => ['t' => 'select', 'l' => 'Panel de lectura', 'o' => [
                                        'derecha' => 'A la derecha', 'abajo' => 'Abajo', 'oculto' => 'Oculto']],
    'interfaz.lineas_extracto'    => ['t' => 'numero', 'l' => 'Líneas de vista previa'],
    'interfaz.mensajes_por_pagina'=> ['t' => 'numero', 'l' => 'Mensajes por página'],
    'interfaz.mostrar_rail'       => ['t' => 'bool', 'l' => 'Barra lateral de iconos'],
    'interfaz.rail_colapsable'    => ['t' => 'bool', 'l' => 'Barra lateral desplegable'],
    'interfaz.mostrar_carpetas'   => ['t' => 'bool', 'l' => 'Columna de carpetas'],
    'interfaz.mostrar_buscador'   => ['t' => 'bool', 'l' => 'Buscador'],
    'interfaz.mostrar_filtros'    => ['t' => 'bool', 'l' => 'Filtros Todos / Leídos / No leídos'],
    'interfaz.mostrar_avatares'   => ['t' => 'bool', 'l' => 'Avatares'],
    'interfaz.mostrar_extracto'   => ['t' => 'bool', 'l' => 'Vista previa del mensaje'],
    'interfaz.mostrar_adjuntos'   => ['t' => 'bool', 'l' => 'Adjuntos'],
    'interfaz.seleccion_multiple' => ['t' => 'bool', 'l' => 'Selección múltiple'],
    'interfaz.menu_contextual'    => ['t' => 'bool', 'l' => 'Menú con clic derecho'],
    'interfaz.boton_redactar'     => ['t' => 'bool', 'l' => 'Botón de redactar'],
    'interfaz.atajos_teclado'     => ['t' => 'bool', 'l' => 'Atajos de teclado'],
    'interfaz.notificaciones'     => ['t' => 'bool', 'l' => 'Avisos flotantes con Deshacer'],
    'interfaz.agrupar_por_fecha'  => ['t' => 'bool', 'l' => 'Agrupar por fecha'],
    'interfaz.auto_marcar_leido'  => ['t' => 'bool', 'l' => 'Marcar como leído al abrir'],
    'interfaz.confirmar_eliminar' => ['t' => 'bool', 'l' => 'Preguntar antes de eliminar'],
  ],

  'Cuenta de correo' => [
    'origen.tipo' => ['t' => 'select', 'l' => 'Origen de los mensajes', 'o' => [
                        'demo' => 'Demostración (datos de ejemplo)',
                        'json' => 'Archivo JSON propio',
                        'imap' => 'Cuenta real por IMAP'], 'a' => 'Con IMAP se lee la casilla de verdad; con demostración, datos de ejemplo'],
    'origen.archivo'          => ['t' => 'texto', 'l' => 'Archivo JSON', 'a' => 'Solo si elegiste "Archivo JSON propio"'],
    'origen.imap.host'        => ['t' => 'texto', 'l' => 'Servidor IMAP', 'p' => 'imap.midominio.cl'],
    'origen.imap.puerto'      => ['t' => 'numero', 'l' => 'Puerto IMAP', 'p' => '993'],
    'origen.imap.cifrado'     => ['t' => 'select', 'l' => 'Cifrado IMAP', 'o' => ['ssl' => 'SSL', 'tls' => 'TLS', '' => 'Ninguno']],
    'origen.imap.usuario'     => ['t' => 'texto', 'l' => 'Usuario IMAP', 'p' => 'correo@midominio.cl'],
    'origen.imap.clave'       => ['t' => 'clave', 'l' => 'Clave IMAP', 'a' => 'Déjala vacía para conservar la guardada'],
    'origen.imap.carpeta'     => ['t' => 'texto', 'l' => 'Carpeta', 'p' => 'INBOX'],
    'origen.imap.validar_certificado' => ['t' => 'bool', 'l' => 'Validar certificado del servidor'],
    'origen.smtp.host'        => ['t' => 'texto', 'l' => 'Servidor SMTP', 'p' => 'smtp.midominio.cl'],
    'origen.smtp.puerto'      => ['t' => 'numero', 'l' => 'Puerto SMTP', 'p' => '465'],
    'origen.smtp.cifrado'     => ['t' => 'select', 'l' => 'Cifrado SMTP', 'o' => ['ssl' => 'SSL', 'tls' => 'TLS', '' => 'Ninguno']],
    'origen.smtp.usuario'     => ['t' => 'texto', 'l' => 'Usuario SMTP'],
    'origen.smtp.clave'       => ['t' => 'clave', 'l' => 'Clave SMTP', 'a' => 'Déjala vacía para conservar la guardada'],
    'origen.smtp.desde'       => ['t' => 'texto', 'l' => 'Enviar desde', 'p' => 'correo@midominio.cl'],
  ],

  'Actualizaciones' => [
    'actualizaciones.repositorio' => ['t' => 'texto', 'l' => 'Repositorio en GitHub', 'a' => 'Formato usuario/proyecto', 'p' => 'jurecode/bacano-mail'],
    'actualizaciones.token'       => ['t' => 'clave', 'l' => 'Token de GitHub', 'a' => 'Sólo si el repositorio es privado. Déjalo vacío para conservar el guardado'],
    'actualizaciones.revisar'     => ['t' => 'bool',  'l' => 'Avisarme cuando haya una versión nueva'],
  ],

  'Fechas y seguridad' => [
    'formato.zona_horaria' => ['t' => 'zona',  'l' => 'Zona horaria'],
    'formato.hoy'          => ['t' => 'texto', 'l' => 'Formato de hoy', 'p' => 'H:i'],
    'formato.semana'       => ['t' => 'texto', 'l' => 'Formato de esta semana', 'p' => 'D H:i'],
    'formato.anterior'     => ['t' => 'texto', 'l' => 'Formato antiguo', 'p' => 'd/m/Y'],
    'formato.completa'     => ['t' => 'texto', 'l' => 'Formato completo', 'p' => 'd/m/Y · H:i'],
    'seguridad.sanitizar_html'            => ['t' => 'bool', 'l' => 'Limpiar el HTML de los mensajes'],
    'seguridad.bloquear_imagenes_remotas' => ['t' => 'bool', 'l' => 'Bloquear imágenes externas'],
    'seguridad.abrir_enlaces_nueva'       => ['t' => 'bool', 'l' => 'Abrir enlaces en otra pestaña'],
  ],
];

/* ------------------------------------------------------------
   Utilidades del formulario
   ------------------------------------------------------------ */
function mj_leer(array $a, string $ruta)
{
  foreach (explode('.', $ruta) as $k) {
    if (!is_array($a) || !array_key_exists($k, $a)) return null;
    $a = $a[$k];
  }
  return $a;
}
function mj_poner(array &$a, string $ruta, $v): void
{
  $ks = explode('.', $ruta);
  $ult = array_pop($ks);
  foreach ($ks as $k) {
    if (!isset($a[$k]) || !is_array($a[$k])) $a[$k] = [];
    $a = &$a[$k];
  }
  $a[$ult] = $v;
}
function mj_token(): string
{
  if (empty($_SESSION['mj_token'])) $_SESSION['mj_token'] = bin2hex(random_bytes(16));
  return $_SESSION['mj_token'];
}

/* ------------------------------------------------------------
   Comprobación del entorno
   ------------------------------------------------------------ */
$req = [
  ['PHP 8.0 o superior', PHP_VERSION_ID >= 80000, PHP_VERSION, true],
  ['Extensión mbstring', extension_loaded('mbstring'), '', true],
  ['Extensión json',     extension_loaded('json'), '', true],
  ['Carpeta con permiso de escritura', is_writable(__DIR__), __DIR__, true],
  ['OpenSSL (para leer la casilla)', extension_loaded('openssl'), 'Sin OpenSSL no se puede conectar con el servidor de correo.', true],
  ['Extensión imap (opcional: si falta, se lee por sockets)', extension_loaded('imap'), '', false],
  ['Conexión segura HTTPS', !empty($_SERVER['HTTPS']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost', '', false],
];
$bloqueado = false;
foreach ($req as $r) { if ($r[3] && !$r[1]) $bloqueado = true; }

/* ------------------------------------------------------------
   Acciones
   ------------------------------------------------------------ */
$accion = $_POST['accion'] ?? '';

if ($accion && (!hash_equals(mj_token(), $_POST['token'] ?? ''))) {
  $errores[] = 'La sesión expiró. Vuelve a intentarlo.';
  $accion = '';
}

// --- Entrar al panel ---
if ($accion === 'entrar') {
  if (password_verify($_POST['clave'] ?? '', $cfg['admin']['clave_hash'])) {
    session_regenerate_id(true);
    $_SESSION['mj_admin'] = true;
    $autenticado = true;
    $avisos[] = 'Sesión iniciada.';
  } else {
    sleep(1);
    $errores[] = 'La clave no es correcta.';
  }
}

// --- Salir ---
if ($accion === 'salir') {
  $_SESSION = [];
  session_destroy();
  header('Location: instalar.php');
  exit;
}

// --- Probar la conexión (entra de verdad a la casilla) ---
if ($accion === 'probar' && (!$instalado || $autenticado)) {
  require_once __DIR__ . '/inc/proveedores.php';

  $conf = [
    'host'     => trim($_POST['c']['origen.imap.host'] ?? ''),
    'puerto'   => (int) ($_POST['c']['origen.imap.puerto'] ?? 993),
    'cifrado'  => $_POST['c']['origen.imap.cifrado'] ?? 'ssl',
    'usuario'  => trim($_POST['c']['origen.imap.usuario'] ?? ''),
    'clave'    => (string) ($_POST['c']['origen.imap.clave'] ?? ''),
    'carpeta'  => trim($_POST['c']['origen.imap.carpeta'] ?? '') ?: 'INBOX',
    'validar_certificado' => !empty($_POST['c']['origen.imap.validar_certificado']),
  ];
  // si no escribió la clave ahora, se usa la que ya está guardada
  if ($conf['clave'] === '') {
    $conf['clave'] = (string) ($cfg['origen']['imap']['clave'] ?? '');
  }

  if ($conf['host'] === '') {
    $errores[] = 'Escribe primero el servidor IMAP.';
  } elseif ($conf['usuario'] === '' || $conf['clave'] === '') {
    $errores[] = 'Faltan el usuario o la clave de la casilla.';
  } else {
    $ini = microtime(true);
    $prueba = mj_probar_imap($conf);
    $ms = round((microtime(true) - $ini) * 1000);

    if ($prueba['ok']) {
      $avisos[] = $prueba['mensaje'] . ' (' . $ms . ' ms)';
    } else {
      $errores[] = $prueba['mensaje'] . ' — ' . implode(' · ', array_slice($prueba['registro'], -2));
    }
  }
}

// --- Buscar actualización ---
$info_ver = null;
if ($accion === 'buscar_version' && (!$instalado || $autenticado)) {
  $info_ver = mj_buscar_version($cfg, true);
  if ($info_ver['error'])                 $errores[] = $info_ver['error'];
  elseif (mj_hay_actualizacion($info_ver)) $avisos[]  = 'Hay una versión nueva: ' . mj_e($info_ver['version']) . '. La instalada es la ' . mj_e(mj_version()) . '.';
  else                                     $avisos[]  = 'Ya tienes la última versión (' . mj_e(mj_version()) . ').';
}

// --- Aplicar actualización ---
if ($accion === 'actualizar' && $autenticado) {
  $info_ver = mj_buscar_version($cfg, true);
  if (!mj_hay_actualizacion($info_ver)) {
    $errores[] = 'No hay ninguna versión nueva que instalar.';
  } else {
    $r = mj_aplicar_actualizacion($info_ver);
    if ($r['ok']) { $avisos[] = $r['mensaje'] . ' Respaldo guardado en ' . mj_e($r['respaldo']) . '.'; }
    else          { $errores[] = $r['mensaje']; }
  }
}

// --- Guardar ---
if ($accion === 'guardar' && (!$instalado || $autenticado) && !$bloqueado) {
  $entrada = $_POST['c'] ?? [];
  $nuevo   = is_readable(__DIR__ . '/config.local.php') ? (require __DIR__ . '/config.local.php') : [];
  if (!is_array($nuevo)) $nuevo = [];

  foreach ($SECCIONES as $campos) {
    foreach ($campos as $ruta => $def) {
      $v = $entrada[$ruta] ?? null;
      switch ($def['t']) {
        case 'bool':
          mj_poner($nuevo, $ruta, isset($entrada[$ruta]));
          break;
        case 'numero':
          mj_poner($nuevo, $ruta, max(0, (int) $v));
          break;
        case 'clave':
          // Vacío = conservar la clave guardada
          if (trim((string) $v) !== '') mj_poner($nuevo, $ruta, (string) $v);
          break;
        case 'select':
          if (array_key_exists((string) $v, $def['o'])) mj_poner($nuevo, $ruta, (string) $v);
          break;
        case 'perfil':
          if (isset(mj_perfiles()[(string) $v])) mj_poner($nuevo, $ruta, (string) $v);
          break;
        case 'zona':
          if (in_array((string) $v, timezone_identifiers_list(), true)) mj_poner($nuevo, $ruta, (string) $v);
          break;
        default:
          mj_poner($nuevo, $ruta, trim((string) $v));
      }
    }
  }

  // Módulos activos
  $ids = array_keys(mj_modulos());
  mj_poner($nuevo, 'modulos.activos', array_values(array_intersect($ids, (array) ($_POST['modulos'] ?? []))));
  mj_poner($nuevo, 'modulos.mostrar_en_rail', isset($_POST['modulos_rail']));

  // Clave de administración
  $c1 = (string) ($_POST['admin_clave'] ?? '');
  $c2 = (string) ($_POST['admin_clave2'] ?? '');
  if ($c1 !== '' || !$instalado) {
    if (mb_strlen($c1) < 8)      $errores[] = 'La clave de administración debe tener al menos 8 caracteres.';
    elseif ($c1 !== $c2)         $errores[] = 'Las dos claves de administración no coinciden.';
    else mj_poner($nuevo, 'admin.clave_hash', password_hash($c1, PASSWORD_DEFAULT));
  }

  if (!$errores) {
    mj_poner($nuevo, 'admin.instalado', true);
    if (mj_guardar_config($nuevo)) {
      $_SESSION['mj_admin'] = true;
      $avisos[]  = 'Configuración guardada en config.local.php.';
      $instalado = true;
      $autenticado = true;
      $cfg = mj_fusionar(mj_aplicar_perfil(require __DIR__ . '/config.php', $nuevo['perfil'] ?? 'generico'), $nuevo);
    } else {
      $errores[] = 'No se pudo escribir config.local.php. Da permisos de escritura a la carpeta /mails.';
    }
  }
}

/* Al repintar tras "Probar la conexión" se conserva lo que la persona
   escribió; si no, se muestra lo guardado. Las claves nunca se repintan. */
$valor = function (string $ruta) use ($cfg) {
  if (isset($_POST['c']) && is_array($_POST['c'])) {
    return $_POST['c'][$ruta] ?? '';
  }
  return mj_leer($cfg, $ruta);
};
$modo_login = $instalado && !$autenticado;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $instalado ? 'Configuración del correo' : 'Instalación del correo' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#eef1f6; --card:#fff; --txt:#15171c; --txt2:#5a6070; --txt3:#8b92a3;
    --linea:rgba(17,19,24,.10); --campo:#f4f6fa; --acento:#111318; --ok:#059669; --mal:#DC2626;
    --r:14px;
  }
  @media (prefers-color-scheme:dark){
    :root{ --bg:#0a0c11; --card:#161a21; --txt:#e8eaf0; --txt2:#a6acba; --txt3:#767d8c;
           --linea:rgba(255,255,255,.10); --campo:#1e232c; --acento:#fff; }
    body{ color-scheme:dark; }
    .btn{ color:#111318 !important; }
  }
  *{ box-sizing:border-box; }
  body{
    margin:0; background:var(--bg); color:var(--txt);
    font-family:'Inter',system-ui,sans-serif; font-size:14.5px; line-height:1.55;
    -webkit-font-smoothing:antialiased;
  }
  .envoltura{ max-width:960px; margin:0 auto; padding:28px 18px 70px; }
  header.top{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:8px; }
  header.top h1{ margin:0; font-size:24px; letter-spacing:-.02em; }
  .paso{ font-size:12.5px; color:var(--txt3); }
  .sub{ margin:0 0 22px; color:var(--txt2); max-width:62ch; }
  .tarjeta{ background:var(--card); border:1px solid var(--linea); border-radius:var(--r); padding:20px 22px; margin-bottom:16px; }
  .tarjeta > h2{ margin:0 0 4px; font-size:16px; letter-spacing:-.01em; }
  .tarjeta > .desc{ margin:0 0 16px; font-size:13px; color:var(--txt3); }
  .campos{ display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:14px 20px; }
  .campo{ display:flex; flex-direction:column; gap:5px; min-width:0; }
  .campo > label{ font-size:12.5px; font-weight:600; color:var(--txt2); }
  .campo .ayuda{ font-size:11.5px; color:var(--txt3); }
  input[type=text],input[type=password],input[type=number],select{
    width:100%; height:40px; padding:0 12px; border:1px solid var(--linea);
    border-radius:10px; background:var(--campo); color:var(--txt);
    font:inherit; font-size:13.5px;
  }
  input:focus,select:focus{ outline:2px solid var(--acento); outline-offset:-1px; }
  .interruptores{ display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:8px 20px; }
  .sw{ display:flex; align-items:center; gap:9px; padding:7px 0; font-size:13.5px; color:var(--txt2); cursor:pointer; }
  .sw input{ appearance:none; width:38px; height:22px; border-radius:99px; background:var(--linea);
             position:relative; cursor:pointer; flex:0 0 auto; transition:background .18s; }
  .sw input::after{ content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%;
                    background:#fff; transition:transform .18s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
  .sw input:checked{ background:var(--ok); }
  .sw input:checked::after{ transform:translateX(16px); }
  .btn{
    display:inline-flex; align-items:center; gap:8px; height:42px; padding:0 20px;
    border:0; border-radius:10px; background:var(--acento); color:#fff;
    font:inherit; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none;
  }
  .btn.sec{ background:var(--campo); color:var(--txt); border:1px solid var(--linea); }
  .barra{ position:sticky; bottom:0; display:flex; gap:10px; flex-wrap:wrap; align-items:center;
          padding:14px 0; background:linear-gradient(transparent,var(--bg) 30%); }
  .aviso{ display:flex; gap:10px; padding:12px 14px; border-radius:10px; margin-bottom:10px; font-size:13.5px; }
  .aviso.ok{ background:color-mix(in srgb,var(--ok) 12%,transparent); color:var(--ok); }
  .aviso.mal{ background:color-mix(in srgb,var(--mal) 12%,transparent); color:var(--mal); }
  .reqs{ list-style:none; margin:0; padding:0; display:grid; gap:7px; }
  .reqs li{ display:flex; align-items:center; gap:9px; font-size:13.5px; color:var(--txt2); }
  .marca-req{ width:20px; height:20px; border-radius:50%; display:grid; place-items:center;
              font-size:12px; font-weight:700; flex:0 0 auto; color:#fff; }
  .si{ background:var(--ok); } .no{ background:var(--mal); } .op{ background:var(--txt3); }
  .perfiles{ display:grid; grid-template-columns:repeat(auto-fit,minmax(215px,1fr)); gap:10px; }
  .perfil{ position:relative; border:1.5px solid var(--linea); border-radius:12px; padding:12px 14px; cursor:pointer; }
  .perfil input{ position:absolute; opacity:0; }
  .perfil:has(:checked){ border-color:var(--ok); background:color-mix(in srgb,var(--ok) 7%,transparent); }
  .perfil strong{ display:block; font-size:13.5px; }
  .perfil span{ font-size:11.5px; color:var(--txt3); }
  .login{ max-width:390px; margin:9vh auto; }
  code{ background:var(--campo); padding:2px 6px; border-radius:6px; font-size:12.5px; }
  .pie{ margin-top:26px; font-size:12.5px; color:var(--txt3); }
  .pie a{ color:var(--txt2); }
</style>
</head>
<body>
<div class="envoltura">

<?php foreach ($avisos as $a): ?><div class="aviso ok">✓ <span><?= $a ?></span></div><?php endforeach; ?>
<?php foreach ($errores as $a): ?><div class="aviso mal">✕ <span><?= $a ?></span></div><?php endforeach; ?>

<?php if ($modo_login): /* ============ LOGIN ============ */ ?>

  <div class="login">
    <header class="top"><h1>Configuración del correo</h1></header>
    <p class="sub">Este módulo ya está instalado. Ingresa tu clave de administración para cambiar los ajustes.</p>
    <form method="post" class="tarjeta">
      <input type="hidden" name="token" value="<?= mj_e(mj_token()) ?>">
      <input type="hidden" name="accion" value="entrar">
      <div class="campo">
        <label for="clave">Clave de administración</label>
        <input type="password" id="clave" name="clave" required autofocus autocomplete="current-password">
      </div>
      <div style="margin-top:16px; display:flex; gap:10px;">
        <button class="btn" type="submit">Entrar</button>
        <a class="btn sec" href="./">Ver la bandeja</a>
      </div>
    </form>
    <p class="pie">¿Olvidaste la clave? Borra el archivo <code>config.local.php</code> por FTP y vuelve a instalar.</p>
  </div>

<?php else: /* ============ ASISTENTE / PANEL ============ */ ?>

  <header class="top">
    <h1><?= $instalado ? 'Configuración del correo' : 'Instalación del correo' ?></h1>
    <?php if ($instalado): ?>
      <form method="post" style="margin-left:auto; display:flex; gap:8px;">
        <input type="hidden" name="token" value="<?= mj_e(mj_token()) ?>">
        <input type="hidden" name="accion" value="salir">
        <a class="btn sec" href="./">Ver la bandeja</a>
        <button class="btn sec" type="submit">Cerrar sesión</button>
      </form>
    <?php endif; ?>
  </header>
  <p class="sub">
    <?= $instalado
      ? 'Cambia lo que necesites y guarda. Todo queda en config.local.php, así puedes actualizar el módulo sin perder estos ajustes.'
      : 'Completa estos datos una sola vez. Se guardan en config.local.php dentro de esta misma carpeta: no se necesita base de datos.' ?>
  </p>

  <!-- Requisitos -->
  <div class="tarjeta">
    <h2>Requisitos del servidor</h2>
    <p class="desc">Los tres primeros son obligatorios; los últimos solo hacen falta para conectar la cuenta real.</p>
    <ul class="reqs">
      <?php foreach ($req as [$txt, $ok, $extra, $obl]): ?>
        <li>
          <span class="marca-req <?= $ok ? 'si' : ($obl ? 'no' : 'op') ?>"><?= $ok ? '✓' : ($obl ? '✕' : '!') ?></span>
          <span><?= mj_e($txt) ?><?= $extra ? ' — <code>' . mj_e($extra) . '</code>' : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <!-- Versión y actualizaciones -->
  <?php
  $impedimentos = mj_puede_actualizar();   // vacío = sí se puede
  $ver_rem = $info_ver ?? (($instalado && !empty($cfg['actualizaciones']['revisar'])) ? mj_buscar_version($cfg) : null);
  $hay_new = $ver_rem && mj_hay_actualizacion($ver_rem);
  ?>
  <div class="tarjeta">
    <h2>Versión y actualizaciones</h2>
    <p class="desc">
      Instalada: <strong><?= mj_e(mj_version()) ?></strong>
      · <?= mj_es_git() ? 'esta copia se instaló con git' : 'esta copia se instaló copiando archivos' ?>
    </p>

    <?php if ($hay_new): ?>
      <div class="aviso ok" style="margin-bottom:12px">
        ↑ <span>
          Hay una versión nueva: <strong><?= mj_e($ver_rem['version']) ?></strong>.
          <?php if ($ver_rem['url']): ?><a href="<?= mj_e($ver_rem['url']) ?>" target="_blank" rel="noopener">Ver los cambios</a><?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <button class="btn sec" type="submit" name="accion" value="buscar_version" form="form-config">Buscar actualizaciones</button>

      <?php if ($hay_new && $autenticado && !$impedimentos): ?>
        <button class="btn" type="submit" name="accion" value="actualizar" form="form-config"
                onclick="return confirm('Se reemplazarán los archivos del módulo. Tu configuración y tus claves se conservan. ¿Continuar?')">
          Actualizar a <?= mj_e($ver_rem['version']) ?>
        </button>
      <?php endif; ?>
    </div>

    <?php if (mj_es_git()): ?>
      <p class="ayuda" style="margin-top:10px">
        Como está instalada con git, lo más limpio es actualizar desde la consola del hosting:
        <code>git pull</code>. Tu <code>config.local.php</code> no se toca porque está en el .gitignore.
      </p>
    <?php elseif ($impedimentos): ?>
      <p class="ayuda" style="margin-top:10px">
        Este servidor no puede aplicar la actualización solo (<?= mj_e(implode(', ', $impedimentos)) ?>).
        Descarga el ZIP del release y súbelo por FTP reemplazando los archivos,
        <strong>menos</strong> <code>config.local.php</code>.
      </p>
    <?php else: ?>
      <p class="ayuda" style="margin-top:10px">
        Al actualizar se guarda antes una copia completa en <code>respaldos/</code>,
        y nunca se tocan <code>config.local.php</code> ni tus claves.
      </p>
    <?php endif; ?>
  </div>

  <!-- Módulos -->
  <?php $mods = mj_modulos(); if ($mods): ?>
    <div class="tarjeta">
      <h2>Módulos</h2>
      <p class="desc">
        Todo lo que pongas en la carpeta <code>modulos/</code> aparece aquí. Al activarlo
        se suma al menú lateral del correo.
      </p>
      <div class="perfiles">
        <?php foreach ($mods as $m):
          $on = in_array($m['id'], (array) ($cfg['modulos']['activos'] ?? []), true); ?>
          <label class="perfil">
            <input type="checkbox" name="modulos[]" value="<?= mj_e($m['id']) ?>" form="form-config" <?= $on ? 'checked' : '' ?>>
            <strong><?= mj_e($m['nombre']) ?> <span style="font-weight:400; color:var(--txt3)">v<?= mj_e($m['version']) ?></span></strong>
            <span><?= mj_e($m['descripcion']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <label class="sw" style="margin-top:12px">
        <input type="checkbox" name="modulos_rail" form="form-config" <?= !empty($cfg['modulos']['mostrar_en_rail']) ? 'checked' : '' ?>>
        <span>Mostrar los módulos activos en el menú lateral</span>
      </label>
    </div>
  <?php endif; ?>

  <?php if ($bloqueado): ?>
    <div class="aviso mal">✕ <span>Falta algo obligatorio en el servidor. Corrígelo antes de continuar.</span></div>
  <?php endif; ?>

  <form method="post" autocomplete="off" id="form-config">
    <input type="hidden" name="token" value="<?= mj_e(mj_token()) ?>">

    <?php foreach ($SECCIONES as $titulo => $campos): ?>
      <div class="tarjeta">
        <h2><?= mj_e($titulo) ?></h2>

        <?php /* Interruptores agrupados aparte, se ven mejor */
        $normales = array_filter($campos, fn($d) => $d['t'] !== 'bool');
        $bools    = array_filter($campos, fn($d) => $d['t'] === 'bool'); ?>

        <?php if ($normales): ?>
          <div class="campos">
            <?php foreach ($normales as $ruta => $def):
              $id = 'f_' . md5($ruta); $v = $valor($ruta); ?>

              <?php if ($def['t'] === 'perfil'): ?>
                <div class="campo" style="grid-column:1/-1">
                  <label><?= mj_e($def['l']) ?></label>
                  <?php if (!empty($def['a'])): ?><span class="ayuda"><?= mj_e($def['a']) ?></span><?php endif; ?>
                  <div class="perfiles">
                    <?php foreach (mj_perfiles() as $k => $p): ?>
                      <label class="perfil">
                        <input type="radio" name="c[<?= mj_e($ruta) ?>]" value="<?= mj_e($k) ?>" <?= $v === $k ? 'checked' : '' ?>>
                        <strong><?= mj_e($p['nombre']) ?></strong>
                        <span><?= mj_e(implode(' · ', array_column($p['rail'], 'texto'))) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>

              <?php elseif ($def['t'] === 'select'): ?>
                <div class="campo">
                  <label for="<?= $id ?>"><?= mj_e($def['l']) ?></label>
                  <select id="<?= $id ?>" name="c[<?= mj_e($ruta) ?>]">
                    <?php foreach ($def['o'] as $k => $txt): ?>
                      <option value="<?= mj_e($k) ?>" <?= (string) $v === (string) $k ? 'selected' : '' ?>><?= mj_e($txt) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (!empty($def['a'])): ?><span class="ayuda"><?= mj_e($def['a']) ?></span><?php endif; ?>
                </div>

              <?php elseif ($def['t'] === 'zona'): ?>
                <div class="campo">
                  <label for="<?= $id ?>"><?= mj_e($def['l']) ?></label>
                  <select id="<?= $id ?>" name="c[<?= mj_e($ruta) ?>]">
                    <?php foreach (timezone_identifiers_list() as $z): ?>
                      <option value="<?= mj_e($z) ?>" <?= $v === $z ? 'selected' : '' ?>><?= mj_e($z) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

              <?php else: ?>
                <div class="campo">
                  <label for="<?= $id ?>"><?= mj_e($def['l']) ?></label>
                  <input id="<?= $id ?>" name="c[<?= mj_e($ruta) ?>]"
                         type="<?= $def['t'] === 'clave' ? 'password' : ($def['t'] === 'numero' ? 'number' : 'text') ?>"
                         value="<?= $def['t'] === 'clave' ? '' : mj_e($v) ?>"
                         placeholder="<?= mj_e($def['p'] ?? '') ?>"
                         <?= $def['t'] === 'clave' ? 'autocomplete="new-password"' : '' ?>>
                  <?php if (!empty($def['a'])): ?><span class="ayuda"><?= mj_e($def['a']) ?></span><?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($bools): ?>
          <div class="interruptores" style="<?= $normales ? 'margin-top:16px' : '' ?>">
            <?php foreach ($bools as $ruta => $def): ?>
              <label class="sw">
                <input type="checkbox" name="c[<?= mj_e($ruta) ?>]" <?= $valor($ruta) ? 'checked' : '' ?>>
                <span><?= mj_e($def['l']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($titulo === 'Cuenta de correo'):
              $tipoGuardado = mj_leer($cfg, 'origen.tipo') ?: 'demo';
              $claveImap = (string) mj_leer($cfg, 'origen.imap.clave');
              $claveSmtp = (string) mj_leer($cfg, 'origen.smtp.clave'); ?>

          <div class="estado-cuenta" style="margin-top:16px; padding:12px 14px; border:1px solid rgba(255,255,255,.12); border-radius:10px;">
            <strong style="display:block; margin-bottom:6px;">Lo que hay guardado ahora</strong>
            <span class="ayuda">
              Origen: <b><?= $tipoGuardado === 'imap' ? 'cuenta real por IMAP' : 'demostración' ?></b> ·
              clave IMAP: <b><?= $claveImap !== '' ? 'guardada' : 'NO guardada' ?></b> ·
              clave SMTP: <b><?= $claveSmtp !== '' ? 'guardada' : 'NO guardada' ?></b>
              <?php if ($tipoGuardado === 'imap' && $claveImap === ''): ?>
                <br>Sin la clave IMAP la bandeja seguirá mostrando mensajes de ejemplo:
                escríbela y usa <em>Guardar cambios</em>.
              <?php endif; ?>
            </span>
          </div>

          <div style="margin-top:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <button class="btn sec" type="submit" name="accion" value="probar">Probar la conexión</button>
            <span class="ayuda">Entra a la casilla con estos datos y cuenta los mensajes que hay.</span>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <!-- Clave de administración -->
    <div class="tarjeta">
      <h2>Clave de administración</h2>
      <p class="desc">
        <?= $instalado
          ? 'Déjala en blanco para conservar la actual.'
          : 'Protege esta pantalla. Sin ella, cualquiera que llegue a instalar.php podría cambiar tu configuración.' ?>
      </p>
      <div class="campos">
        <div class="campo">
          <label for="ac1">Clave<?= $instalado ? ' nueva' : '' ?></label>
          <input type="password" id="ac1" name="admin_clave" autocomplete="new-password" <?= $instalado ? '' : 'required' ?>>
          <span class="ayuda">Mínimo 8 caracteres.</span>
        </div>
        <div class="campo">
          <label for="ac2">Repetir la clave</label>
          <input type="password" id="ac2" name="admin_clave2" autocomplete="new-password" <?= $instalado ? '' : 'required' ?>>
        </div>
      </div>
    </div>

    <div class="barra">
      <button class="btn" type="submit" name="accion" value="guardar" <?= $bloqueado ? 'disabled' : '' ?>>
        <?= $instalado ? 'Guardar cambios' : 'Instalar' ?>
      </button>
      <?php if ($instalado): ?><a class="btn sec" href="./">Ver la bandeja</a><?php endif; ?>
    </div>
  </form>

  <p class="pie">
    Las claves del correo quedan en <code>config.local.php</code> (permisos 0640). Si prefieres no guardarlas
    en el archivo, define en tu hosting las variables <code>MJ_IMAP_CLAVE</code> y <code>MJ_SMTP_CLAVE</code>:
    tienen prioridad sobre lo guardado. Publica siempre el sitio por HTTPS.
  </p>

<?php endif; ?>

</div>
</body>
</html>
