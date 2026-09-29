# Diseño: fork FewohBee con SQLite para validación local

Este documento define un **fork de validación de mercado**, de una sola máquina y baja concurrencia de escritura. No pretende convertir FewohBee en una aplicación multiinstancia ni reemplazar el soporte oficial de MySQL/MariaDB. La decisión reduce infraestructura para probar el producto, pero acepta un coste de mantenimiento explícito por cada actualización upstream.

## Decisión y límite

| Tema | Decisión |
| --- | --- |
| Caso objetivo | Una propiedad/operador, una instancia de aplicación y pocos usuarios que escriben en paralelo. |
| Base del fork | SQLite en un archivo local persistente; no en un volumen de red. |
| Soporte upstream | MySQL/MariaDB continúa siendo el baseline oficial y el camino de producción recomendado. |
| Estrategia | Fork separado, con rama `sqlite-market-validation` sobre una etiqueta SHA de upstream. |
| Salida segura | Migración exportable hacia MariaDB antes de crecer en usuarios, propiedades, canales o criticidad financiera. |

**Recomendación:** aprobar este diseño sólo para aprendizaje y validación temprana. Si pasan a convivir recepción, reservas web, sincronizaciones de canales y tareas programadas con frecuencia, migrar a MariaDB antes de tratarlo como operación real.

## Hechos verificados y supuestos

### Hechos verificados

- `app/config/packages/doctrine.yaml` fija `pdo_mysql` para las conexiones `default` y `geo`, con `utf8mb4`, collation MySQL y `DB_SERVER_VERSION=8.0`.
- `app/.env.dist` declara una URL `mysql://`; el README upstream exige MySQL 8+ o MariaDB.
- Las migraciones base `Version20190802123327.php`, `Version20200201112113.php` y `Version20200229105042.php` abortan fuera de `AbstractMySQLPlatform`.
- La historia de migraciones contiene DDL/DML de MySQL: `AUTO_INCREMENT`, `TINYINT`, `LONGTEXT`, `ENGINE=InnoDB`, collations, `CHANGE`, eliminación de claves foráneas, `INSERT IGNORE`, `NOW()` y JSON.
- `app/src/Command/ImportPostalcodedataCommand.php` ejecuta `SET FOREIGN_KEY_CHECKS`, que debe sustituirse para SQLite.
- `app/tests/bootstrap.php` sólo contempla SQLite para borrar `var/test.db`; no prueba la aplicación sobre SQLite. `app/bin/run-tests.sh` siempre recrea una base y aplica migraciones.
- Doctrine DBAL incluye controladores SQLite y permite rutas de archivos, pero esa capacidad no vuelve portátil al SQL propio de la aplicación. [Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.4/reference/configuration.html)

### Supuestos por validar mediante pruebas

- La carga tendrá pocos escritores simultáneos y cada operación de escritura podrá terminar en segundos, no minutos.
- El archivo de base, su WAL y su SHM residirán en un disco local con snapshots/backup confiables.
- No habrá múltiples procesos de aplicación en hosts distintos ni acceso directo al archivo por red.
- Las entidades Doctrine reflejan un esquema suficiente para crear el baseline nuevo; los datos de inicialización necesarios se cubrirán con `app:first-run` o seeds equivalentes comprobados.

## Superficie exacta del port

