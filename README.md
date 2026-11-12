# Dental SaaS

Sistema de gestión para consultorios dentales. Usa Laravel 10, MariaDB/MySQL (XAMPP), Blade, Tailwind CSS y Alpine.js.

El módulo **3. Gestión de pacientes y contactos** (rama `feature/gestion-pacientes`) es el **módulo de referencia**: copien su estructura y convenciones para los demás módulos.

- Convenciones completas: [`CLAUDE.md`](CLAUDE.md). Léanlo antes de empezar un módulo. También sirve como instrucciones si usan Claude Code.
- Diseño: Figma *Interfaces Graficas Dental Saas*, con la página `Componentes` y una página por módulo.
- Requerimientos: documento formal (PDF). **Si choca con la base de datos, manda la base de datos.**

---

## 1. Instalación local

Requisitos: XAMPP con MySQL/MariaDB encendido, PHP 8.1 o superior, Composer y Node 18 o superior.

```bash
git clone https://github.com/JesusEscudero-S/DentalSaaS.git
cd DentalSaaS
git checkout feature/gestion-pacientes

composer install
npm install

cp .env.example .env
php artisan key:generate
```

### Base de datos

1. Creen la BD `dentalsaas` (utf8mb4) en phpMyAdmin o con:
   ```bash
   mysql -uroot -e "CREATE DATABASE dentalsaas CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
   ```
2. Carguen el esquema y los datos de demo:
   ```bash
   php artisan migrate --seed
   ```
   La primera migración carga `database/schema/dentalsaas.sql` (el script oficial). Si ya habían importado ese SQL a mano, la migración lo detecta y no lo vuelve a cargar. Las migraciones siguientes son las correcciones al esquema.

Los datos de demo crean la organización "Clínica Dental Sonrisas", con 7 miembros (un usuario por rol) y 12 pacientes.

### Levantar la aplicación

```bash
npm run build
php artisan serve
```

Abran **http://localhost:8000/login** y elijan con qué usuario entrar.

> El login es **temporal y solo funciona en entorno local**: la tabla `users` no tiene contraseña. El login real se hace en el módulo 1 (Gestión de usuarios).

Mientras editan vistas, usen `npm run dev` en otra terminal para que los estilos se recarguen solos.

### Pruebas

```bash
mysql -uroot -e "CREATE DATABASE dentalsaas_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
php artisan test
./vendor/bin/pint
```

Las pruebas usan su propia BD (`dentalsaas_testing`) y la recrean en cada corrida. Las dos últimas instrucciones deben pasar antes de cada commit.

---

## 2. Cómo está armado (mapa del módulo de referencia)

| Qué | Dónde (ejemplo: Pacientes) |
|---|---|
| Rutas del módulo | `routes/modules/pacientes.php` (se incluye en `routes/web.php`, grupo `auth` + `organization`) |
| Controladores delgados | `app/Http/Controllers/Patients/` |
| Validación (mensajes en español, mayúsculas, teléfonos) | `app/Http/Requests/Patients/` |
| Lógica de negocio en transacción | `app/Services/Patients/PatientService.php` |
| Modelos (UUID, multi-organización) | `app/Models/Patient.php`, trait `app/Models/Concerns/BelongsToOrganization.php` |
| Enums de la BD con etiqueta en español | `app/Enums/` |
| Folios `PAC-0001` | `app/Services/FolioService.php` |
| Bitácora | `app/Services/AuditLogger.php` → tabla `audit_log` |
| Permisos `pacientes.crear`, etc. | `database/seeders/PermissionSeeder.php` + `Gate::before` en `AuthServiceProvider` |
| Vistas | `resources/views/patients/` (index, show, partials con formularios y modales) |
| Layout (sidebar + topbar) | `resources/views/components/layouts/app.blade.php` |
| Componentes UI de Figma | `resources/views/components/ui/` (`button`, `input`, `select`, `modal`, `toast`, …) |
| Íconos de Figma | `public/img/ui/*.svg` → `<x-ui.icon name="search" />` |
| Tokens de diseño (colores, tipografía) | `tailwind.config.js` |
| Pruebas | `tests/Feature/Patients/` + helpers en `tests/TestCase.php` |

### Ideas clave

- **Todo está separado por organización.** Los modelos usan `BelongsToOrganization`, que filtra por la organización de la sesión y la asigna al crear. Nunca reciban `organization_id` desde un formulario.
- **Autorizar siempre en el backend**: `$this->authorize('modulo.accion')` en el controlador, o `authorize()` en el FormRequest. En Blade, `@can` solo oculta botones.
- **Baja lógica**: "desactivar" cambia `status` a `inactive`; no se borra nada que tenga `status` o `deleted_at`.
- **Altas y ediciones en modales** (Alpine). Cada formulario tiene su *error bag*, y el modal se reabre solo con los errores marcados.
- **Componentes Blade = componentes de Figma.** Antes de crear uno, revisen si ya existe en `components/ui`.

---

## 3. Checklist para hacer un módulo nuevo

1. Creen su rama desde la rama base: `git checkout -b feature/<modulo>`.
2. **Plan antes de código**: tablas de la BD involucradas, pantallas de Figma (y las que faltan), funcionalidades del PDF, inconsistencias y preguntas. Revísenlo con el equipo.
3. Si necesitan cambiar la BD, hagan **una migración nueva** en `database/migrations/`. Nunca editen `database/schema/dentalsaas.sql`.
4. Modelos en `app/Models` con `HasUuids` y `BelongsToOrganization`, y los enums en `app/Enums`.
5. Permisos del módulo en `PermissionSeeder::PERMISSIONS`, asignados a los roles en `DemoOrganizationSeeder`.
6. Archivos del módulo:
   - Rutas en `routes/modules/<modulo>.php`, incluidas en `routes/web.php` dentro del grupo `auth` + `organization`.
   - FormRequests, Service y controladores, siguiendo la carpeta `Patients` como modelo.
7. Vistas con `<x-layouts.app active="<modulo>">` y los componentes de `components/ui`. Activen su enlace en el sidebar (`href` en `components/layouts/app.blade.php`).
8. Pruebas en `tests/Feature/<Modulo>/`: éxito, validaciones, reglas de negocio, permisos y **aislamiento entre organizaciones**.
9. `php artisan test` y `./vendor/bin/pint` en verde. Hagan commits pequeños en español y en imperativo.
10. Si fijan una convención nueva, agréguenla a `CLAUDE.md`.

---

## 4. Pendientes conocidos del esquema

El SQL importa sin errores de sintaxis, pero tiene errores lógicos. La FK invertida de la línea 919 ya está corregida con una migración. Los demás deben corregirse en el módulo correspondiente, cada uno con su migración:

| Línea | Problema | Módulo |
|---|---|---|
| 809 | `uq_one_main_branch` es UNIQUE solo sobre `organization_id`: una sola sucursal por organización | Configuración |
| 847 | `tooth_findings` UNIQUE `(org, patient, tooth_fdi)` impide el historial de hallazgos | 6. Expedientes |
| 884 | `cash_sessions` UNIQUE `(cash_register_id)`: una sola sesión de caja | 9. Pagos |
| 897 | `inventory_movements` UNIQUE `(branch_id, item_id)`: un solo movimiento por artículo | 10. Inventario |
| 1015 | FK `payments → financial_movements` invertida | 9. Pagos |

Los `CHECK` del SQL solo están como comentario, así que esas reglas se validan en la aplicación.
