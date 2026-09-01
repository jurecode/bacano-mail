<?php
/* ============================================================
   MÓDULO DE CORREO — Vista
   ------------------------------------------------------------
   Un solo punto de entrada:   mj_correo();
   Imprime CSS + marcado + JS. Se puede incrustar en cualquier
   página PHP con:   require 'mails/correo.php';  mj_correo();
   ============================================================ */

require_once __DIR__ . '/ayuda.php';
require_once __DIR__ . '/iconos.php';
require_once __DIR__ . '/cargar.php';
require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/proveedores.php';
require_once __DIR__ . '/actualizador.php';   // mj_version(): sirve para refrescar la caché

/** Punto de entrada. $ov permite sobrescribir cualquier opción del config. */
function mj_correo(array $ov = []): void
{
  static $instancias = 0;
  $instancias++;

  $cfg = mj_config($ov);

  $base = mj_base($cfg);
  $prov = mj_proveedor($cfg);
  $msgs = $prov->mensajes();

  // ---- Estado desde la URL (funciona incluso sin JavaScript) ----
  $carpetas_ids = array_merge(
    array_column($cfg['carpetas'], 'id'),
    array_column($cfg['carpetas_propias'], 'id')
  );
  $carpeta = mj_param('carpeta', $carpetas_ids, $carpetas_ids[0] ?? 'entrada');
  $filtro  = mj_param('f', ['todos', 'leidos', 'no_leidos'], 'todos');
  $busca   = trim((string) ($_GET['q'] ?? ''));
  $activo  = (string) ($_GET['m'] ?? '');

  // ---- Conteos por carpeta (para los badges) ----
  $conteo = [];
  foreach ($msgs as $m) {
    $c = $m['carpeta'];
    $conteo[$c]['total'] = ($conteo[$c]['total'] ?? 0) + 1;
    $conteo[$c]['no_leidos'] = ($conteo[$c]['no_leidos'] ?? 0) + ($m['leido'] ? 0 : 1);
  }

  // ---- Mensajes de la carpeta activa ----
  $lista = array_values(array_filter($msgs, fn($m) => $m['carpeta'] === $carpeta));
  if ($carpeta === 'destacado') {
    $lista = array_values(array_filter($msgs, fn($m) => $m['destacado'] || $m['importante']));
  }
  $lista = array_slice($lista, 0, (int) ($cfg['interfaz']['mensajes_por_pagina'] ?? 50));

  // Mensaje abierto: el de la URL, o el primero de la lista
  $sel = null;
  foreach ($lista as $m) { if ($m['id'] === $activo) { $sel = $m; break; } }
  if (!$sel && $cfg['interfaz']['panel_lectura'] !== 'oculto') {
    foreach ($lista as $m) {
      if ($filtro === 'todos' || ($filtro === 'leidos') === (bool) $m['leido']) { $sel = $m; break; }
    }
  }

  $t = $cfg['textos'];

  // ---- CSS + fuentes (una sola vez por página) ----
  if ($instancias === 1) {
    if (!empty($cfg['tema']['fuente_google'])) {
      echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
      echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
      echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family='
         . mj_e($cfg['tema']['fuente_google']) . '&display=swap">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . mj_e($base) . '/assets/css/mail.css?v=' . mj_e(mj_version()) . '">' . "\n";
  }

  // ---- Variables de tema en línea ----
  $estilo = '--mj-radio:' . $cfg['tema']['radio'] . ';'
          . '--mj-ancho:' . $cfg['tema']['ancho_maximo'] . ';'
          . '--mj-alto:'  . ($cfg['tema']['alto'] ?? '100svh') . ';'
          . '--mj-fuente:' . $cfg['tema']['fuente_texto'] . ';'
          . '--mj-fuente-titulo:' . $cfg['tema']['fuente_titulo'] . ';'
          . '--mj-fuente-marca:' . ($cfg['tema']['fuente_marca'] ?? $cfg['tema']['fuente_titulo']) . ';'
          . (!empty($cfg['tema']['color_marca']) ? '--mj-marca-color:' . $cfg['tema']['color_marca'] . ';' : '')
          . (!empty($cfg['tema']['acento']) ? '--mj-acento:' . $cfg['tema']['acento'] . ';' : '')
          . ($cfg['tema']['fondo'] === 'imagen' && $cfg['tema']['fondo_imagen']
              ? "--mj-fondo-img:url('" . mj_e($cfg['tema']['fondo_imagen']) . "');" : '');

  $raiz_attrs = mj_datos([
    'tema'      => $cfg['tema']['preset'],
    'modo'      => $cfg['tema']['modo'],
    'fondo'     => $cfg['tema']['fondo'],
    'animar'    => !empty($cfg['tema']['animar_fondo']),
    'densidad'  => $cfg['tema']['densidad'],
    'panel'     => $cfg['interfaz']['panel_lectura'],
    'carpeta'   => $carpeta,
    'filtro'    => $filtro,
    'vista'     => ($sel && $activo !== '') ? 'lector' : 'lista',
    'ventana'   => !empty($cfg['tema']['ventana']),
  ]);
  ?>
  <div class="mjmail" style="<?= $estilo ?>" <?= $raiz_attrs ?>>

    <?php if ($cfg['tema']['fondo'] !== 'solido'): ?>
      <div class="mj-fondo" aria-hidden="true"><i></i><i></i><i></i></div>
    <?php endif; ?>

    <div class="mj-ventana">

      <?php if ($cfg['interfaz']['mostrar_rail']) mj_v_rail($cfg, $base); ?>

      <?php if ($cfg['interfaz']['mostrar_carpetas']) mj_v_carpetas($cfg, $carpeta, $conteo); ?>

      <?php mj_v_lista($cfg, $lista, $carpeta, $filtro, $busca, $sel); ?>

      <?php
        // El resto de la conversación: mismo hilo, más antiguos primero.
        $conversacion = [];
        if ($sel && ($sel['hilo'] ?? '') !== '') {
          foreach ($msgs as $otro) {
            if (($otro['hilo'] ?? '') === $sel['hilo'] && $otro['id'] !== $sel['id']) {
              $conversacion[] = $otro;
            }
          }
          usort($conversacion, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        }
        if ($cfg['interfaz']['panel_lectura'] !== 'oculto') mj_v_lector($cfg, $sel, $conversacion);
      ?>

    </div>

    <?php /* Contenido de cada mensaje: el JS los intercambia sin recargar */ ?>
    <div class="mj-plantillas" hidden>
      <?php foreach ($lista as $m): ?>
        <template data-mensaje="<?= mj_e($m['id']) ?>"><?php mj_v_lector_contenido($cfg, $m); ?></template>
      <?php endforeach; ?>
    </div>

    <?php if ($cfg['interfaz']['menu_contextual']) mj_v_menu($cfg); ?>
    <?php if ($cfg['interfaz']['boton_redactar'])  mj_v_compositor($cfg); ?>
    <?php if ($cfg['interfaz']['mostrar_ayuda_atajos'] && $cfg['interfaz']['atajos_teclado']) mj_v_atajos($cfg); ?>

    <div class="mj-avisos" role="status" aria-live="polite"></div>

    <?php if (!mj_instalado()): ?>
      <div class="mj-sin-instalar">
        <strong>Módulo sin configurar</strong>
        <span>Estás viendo datos de ejemplo.</span>
        <a href="<?= mj_e($base) ?>/instalar.php">Configurar ahora</a>
      </div>
    <?php endif; ?>

    <script type="application/json" class="mj-datos"><?= json_encode([
      'textos'   => $t,
      'colores'  => $cfg['colores_estrella'],
      'carpetas' => array_map(fn($c) => ['id' => $c['id'], 'nombre' => $c['nombre']],
                     array_merge($cfg['carpetas'], $cfg['carpetas_propias'])),
      'opciones' => [
        'autoLeer'    => (bool) $cfg['interfaz']['auto_marcar_leido'],
        'atajos'      => (bool) $cfg['interfaz']['atajos_teclado'],
        'avisos'      => (bool) $cfg['interfaz']['notificaciones'],
        'confirmar'   => (bool) $cfg['interfaz']['confirmar_eliminar'],
        'seleccion'   => (bool) $cfg['interfaz']['seleccion_multiple'],
        'modo'        => $cfg['tema']['modo'],
        'permitirModo'=> (bool) $cfg['tema']['permitir_cambio_modo'],
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  </div>
  <?php
  if ($instancias === 1) {
    echo '<script src="' . mj_e($base) . '/assets/js/mail.js?v=' . mj_e(mj_version()) . '" defer></script>' . "\n";
  }
}

/* ------------------------------------------------------------
   BLOQUES DE LA VISTA
   ------------------------------------------------------------ */

/** Barra oscura de iconos */
function mj_v_rail(array $cfg, string $base = '.'): void
{
  // Menú del perfil + lo que aporten los módulos activos
  $items = array_merge($cfg['rail'], mj_modulos_rail($cfg, $base));

  // Las secciones sin dirección real no se muestran: un menú donde la mitad
  // de los botones no lleva a ninguna parte confunde más de lo que ayuda.
  // Se pueden mostrar igual, apagadas, con interfaz.rail_mostrar_pendientes.
  $pendiente = static fn(array $it): bool => trim((string) ($it['url'] ?? '')) === ''
                                          || trim((string) ($it['url'] ?? '')) === '#';
  $mostrarPendientes = !empty($cfg['interfaz']['rail_mostrar_pendientes']);
  if (!$mostrarPendientes) {
    $items = array_values(array_filter($items, fn($it) => !$pendiente($it)));
  }
  ?>
  <nav class="mj-rail" aria-label="Secciones del sistema">
    <a class="mj-rail-marca" href="<?= mj_e($cfg['marca']['url']) ?>">
      <?php if ($cfg['marca']['logo']): ?>
        <img src="<?= mj_e($cfg['marca']['logo']) ?>" alt="<?= mj_e($cfg['marca']['nombre_full']) ?>">
      <?php else:
        $corto = $cfg['marca']['nombre_corto'] ?: mb_substr($cfg['marca']['nombre'], 0, 1, 'UTF-8') . '.'; ?>
        <span class="mj-marca-corta" aria-hidden="true"><?= mj_e($corto) ?></span>
        <span class="mj-marca-larga"><?= mj_e($cfg['marca']['nombre']) ?></span>
      <?php endif; ?>
    </a>

    <?php if ($cfg['interfaz']['rail_colapsable']): ?>
      <button class="mj-rail-tirador" type="button" data-accion="rail"
              aria-label="Mostrar u ocultar los nombres del menú"><?= mj_icono('adelante', 16) ?></button>
    <?php endif; ?>

    <ul class="mj-rail-lista">
      <?php foreach ($items as $it): ?>
        <li>
          <?php if ($pendiente($it)): ?>
            <span class="mj-rail-item is-pendiente" title="Todavía no está disponible" aria-disabled="true">
              <?= mj_icono($it['icono'], 20) ?><span><?= mj_e($it['texto']) ?></span>
            </span>
          <?php else: ?>
            <a class="mj-rail-item<?= !empty($it['activo']) ? ' is-activo' : '' ?>"
               href="<?= mj_e($it['url']) ?>" <?= !empty($it['activo']) ? 'aria-current="page"' : '' ?>>
              <?= mj_icono($it['icono'], 20) ?><span><?= mj_e($it['texto']) ?></span>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <ul class="mj-rail-lista mj-rail-pie">
      <?php foreach ($cfg['rail_pie'] as $it):
            if ($pendiente($it) && !$mostrarPendientes) continue; ?>
        <li>
          <a class="mj-rail-item" href="<?= mj_e($it['url']) ?>">
            <?= mj_icono($it['icono'], 20) ?><span><?= mj_e($it['texto']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
<?php }

/**
 * Esconde la parte citada de una respuesta detrás de un botón, como hace
 * Gmail: lo que se escribió ahora queda arriba, y "El … escribió:" con sus
 * ">" se pliega. El corte puede caer dentro de un párrafo, porque los
 * correos de texto llegan como un solo bloque con <br>.
 */
function mj_plegar_citado(string $html): string
{
    $marcas = [
        '/(?:<br\s*\/?>\s*)*(?:El |On |&gt;\s*El )[^<]{0,220}?escribi[oó]\s*:/iu',
        '/(?:<br\s*\/?>\s*)+&gt;/u',      // la primera línea citada
        '/(?:<br\s*\/?>\s*)*-{2,}\s*Mensaje reenviado\s*-{2,}/iu',
    ];

    $corte = null;
    foreach ($marcas as $patron) {
        if (preg_match($patron, $html, $m, PREG_OFFSET_CAPTURE)) {
            $corte = $corte === null ? $m[0][1] : min($corte, $m[0][1]);
        }
    }
    if ($corte === null || $corte === 0) {
        return $html;
    }

    $nuevo  = substr($html, 0, $corte);
    $citado = ltrim(substr($html, $corte));
    $citado = preg_replace('/^(?:<br\s*\/?>\s*)+/i', '', $citado);

    if (trim(strip_tags($nuevo)) === '' || trim(strip_tags($citado)) === '') {
        return $html;
    }

    // El corte suele caer dentro de un <p>: se cierra el visible y se abre
    // uno nuevo para la cita, así el HTML sigue siendo válido.
    if (substr_count($nuevo, '<p') > substr_count($nuevo, '</p')) {
        $nuevo .= '</p>';
    }
    if (substr_count($citado, '</p') > substr_count($citado, '<p')) {
        $citado = '<p>' . $citado;
    }

    return $nuevo
        . '<details class="mj-citado"><summary title="Mostrar lo anterior">···</summary>'
        . '<div class="mj-citado-cuerpo">' . $citado . '</div></details>';
}

/** Columna de carpetas */
function mj_v_carpetas(array $cfg, string $carpeta, array $conteo): void
{
  $t = $cfg['textos'];
  $enlace = fn($id) => '?' . http_build_query(['carpeta' => $id]);
  ?>
  <aside class="mj-carpetas" id="mj-carpetas" aria-label="Carpetas de correo">
    <div class="mj-carpetas-cab">
      <h2 class="mj-h2"><?= mj_e($t['correo']) ?></h2>
      <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="cerrar-carpetas"
              aria-label="Cerrar el menú de carpetas"><?= mj_icono('cerrar', 18) ?></button>
    </div>

    <nav class="mj-carpetas-scroll">
      <ul class="mj-nav">
        <?php foreach ($cfg['carpetas'] as $c):
          $n = $c['contador'] === 'no_leidos' ? ($conteo[$c['id']]['no_leidos'] ?? 0)
             : ($c['contador'] === 'total' ? ($conteo[$c['id']]['total'] ?? 0) : 0); ?>
          <li>
            <a class="mj-nav-item<?= $carpeta === $c['id'] ? ' is-activo' : '' ?>"
               href="<?= mj_e($enlace($c['id'])) ?>" data-carpeta="<?= mj_e($c['id']) ?>"
               <?= $carpeta === $c['id'] ? 'aria-current="true"' : '' ?>>
              <?= mj_icono($c['icono'], 19) ?>
              <span><?= mj_e($c['nombre']) ?></span>
              <?php if ($n > 0): ?><em class="mj-badge"><?= $n ?></em><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($cfg['carpetas_propias'] || $cfg['mostrar_agregar_carpeta']): ?>
        <div class="mj-nav-titulo">
          <span><?= mj_e($t['carpetas']) ?></span>
          <button class="mj-icono-btn" type="button" data-accion="config-carpetas"
                  aria-label="Administrar carpetas"><?= mj_icono('ajustes', 16) ?></button>
        </div>
        <ul class="mj-nav">
          <?php if ($cfg['mostrar_agregar_carpeta']): ?>
            <li>
              <button class="mj-nav-item mj-nav-agregar" type="button" data-accion="nueva-carpeta">
                <?= mj_icono('carpeta_mas', 19) ?><span><?= mj_e($t['agregar_carpeta']) ?></span>
              </button>
            </li>
          <?php endif; ?>
          <?php foreach ($cfg['carpetas_propias'] as $c): ?>
            <li>
              <a class="mj-nav-item<?= $carpeta === $c['id'] ? ' is-activo' : '' ?>"
                 href="<?= mj_e($enlace($c['id'])) ?>" data-carpeta="<?= mj_e($c['id']) ?>"
                 <?php if (!empty($c['color'])): ?>style="--mj-carpeta-color:<?= mj_e($c['color']) ?>"<?php endif; ?>>
                <?= mj_icono($c['icono'] ?? 'carpeta', 19) ?>
                <span><?= mj_e($c['nombre']) ?></span>
                <?php if (!empty($conteo[$c['id']]['total'])): ?>
                  <em class="mj-badge mj-badge-suave"><?= (int) $conteo[$c['id']]['total'] ?></em>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </nav>

    <div class="mj-carpetas-pie">
      <div class="mj-usuario">
        <?= mj_v_avatar(['nombre' => $cfg['usuario']['nombre'], 'avatar' => $cfg['usuario']['avatar']], 32) ?>
        <div class="mj-usuario-txt">
          <strong><?= mj_e($cfg['usuario']['nombre']) ?></strong>
          <span><?= mj_e($cfg['usuario']['email']) ?></span>
        </div>
      </div>
      <?php if (function_exists('mj_dentro') && mj_dentro()): ?>
        <a class="mj-icono-btn" href="cuenta.php" title="Ajustes de tu cuenta" aria-label="Ajustes de tu cuenta">
          <?= mj_icono('ajustes', 18) ?>
        </a>
        <a class="mj-icono-btn mj-salir" href="?salir=1" title="Cerrar sesión" aria-label="Cerrar sesión">
          <?= mj_icono('salir', 18) ?>
        </a>
      <?php endif; ?>
      <?php if ($cfg['tema']['permitir_cambio_modo']): ?>
        <button class="mj-icono-btn" type="button" data-accion="modo" aria-label="Cambiar entre modo claro y oscuro">
          <span class="mj-modo-claro"><?= mj_icono('luna', 18) ?></span>
          <span class="mj-modo-oscuro"><?= mj_icono('sol', 18) ?></span>
        </button>
      <?php endif; ?>
    </div>
  </aside>
<?php }

/** Columna del listado */
function mj_v_lista(array $cfg, array $lista, string $carpeta, string $filtro, string $busca, ?array $sel): void
{
  $t = $cfg['textos'];
  $nombre_carpeta = 'Correo';
  foreach (array_merge($cfg['carpetas'], $cfg['carpetas_propias']) as $c) {
    if ($c['id'] === $carpeta) { $nombre_carpeta = $c['nombre']; break; }
  }
  $grupo_previo = '';
  ?>
  <section class="mj-col-lista" aria-label="Lista de mensajes">

    <header class="mj-lista-cab">
      <?php if ($cfg['interfaz']['mostrar_carpetas']): ?>
        <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="abrir-carpetas"
                aria-label="Abrir el menú de carpetas"><?= mj_icono('menu', 20) ?></button>
      <?php endif; ?>
      <h1 class="mj-h1" data-rol="titulo"><?= mj_e($nombre_carpeta) ?></h1>
      <button class="mj-icono-btn mj-refrescar" type="button" data-accion="refrescar"
              aria-label="Actualizar la lista"><?= mj_icono('refrescar', 18) ?></button>
      <?php if ($cfg['interfaz']['boton_redactar']): ?>
        <button class="mj-redactar" type="button" data-accion="redactar"
                aria-label="<?= mj_e($t['redactar']) ?>" title="<?= mj_e($t['redactar']) ?>">
          <?= mj_icono('mas', 20) ?>
        </button>
      <?php endif; ?>
    </header>

    <?php if ($cfg['interfaz']['mostrar_buscador']): ?>
      <div class="mj-buscador">
        <?= mj_icono('buscar', 18) ?>
        <input type="search" id="mj-buscar" name="q" value="<?= mj_e($busca) ?>"
               placeholder="<?= mj_e($t['buscar']) ?>" aria-label="<?= mj_e($t['buscar']) ?>"
               autocomplete="off" spellcheck="false">
        <button class="mj-limpiar" type="button" data-accion="limpiar-busqueda"
                aria-label="Limpiar la búsqueda" <?= $busca === '' ? 'hidden' : '' ?>><?= mj_icono('cerrar', 14) ?></button>
      </div>
    <?php endif; ?>

    <?php if ($cfg['interfaz']['mostrar_filtros']): ?>
      <div class="mj-filtros" role="tablist" aria-label="Filtrar mensajes">
        <?php foreach (['todos' => $t['todos'], 'leidos' => $t['leidos'], 'no_leidos' => $t['no_leidos']] as $k => $txt): ?>
          <a role="tab" class="mj-filtro<?= $filtro === $k ? ' is-activo' : '' ?>"
             href="?<?= mj_e(http_build_query(['carpeta' => $carpeta, 'f' => $k])) ?>"
             data-filtro="<?= $k ?>" aria-selected="<?= $filtro === $k ? 'true' : 'false' ?>">
            <?= mj_e($txt) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($cfg['interfaz']['seleccion_multiple']): ?>
      <div class="mj-barra-sel" hidden>
        <label class="mj-check mj-check-todos">
          <input type="checkbox" data-accion="marcar-todos" aria-label="Seleccionar todos los mensajes">
          <span></span>
        </label>
        <strong data-rol="conteo-sel">0</strong>
        <span><?= mj_e($t['seleccionados']) ?></span>
        <div class="mj-barra-sel-acciones">
          <button class="mj-icono-btn" type="button" data-masivo="leido" aria-label="Marcar como leídos"><?= mj_icono('sobre_abrir', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-masivo="destacar" aria-label="Destacar"><?= mj_icono('estrella', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-masivo="archivar" aria-label="Archivar"><?= mj_icono('archivar', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-masivo="eliminar" aria-label="Eliminar"><?= mj_icono('papelera', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-accion="cancelar-sel" aria-label="Cancelar selección"><?= mj_icono('cerrar', 17) ?></button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (($mj_fallo = mj_fallo_imap()) !== null): ?>
      <p class="mj-aviso-demo">
        <strong>Estás viendo mensajes de ejemplo.</strong>
        No se pudo leer la casilla real: <?= mj_e($mj_fallo) ?>
        Revisa los datos en <code>instalar.php</code>.
      </p>
    <?php elseif (($cfg['origen']['tipo'] ?? 'demo') !== 'imap'): ?>
      <p class="mj-aviso-demo">
        <strong>Estás viendo mensajes de ejemplo.</strong>
        Para leer tu casilla real, elige <em>Cuenta real por IMAP</em> en <code>instalar.php</code>.
      </p>
    <?php endif; ?>

    <ol class="mj-lista" id="mj-lista">
      <?php
      $pasa = static fn(array $m): bool => $filtro === 'todos'
        || ($filtro === 'leidos' && $m['leido'])
        || ($filtro === 'no_leidos' && !$m['leido']);

      foreach ($lista as $i => $m):
        if ($cfg['interfaz']['agrupar_por_fecha']):
          $g = mj_grupo_fecha($m['fecha'], $cfg);
          if ($g !== $grupo_previo): $grupo_previo = $g;
            // El separador se oculta si su grupo no tiene ningún mensaje visible
            $hay = false;
            foreach (array_slice($lista, $i) as $sig) {
              if (mj_grupo_fecha($sig['fecha'], $cfg) !== $g) break;
              if ($pasa($sig)) { $hay = true; break; }
            } ?>
            <li class="mj-sep" data-grupo="<?= $g ?>" aria-hidden="true" <?= $hay ? '' : 'hidden' ?>><?= mj_e($cfg['textos'][$g] ?? $g) ?></li>
          <?php endif;
        endif;
        mj_v_item($cfg, $m, $sel && $sel['id'] === $m['id'], $carpeta, !$pasa($m));
      endforeach; ?>
    </ol>

    <div class="mj-vacio" <?= $lista ? 'hidden' : '' ?> data-rol="vacio">
      <?= mj_icono('bandeja', 34) ?>
      <p class="mj-vacio-t"><?= mj_e($t['sin_mensajes']) ?></p>
      <p class="mj-vacio-d"><?= mj_e($t['sin_mensajes_desc']) ?></p>
    </div>
    <div class="mj-vacio" hidden data-rol="sin-resultados">
      <?= mj_icono('buscar', 34) ?>
      <p class="mj-vacio-t"><?= mj_e($t['sin_resultados']) ?></p>
      <p class="mj-vacio-d">Prueba con otras palabras o revisa otra carpeta.</p>
    </div>

    <footer class="mj-lista-pie">
      <?php $visibles = count(array_filter($lista, $pasa)); ?>
      <span><?= $visibles ?> mensaje<?= $visibles === 1 ? '' : 's' ?></span>
      <?php if ($cfg['interfaz']['atajos_teclado'] && $cfg['interfaz']['mostrar_ayuda_atajos']): ?>
        <button type="button" class="mj-link" data-accion="atajos"><?= mj_icono('teclado', 15) ?> <?= mj_e($t['atajos']) ?></button>
      <?php endif; ?>
    </footer>
  </section>
<?php }

/** Una fila del listado */
function mj_v_item(array $cfg, array $m, bool $activo, string $carpeta, bool $oculto = false): void
{
  $url = '?' . http_build_query(['carpeta' => $carpeta, 'm' => $m['id']]);
  $persona = in_array($m['carpeta'], ['enviados', 'borrador'], true)
    ? ($m['para'][0] ?? $m['de']) : $m['de'];
  $busq = mb_strtolower(
    $persona['nombre'] . ' ' . $persona['email'] . ' ' . $m['asunto'] . ' ' . $m['extracto'], 'UTF-8');
  ?>
  <li class="mj-item<?= $activo ? ' is-activo' : '' ?><?= $m['leido'] ? '' : ' is-nuevo' ?>"
      <?= $oculto ? 'hidden' : '' ?>
      <?= mj_datos([
        'id'        => $m['id'],
        'leido'     => $m['leido'] ? '1' : '0',
        'destacado' => $m['destacado'] ?: '',
        'buscar'    => $busq,
        'grupo'     => mj_grupo_fecha($m['fecha'], $cfg),
      ]) ?>>
    <a class="mj-item-liga" href="<?= mj_e($url) ?>">
      <span class="mj-sr">Abrir mensaje: <?= mj_e($m['asunto']) ?></span>
    </a>

    <?php if ($cfg['interfaz']['seleccion_multiple']): ?>
      <label class="mj-check mj-item-check">
        <input type="checkbox" aria-label="Seleccionar: <?= mj_e($m['asunto']) ?>"><span></span>
      </label>
    <?php endif; ?>

    <span class="mj-punto" aria-hidden="true"></span>

    <?php if ($cfg['interfaz']['mostrar_avatares']): ?>
      <?= mj_v_avatar($persona, 38) ?>
    <?php endif; ?>

    <div class="mj-item-txt">
      <div class="mj-item-linea">
        <span class="mj-de"><?= mj_e($persona['nombre'] ?: $persona['email']) ?></span>
        <time class="mj-hora" datetime="<?= mj_e($m['fecha']) ?>"><?= mj_e(mj_fecha_corta($m['fecha'], $cfg)) ?></time>
      </div>
      <div class="mj-item-asunto"><?= mj_e($m['asunto']) ?></div>
      <?php if ($cfg['interfaz']['mostrar_extracto']): ?>
        <p class="mj-item-extracto" style="--mj-lineas:<?= (int) $cfg['interfaz']['lineas_extracto'] ?>">
          <?= mj_e($m['extracto']) ?>
        </p>
      <?php endif; ?>
      <?php if ($m['etiquetas'] || ($m['adjuntos'] && $cfg['interfaz']['mostrar_adjuntos'])): ?>
        <div class="mj-item-meta">
          <?php foreach ($m['etiquetas'] as $et):
            $e = $cfg['etiquetas'][$et] ?? null; if (!$e) continue; ?>
            <span class="mj-tag" style="--mj-tag:<?= mj_e($e['color']) ?>"><?= mj_e($e['nombre']) ?></span>
          <?php endforeach; ?>
          <?php if ($m['adjuntos'] && $cfg['interfaz']['mostrar_adjuntos']): ?>
            <span class="mj-clip" title="<?= count($m['adjuntos']) ?> adjunto(s)">
              <?= mj_icono('clip', 14) ?><?= count($m['adjuntos']) ?>
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <button class="mj-estrella<?= $m['destacado'] ? ' is-on' : '' ?>" type="button"
            <?php if ($m['destacado']): ?>style="--mj-estrella:<?= mj_e($m['destacado']) ?>"<?php endif; ?>
            aria-label="Destacar mensaje" aria-pressed="<?= $m['destacado'] ? 'true' : 'false' ?>">
      <?= mj_icono('estrella', 17) ?>
    </button>
  </li>
<?php }

/** Panel de lectura */
function mj_v_lector(array $cfg, ?array $sel, array $conversacion = []): void
{
  $t = $cfg['textos'];
  ?>
  <section class="mj-lector" aria-label="Mensaje">
    <header class="mj-lector-barra">
      <button class="mj-btn-txt mj-solo-movil" type="button" data-accion="volver">
        <?= mj_icono('atras', 18) ?><span><?= mj_e($t['volver']) ?></span>
      </button>
      <div class="mj-acciones">
        <?php foreach ($cfg['acciones_lector'] as $a): ?>
          <button class="mj-btn-txt" type="button" data-accion="<?= mj_e($a['id']) ?>">
            <?= mj_icono($a['icono'], 17) ?><span><?= mj_e($a['texto']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="mj-acciones-fin">
        <button class="mj-icono-btn" type="button" data-accion="agendar" aria-label="Agendar en el calendario">
          <?= mj_icono('calendario', 19) ?>
        </button>
      </div>
    </header>

    <div class="mj-lector-scroll" data-rol="lector">
      <?php if ($sel) { mj_v_lector_contenido($cfg, $sel, $conversacion); } else { ?>
        <div class="mj-vacio mj-vacio-lector">
          <?= mj_icono('sobre_abrir', 34) ?>
          <p class="mj-vacio-t"><?= mj_e($t['sin_seleccion']) ?></p>
          <p class="mj-vacio-d"><?= mj_e($t['sin_seleccion_desc']) ?></p>
        </div>
      <?php } ?>
    </div>
  </section>
<?php }

/** Contenido de un mensaje dentro del lector */
function mj_v_lector_contenido(array $cfg, array $m, array $conversacion = []): void
{
  $t     = $cfg['textos'];
  $html  = mj_plegar_citado(mj_html_seguro($m['cuerpo'], $cfg));
  $block = mj_tiene_imagenes_bloqueadas($html);
  ?>
  <article class="mj-msg"
           data-id="<?= mj_e($m['id']) ?>"
           data-nombre="<?= mj_e($m['de']['nombre']) ?>"
           data-email="<?= mj_e($m['de']['email']) ?>"
           data-asunto="<?= mj_e($m['asunto']) ?>"
           data-todos="<?= mj_e(implode(', ', array_filter(array_merge(
                 array_column($m['para'], 'email'), array_column($m['cc'], 'email'))))) ?>">
    <header class="mj-msg-cab">
      <?= mj_v_avatar($m['de'], 46) ?>
      <div class="mj-msg-quien">
        <div class="mj-msg-linea">
          <strong class="mj-msg-de"><?= mj_e($m['de']['nombre'] ?: $m['de']['email']) ?></strong>
          <time class="mj-msg-fecha" datetime="<?= mj_e($m['fecha']) ?>"><?= mj_e(mj_fecha_larga($m['fecha'], $cfg)) ?></time>
        </div>
        <h2 class="mj-msg-asunto"><?= mj_e($m['asunto']) ?></h2>
        <div class="mj-msg-destinos">
          <?php if ($m['para']): ?>
            <span><em><?= mj_e($t['para']) ?>:</em> <?= mj_e(mj_personas($m['para'])) ?></span>
          <?php endif; ?>
          <?php if ($m['cc']): ?>
            <span><em><?= mj_e($t['cc']) ?>:</em> <?= mj_e(mj_personas($m['cc'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <?php if ($m['etiquetas']): ?>
      <div class="mj-msg-tags">
        <?php foreach ($m['etiquetas'] as $et):
          $e = $cfg['etiquetas'][$et] ?? null; if (!$e) continue; ?>
          <span class="mj-tag" style="--mj-tag:<?= mj_e($e['color']) ?>"><?= mj_e($e['nombre']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($block): ?>
      <div class="mj-aviso-img">
        <?= mj_icono('spam', 16) ?>
        <span><?= mj_e($t['imagenes_bloqueadas']) ?></span>
        <button type="button" class="mj-link" data-accion="mostrar-imagenes"><?= mj_e($t['mostrar_imagenes']) ?></button>
      </div>
    <?php endif; ?>

    <?php if ($conversacion): ?>
      <div class="mj-hilo">
        <p class="mj-hilo-titulo"><?= count($conversacion) ?>
          <?= count($conversacion) === 1 ? 'mensaje anterior' : 'mensajes anteriores' ?> en esta conversación</p>

        <?php foreach ($conversacion as $previo):
              $quien = $previo['de']['nombre'] ?: $previo['de']['email']; ?>
          <details class="mj-hilo-item">
            <summary>
              <b><?= mj_e($quien) ?></b>
              <span><?= mj_e(mb_substr(trim(strip_tags($previo['cuerpo'])), 0, 70)) ?></span>
              <time><?= mj_e(mj_fecha_corta($previo['fecha'], $cfg)) ?></time>
            </summary>
            <div class="mj-hilo-cuerpo"><?= mj_plegar_citado(mj_html_seguro($previo['cuerpo'], $cfg)) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mj-msg-cuerpo"><?= $html ?></div>

    <?php if ($m['adjuntos'] && $cfg['interfaz']['mostrar_adjuntos']): ?>
      <section class="mj-adjuntos">
        <h3 class="mj-h3"><?= mj_icono('clip', 15) ?> <?= mj_e($t['adjuntos']) ?> (<?= count($m['adjuntos']) ?>)</h3>
        <ul>
          <?php foreach ($m['adjuntos'] as $a): ?>
            <li class="mj-adjunto">
              <span class="mj-adjunto-tipo" data-tipo="<?= mj_e($a['tipo']) ?>"><?= mj_e(strtoupper($a['tipo'] ?: '?')) ?></span>
              <span class="mj-adjunto-nom"><?= mj_e($a['nombre']) ?></span>
              <span class="mj-adjunto-peso"><?= mj_e(mj_peso($a['peso'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <footer class="mj-msg-pie">
      <button class="mj-btn" type="button" data-accion="responder"><?= mj_icono('responder', 17) ?> Responder</button>
      <button class="mj-btn mj-btn-2" type="button" data-accion="reenviar"><?= mj_icono('reenviar', 17) ?> Reenviar</button>
    </footer>
  </article>
<?php }

/** Menú contextual (clic derecho) */
function mj_v_menu(array $cfg): void
{ ?>
  <div class="mj-menu" role="menu" hidden aria-label="Acciones del mensaje">
    <?php foreach ($cfg['menu_contextual_items'] as $it):
      if (!empty($it['sep'])) { echo '<hr class="mj-menu-sep">'; continue; }
      $tipo = $it['tipo'] ?? 'simple'; ?>

      <?php if ($tipo === 'colores'): ?>
        <div class="mj-menu-item mj-menu-colores">
          <span><?= mj_e($it['texto']) ?></span>
          <span class="mj-colores">
            <?php foreach ($cfg['colores_estrella'] as $c): ?>
              <button type="button" class="mj-color" style="--mj-c:<?= mj_e($c) ?>"
                      data-menu="destacar" data-color="<?= mj_e($c) ?>"
                      aria-label="Destacar en <?= mj_e($c) ?>"><?= mj_icono('estrella', 14) ?></button>
            <?php endforeach; ?>
          </span>
        </div>

      <?php elseif ($tipo === 'carpetas'): ?>
        <div class="mj-menu-item mj-menu-sub" tabindex="0" role="menuitem" aria-haspopup="true">
          <span><?= mj_e($it['texto']) ?></span><?= mj_icono('adelante', 14) ?>
          <div class="mj-submenu" role="menu">
            <?php foreach (array_merge($cfg['carpetas'], $cfg['carpetas_propias']) as $c): ?>
              <button type="button" class="mj-menu-item" role="menuitem"
                      data-menu="<?= mj_e($it['id']) ?>" data-destino="<?= mj_e($c['id']) ?>">
                <?= mj_icono($c['icono'] ?? 'carpeta', 15) ?><span><?= mj_e($c['nombre']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

      <?php else: ?>
        <button type="button" class="mj-menu-item" role="menuitem" data-menu="<?= mj_e($it['id']) ?>">
          <span><?= mj_e($it['texto']) ?></span>
        </button>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php }

/** Ventana de redacción (solo vista: no envía todavía) */
function mj_v_compositor(array $cfg): void
{
  $t = $cfg['textos']; ?>
  <div class="mj-modal" data-modal="redactar" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-modal-caja" role="dialog" aria-modal="true" aria-label="<?= mj_e($t['redactar']) ?>">
      <header class="mj-modal-cab">
        <h2 class="mj-h2"><?= mj_e($t['redactar']) ?></h2>
        <button class="mj-icono-btn" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 18) ?></button>
      </header>
      <form class="mj-form" data-rol="form-redactar" data-token="<?= mj_e(function_exists('mj_token_sesion') ? mj_token_sesion() : '') ?>">
        <label class="mj-campo">
          <span><?= mj_e($t['para']) ?></span>
          <input type="email" name="para" multiple placeholder="destinatario@correo.cl" autocomplete="off">
        </label>
        <label class="mj-campo">
          <span><?= mj_e($t['cc']) ?></span>
          <input type="email" name="cc" multiple placeholder="opcional" autocomplete="off">
        </label>
        <label class="mj-campo">
          <span><?= mj_e($t['asunto']) ?></span>
          <input type="text" name="asunto" placeholder="Asunto del mensaje">
        </label>
        <label class="mj-campo mj-campo-area">
          <span class="mj-sr">Mensaje</span>
          <textarea name="cuerpo" rows="9" placeholder="Escribe tu mensaje…"></textarea>
        </label>
        <footer class="mj-modal-pie">
          <div class="mj-modal-pie-iconos">
            <button class="mj-icono-btn" type="button" aria-label="Adjuntar archivo"><?= mj_icono('clip', 18) ?></button>
            <button class="mj-icono-btn" type="button" aria-label="Descartar"><?= mj_icono('papelera', 18) ?></button>
          </div>
          <div class="mj-modal-pie-btns">
            <button class="mj-btn mj-btn-2" type="button" data-accion="cerrar-modal"><?= mj_e($t['cancelar']) ?></button>
            <button class="mj-btn" type="submit"><?= mj_icono('enviar', 16) ?> <?= mj_e($t['enviar']) ?></button>
          </div>
        </footer>
      </form>
    </div>
  </div>
<?php }

/** Ayuda de atajos */
function mj_v_atajos(array $cfg): void
{
  $filas = [
    ['↑ / ↓ · J / K', 'Moverse por la lista'],
    ['Enter',          'Abrir el mensaje'],
    ['Esc',            'Cerrar el mensaje o la ventana'],
    ['/',              'Ir al buscador'],
    ['R',              'Responder'],
    ['A',              'Responder a todos'],
    ['F',              'Reenviar'],
    ['E',              'Archivar'],
    ['S',              'Destacar'],
    ['U',              'Marcar como no leído'],
    ['C',              'Redactar un correo nuevo'],
    ['Supr',           'Eliminar'],
    ['?',              'Ver esta ayuda'],
  ]; ?>
  <div class="mj-modal" data-modal="atajos" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-modal-caja mj-modal-chica" role="dialog" aria-modal="true" aria-label="<?= mj_e($cfg['textos']['atajos']) ?>">
      <header class="mj-modal-cab">
        <h2 class="mj-h2"><?= mj_e($cfg['textos']['atajos']) ?></h2>
        <button class="mj-icono-btn" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 18) ?></button>
      </header>
      <ul class="mj-atajos">
        <?php foreach ($filas as [$k, $d]): ?>
          <li><kbd><?= mj_e($k) ?></kbd><span><?= mj_e($d) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php }

/** Avatar con imagen o iniciales */
function mj_v_avatar(array $p, int $tam = 38): string
{
  $nombre = $p['nombre'] ?: ($p['email'] ?? '');
  $st = 'width:' . $tam . 'px;height:' . $tam . 'px';
  if (!empty($p['avatar'])) {
    return '<img class="mj-avatar" src="' . mj_e($p['avatar']) . '" alt="" loading="lazy" style="' . $st . '">';
  }
  return '<span class="mj-avatar mj-avatar-txt" aria-hidden="true" style="' . $st
       . ';--mj-av:' . mj_color_avatar($nombre) . '">' . mj_e(mj_iniciales($nombre)) . '</span>';
}

/* ------------------------------------------------------------
   Internos
   ------------------------------------------------------------ */

/** Lee un parámetro GET validándolo contra una lista blanca */
function mj_param(string $clave, array $validos, string $defecto): string
{
  $v = (string) ($_GET[$clave] ?? '');
  return in_array($v, $validos, true) ? $v : $defecto;
}