| Área upstream | Cambio del fork | Motivo |
| --- | --- | --- |
| `config/packages/doctrine.yaml` | Perfil/conexiones SQLite para `default` y `geo`; conservar filtros de esquema. | Hoy ambas son `pdo_mysql`; en modo autoalojado `geo` termina usando la base principal. |
| `.env.dist` y configuración local no versionada | URL de archivo SQLite, ruta persistente y parámetros de operación. | El formato actual es MySQL. No versionar secretos ni bases reales. |
| `migrations/` | No editar ni reejecutar la historia MySQL en SQLite. Agregar una línea de migraciones SQLite aislada. | La historia está explícitamente ligada a MySQL. |
| Nueva configuración de migraciones SQLite | Namespace, tabla de versiones y ruta separada, seleccionados sólo por perfil SQLite. | Evita que Doctrine mezcle o interprete como portables las migraciones upstream. |
| `src/Command/ImportPostalcodedataCommand.php` | Adaptador por plataforma para vaciar/importar geodatos y garantizar claves foráneas. | Usa `SET FOREIGN_KEY_CHECKS`. |
| Servicios/repositorios que inician escrituras | Frontera transaccional SQLite y clasificación de transacciones de reserva/factura. | Un chequeo de disponibilidad seguido de una inserción debe ser atómico. |
| `tests/bootstrap.php`, `bin/run-tests.sh`, configuración de test | Fixture SQLite aislado, reinicialización segura y suite de compatibilidad. | El soporte actual es sólo un borrado condicional de archivo. |
| Docker/deployment propio del fork | Volumen local para el archivo, WAL, SHM y backups; excluir MariaDB sólo en ese perfil. | El Compose oficial incluye MariaDB y sigue siendo el baseline. |

## Estrategia de fork y sincronización

1. Fijar un SHA upstream inicial y etiquetarlo en el fork como `upstream-base/<sha>`.
2. Mantener los cambios SQLite en commits pequeños, separados por: configuración, baseline, adaptadores SQL, concurrencia, pruebas y operación.
3. Para cada actualización upstream, fusionar primero en una rama de integración; leer cada migración y SQL nuevo antes de promoverla.
4. Crear una migración SQLite equivalente para cada cambio upstream que altere esquema o datos. Registrar en un manifiesto del fork la pareja `versión-upstream → versión-sqlite`, su estado y pruebas.
5. No modificar retrospectivamente migraciones upstream. Si una actualización no puede traducirse y probarse, el fork queda bloqueado en su SHA anterior.

Esto preserva una referencia auditable y permite abandonar el fork sin contaminar la línea oficial.

## Esquema y migraciones SQLite

### Baseline nuevo

No se debe intentar ejecutar la cadena MySQL existente contra SQLite. El primer entregable de esquema será una **migración baseline SQLite** generada y revisada desde las entidades y los requisitos de datos mínimos de la versión upstream fijada.

Debe incluir:

- tablas, índices, claves foráneas y restricciones requeridas por las entidades actuales;
- conversión explícita de tipos: claves `INTEGER`, booleanos con `INTEGER` y `CHECK` cuando aplique, texto largo como `TEXT`, importes como `NUMERIC` con reglas de formato/precisión verificadas, fechas/hora según el mapeo Doctrine;
- JSON almacenado bajo el tipo compatible de Doctrine y validado por los flujos que lo consumen;
- seeds estructurales necesarios para que `app:first-run` complete la inicialización sin depender de datos MySQL previos;
- una tabla/registro de versión de baseline separado del historial de migraciones upstream.

### Evolución

Las migraciones SQLite futuras se escriben específicamente para SQLite. Cualquier `ALTER TABLE` no soportado directamente se implementa como una reconstrucción controlada de tabla dentro de una transacción: crear tabla nueva, copiar/transformar datos, validar conteos y restricciones, reemplazar y recrear índices/triggers. No se aceptan conversiones automáticas de SQL MySQL.

Antes de cada migración, hacer un backup consistente y ejecutar comprobación de integridad. Cada migración debe tener plan de reversión probado o una restauración validada desde backup.

## Configuración y reglas de operación SQLite

### Conexiones Doctrine

- Usar `pdo_sqlite` para `default` y `geo`, con ambas conexiones al mismo archivo local en el modo autoalojado.
- Conservar `schema_filter` para que `postal_code_data` siga siendo propiedad del gestor `geo` y no aparezca en diffs del gestor principal.
- Eliminar del perfil SQLite `server_version`, charset/collation y opciones propias de MySQL; no alterar esos valores en el perfil MySQL upstream.
- Requerir la extensión PHP `pdo_sqlite`; validar la ruta y permisos antes de arrancar.

### Pragmática de integridad y concurrencia

