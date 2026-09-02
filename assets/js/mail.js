/* ============================================================
   MÓDULO DE CORREO — Interacción (vanilla JS, sin dependencias)
   ------------------------------------------------------------
   Todo es de vista: filtra, abre, destaca, selecciona y avisa.
   Cuando se conecte el correo real, cada acción marcada con
   //  ⇢ API   es el punto donde va la llamada al servidor.
   ============================================================ */
(function () {
  'use strict';

  document.querySelectorAll('.mjmail').forEach(function (raiz) {
    if (raiz.dataset.listo) return;
    raiz.dataset.listo = '1';
    new Correo(raiz);
  });

  function Correo(raiz) {
    var d       = leerDatos(raiz);
    var T       = d.textos || {};
    var OP      = d.opciones || {};
    var lista   = raiz.querySelector('.mj-lista');
    var lector  = raiz.querySelector('[data-rol="lector"]');
    var buscar  = raiz.querySelector('#mj-buscar');
    var menu    = raiz.querySelector('.mj-menu');
    var avisos  = raiz.querySelector('.mj-avisos');
    var plant   = raiz.querySelector('.mj-plantillas');
    var objetivo = null;     // mensaje sobre el que actúa el menú contextual
    var temporizador = null;

    /* ---------------------------------------------------
       MODO CLARO / OSCURO
       --------------------------------------------------- */
    var guardado = almacen('mj-modo');
    if (guardado) raiz.dataset.modo = guardado;
    else if (raiz.dataset.modo === 'auto') {
      raiz.dataset.modo = matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
    }
    if (almacen('mj-rail') === 'abierto') raiz.dataset.rail = 'abierto';

    /* ---------------------------------------------------
       DELEGACIÓN DE EVENTOS
       --------------------------------------------------- */
    raiz.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-accion]');
      if (btn && raiz.contains(btn)) { accion(btn.dataset.accion, btn, ev); return; }

      var opc = ev.target.closest('[data-menu]');
      if (opc) { ev.preventDefault(); accionMenu(opc.dataset.menu, opc); return; }

      var masivo = ev.target.closest('[data-masivo]');
      if (masivo) { accionMasiva(masivo.dataset.masivo); return; }

      var filtro = ev.target.closest('.mj-filtro');
      if (filtro) { ev.preventDefault(); aplicarFiltro(filtro.dataset.filtro); return; }

      var estrella = ev.target.closest('.mj-estrella');
      if (estrella) { ev.preventDefault(); destacar(estrella.closest('.mj-item')); return; }

      var liga = ev.target.closest('.mj-item-liga');
      if (liga) { ev.preventDefault(); abrir(liga.closest('.mj-item'), true); return; }
    });

    raiz.addEventListener('change', function (ev) {
      if (ev.target.closest('.mj-item-check')) refrescarSeleccion();
    });

    /* Buscador ------------------------------------------------ */
    if (buscar) {
      buscar.addEventListener('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(filtrar, 110);
        var lim = raiz.querySelector('.mj-limpiar');
        if (lim) lim.hidden = buscar.value === '';
      });
      buscar.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { buscar.value = ''; filtrar(); buscar.blur(); }
      });
    }

    /* Menú contextual ---------------------------------------- */
    if (menu && lista) {
      lista.addEventListener('contextmenu', function (ev) {
        var it = ev.target.closest('.mj-item');
        if (!it) return;
        ev.preventDefault();
        abrirMenu(it, ev.clientX, ev.clientY);
      });
      // Toque largo en móvil
      var pulso = null;
      lista.addEventListener('touchstart', function (ev) {
        var it = ev.target.closest('.mj-item');
        if (!it) return;
        var t = ev.touches[0];
        pulso = setTimeout(function () { abrirMenu(it, t.clientX, t.clientY); }, 500);
      }, { passive: true });
      ['touchend', 'touchmove', 'touchcancel'].forEach(function (e) {
        lista.addEventListener(e, function () { clearTimeout(pulso); }, { passive: true });
      });
      document.addEventListener('click', function (ev) {
        if (!menu.hidden && !menu.contains(ev.target)) cerrarMenu();
      });
      document.addEventListener('scroll', cerrarMenu, true);
    }

    /* Teclado ------------------------------------------------- */
    if (OP.atajos) document.addEventListener('keydown', atajos);

    /* Historial ----------------------------------------------- */
    window.addEventListener('popstate', function (ev) {
      var id = (ev.state && ev.state.m) || new URLSearchParams(location.search).get('m');
      var it = id && lista && lista.querySelector('.mj-item[data-id="' + css(id) + '"]');
      if (it) abrir(it, false); else raiz.dataset.vista = 'lista';
    });

    /* ---------------------------------------------------
       ACCIONES DE BOTONES
       --------------------------------------------------- */
    function accion(nombre, btn, ev) {
      switch (nombre) {
        case 'rail':
          raiz.dataset.rail = raiz.dataset.rail === 'abierto' ? '' : 'abierto';
          almacen('mj-rail', raiz.dataset.rail || 'cerrado');
          break;

        case 'modo':
          raiz.dataset.modo = raiz.dataset.modo === 'oscuro' ? 'claro' : 'oscuro';
          almacen('mj-modo', raiz.dataset.modo);
          break;

        case 'abrir-carpetas':  raiz.dataset.carpetas = 'abierto'; break;
        case 'cerrar-carpetas': raiz.dataset.carpetas = ''; break;

        case 'volver':
          raiz.dataset.vista = 'lista';
          quitarParam('m');
          break;

        case 'limpiar-busqueda':
          if (buscar) { buscar.value = ''; buscar.focus(); }
          btn.hidden = true;
          filtrar();
          break;

        case 'refrescar':
          btn.animate([{ transform: 'rotate(0)' }, { transform: 'rotate(360deg)' }], { duration: 600, easing: 'ease-in-out' });
          //  ⇢ API: volver a pedir la lista de mensajes
          aviso('Bandeja actualizada');
          break;

        case 'redactar':      abrirModal('redactar'); break;
        case 'atajos':        abrirModal('atajos');   break;
        case 'cerrar-modal':  cerrarModal();          break;

        case 'responder':      responder('uno');   break;
        case 'responder_todo': responder('todos'); break;
        case 'reenviar':       responder('reenviar'); break;

        case 'eliminar':
          if (OP.carpeta === 'papelera') { borrarSiempre(itemActivo()); }
          else { quitar(itemActivo(), 'eliminado', 'papelera'); }
          break;
        case 'archivar':   quitar(itemActivo(), 'archivado', 'archivo'); break;
        case 'importante': destacar(itemActivo()); break;

        case 'mostrar-imagenes':
          lector.querySelectorAll('img[data-mj-src]').forEach(function (img) {
            img.src = img.getAttribute('data-mj-src');
            img.removeAttribute('data-mj-src');
          });
          var av = btn.closest('.mj-aviso-img'); if (av) av.remove();
          break;

        case 'nueva-carpeta':
        case 'config-carpetas':
          aviso('Por ahora las carpetas se definen en instalar.php');
          break;

        case 'agendar':
          aviso('Programar el envío todavía no está disponible');
          break;

        case 'marcar-todos':
          var marcar = btn.checked;
          visibles().forEach(function (it) {
            var c = it.querySelector('.mj-item-check input'); if (c) c.checked = marcar;
          });
          refrescarSeleccion();
          break;

        case 'cancelar-sel': limpiarSeleccion(); break;
      }
    }

    /* ---------------------------------------------------
       ABRIR MENSAJE
       --------------------------------------------------- */
    function abrir(item, empujar) {
      if (!item || !lector) return;
      var id = item.dataset.id;
      var tpl = plant && plant.querySelector('template[data-mensaje="' + css(id) + '"]');

      lista.querySelectorAll('.mj-item.is-activo').forEach(function (i) { i.classList.remove('is-activo'); });
      item.classList.add('is-activo');

      if (tpl) {
        lector.replaceChildren(tpl.content.cloneNode(true));
        lector.scrollTop = 0;
      }
      if (OP.autoLeer) marcarLeido(item, true);
      raiz.dataset.vista = 'lector';

      var u = new URL(location.href);
      u.searchParams.set('m', id);
      if (empujar) history.pushState({ m: id }, '', u); else history.replaceState({ m: id }, '', u);
    }

    function itemActivo() { return lista && lista.querySelector('.mj-item.is-activo'); }

    /* ---------------------------------------------------
       ESTADOS DEL MENSAJE
       --------------------------------------------------- */
    /* Envía una acción al servidor. Si falla, avisa: la pantalla ya cambió,
       pero la persona debe saber que en la casilla no se aplicó. */
    function accionServidor(accion, id, valor) {
      var token = raiz.querySelector('[data-rol="form-redactar"]');
      token = token ? (token.dataset.token || '') : '';
      if (!token || !id) return Promise.resolve({ ok: false });

      var datos = new FormData();
      datos.append('accion', accion);
      datos.append('id', id);
      datos.append('valor', valor || '');
      datos.append('token', token);

      return fetch('acciones.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok && r.mensaje) aviso(r.mensaje);
          return r;
        })
        .catch(function () { return { ok: false }; });
    }

    function marcarLeido(item, leido) {
      if (!item || (item.dataset.leido === '1') === leido) return;
      item.dataset.leido = leido ? '1' : '0';
      item.classList.toggle('is-nuevo', !leido);
      badge(leido ? -1 : 1);
      accionServidor(leido ? 'leido' : 'no_leido', item.dataset.id);
    }

    function destacar(item, color) {
      if (!item) return;
      var on = !!item.dataset.destacado && !color;
      var c  = color || (d.colores && d.colores[0]) || '#F59E0B';
      item.dataset.destacado = on ? '' : c;
      var b = item.querySelector('.mj-estrella');
      if (b) {
        b.classList.toggle('is-on', !on);
        b.setAttribute('aria-pressed', on ? 'false' : 'true');
        b.style.setProperty('--mj-estrella', c);
      }
      accionServidor(on ? 'quitar_destacado' : 'destacar', item.dataset.id, c);
    }

    /* Borrado definitivo: sin papelera donde caer y sin Deshacer, así que
       se pregunta con todas las letras antes de tocar nada. */
    function borrarSiempre(item) {
      if (!item) return;

      var quien = (item.dataset.nombre || 'este mensaje');
      var cuantos = parseInt(item.dataset.hilo || '1', 10);
      var texto = cuantos > 1
        ? 'Se borrarán definitivamente los ' + cuantos + ' mensajes de esta conversación de ' + quien + '.'
        : 'Se borrará definitivamente el mensaje de ' + quien + '.';

      confirmar(texto, function () { borrarYa(item, cuantos); });
    }

    function borrarYa(item, cuantos) {
      var padre = item.parentNode, sig = item.nextSibling;
      if (item.dataset.leido === '0') badge(-1);
      item.remove();
      contar();
      if (item.classList.contains('is-activo')) {
        var otro = visibles()[0];
        if (otro) abrir(otro, false); else vaciarLector();
      }

      accionServidor('borrar', item.dataset.id).then(function (r) {
        if (r && r.ok) {
          aviso(cuantos > 1 ? 'Conversación eliminada definitivamente' : 'Mensaje eliminado definitivamente');
        } else {
          // no se pudo: se devuelve a su sitio para no mentir
          padre.insertBefore(item, sig);
          contar(); filtrar();
        }
      });
    }

    function quitar(item, verbo, destino) {
      if (!item) return;
      if (OP.confirmar && !confirm('¿Eliminar este mensaje?')) return;
      var padre = item.parentNode, sig = item.nextSibling;
      if (!item.dataset.leido || item.dataset.leido === '0') badge(-1);
      item.remove();
      contar();
      if (item.classList.contains('is-activo')) {
        var otro = visibles()[0];
        if (otro) abrir(otro, false); else vaciarLector();
      }
      var carpeta = destino || (verbo && verbo.indexOf('archiv') === 0 ? 'archivo' : 'papelera');
      accionServidor('mover', item.dataset.id, carpeta);

      aviso('Mensaje ' + verbo, T.deshacer || 'Deshacer', function () {
        padre.insertBefore(item, sig);
        if (item.dataset.leido === '0') badge(1);
        contar(); filtrar();
        // se devuelve a donde estaba
        accionServidor('mover', item.dataset.id, item.dataset.carpeta || 'entrada');
      });
    }

    function accionMasiva(tipo) {
      var sel = seleccionados();
      if (!sel.length) return;
      sel.forEach(function (it) {
        if (tipo === 'leido')    marcarLeido(it, true);
        if (tipo === 'destacar') destacar(it, (d.colores || [])[0]);
        if (tipo === 'archivar' || tipo === 'eliminar') {
          if (it.dataset.leido === '0') badge(-1);
          it.remove();
        }
      });
      if (tipo === 'archivar' || tipo === 'eliminar') { contar(); vaciarSiFalta(); }
      limpiarSeleccion();
      aviso(sel.length + ' mensaje' + (sel.length > 1 ? 's' : '') + ' ' +
            ({ leido: 'marcados como leídos', destacar: 'destacados', archivar: 'archivados', eliminar: 'eliminados' }[tipo] || ''));
    }

    /* ---------------------------------------------------
       MENÚ CONTEXTUAL
       --------------------------------------------------- */
    function abrirMenu(item, x, y) {
      objetivo = item;
      menu.hidden = false;
      menu.style.visibility = 'hidden';
      menu.style.left = '0px'; menu.style.top = '0px';
      var r = menu.getBoundingClientRect();
      var iz = Math.min(x, innerWidth  - r.width  - 8);
      var ar = Math.min(y, innerHeight - r.height - 8);
      menu.style.left = Math.max(8, iz) + 'px';
      menu.style.top  = Math.max(8, ar) + 'px';
      menu.querySelectorAll('.mj-submenu').forEach(function (s) {
        s.classList.toggle('mj-izq', iz + r.width + 190 > innerWidth);

        // Si el submenú no cabe hacia abajo, se ancla por su borde inferior
        // en vez de quedar cortado por el borde de la ventana.
        var fila = s.parentNode.getBoundingClientRect();
        var alto = s.scrollHeight;
        s.classList.toggle('mj-arriba', fila.top + alto + 16 > innerHeight);
      });
      menu.style.visibility = '';
      var no = menu.querySelector('[data-menu="no_leido"]');
      if (no) no.querySelector('span').textContent =
        item.dataset.leido === '1' ? 'Marcar como no leído' : 'Marcar como leído';

      // En la papelera no hay a dónde mover: ahí eliminar es para siempre
      var eliminar = menu.querySelector('[data-menu="eliminar"]');
      if (eliminar) {
        eliminar.querySelector('span').textContent =
          OP.carpeta === 'papelera' ? 'Eliminar permanentemente' : 'Eliminar';
        eliminar.classList.toggle('mj-menu-peligro', OP.carpeta === 'papelera');
      }
      var archivar = menu.querySelector('[data-menu="archivar"]');
      if (archivar) archivar.hidden = (OP.carpeta === 'papelera');
    }

    function cerrarMenu() { if (menu && !menu.hidden) { menu.hidden = true; objetivo = null; } }

    function accionMenu(id, btn) {
      var item = objetivo || itemActivo();
      cerrarMenu();
      if (!item) return;
      switch (id) {
        case 'abrir':      abrir(item, true); break;
        case 'no_leido':   marcarLeido(item, item.dataset.leido !== '1'); break;
        case 'destacar':   destacar(item, btn.dataset.color); break;
        case 'eliminar':
          if (OP.carpeta === 'papelera') { borrarSiempre(item); }
          else { quitar(item, 'eliminado', 'papelera'); }
          break;
        case 'archivar':   quitar(item, 'archivado', 'archivo'); break;
        case 'spam':       quitar(item, 'movido a Spam', 'spam'); break;
        case 'silenciar':  aviso('Silenciar todavía no está disponible'); break;
        case 'mover':
        case 'copiar':
          var destino = btn.dataset.destino;
          var nom = nombreCarpeta(destino);
          if (id === 'mover') { quitar(item, 'movido a ' + nom, destino); }
          else { aviso('Copiar a otra carpeta todavía no está disponible'); }
          break;
        case 'responder':      abrir(item, true); responder('uno'); break;
        case 'responder_todo': abrir(item, true); responder('todos'); break;
        case 'reenviar':
        case 'reenviar_adj':   abrir(item, true); responder('reenviar'); break;
      }
    }

    function nombreCarpeta(id) {
      var c = (d.carpetas || []).filter(function (x) { return x.id === id; })[0];
      return c ? c.nombre : id;
    }

    /* ---------------------------------------------------
       FILTROS Y BÚSQUEDA
       --------------------------------------------------- */
    function aplicarFiltro(f) {
      raiz.dataset.filtro = f;
      raiz.querySelectorAll('.mj-filtro').forEach(function (b) {
        var on = b.dataset.filtro === f;
        b.classList.toggle('is-activo', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      filtrar();
      var u = new URL(location.href); u.searchParams.set('f', f); history.replaceState(history.state, '', u);
    }

    function filtrar() {
      if (!lista) return;
      var q = (buscar ? buscar.value : '').trim().toLowerCase();
      var f = raiz.dataset.filtro || 'todos';
      var n = 0;

      lista.querySelectorAll('.mj-item').forEach(function (it) {
        var okF = f === 'todos'
          || (f === 'leidos'    && it.dataset.leido === '1')
          || (f === 'no_leidos' && it.dataset.leido !== '1');
        var okQ = !q || (it.dataset.buscar || '').indexOf(q) !== -1;
        var ver = okF && okQ;
        it.hidden = !ver;
        if (ver) n++;
      });

      // Separadores de fecha: se ocultan si su grupo quedó vacío
      lista.querySelectorAll('.mj-sep').forEach(function (sep) {
        var hay = false, s = sep.nextElementSibling;
        while (s && !s.classList.contains('mj-sep')) { if (!s.hidden) { hay = true; break; } s = s.nextElementSibling; }
        sep.hidden = !hay;
      });

      var vacio = raiz.querySelector('[data-rol="vacio"]');
      var sinRes = raiz.querySelector('[data-rol="sin-resultados"]');
      var total = lista.querySelectorAll('.mj-item').length;
      if (vacio)  vacio.hidden  = total !== 0;
      if (sinRes) sinRes.hidden = !(total > 0 && n === 0);
      contar(n);
    }

    /* ---------------------------------------------------
       SELECCIÓN MÚLTIPLE
       --------------------------------------------------- */
    function seleccionados() {
      return Array.prototype.slice.call(raiz.querySelectorAll('.mj-item')).filter(function (it) {
        var c = it.querySelector('.mj-item-check input');
        return c && c.checked;
      });
    }
    function refrescarSeleccion() {
      var barra = raiz.querySelector('.mj-barra-sel');
      if (!barra) return;
      var n = seleccionados().length;
      barra.hidden = n === 0;
      raiz.dataset.seleccion = n ? 'on' : '';
      var cnt = barra.querySelector('[data-rol="conteo-sel"]');
      if (cnt) cnt.textContent = n;
    }
    function limpiarSeleccion() {
      raiz.querySelectorAll('.mj-item-check input, [data-accion="marcar-todos"]').forEach(function (c) { c.checked = false; });
      refrescarSeleccion();
    }

    /* ---------------------------------------------------
       REDACCIÓN
       --------------------------------------------------- */
    function responder(tipo) {
      var msg = lector && lector.querySelector('.mj-msg');
      if (!msg) { abrirModal('redactar'); return; }
      var asunto = msg.dataset.asunto || '';

      // Se guarda a qué mensaje responde: así el correo lleva In-Reply-To y
      // la conversación no se parte en dos.
      var oculto = raiz.querySelector('[data-rol="form-redactar"] [name="responde_a"]');
      if (oculto) oculto.value = (tipo === 'reenviar') ? '' : (msg.dataset.idMensaje || '');

      abrirModal('redactar', {
        para: tipo === 'reenviar' ? '' : (msg.dataset.email || ''),
        cc: tipo === 'todos' ? (msg.dataset.todos || '') : '',
        asunto: (tipo === 'reenviar' ? 'Rv: ' : 'Re: ') + asunto,
        cuerpo: '\n\n———\n' + (tipo === 'reenviar' ? 'Mensaje reenviado de ' : 'El ') +
                (msg.dataset.nombre || '') + ' escribió:\n'
      });
    }

    function abrirModal(nombre, datos) {
      var m = raiz.querySelector('[data-modal="' + nombre + '"]');
      if (!m) return;
      m.hidden = false;
      if (datos) {
        Object.keys(datos).forEach(function (k) {
          var campo = m.querySelector('[name="' + k + '"]');
          if (campo) campo.value = datos[k];
        });
      }
      var foco = m.querySelector('input, textarea, button');
      if (foco) foco.focus();
    }
    function cerrarModal() {
      raiz.querySelectorAll('.mj-modal').forEach(function (m) { m.hidden = true; });
    }

    /* Ventana de confirmación propia: el confirm() del navegador desentona
       y en el móvil algunos lo bloquean. */
    function confirmar(texto, alAceptar) {
      var m = raiz.querySelector('[data-modal="confirmar"]');
      if (!m) { if (window.confirm(texto)) alAceptar(); return; }

      m.querySelector('[data-rol="confirmar-texto"]').textContent = texto;
      var si = m.querySelector('[data-rol="confirmar-si"]');
      var nuevo = si.cloneNode(true);        // se limpia el oyente anterior
      si.parentNode.replaceChild(nuevo, si);
      nuevo.addEventListener('click', function () { cerrarModal(); alAceptar(); });

      m.hidden = false;
      nuevo.focus();
    }

    var form = raiz.querySelector('[data-rol="form-redactar"]');
    if (form) form.addEventListener('submit', function (ev) {
      ev.preventDefault();

      var boton = form.querySelector('[type="submit"]');
      var textoBoton = boton ? boton.textContent : '';
      if (boton) { boton.disabled = true; boton.textContent = 'Enviando…'; }

      var datos = new FormData(form);
      datos.append('token', form.dataset.token || '');

      fetch('enviar.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (r.ok) {
            cerrarModal();
            form.reset();
          }
          aviso(r.mensaje);
        })
        .catch(function () {
          aviso('No se pudo enviar: revisa la conexión.');
        })
        .finally(function () {
          if (boton) { boton.disabled = false; boton.textContent = textoBoton; }
        });
    });

    /* ---------------------------------------------------
       ATAJOS DE TECLADO
       --------------------------------------------------- */
    function atajos(ev) {
      var et = ev.target;
      if (et.matches('input, textarea, select, [contenteditable]')) {
        if (ev.key === 'Escape') et.blur();
        return;
      }
      var k = ev.key;

      if (k === 'Escape') {
        if (menu && !menu.hidden) return cerrarMenu();
        if (raiz.querySelector('.mj-modal:not([hidden])')) return cerrarModal();
        if (raiz.dataset.carpetas === 'abierto') { raiz.dataset.carpetas = ''; return; }
        if (raiz.dataset.vista === 'lector' && innerWidth <= 900) { raiz.dataset.vista = 'lista'; quitarParam('m'); }
        return;
      }
      if (k === '/' ) { ev.preventDefault(); if (buscar) buscar.focus(); return; }
      if (k === '?' ) { abrirModal('atajos'); return; }

      var vis = visibles();
      var act = itemActivo();
      var i   = vis.indexOf(act);

      if (k === 'ArrowDown' || k === 'j') { ev.preventDefault(); if (vis[i + 1]) abrir(vis[i + 1], true); else if (i < 0 && vis[0]) abrir(vis[0], true); return; }
      if (k === 'ArrowUp'   || k === 'k') { ev.preventDefault(); if (vis[i - 1]) abrir(vis[i - 1], true); return; }
      if (k === 'Enter' && act) { abrir(act, true); return; }

      if (!act) return;
      switch (k.toLowerCase()) {
        case 'r': ev.preventDefault(); responder('uno');   break;
        case 'a': ev.preventDefault(); responder('todos'); break;
        case 'f': ev.preventDefault(); responder('reenviar'); break;
        case 'e': quitar(act, 'archivado', 'archivo'); break;
        case 's': destacar(act); break;
        case 'u': marcarLeido(act, act.dataset.leido !== '1'); break;
        case 'c': ev.preventDefault(); abrirModal('redactar'); break;
      }
      if (k === 'Delete' || k === 'Backspace') { ev.preventDefault(); quitar(act, 'eliminado'); }
    }

    /* ---------------------------------------------------
       AVISOS FLOTANTES
       --------------------------------------------------- */
    function aviso(texto, textoBoton, alPulsar) {
      if (!OP.avisos || !avisos) return;
      var el = document.createElement('div');
      el.className = 'mj-aviso';
      el.appendChild(document.createTextNode(texto));
      if (textoBoton) {
        var b = document.createElement('button');
        b.type = 'button'; b.textContent = textoBoton;
        b.addEventListener('click', function () { alPulsar(); cerrar(); });
        el.appendChild(b);
      }
      avisos.appendChild(el);
      var t = setTimeout(cerrar, textoBoton ? 6000 : 3200);
      function cerrar() {
        clearTimeout(t);
        el.classList.add('is-va');
        setTimeout(function () { el.remove(); }, 260);
      }
    }

    /* ---------------------------------------------------
       AUXILIARES
       --------------------------------------------------- */
    function visibles() {
      return Array.prototype.slice.call(raiz.querySelectorAll('.mj-item')).filter(function (i) { return !i.hidden; });
    }
    function contar(n) {
      var pie = raiz.querySelector('.mj-lista-pie span');
      if (!pie) return;
      var t = typeof n === 'number' ? n : visibles().length;
      pie.textContent = t + ' mensaje' + (t === 1 ? '' : 's');
    }
    function badge(delta) {
      var b = raiz.querySelector('.mj-nav-item.is-activo .mj-badge');
      if (!b) return;
      var v = Math.max(0, (parseInt(b.textContent, 10) || 0) + delta);
      b.textContent = v;
      b.hidden = v === 0;
    }
    function vaciarLector() {
      if (!lector) return;
      lector.innerHTML = '<div class="mj-vacio mj-vacio-lector"><p class="mj-vacio-t">' +
        (T.sin_seleccion || '') + '</p><p class="mj-vacio-d">' + (T.sin_seleccion_desc || '') + '</p></div>';
      raiz.dataset.vista = 'lista';
      quitarParam('m');
    }
    function vaciarSiFalta() {
      if (!visibles().length) vaciarLector();
      var vacio = raiz.querySelector('[data-rol="vacio"]');
      if (vacio) vacio.hidden = lista.querySelectorAll('.mj-item').length !== 0;
    }
    function quitarParam(p) {
      var u = new URL(location.href); u.searchParams.delete(p);
      history.replaceState(history.state, '', u);
    }
    function css(v) { return String(v).replace(/"/g, '\\"'); }
    function leerDatos(r) {
      var s = r.querySelector('script.mj-datos');
      try { return s ? JSON.parse(s.textContent) : {}; } catch (e) { return {}; }
    }
    function almacen(clave, valor) {
      try {
        if (valor === undefined) return localStorage.getItem(clave);
        localStorage.setItem(clave, valor);
      } catch (e) { return null; }
    }

    // Estado inicial coherente con la URL
    filtrar();
  }
})();
