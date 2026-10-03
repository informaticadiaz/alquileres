# Plan de tandas de población del directorio

Tarea abierta el 2026-10-02. Objetivo: poblar el sitio con fichas de directorio
de complejos turísticos de toda la Argentina, por tandas, como parte central del
desarrollo del producto. Cada tanda aplica las reglas de
`investigacion-complejos-turisticos.md` y el esquema de
`esquema-seed-complejos.md`; este documento sólo define el orden, el alcance y
el circuito de cada tanda.

## Punto de partida (verificado el 2026-10-02)

- Tanda 1 (piloto): 25 fichas publicadas en
  `costa-atlantica-villa-gesell-mar-de-las-pampas` y 5 filas `sin_web` sin
  publicar. Seed y base coinciden: el importador en simulación informa 25 sin
  cambios y la lectura de la base confirma nombre, destino, tipo y web de las 25.
- Cobertura: 1 de 51 destinos.

## Criterio de orden: por temporada

Las tandas siguen el calendario turístico argentino, para que cada destino tenga
fichas antes de su temporada alta. Dentro de cada tanda, se empieza por los
destinos con fuentes oficiales más completas (más fichas por hora de relevamiento).

| Tanda | Ventana de trabajo | Temporada objetivo | Destinos (clave del sitio) |
| --- | --- | --- | --- |
| 2 | octubre 2026 | Verano (costa) | `costa-atlantica-pinamar-carilo`, `costa-atlantica-san-bernardo-costa-esmeralda`, `costa-atlantica-mar-del-plata` |
| 3 | noviembre 2026 | Verano (sierras) | `villa-carlos-paz`, `la-cumbre-la-falda`, `villa-general-belgrano`, `mina-clavero`, `la-cumbrecita` |
| 4 | noviembre–diciembre 2026 | Verano (Patagonia) | `san-carlos-de-bariloche`, `villa-la-angostura`, `san-martin-de-los-andes`, `el-calafate`, `el-chalten`, `ushuaia`, `puerto-madryn`, `las-grutas` |
| 5 | diciembre 2026 | Verano (Litoral, termas y carnaval) | `gualeguaychu`, `colon-concepcion-del-uruguay`, `federacion`, `puerto-iguazu` |
| 6 | enero–febrero 2027 | Vendimia y Semana Santa | `ciudad-de-mendoza`, `valle-de-uco`, `san-rafael`, `salta`, `cafayate`, `purmamarca`, `tilcara`, `humahuaca`, `tafi-del-valle`, `tandil`, `sierra-de-la-ventana` |
| 7 | marzo–abril 2027 | Vacaciones de invierno | `malargue`, `termas-de-rio-hondo`, `esteros-del-ibera`, `san-juan`, `san-luis` |
| 8 | mayo 2027 en adelante | Todo el año (urbanos y escapadas) | `caba`, `ciudad-de-cordoba`, `rosario`, `la-plata`, `tucuman-ciudad`, `corrientes-ciudad`, `alta-gracia`, `san-antonio-de-areco` |

Los destinos `otros-destinos-*` no tienen tanda propia: reciben complejos que
aparezcan al relevar un destino vecino.

Notas por tanda:

- **Tanda 2:** el índice `data/prospeccion/fuentes-costa-atlantica.md` ya existe.
  El buscador de EMTUR (Mar del Plata) se usa sólo para descubrir complejos: su
  deslinde exige autorización escrita para reutilizar contenido, y cada ficha se
  arma desde la web propia del complejo. Costa Esmeralda no tiene fuente oficial
  y su oferta es mayormente de particulares: puede quedar con pocas fichas.
- **Tanda 8:** en CABA la oferta temporaria es mayormente de departamentos de
  particulares, que no se publican como ficha. Relevar la normativa local (RAT,
  Ley 6255) antes de empezar.

## Circuito de cada tanda

1. **Relevar fuentes** de los destinos de la tanda e indexarlas en
   `data/prospeccion/fuentes-<region>.md` (sin datos de terceros en documentos
   versionados).
2. **Cargar filas en el seed** `data/prospeccion/seed/complejos.csv`, dato por
   dato desde la web propia de cada complejo, con `fuente` y `fecha_consulta`.
   Sin web propia: `sin_web`.
3. **Simular** con `php bin/tourist-directory-import.php --file=<seed>` y revisar
   altas, cambios y errores de validación.
4. **Copia de seguridad** de la base en `data/osclass/backups/` antes de
   escribir.
5. **Aplicar** con `--apply`, sólo con autorización explícita del usuario.
6. **Verificar** por HTTPS: búsqueda del destino, filtro por tipo, una ficha al
   azar y su formulario de baja.
7. **Registrar** el avance agregado (destinos, cantidad de fichas) en este
   documento y en `CODEX_STATE.md`.

## Criterio de cierre de una tanda

- Cada destino de la tanda tiene sus fuentes relevadas y sus complejos cargados
  en el seed, o una nota que explica por qué no hay fichas.
- La simulación posterior a la carga informa 0 altas y 0 cambios pendientes.
- La verificación por HTTPS pasó.

## Avance

| Tanda | Estado | Fichas publicadas | Fecha |
| --- | --- | --- | --- |
| 1 (piloto) | Cerrada | 25 | 2026-09-30 |
| 2 | En curso: seed cargado, falta simular y aplicar | — | — |

### Tanda 2 — estado al 2026-10-02

- Relevamiento hecho (fuentes: pinamar.tur.ar, lacosta.tur.ar, buscador
  provincial y EMTUR sólo para descubrir nombres). 83 filas sumadas al seed:
  Pinamar y Cariló 35 + 1 `sin_web`; San Bernardo y Costa Esmeralda 18 + 7;
  Mar del Plata 14 + 8. Costa Esmeralda no aportó complejos.
- Copia previa del seed: `data/prospeccion/seed/complejos.pre-tanda2-20261002.csv`.
- 6 filas con tipo dudoso (apart u hotel) quedaron fuera del seed, en
  `data/prospeccion/seed/revision-tanda2.csv`, hasta revisarlas una por una.
- En Pinamar la verificación fue liviana (título, contactos y palabras clave de
  la portada) y quedan unos 30 complejos válidos más en Valeria del Mar y Cariló.
- Próximo paso: simular (resultado esperado: 67 create, 0 update, 25 noop,
  21 skip, 0 errores), copia de seguridad de la base, `--apply` con
  autorización y verificación por HTTPS.