Al abrir **cada conexión**, aplicar y verificar:

| Ajuste | Regla del fork | Propósito |
| --- | --- | --- |
| `foreign_keys` | Activado por conexión y comprobado en health check. | SQLite no debe permitir relaciones inválidas por una conexión nueva. |
| `journal_mode` | WAL, establecido en bootstrap operativo y verificado antes de servir tráfico. | Permite lectores durante escrituras; requiere preservar archivos WAL/SHM. |
| `busy_timeout` | Valor inicial de 5 segundos, medido y ajustado sólo con evidencia. | Evita fallos inmediatos ante una escritura breve ya en curso. |
| `synchronous` | `NORMAL` para validación; `FULL` si se adopta para datos financieros reales. | Hace explícito el equilibrio entre rendimiento y durabilidad. |

SQLite conserva ACID, pero admite un único escritor a la vez. WAL y timeout reducen fricción, no eliminan el límite. La documentación oficial lo confirma y aconseja motor cliente-servidor cuando hay muchos escritores simultáneos. [SQLite: elección y concurrencia](https://www.sqlite.org/whentouse.html)

### Fronteras transaccionales

- La creación/confirmación de una reserva debe encerrar en una sola transacción la lectura de disponibilidad, el bloqueo lógico de capacidad, la inserción y los efectos locales necesarios. Para operaciones que pasarán de leer a escribir, usar una adquisición de escritura temprana (`BEGIN IMMEDIATE` o un coordinador Doctrine equivalente) para evitar el fallo de promoción tardía.
- La importación de calendarios procesa cada entrada o lote pequeño de forma idempotente y transaccional; nunca mantener una transacción abierta durante red, parsing o envío de correo.
- Factura, pago, asiento y cambio de estado asociado deben compartir una transacción corta. Los artefactos PDF y correos se producen después del commit mediante una cola/outbox persistente o se reintentan de forma idempotente.
- Los cron/workflows se serializan por proceso y usan una marca de exclusión local; no correr dos consumidores programados simultáneamente.

## Backup, restauración e integridad

| Operación | Diseño requerido |
| --- | --- |
| Backup | Realizar copia consistente mediante API/herramienta SQLite compatible con WAL; no copiar solamente el archivo principal mientras hay escrituras. Incluir metadatos: SHA upstream, versión SQLite, hora, resultado de integridad y checksum. |
| Frecuencia | Diario como mínimo durante validación; antes de migrar; retención definida fuera de este diseño. |
| Integridad | Ejecutar `integrity_check`, `foreign_key_check` y una restauración de muestra antes de considerar un backup válido. |
| Restauración | Detener escritor/servicio, reemplazar el conjunto de archivos de base de manera atómica, retirar WAL/SHM antiguos de forma segura, ejecutar checks y arrancar sólo después de validar. |
| Exportación a MariaDB | Construir una herramienta de exportación versionada que preserve claves, fechas, decimales, relaciones y adjuntos; ensayarla con una copia, nunca como única vía de recuperación. |

## Flujos que exigen pruebas de concurrencia

| Flujo | Riesgo | Prueba mínima |
| --- | --- | --- |
| Reserva manual y reserva pública para mismo alojamiento/fechas | Sobreventa por lectura-escritura no atómica. | Dos solicitudes concurrentes; exactamente una confirma si no hay capacidad. |
| Importación iCal y edición manual | Duplicado, cancelación o disponibilidad inconsistente. | Intercalar importación y cambio manual; repetir el mismo evento sin duplicarlo. |
| Factura, pago y asiento | Registros parciales o numeración incorrecta. | Interrumpir antes/después de commit y validar consistencia financiera. |
| Workflows programados y recordatorios | Doble ejecución o bloqueo prolongado. | Dos disparadores simultáneos; una sola acción efectiva, reintento seguro. |
| Importación postal y consultas | Bloqueo global/relaciones deshabilitadas. | Importación mientras hay lecturas; las claves foráneas permanecen activas al finalizar. |

## Plan TDD y criterios de aceptación

### Pruebas antes de implementar

1. **RED — configuración:** prueba de kernel que exige dos conexiones `pdo_sqlite`, mismo archivo y claves foráneas activas.
2. **RED — baseline:** prueba de base vacía que crea esquema SQLite, corre inicialización y verifica entidades, índices y FKs críticas.
3. **RED — adaptador de geodatos:** prueba que importa/reemplaza la tabla sin SQL MySQL y conserva integridad.
4. **RED — concurrencia:** pruebas de proceso separado para los cinco flujos de la tabla anterior, con errores `database is locked` tratados como fallas.
5. **GREEN:** la mínima implementación por fase; luego refactor sin cambiar observables.
6. **Regresión:** conservar la suite MySQL upstream intacta y ejecutar una matriz MySQL + SQLite para los comportamientos compartidos.

### Criterios de aceptación

- [ ] Una instancia nueva SQLite se inicializa sin ejecutar una migración MySQL ni SQL específico de MySQL.
- [ ] `foreign_keys`, WAL y timeout se verifican en cada conexión relevante.
- [ ] La suite de dominio y los flujos críticos pasan en SQLite y MySQL baseline.
- [ ] Ninguna prueba concurrente acepta sobreventa, duplicado de workflow o persistencia parcial de factura/pago.
- [ ] Backup consistente, integridad y restauración se demuestran en una copia de prueba.
- [ ] Cada merge upstream con cambio de datos/esquema tiene equivalencia SQLite documentada y probada.
- [ ] La documentación declara que SQLite es experimental y define cuándo migrar a MariaDB.

## Plan por fases, esfuerzo y puntos de control

| Fase | Entregable | Punto de control | Esfuerzo/riesgo |
| --- | --- | --- | --- |
| 0. Congelar baseline | SHA upstream, manifiesto y matriz de SQL no portable. | Revisión del alcance del fork. | Bajo / bajo |
| 1. Infraestructura de pruebas | Perfil SQLite, fixture aislado y tests RED de configuración. | Ninguna modificación de migraciones upstream. | Medio / medio |
| 2. Baseline SQLite | Esquema nuevo, seeds mínimos e inicialización limpia. | Comparar esquema/entidades y first-run. | Alto / alto |
| 3. Adaptadores y transacciones | Geodatos, pragmas, coordinador de escrituras y exclusión de cron. | Pruebas de reserva/factura/importación concurrentes. | Alto / alto |
| 4. Operación recuperable | Backup, restore, integridad, health check y runbook. | Restauración ensayada. | Medio / alto |
| 5. Piloto acotado | Datos ficticios y observabilidad de locks/latencia. | Sin `database is locked`, sin inconsistencias. | Medio / alto |
| 6. Go/no-go | Decidir continuidad SQLite o migración a MariaDB. | Evidencia de carga, backups y actualización upstream. | Bajo / alto |

Estimación total: **alta** para un fork de validación: aproximadamente 6–10 semanas de trabajo experimentado, más coste recurrente por actualización upstream. La mayor incertidumbre está en convertir la historia de datos y demostrar concurrencia sin alterar reglas de negocio.

## No objetivos explícitos

- No convertir este diseño en soporte oficial upstream ni enviar cambios que obliguen a FewohBee a soportar SQLite.
- No despliegue público, multi-host, alta disponibilidad, clustering, multi-tenant ni réplica de SQLite.
- No reutilizar por red un archivo `.sqlite` ni compartirlo entre contenedores/hosts.
- No procesar reservas, huéspedes, pagos o facturas reales antes de superar backup, restauración, integridad y pruebas de concurrencia.
- No eliminar MariaDB del proyecto upstream ni de la ruta de salida.

## Próximo hito

Antes de escribir código, confirmar que el alcance de validación de mercado acepta un fork SQLite de una sola máquina. Con esa confirmación, iniciar la fase 0 sin datos reales ni exposición pública; MariaDB/MySQL permanece como baseline upstream y ruta de migración, no como una decisión predeterminada.
