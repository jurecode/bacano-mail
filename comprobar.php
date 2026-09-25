<?php
/* ============================================================
   BACANO.MAIL · Comprobar el servidor antes de instalar
   ------------------------------------------------------------
   Este archivo está escrito a propósito en PHP antiguo: sin
   match, sin funciones flecha, sin tipos. El resto del programa
   necesita PHP 8, y en un servidor con PHP 7 ni siquiera se
   puede leer —sale la página en blanco y no hay forma de saber
   por qué—. Éste sí se lee en cualquier parte y lo dice.

   Se abre en el navegador:  tudominio.cl/mails/comprobar.php
   ============================================================ */

$MINIMO = '8.0';

$fila = array();
$hayFallo = false;

function anotar($estado, $que, $detalle) {
    global $fila, $hayFallo;
    if ($estado === 'mal') { $hayFallo = true; }
    $fila[] = array('e' => $estado, 'q' => $que, 'd' => $detalle);
}

/* --- 1. La versión de PHP, que es lo que más falla al mudarse --- */
if (version_compare(PHP_VERSION, $MINIMO, '>=')) {
    anotar('bien', 'PHP ' . PHP_VERSION, 'Suficiente (hace falta ' . $MINIMO . ' o más).');
} else {
    anotar('mal', 'PHP ' . PHP_VERSION,
        'Demasiado antiguo. El correo necesita PHP ' . $MINIMO . ' o más. '
      . 'En cPanel se cambia en "Selector de versión de PHP". Con esta versión '
      . 'las páginas salen en blanco, sin ningún mensaje.');
}

/* --- 2. Las extensiones --- */
$extensiones = array(
    'openssl'  => 'conectar con el correo por SSL y cifrar las contraseñas guardadas',
    'mbstring' => 'leer los acentos de los asuntos y los nombres',
    'fileinfo' => 'reconocer el tipo de los archivos adjuntos',
    'zip'      => 'instalar las actualizaciones desde el panel',
    'curl'     => 'hablar con GitHub y con cPanel',
);
foreach ($extensiones as $ext => $paraQue) {
    if (extension_loaded($ext)) {
        anotar('bien', 'Extensión ' . $ext, 'Presente.');
    } elseif ($ext === 'curl' && ini_get('allow_url_fopen')) {
        anotar('ojo', 'Extensión curl', 'No está, pero allow_url_fopen sí: se puede vivir sin ella.');
    } elseif ($ext === 'zip' || $ext === 'curl') {
        anotar('ojo', 'Extensión ' . $ext, 'No está. Hace falta para ' . $paraQue . '. El correo funciona igual.');
    } else {
        anotar('mal', 'Extensión ' . $ext, 'Falta, y hace falta para ' . $paraQue . '.');
    }
}

/* --- 3. Permisos de escritura --- */
$raiz = dirname(__FILE__);
if (is_writable($raiz)) {
    anotar('bien', 'Permisos de la carpeta', 'Se puede escribir en ' . basename($raiz) . '/.');
} else {
    anotar('mal', 'Permisos de la carpeta',
        'No se puede escribir en ' . $raiz . '. El instalador no podrá guardar '
      . 'config.local.php. Dale permiso de escritura a la carpeta (755 suele bastar '
      . 'si el dueño es el usuario del hosting).');
}

$datos = $raiz . '/data';
if (!file_exists($datos)) {
    anotar('ojo', 'Carpeta data', 'No existe todavía; se creará sola si la carpeta principal deja escribir.');
} elseif (is_writable($datos)) {
    anotar('bien', 'Carpeta data', 'Se puede escribir: ahí van la agenda, las firmas y las sesiones.');
} else {
    anotar('mal', 'Carpeta data', 'Existe pero no deja escribir. Sin eso no hay sesiones ni contactos.');
}

/* --- 4. ¿Ya hay una instalación aquí? --- */
if (file_exists($raiz . '/config.local.php')) {
    anotar('ojo', 'config.local.php',
        'Ya existe. Si copiaste esta carpeta de otro servidor, trae los datos '
      . 'del anterior y su clave de administración: instalar.php te pedirá ESA '
      . 'clave. Si quieres empezar de cero, bórralo o renómbralo y vuelve a entrar.');
} else {
    anotar('bien', 'config.local.php', 'No existe: instalar.php abrirá el asistente de primera vez.');
}

/* --- 4b. Copias del config olvidadas al mudar de servidor --- */
$sueltos = array();
$d = @opendir($raiz);
if ($d) {
    while (($n = readdir($d)) !== false) {
        if (strpos($n, 'config.local.php') === 0 && $n !== 'config.local.php') { $sueltos[] = $n; }
        if (strpos($n, 'config.php.') === 0) { $sueltos[] = $n; }
    }
    closedir($d);
}
if (count($sueltos)) {
    anotar('mal', 'Copias del archivo de configuración',
        'Hay ' . implode(', ', $sueltos) . ' en la carpeta. Al no terminar en .php, el '
      . 'servidor los entrega como texto plano y cualquiera puede leer tus contraseñas '
      . 'desde el navegador. Bórralos, o renómbralos para que terminen en .php '
      . '(por ejemplo anterior.config.local.php).');
}

