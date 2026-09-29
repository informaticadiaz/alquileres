# FewohBee — Guía del workspace

## Misión

Este workspace evalúa y documenta la posible adopción de FewohBee para gestionar
alquileres temporarios, casas de huéspedes y departamentos vacacionales.
FewohBee se distribuye bajo la licencia GPL-3.0.

## Alcance

- Relevar necesidades operativas, procesos de reservas y requisitos funcionales.
- Evaluar la compatibilidad técnica, operativa y de licenciamiento de FewohBee.
- Documentar decisiones de adopción, alternativas, riesgos y criterios de
  despliegue.
- Preparar, sólo cuando haya autorización explícita, un plan verificable de
  instalación, operación, copias de seguridad y exposición.

## Límites y autoridad

- Este workspace no instala, clona, descarga, configura ni despliega FewohBee
  sin autorización explícita.
- No crear servicios, contenedores, bases de datos, usuarios del sistema,
  puertos, DNS ni configuraciones de Cloudflare sin autorización explícita.
- No guardar credenciales, datos de huéspedes, reservas reales, datos de pago ni
  secretos en documentación versionada.
- Coordinar con `../servidor/` antes de cualquier operación local, persistencia,
  copias de seguridad o servicio; y con `../cloudflare/` antes de exponer una
  interfaz pública.
- La evaluación y la documentación pertenecen a este workspace; las decisiones
  de infraestructura, seguridad y exposición permanecen en los contextos que
  las gobiernan.

## Organización

- `CODEX_STATE.md` conserva el foco, las decisiones, los riesgos y el próximo
  paso.
- `data/` contiene exclusivamente datos de ejecución locales e ignorados por
  Git; no es un subagente ni una fuente documental autoritativa.
- La documentación se redacta en español profesional y distingue hechos
  verificados de supuestos por validar.

## Próximo paso

Relevar los requisitos del caso de uso y evaluar la compatibilidad de despliegue
antes de autorizar cualquier instalación o configuración.
