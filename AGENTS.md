# Alquileres Temporarios — Guía del workspace

## Misión

Este workspace construye y opera **Alquileres Temporarios**
(`https://alquileres.diazignacio.ar`): una vitrina pública y multipropietario
de alojamientos temporarios en Argentina, basada en Osclass 8.3.1 y extendida
con plugins propios. La vitrina conecta propietarios y huéspedes; no procesa
reservas, pagos ni contrataciones.

El nombre de la carpeta (`fewohbee/`) es histórico: FewohBee fue evaluado,
publicado como demo y retirado el 2026-09-27 porque no correspondía al producto
buscado. El repositorio remoto es `informaticadiaz/alquileres`; la rama de
trabajo vigente es `osclass`; `fewohbee` conserva la historia de FewohBee, y
`main` es sólo un índice de ramas/soluciones (un `README.md`, sin código) que
se actualiza al sumar o retirar una solución.

## Alcance

- Desarrollar los plugins propios (`plugins/tourist-identity/`,
  `plugins/tourist-showcase/`, `plugins/tourist-directory/`), sus pruebas y sus
  especificaciones OpenSpec (`openspec/`).
- Documentar la instalación, la operación y la verificación de la instancia
  Osclass local (`app/osclass`, ignorada por Git), su servicio y su frente nginx.
- Investigar complejos turísticos y preparar el catálogo y la prospección
  dentro del marco legal (`investigacion-complejos-turisticos.md`).
- Registrar decisiones de producto, riesgos y pendientes antes de recibir
  tráfico real.

## Límites y autoridad

- La instancia está publicada: todo cambio sobre ella (copiar o activar
  plugins, reaplicar configuraciones, modificar la base, reiniciar servicios,
  instalar o actualizar idiomas) requiere autorización explícita y una copia de
  seguridad previa en `data/osclass/backups/`.
- No instalar paquetes, crear servicios, bases de datos, usuarios del sistema,
  puertos, DNS ni configuraciones de Cloudflare sin autorización explícita.
- No reinstalar ni exponer FewohBee sin una nueva autorización explícita.
- No contactar complejos ni enviar correos sin autorización explícita; el
  contacto está postergado por decisión del usuario.
- No guardar credenciales, datos de huéspedes, datos de terceros, reservas,
  datos de pago ni secretos en documentación versionada; los datos de
  prospección viven sólo en `data/prospeccion/`.
- Coordinar con `../servidor/` antes de cambios en servicios, nginx,
  persistencia o copias de seguridad; y con `../cloudflare/` antes de cambiar la
  exposición pública.

## Organización

- `CODEX_STATE.md` conserva el estado vigente, las decisiones, los riesgos y el
  próximo paso.
- `osclass-instalacion-minima.md` y `osclass-nginx-frente.md` documentan la
  instalación, el servicio, el frente nginx y su vuelta atrás.
- `systemd/osclass.service`, `systemd/osclass-backup.{service,timer}`,
  `scripts/osclass-backup.sh` y `nginx/alquileres.conf` son las fuentes
  versionadas de la configuración desplegada; `osclass-backups.md` documenta
  las copias automáticas y la restauración.
- `plan-tandas-complejos.md` define el orden, el circuito y el avance de las
  tandas de población del directorio.
- `task.md`, `port-sqlite-design.md`, `sqlite-port-manifest.md` y
  `systemd/fewohbee*` son registros históricos de FewohBee; no describen el
  estado vigente.
- `data/` contiene exclusivamente datos de ejecución locales e ignorados por
  Git (credenciales, backups, prospección, scripts auxiliares); no es un
  subagente ni una fuente documental autoritativa.
- La documentación se redacta en español profesional y distingue hechos
  verificados de supuestos por validar.

## Próximo paso

Ver la sección "Próximo paso recomendado" de `CODEX_STATE.md`.
