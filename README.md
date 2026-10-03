# Alquileres Temporarios — Índice de ramas

Este repositorio agrupa las soluciones evaluadas para **Alquileres
Temporarios** (`https://alquileres.diazignacio.ar`), una vitrina pública y
multipropietario de alojamientos temporarios en Argentina.

La rama `main` no contiene código: funciona como índice. Cada solución vive en
su propia rama, con historia independiente.

| Rama | Solución | Estado |
|------|----------|--------|
| [`osclass`](../../tree/osclass) | Osclass 8.3.1 con plugins propios (`tourist-identity`, `tourist-showcase`, `tourist-directory`) y especificaciones OpenSpec. | **Vigente.** Publicada en `alquileres.diazignacio.ar`. |
| [`fewohbee`](../../tree/fewohbee) | Evaluación de FewohBee (gestión de alojamientos), con port a SQLite y traducciones. | **Retirada** el 2026-09-27: no correspondía a una vitrina multipropietario. Se conserva como registro histórico. |

## Uso

```sh
git clone https://github.com/informaticadiaz/alquileres.git
cd alquileres
git switch osclass   # o: git switch fewohbee
```

## Convención

- Una rama por solución; las ramas no se fusionan entre sí.
- Al sumar o retirar una solución, actualizar esta tabla en `main`.
