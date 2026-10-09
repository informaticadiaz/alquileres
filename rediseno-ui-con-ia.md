# Rediseño de UI con IA — Guía de proceso

Guía para planificar y ejecutar un rediseño completo de interfaz con asistencia
de IA. La primera parte es general y reutilizable; la última aplica el proceso
a Alquileres Temporarios (tema Osclass `sigma` más plugins propios).

## Por qué fallan los intentos

Los intentos de rediseño con IA suelen fracasar por falta de proceso, no de
herramienta:

- **Pedido sin criterio.** "Hacelo más lindo" obliga al modelo a inventar un
  estilo; el resultado tiende a lo genérico (gradientes, tarjetas iguales,
  tipografía por defecto).
- **Sin diagnóstico.** No se registra qué falla hoy, por lo que no hay forma de
  saber si la nueva versión es mejor.
- **Páginas antes que sistema.** Cada pantalla se rediseña por separado y
  termina con su propia paleta, espaciado y componentes.
- **Todo de una vez.** Un cambio masivo es imposible de revisar y difícil de
  revertir.
- **Revisión por código, no por imagen.** La IA declara el trabajo terminado
  sin que nadie haya mirado el resultado en escritorio y en celular.

## Proceso en cinco etapas

Cada etapa produce un entregable verificable. No se avanza sin cerrar la
anterior.

### 1. Diagnóstico con evidencia

- Capturar las pantallas clave en escritorio (1440 px) y celular (390 px).
- Por cada pantalla, anotar problemas concretos y observables (por ejemplo: "el
  precio no aparece sin desplazarse", "el buscador no indica qué filtra").
- Identificar las tareas principales de cada tipo de usuario y cuántos pasos
  requieren hoy.

**Entregable:** carpeta de capturas "antes" y lista de problemas priorizada.

### 2. Brief y referencias

- **Audiencia:** quién usa la interfaz, desde qué dispositivo y con qué
  objetivo.
- **Tono:** tres o cuatro adjetivos que la interfaz debe transmitir, y tres que
  debe evitar.
- **Referencias:** entre tres y cinco sitios, indicando qué se toma de cada uno
  (la galería de uno, la tipografía de otro). Una referencia sin motivo no
  orienta.
- **Restricciones:** plataforma, rendimiento, accesibilidad, contenido real
  disponible (fotos, textos).

**Entregable:** brief de una página. Es la etapa que más suele omitirse y la
que más condiciona el resultado.

### 3. Sistema de diseño

Definir los cimientos antes que las pantallas:

- **Tokens:** colores (con contraste AA verificado), escala tipográfica,
  espaciado, radios, sombras, puntos de quiebre.
- **Componentes base:** botón, campo, tarjeta, buscador, galería, encabezado,
  pie, estados vacíos y de error.

**Entregable:** hoja de tokens y página de componentes renderizada.

### 4. Prototipo estático

- Construir en HTML independiente las dos o tres pantallas de mayor impacto,
  usando sólo el sistema de diseño y contenido real.
- Iterar sobre capturas, no sobre código: cada ronda compara imagen contra
  brief.
- Validar en celular antes que en escritorio cuando la audiencia es móvil.

**Entregable:** prototipo aprobado de las pantallas clave.

### 5. Migración incremental

- Trasladar el diseño a la plataforma real de a una pantalla o componente por
  vez.
- Por cada paso: captura antes/después en ambos tamaños, revisión de
  accesibilidad básica y confirmación de que la funcionalidad no cambió.
- Un commit por unidad de trabajo, para poder revertir sin arrastrar el resto.

**Entregable:** cada pantalla migrada con su evidencia visual.

## Cómo dirigir a la IA

- **La persona decide, la IA ejecuta.** El brief y la aprobación de cada etapa
  son humanos.
- **Contexto explícito en cada pedido:** brief, tokens y la pantalla concreta.
  Nunca "mejorá el diseño" a secas.
- **Pedir alternativas sólo en etapas tempranas** (dirección visual); a partir
  del sistema de diseño, pedir ejecución fiel.
- **Exigir evidencia visual** como condición de cierre de cada tarea.
- **Acotar el alcance** de cada pedido a una pantalla o componente.

## Herramientas disponibles

Hechos verificados el 2026-10-09 en este equipo:

| Herramienta | Uso | Estado |
|---|---|---|
| Plugin `frontend-design` (marketplace `claude-plugins-official`) | Criterio estético y dirección visual que evita resultados de plantilla | Presente en el marketplace; no habilitado como skill en la sesión |
| Workspace `../playwright/` | Capturas sin interfaz gráfica para diagnóstico y antes/después | Disponible |
| Artifacts de Claude (tipo *design*) | Prototipar sistema de diseño y pantallas en un lienzo | Disponible en Claude Code |
| SDD / OpenSpec (`openspec/`) | Fijar brief, requisitos y tareas cuando la ambigüedad es alta | Disponible; uso opcional |

Referencias de estudio (sin verificar su vigencia en esta fecha):

- *Refactoring UI* (Wathan y Schoger): diseño práctico para desarrolladores.
- *Laws of UX* (lawsofux.com): principios de usabilidad con ejemplos.
- Mobbin: biblioteca de patrones de interfaces reales.

## Aplicación a Alquileres Temporarios

- **Plataforma:** Osclass 8.3.1, tema activo `sigma`, plugins
  `tourist-identity`, `tourist-showcase` y `tourist-directory`.
- **Pantallas clave a diagnosticar:** inicio, resultados de búsqueda/destino,
  ficha de alojamiento y formulario de contacto o baja.
- **Supuesto por validar:** la audiencia principal navega desde el celular.
- **Estrategia de migración sugerida:** un tema hijo o tema propio en lugar de
  editar `sigma`, para preservar actualizaciones y tener vuelta atrás. Requiere
  evaluación técnica antes de decidir.
- **Límites:** todo trabajo se hace primero en local; activar un tema o cambiar
  la instancia publicada requiere autorización explícita y copia previa en
  `data/osclass/backups/` (ver `AGENTS.md`).

## Estado

- Etapa 1 cerrada el 2026-10-09: `diagnostico-ui.md`.
- Etapa 4 adelantada por decisión del usuario: prototipo v1 (inicio, destino y
  ficha) en `prototipo-ui/`.
- Etapa 2 cerrada el 2026-10-09: `brief-ui.md`.
- Etapa 3 cerrada el 2026-10-09: `sistema-diseno/` (v1, con pendientes
  anotados en su `README.md`).
- Etapa 5 (migración a Osclass) pendiente.
