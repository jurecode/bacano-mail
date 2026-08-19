# BACANO.MAIL

Bandeja de entrada instalable en cualquier sitio web, en **PHP puro**: sin
Composer, sin base de datos, sin librerías externas. Se clona la carpeta en el
hosting, se abre `instalar.php` y queda funcionando.

Sirve igual para una automotora, una tienda, una clínica, un colegio, una
agencia o una intranet: el rubro se elige durante la instalación.

La carpeta `modulos/` está pensada para lo que venga después (editor de
páginas, agenda, catálogo…): cualquier módulo nuevo se descubre solo.

> **Estado:** la vista y toda su usabilidad están terminadas, trabajando con
> datos de ejemplo. La conexión real por IMAP/SMTP está preparada y se activa
> en la versión 1.1.

---

## Instalar en un sitio

### Con git (recomendado — permite actualizar con `git pull`)

```bash
cd /ruta/del/sitio/public_html
git clone https://github.com/jurecode/bacano-mail.git mail
```

### Sin git

Descarga el ZIP del release desde GitHub, descomprímelo y sube la carpeta por
FTP con el nombre que quieras (`mail`, `correo`, `webmail`…).

### Después, en los dos casos

Abre `https://el-sitio.cl/mail/instalar.php`, completa el formulario y define
tu clave de administración. Eso es todo: la bandeja queda en
`https://el-sitio.cl/mail/`.

Requisitos: **PHP 8.0+** con `mbstring` y `json`. La extensión `imap` solo hace
falta al conectar la casilla real. El instalador comprueba todo y lo muestra en
pantalla antes de empezar.

---

## Actualizar los sitios ya instalados

La configuración de cada sitio vive en `config.local.php`, que está en el
`.gitignore` y nunca se sobrescribe. Por eso actualizar es seguro.

**Si instalaste con git** — en la consola del hosting:

```bash
cd /ruta/del/sitio/public_html/mail && git pull
```

**Si no tienes git** — entra a `instalar.php` con tu clave y usa el botón
**Buscar actualizaciones**. Si hay una versión nueva, el botón *Actualizar*
descarga el release, guarda una copia completa en `respaldos/AAAA-MM-DD_HHMMSS/`
y reemplaza los archivos. Requiere la extensión `zip` y permiso de escritura;
si el hosting no los tiene, el panel lo dice y quedas con la vía manual (subir
el ZIP por FTP sin tocar `config.local.php`).

El panel siempre muestra qué versión tiene instalada ese sitio.

### Publicar una versión nueva (tú, en el repositorio)

1. Haz los cambios y súbelos a `main`.
2. Sube el número en `VERSION` y describe los cambios en `CHANGELOG.md`.
3. Crea el release en GitHub con el tag `v1.1.0` (mismo número que `VERSION`).

