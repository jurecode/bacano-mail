# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/).
Este proyecto usa [versionado semántico](https://semver.org/lang/es/):
`MAYOR.MENOR.PARCHE`.

- **MAYOR** — cambios que obligan a revisar la configuración de los sitios ya instalados
- **MENOR** — funciones nuevas compatibles con lo anterior
- **PARCHE** — correcciones

---

## [1.3.1] — 2026-09-01

### Agregado
- Botón para cerrar sesión, junto a la cuenta en la barra lateral. Antes se
  entraba con el correo y no había manera de salir sin conocer `?salir=1`.

## [1.3.0] — 2026-09-01

### Agregado
- **Actualizar desde GitHub sin publicar releases.** Si el repositorio no
  tiene ninguno, se compara el archivo `VERSION` de la rama y se descarga el
  zip de esa rama. Con un token, funciona igual en repositorios privados.

### Corregido
- El token no llegaba a la descarga del paquete: `mj_aplicar_actualizacion`
  usaba una variable que nunca recibía, así que en repositorios privados la
  descarga fallaba en silencio.
- Tres botones sin implementar (crear carpeta, ajustes de carpetas y agendar)
  decían "Disponible al conectar la cuenta de correo", como si el problema
  fuera la conexión. Ahora dicen lo que realmente pasa.

## [1.2.1] — 2026-09-01

### Corregido
- Página en blanco al entrar a la bandeja: al reorganizar los archivos, la
  clase que lee por sockets quedó sin el `require` del cliente y PHP moría con
  un error fatal antes de dibujar nada.

## [1.2.0] — 2026-09-01

### Agregado
- **Se entra con el correo y su contraseña**, comprobados contra el servidor
  IMAP. La sesión usa esa casilla para leer y enviar, así que ya no hace falta
  dejar la clave guardada en el servidor. Queda disponible el modo de clave
  única con `acceso.modo => 'clave'`.
- **La bandeja pide clave.** Antes cualquiera con la dirección veía la
  casilla; ahora se entra con la misma clave del panel. Se puede desactivar
  con `acceso.proteger` sólo si se incrusta en una página que ya pide acceso.
- **Envío por SMTP** desde la ventana de redacción, con la cuenta configurada
  en el instalador. El endpoint exige sesión y token, para no quedar como un
  relay abierto.

## [1.1.1] — 2026-09-01

### Agregado
- Lectura por sockets como respaldo: si el hosting no tiene la extensión
  `imap` (desde PHP 8.4 ya no viene de serie), la casilla se lee igual.
  La fábrica elige sola según lo que haya en el servidor.
- El instalador prueba la conexión entrando de verdad a la casilla: informa
  cuántos mensajes hay, o en qué punto del diálogo falló.

### Cambiado
- La extensión `imap` pasa a ser opcional en la lista de requisitos.
- El buscador de actualizaciones dice qué pasó de verdad: si el servidor no
  llegó a GitHub, si el repositorio es privado, si el token no sirve o si
  simplemente no hay ningún release publicado.

### Agregado (actualizador)
- Campo de token en el instalador, para repositorios privados.

### Corregido
- Al usar "Probar la conexión" ya no se pierde lo escrito en el formulario:
  antes se repintaba con lo guardado y había que teclearlo todo de nuevo.
- La bandeja avisa cuando está mostrando mensajes de ejemplo, con el motivo.
- El menú lateral ya no muestra secciones que no llevan a ninguna parte:
  las que no tienen dirección quedan ocultas hasta que se configuren o se
  instale el módulo correspondiente.

## [1.1.0] — 2026-08-19

### Agregado
- Lectura real de la casilla por IMAP: `MjProveedorImap` conecta, lista los
  últimos mensajes, decodifica cabeceras MIME y cuerpos en base64 o
  quoted-printable, convierte a UTF-8 y detecta adjuntos.
- `mj_fallo_imap()` expone por qué no se pudo leer la casilla, para que el
  panel no muestre datos de ejemplo creyendo que son reales.

### Cambiado
- La fábrica de proveedores intenta la conexión antes de decidir: una casilla
  vacía ya no se confunde con un fallo.

### Pendiente
- Envío por SMTP desde la ventana de redacción.
- Probado contra una casilla real (requiere la extensión imap en el hosting).

## [1.0.0] — 2026-08-19

Primera versión publicable.

### Agregado
- Bandeja de entrada completa: carpetas, contadores, buscador, filtros,
  panel de lectura, menú contextual, destacados con color, selección
  múltiple, redacción, avisos con Deshacer y atajos de teclado.
- Cuatro temas (Aurora, Nieve, Tinta, Bosque), modo claro/oscuro automático
  y diseño responsive de escritorio a teléfono.
- Instalador web con comprobación de requisitos, panel de configuración
  protegido por clave y prueba de conexión al servidor IMAP.
- Ocho perfiles de rubro que arman menú, carpetas y etiquetas.
- Capa de datos con proveedores intercambiables (demo/JSON, IMAP preparado).
- Registro de módulos: la carpeta `modulos/` se descubre sola.
- Actualizaciones por `git pull` o desde el panel, con respaldo automático.

### Pendiente para 1.1
- Proveedor IMAP funcionando (lectura real de la casilla).
- Envío por SMTP desde la ventana de redacción.
