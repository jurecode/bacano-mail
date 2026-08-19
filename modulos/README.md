# Módulos

Cada carpeta de aquí es un módulo independiente. El núcleo los descubre solo:
si hay un `modulo.php` válido, el módulo aparece en `instalar.php` para
activarlo, y una vez activo se suma al menú lateral del correo.

```
modulos/
└── mi-modulo/
    ├── modulo.php    metadatos (id, nombre, icono, url)
    └── index.php     tu pantalla
```

`modulos/ejemplo/` es una plantilla funcional: cópiala, cámbiale el `id` y
empieza a trabajar. Puede usar la configuración (`mj_config()`), los iconos
(`mj_icono()`), los estilos (`assets/css/mail.css`) y el tema activo, así que
no hay que rehacer el diseño en cada módulo.

Los módulos no se tocan al actualizar salvo que la actualización los incluya:
si desarrollas uno propio, guárdalo en su propia rama o repositorio.