/* --- 4c. El .htaccess que protege la configuración --- */
if (file_exists($raiz . '/.htaccess')) {
    anotar('bien', 'Archivo .htaccess', 'Presente: tapa config.local.php y la carpeta data.');
} else {
    anotar('mal', 'Archivo .htaccess',
        'No está. Es el que impide descargar config.local.php desde el navegador. '
      . 'Al copiar la carpeta por FTP se pierde fácil, porque empieza por punto y '
      . 'muchos programas lo esconden. Súbelo.');
}

/* --- 5. Subida de archivos --- */
anotar('ojo', 'Tamaño máximo de subida',
    'upload_max_filesize = ' . ini_get('upload_max_filesize')
  . ', post_max_size = ' . ini_get('post_max_size')
  . '. Es el tope para adjuntar archivos al escribir un correo.');

/* --- 6. ¿Se puede salir al puerto del correo? --- */
$host = isset($_GET['host']) ? preg_replace('/[^a-zA-Z0-9.\-]/', '', $_GET['host']) : '';
if ($host !== '') {
    $err = 0; $msj = '';
    $s = @fsockopen('ssl://' . $host, 993, $err, $msj, 6);
    if ($s) {
        fclose($s);
        anotar('bien', 'Conexión con ' . $host . ':993', 'El servidor de correo responde.');
    } else {
        anotar('mal', 'Conexión con ' . $host . ':993',
            'No responde (' . $msj . '). Suele ser el cortafuegos del hosting bloqueando la salida.');
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comprobación del servidor</title>
<meta name="robots" content="noindex, nofollow">
<style>
  *{ box-sizing:border-box }
  body{ margin:0; padding:34px 18px; background:#0b1220; color:#e8eaf0;
        font:15px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif }
  .caja{ max-width:760px; margin:0 auto }
  h1{ font-size:22px; margin:0 0 4px }
  .sub{ margin:0 0 24px; color:#93a0b8; font-size:13.5px }
  .veredicto{ padding:14px 16px; border-radius:12px; margin-bottom:20px; font-weight:600 }
  .v-bien{ background:rgba(34,197,94,.14); border:1px solid rgba(34,197,94,.4) }
  .v-mal{ background:rgba(229,72,77,.14); border:1px solid rgba(229,72,77,.42) }
  ul{ list-style:none; margin:0; padding:0 }
  li{ padding:13px 0; border-bottom:1px solid rgba(255,255,255,.08); display:flex; gap:12px }
  li:last-child{ border-bottom:0 }
  .m{ flex:0 0 auto; width:22px; font-size:16px; text-align:center }
  .bien .m{ color:#6ee7a8 } .mal .m{ color:#fca5a5 } .ojo .m{ color:#fcd34d }
  .q{ font-weight:600 }
  .d{ color:#93a0b8; font-size:13.5px }
  code{ background:rgba(255,255,255,.08); padding:1px 5px; border-radius:5px; font-size:12.5px }
  a{ color:#93a0b8 }
</style>
</head>
<body>
  <div class="caja">
    <h1>Comprobación del servidor</h1>
    <p class="sub">Qué hace falta para que el correo funcione aquí. Esta página se lee en
       cualquier versión de PHP, así que sirve aunque el resto salga en blanco.</p>

    <?php if ($hayFallo): ?>
      <p class="veredicto v-mal">Hay algo que impide instalar. Mira lo marcado en rojo.</p>
    <?php else: ?>
      <p class="veredicto v-bien">Todo en orden: puedes abrir <code>instalar.php</code>.</p>
    <?php endif; ?>

    <ul>
      <?php foreach ($fila as $f): ?>
        <li class="<?php echo $f['e']; ?>">
          <span class="m"><?php echo $f['e'] === 'bien' ? '&#10003;' : ($f['e'] === 'mal' ? '&#10007;' : '!'); ?></span>
          <span>
            <span class="q"><?php echo htmlspecialchars($f['q'], ENT_QUOTES, 'UTF-8'); ?></span><br>
            <span class="d"><?php echo htmlspecialchars($f['d'], ENT_QUOTES, 'UTF-8'); ?></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>

    <p class="sub" style="margin-top:22px">
      Para probar además si este servidor alcanza tu correo, agrega el dominio a la
      dirección: <code>comprobar.php?host=mail.tudominio.cl</code>
    </p>
    <p><a href="instalar.php">Ir a la instalación &rarr;</a></p>
  </div>
</body>
</html>