Desde ese momento, todos los sitios instalados ven la actualización.
El versionado es [semántico](https://semver.org/lang/es/): **MAYOR** si obliga a
revisar la configuración, **MENOR** si agrega funciones, **PARCHE** si corrige.

---

## Configuración

| Archivo | Qué es | ¿Se toca? |
|---|---|---|
| `config.php` | Valores de fábrica y documentación de todas las opciones | No |
| `config.local.php` | La configuración **de ese sitio**, la escribe el instalador | Sí, si quieres editar a mano |

Para reconfigurar, vuelve a entrar a `instalar.php`: pide la clave y muestra el
mismo formulario con los valores actuales. ¿Olvidaste la clave? Borra
`config.local.php` por FTP y vuelve a instalar.

**Claves fuera del archivo (opcional):** define en el hosting las variables de
entorno `MJ_IMAP_CLAVE` y `MJ_SMTP_CLAVE`; tienen prioridad sobre lo guardado.

---

## Módulos

```
modulos/
└── mi-modulo/
    ├── modulo.php    id, nombre, icono, dirección
    └── index.php     tu pantalla
```

Cualquier carpeta con un `modulo.php` válido aparece en `instalar.php` para
activarla, y al activarla se suma al menú lateral. Los módulos pueden usar la
configuración (`mj_config()`), los iconos (`mj_icono()`), los estilos y el tema
del núcleo, así que no hay que rehacer el diseño en cada uno.

`modulos/ejemplo/` es una plantilla funcional: cópiala y cámbiale el `id`.

---

## Lo que ya funciona

| Función | Detalle |
|---|---|
| Carpetas | Recibidos, Destacados, Enviados, Borradores, Archivados, Spam, Papelera + las propias |
| Contadores | Badge de no leídos, se actualiza al abrir un mensaje |
| Buscador | Filtra por remitente, asunto y contenido mientras escribes |
| Filtros | Todos · Leídos · No leídos (funcionan incluso sin JavaScript) |
| Lectura | Panel a la derecha, abajo u oculto |
| Menú contextual | Clic derecho, o toque largo en el móvil |
| Destacar | Estrella con 6 colores |
| Selección múltiple | Casillas + barra de acciones masivas |
| Redactar | Responder / Responder a todos / Reenviar se rellenan solos |
| Avisos | Mensajes flotantes con **Deshacer** al eliminar o archivar |
| Atajos | `J`/`K` mover · `Enter` abrir · `R` responder · `E` archivar · `S` destacar · `/` buscar · `?` ayuda |
| Temas | Aurora · Nieve · Tinta · Bosque, más tu color de acento |
| Modo oscuro | Automático según el equipo, con botón para forzarlo |
| Responsive | Escritorio 4 columnas · tablet con cajón · teléfono con barra inferior |
| Accesibilidad | Teclado, foco visible, ARIA y textos para lectores de pantalla |

### Perfiles de rubro

Genérico · Automotora · Inmobiliaria · Tienda · Clínica · Colegio · Servicios ·
Estudio jurídico. Cada uno arma el menú lateral, las carpetas y las etiquetas.
Para agregar otro, copia un bloque de `inc/perfiles.php`.

---

## Incrustarlo dentro de otra página

```php
<?php require __DIR__ . '/mail/correo.php'; ?>
...tu cabecera, tu menú, lo que ya tenga el sitio...
<?php mj_correo(['tema' => ['alto' => 'calc(100svh - 56px)']]); ?>
```

Todo el CSS vive bajo la clase `.mjmail`, así que no se mezcla con los estilos
del sitio anfitrión. Ver `incrustar-ejemplo.php`.

---

## Conectar la casilla real (versión 1.1)

La vista nunca habla con el servidor de correo: le pide los datos a un
**proveedor** (`inc/proveedores.php`). Hoy funciona `MjProveedorJson`; para la
casilla real se completa `MjProveedorImap::mensajes()` devolviendo el mismo
arreglo documentado ahí, y en el instalador se cambia el origen a IMAP. No se
toca el CSS, ni el JS, ni la vista.

En `assets/js/mail.js`, cada acción que necesitará servidor está marcada con el
comentario `⇢ API`.

**Antes de usarlo con casillas reales:** ponlo detrás del login del sitio,
publica por HTTPS y comprueba que `config.local.php` no sea accesible desde el
navegador (el `.htaccess` incluido lo bloquea en Apache; en Nginx hay que
agregar la regla equivalente).

---

## Estructura

```
bacano-mail/
├── instalar.php              Instalador y panel de configuración
├── index.php                 La bandeja
├── correo.php                Punto de entrada para incrustar
├── config.php                Valores de fábrica (no editar)
├── VERSION · CHANGELOG.md    Versión publicada e historial
├── inc/
│   ├── vista.php             Marcado de la interfaz
│   ├── cargar.php            Carga y guardado de la configuración
│   ├── actualizador.php      Comprobación y aplicación de actualizaciones
│   ├── modulos.php           Registro de módulos
│   ├── perfiles.php          Perfiles de rubro
│   ├── proveedores.php       Capa de datos (demo · JSON · IMAP)
│   ├── ayuda.php             Utilidades
│   └── iconos.php            Set de iconos SVG
├── modulos/ejemplo/          Plantilla de módulo
├── data/demo.json            Mensajes de ejemplo
└── assets/{css,js}
```

---

© 2026 BACANO.MAIL — ver [LICENSE](LICENSE).
