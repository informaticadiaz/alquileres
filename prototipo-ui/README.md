# Prototipo de UI

Fuentes del prototipo de rediseño (etapa 4 de `../rediseno-ui-con-ia.md`).
Cada versión vive en su propia carpeta.

## v1 (2026-10-09)

- **Lienzo publicado (privado):** https://claude.ai/artifact/VXCoH4YHoP6GoqpvCCiRAw
- **Formato:** artboards `.dc.html` del tipo de Artifact *Design*; no son
  páginas HTML independientes (dependen del `support.js` que provee el lienzo).
- **Pantallas:** `Main.dc.html` (inicio), `Destino.dc.html` (Mina Clavero),
  `Ficha.dc.html` (Pinar de los Ríos). `canvas.json` es el índice del lienzo.

### Decisiones

- Concepto de guía de rutas por región, sin depender de fotos.
- Colores por región: Buenos Aires `#1F5F8B`, Córdoba `#3F6B3A`, Cuyo
  `#7A2E4A`, Litoral `#1E6B66`, Norte `#8A5A00`, Patagonia `#4B4E8A`.
  Tinta `#1B2633`, texto secundario `#4D5966`, superficie `#F1F3F2`.
- Tipografía: Archivo (Omnibus-Type); expandida (`font-stretch: 125%`) en
  títulos.
- Cabecera sólo con el nombre del sitio; navegación (Destinos, Cómo funciona,
  Ingresar, Publicar alojamiento) en el pie.
- Sin buscador en el inicio.
- Ficha sin formulario de contacto, comentarios, consejos de pago ni contador
  de visitas.

### Supuestos por validar

- La audiencia principal navega desde el celular.
- Los tipos de alojamiento son sólo Cabaña y Apart hotel.

### Actualizar el lienzo

Editar los archivos de `v1/` y volver a publicarlos al mismo Artifact, bajo
`project/<archivo>`.
