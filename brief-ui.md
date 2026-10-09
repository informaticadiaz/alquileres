# Brief de rediseño de UI

Etapa 2 del proceso de `rediseno-ui-con-ia.md`. Define para quién, con qué tono
y bajo qué restricciones se rediseña `https://alquileres.diazignacio.ar`. Los
problemas a resolver salen de `diagnostico-ui.md`.

- **Fecha:** 2026-10-09.
- **Estado:** decisiones del usuario cerradas; supuestos marcados como tales.

## Objetivo

Que un viajero encuentre alojamientos por destino y llegue al contacto o sitio
oficial de cada uno con claridad y confianza, entendiendo que el sitio es una
vitrina y no una plataforma de reservas.

## Audiencia

- **Principal (decisión): viajeros** que buscan alojamiento temporario en
  Argentina. Cuando haya que elegir, el diseño prioriza su recorrido:
  explorar destinos, comparar fichas y llegar al contacto.
- **Secundaria: propietarios** de cabañas, aparts y complejos. Necesitan un
  camino visible para publicar o pedir la baja de una ficha informativa, sin
  competir con el recorrido del viajero.

## Dispositivo

- **Decisión: celular primero.** Se diseña para 390 px y se amplía a
  escritorio.
- **Supuesto por validar:** no hay analítica de tráfico; confirmar la
  proporción de visitas móviles cuando exista medición.

## Tono

- **Decisión: guía confiable.** Claro, ordenado y neutral, como una guía de
  viaje: informa sin vender, no exagera ni promete.
- **Voz:** voseo rioplatense consistente en toda la interfaz ("Elegí",
  "Publicá"), sin regionalismos marcados ni tono publicitario.
- **Evitar:** lenguaje de clasificados ("anuncio", "comprar y vender"), tono
  de urgencia o escasez, cifras o calificaciones que no existan.

## Referencias

| Referencia | Qué se toma | Qué no se toma |
|---|---|---|
| Guías del Automóvil Club Argentino (ACA) | Organización por región y destino; código de color por región; tipografía con espíritu de señalización vial; información práctica densa y ordenada | Estética nostálgica o de época, publicidad intercalada, densidad propia del papel que no funciona en 390 px |

**Pendiente:** el proceso recomienda entre tres y cinco referencias; con una
sola hay riesgo de imitar un estilo en lugar de resolver problemas. Se puede
ampliar antes de la etapa 3.

## Problemas a resolver

Agrupados desde `diagnostico-ui.md` (los números remiten a sus hallazgos).

1. **Confianza en la ficha:** quitar consejos de clasificados, contador de
   visitas y elementos que contradicen "no admite consultas" (1, 5, 6).
2. **Contenido útil sin fotos:** tarjetas y fichas que funcionen sin imagen;
   descripción y ubicación sin errores ni repeticiones (2, 3, 4).
3. **Explicar el producto:** qué es la vitrina y cómo se contacta, desde el
   inicio (7).
4. **Explorar por destino:** regiones y destinos reconocibles; filtros únicos,
   pertinentes y que no tapen los resultados en celular (8, 9, 10, 11, 12).
5. **Publicar alojamiento:** formulario con datos de alojamiento y privacidad
   por defecto (13, 14).
6. **Lenguaje consistente:** voseo, vocabulario "ficha" y "alojamiento",
   textos de Osclass traducidos (15, 16, 17, 18).
7. **Calidad visual y accesibilidad:** pie, recuadros vacíos, identidad
   propia, contraste AA, etiquetas y nombres accesibles (19–24).

## Restricciones

- **Plataforma:** Osclass 8.3.1 con el tema `sigma` y los plugins
  `tourist-identity`, `tourist-showcase` y `tourist-directory`. El rediseño se
  implementa sobre esa base (tema propio o hijo, a evaluar en la migración).
- **Producto:** sin reservas, pagos, comisiones ni intermediación. Conviven
  dos tipos de ficha: informativa (datos públicos, no gestionada por el
  alojamiento) y publicada por el propietario.
- **Contenido:** hoy ninguna ficha tiene fotos; el diseño no puede depender de
  ellas. No se inventan datos, cifras ni opiniones.
- **Accesibilidad:** WCAG 2.1 nivel AA como piso (contraste, etiquetas, foco
  visible, objetivos táctiles de 44 px).
- **Operación:** todo se prueba primero en local; cambiar la instancia
  publicada requiere autorización explícita y copia previa en
  `data/osclass/backups/` (ver `AGENTS.md`).
- **Código desplegado:** antes de redesplegar plugins desde el repo hay que
  rescatar el código que sólo existe en la instancia (ver `CODEX_STATE.md`).

## Criterios de éxito

Verificables con la auditoría de `../playwright/projects/alquileres.mjs`:

- Desde el inicio, la lista de un destino está a dos toques o menos en 390 px.
- En la ficha, el camino al sitio oficial o al contacto se ve sin desplazarse
  en 390 px.
- Cero fallas de contraste, campos sin etiqueta y enlaces sin nombre en el
  chequeo automático.
- Ningún texto de clasificados genérico ni mezcla de voseo y tuteo en las
  pantallas auditadas.
- Los resultados de un destino empiezan en la primera pantalla del celular.

## Fuera de alcance

- Reservas, pagos, calificaciones u opiniones.
- Panel de administración de Osclass.
- Cambios de infraestructura, dominio o exposición pública.
