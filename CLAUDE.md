# Dental SaaS — Convenciones del proyecto

Sistema de gestión para consultorios dentales. Laravel 10 (PHP 8.1+), MySQL, front en Blade.
11 módulos; se desarrollan uno por rama (`feature/<modulo>`).

> Este repo es la **base de aprendizaje** del equipo: trae login (Breeze), la BD, el diseño de Figma y el
> módulo de ejemplo Pacientes. Cada integrante programa sus módulos en su rama. Si usas Claude Code para
> aprender, pídele que **explique y revise**, no que escriba el módulo completo.
> Actualizar este archivo cuando un módulo cambie alguna convención.

## Fuentes de verdad

1. **Base de datos** — **MySQL 8**, BD `dentalsaas`. El esquema son las **migraciones de
   `database/migrations/`** (tomadas tal cual del proyecto de referencia del equipo, con triggers incluidos).
   **No se editan**; cualquier cambio va en una migración nueva y se acuerda con el equipo.
   **La BD manda sobre el documento de requerimientos**: si chocan, se sigue la BD.
   Si un requerimiento necesita algo que la BD no tiene, se pregunta antes de agregar columnas o tablas.
2. **Documento formal de requerimientos** (PDF) — reglas de negocio (RN-xx), mensajes y validaciones,
   en todo lo que no contradiga a la BD. No está 100% actualizado.
3. **Figma** — **manda en el diseño** (layout, componentes, colores, textos de la UI).
   Archivo *Interfaces Graficas Dental Saas* (`fileKey x3hTBzpC11M2iRV89NpLaP`):
   - Página `Componentes` (1:2): componentes y sus variants (`state=default|hover|focus|error|disabled…`).
   - Una página por módulo (`Pacientes`, `Citas y agenda`, `Expediente`, …) y `Prototipo` (808:2078)
     con todas las pantallas y overlays (modales) del flujo.
   - Las pantallas que falten se proponen con los mismos componentes y se validan antes de implementarse.

Antes de programar un módulo: entregar un plan (tablas, pantallas, funcionalidades, inconsistencias,
archivos, preguntas) y **no escribir código hasta que se apruebe**.

## Idioma

- **Código en inglés**, igual que la BD: modelos, columnas, variables, métodos, rutas internas.
- **Todo lo que ve el usuario en español**: textos, etiquetas, mensajes de validación, flashes.
- Los mensajes de éxito/error que el PDF define se usan **textualmente**
  (ej. `"Paciente registrado exitosamente"`, `"Paciente desactivado"`).
- URLs en español y kebab-case: `/pacientes`, `/pacientes/{patient}/editar`.
- `config/app.php`: `locale = es`, `timezone = America/Mexico_City`. Validación en `lang/es/validation.php`.

## Base de datos y modelos

- **Llaves primarias UUID** `CHAR(36)` → usar el trait `HasUuids` en todos los modelos.
- **Multi-tenant por `organization_id`**: casi todas las tablas lo tienen y las FKs son compuestas
  `(organization_id, id)`.
  - Todo modelo con `organization_id` usa el trait `App\Models\Concerns\BelongsToOrganization`, que
    aplica un global scope por la organización actual y la asigna al crear.
  - **Nunca** recibir `organization_id` desde el request; siempre sale del contexto de sesión.
  - Las reglas `unique`/`exists` en validación siempre filtran por `organization_id`.
- **Timestamps**: muchas tablas no tienen `updated_at` o ni `created_at`. Declarar
  `const UPDATED_AT = null;` o `public $timestamps = false;` según la tabla. No asumir que existen.
- **Enums de la BD → PHP backed enums** en `app/Enums` con un método `label()` en español.
  Ej. `PatientStatus::Active` (`'active'`) → `"Activo"`. Castear en el modelo (`$casts`).
- **Baja lógica**: nada se borra físicamente si la tabla tiene `status` o `deleted_at`.
  "Desactivar" = `status = inactive`. Usar `SoftDeletes` solo en tablas con `deleted_at`, y su
  significado se define por módulo.
- **Folios** (`PAC-`, `RX-`, `PRE-`, `F-`): siempre con `App\Services\FolioService`, que usa
  `folio_sequences` dentro de una transacción con `lockForUpdate()`. Nunca calcular `max()+1`.
- **Dinero**: columnas `DECIMAL`; castear a `decimal:2` / `decimal:4`. Nunca `float` para importes.
- **Datos derivados no se guardan** (ej. edad: accessor desde `birth_date`).
- **CHECKs**: los `CHECK` del SQL solo están como comentario; la BD no los aplica. Cada regla
  (montos > 0, FDI válido, `ends_at > starts_at`, etc.) se valida en el FormRequest/Service.
- **Migraciones**: las de `database/migrations/` son el esquema oficial; están excluidas de Pint
  (`pint.json`) para que queden idénticas. Cambios = **migración nueva** (`php artisan make:migration`).
  Hay **triggers** en la BD (documentos firmados inmutables, inventario inmutable, último administrador,
  aplicaciones de pago); si un INSERT/UPDATE falla con `SQLSTATE[45000]`, es una regla del trigger.
- **Nombres en mayúsculas**: nombres de personas, razón social y RFC se guardan en mayúsculas.
- **Teléfonos**: se guardan solo los 10 dígitos y se muestran con `Ui::phone()` ("55 1847 2093").

## Auditoría

- Toda alta, modificación, desactivación/reactivación, firma o cancelación se registra en `audit_log`
  vía `App\Services\AuditLogger` (`entity_type`, `entity_id`, `action`, `changed_fields`).
- `changed_fields` solo con campos relevantes: nunca secretos ni texto clínico completo.

## Roles y permisos

- **Por ahora no hay permisos**: cualquier usuario con sesión y acceso a la clínica puede usar todo.
  Los FormRequests devuelven `true` en `authorize()` y no se usan `@can` ni `$this->authorize()`.
- Las tablas `roles`, `permissions` y `role_permissions` existen y se implementan en el **módulo 2
  (Gestión de roles)**; ese módulo decide cómo verificar permisos en todos los demás.
- Roles del negocio: Administrador, Secretaria/Asistente, Odontólogo, Jefe de odontología
  (el seeder los crea solo como catálogo).

## Login y clínica activa

- **Laravel Breeze** (stack Blade) instalado a mano: solo login, logout y recuperar contraseña
  (`routes/auth.php`, `app/Http/Controllers/Auth`, `resources/views/auth`). **Sin registro público**:
  los usuarios los da de alta el administrador (módulo 1). **No correr `php artisan breeze:install`**:
  sobrescribe el layout, Tailwind y las rutas.
- Solo entran usuarios con `users.is_active = 1`. Mensajes en `lang/es/auth.php` y `lang/es/passwords.php`.
  Con `MAIL_MAILER=log` el correo de recuperación se escribe en `storage/logs/laravel.log`.
- **Una sola clínica**: `ResolveOrganization` (alias `organization`) busca la membresía activa del usuario
  en una organización activa y la deja en `App\Support\CurrentOrganization` (`id()`, `member()`).
  Si no tiene, cierra la sesión. Está en `$middlewarePriority` **antes de `SubstituteBindings`**: si no,
  el route model binding se resolvería sin el filtro de clínica (hay una prueba que lo verifica).
- Toda ruta de la app va en el grupo `['auth', 'organization']` de `routes/web.php`.
- Datos de demo: `php artisan migrate --seed` (clínica "Clínica Dental Sonrisas", 7 usuarios con
  contraseña `password`, 12 pacientes).

## Estructura por módulo

```
app/Enums/                              PatientStatus.php ...
app/Models/                             Patient.php ...
app/Models/Concerns/                    BelongsToOrganization.php
app/Http/Controllers/<Modulo>/          PatientController.php
app/Http/Requests/<Modulo>/             StorePatientRequest.php, UpdatePatientRequest.php
app/Services/<Modulo>/                  PatientService.php   (lógica de negocio con transacciones)
app/Support/                            CurrentOrganization.php, Ui.php (formatos de presentación)
routes/modules/<modulo>.php             incluido desde routes/web.php (grupo auth + organization)
resources/views/<modulo>/               index, show + partials/ (campos de formulario y modales)
resources/views/components/layouts/     app.blade.php  → <x-layouts.app active="..." :breadcrumbs="[...]">
resources/views/components/ui/          componentes reutilizables (de los variants de Figma)
public/img/ui/                          íconos SVG descargados de Figma (<x-ui.icon name="...">)
database/factories, database/seeders
tests/Feature/<Modulo>/
```

- **Controladores delgados**: validan (FormRequest), llaman al Service y devuelven vista o
  redirect. La lógica de varias tablas va en el Service dentro de `DB::transaction()`.
- Controladores resource cuando aplique; acciones extra como rutas explícitas
  (`PATCH /pacientes/{patient}/desactivar`).
- Nombres de ruta en inglés con prefijo del módulo: `patients.index`, `patients.deactivate`.
- Route model binding por UUID; el global scope de organización evita acceso entre organizaciones (404).

## Validación

- Siempre en FormRequests, con mensajes en español.
- Teléfonos: exactamente 10 dígitos (`digits:10`); se guardan sin espacios ni guiones.
- Correos: `email:rfc`, en minúsculas.
- Nombres y textos libres que el PDF pida en mayúsculas: normalizar en `prepareForValidation()`
  con `mb_strtoupper()` (nunca correos).
- Fechas de nacimiento: `before_or_equal:today`.

## Front (Blade)

- **Tailwind CSS** vía Vite. Los tokens de Figma van en `tailwind.config.js`; nada de colores
  hexadecimales sueltos en las vistas:
  - Fuente **Inter**. Tipos: H1 24/32 semibold, Body 14/20, Body Medium 13/20 bold,
    Caption 12/16 semibold, Overline 11/16 semibold (tracking amplio, mayúsculas).
  - Colores: `primary-600 #2563eb`, `primary-50 #eff6ff`, `danger #ef4444`, escala `slate`
    (`50 #f8fafc` fondo, `200 #e2e8f0` bordes, `400/500` texto secundario, `900 #0f172a` texto),
    `surface #ffffff`. Acentos de avatar/badge: blue, sky, green `#10b981`, violet `#8b5cf6`,
    amber `#f59e0b`, slate.
  - Radio base 8px, bordes de 1px.
- Layout `resources/views/components/layouts/app.blade.php` (`<x-layouts.app active="..." :breadcrumbs="[...]">`):
  `Sidebar` (235px, ítem activo según el módulo; cada módulo activa su `href`), `TopBar` (60px,
  breadcrumb, buscador, avatar) y contenido sobre `slate-50`. Pantallas sin sesión: `<x-layouts.guest>`.
- **Componentes Blade = componentes de Figma**, en `resources/views/components/ui/`, con el nombre en
  kebab-case y los variants como props:
  `button` (variant: primary|secondary|danger|ghost; estados default/hover/pressed/focus/disabled/
  loading), `input` (InputText/Field: default/hover/focus/error/disabled), `select`, `badge`
  (color), `status-badge` (active/inactive), `avatar` (size sm|md|lg, color), `table` + `table-row`
  (hover muestra las acciones ver/editar/eliminar), `pagination` (ButtonArrow/ButtonNumber), `modal`
  (+ ModalHeader/ModalFooter default|danger), `toast` (success|error|info), `tabs`, `checkbox`,
  `switch`, `card`. Los estados hover/focus/disabled se resuelven con clases de Tailwind
  (`hover:`, `focus-visible:`, `disabled:`, `aria-invalid:`), no con componentes duplicados.
- Antes de crear un componente nuevo, revisar si ya existe en la página `Componentes` de Figma.
- Altas y ediciones de un registro se hacen en **modales** (overlays), como en Figma; los listados son
  tablas con buscador, filtros (`Select`), pie "Mostrando X-Y de N" y paginación.
- JS mínimo con Alpine.js (modales, toasts, menús); sin SPA. Si falla la validación en un modal, la
  página vuelve con el modal abierto y los errores marcados.
- Toda acción destructiva o de baja pide confirmación en un modal (`ModalFooter type=danger`).
- Los mensajes de éxito/error se muestran con `<x-ui.toast>` desde el flash de sesión.
- Listados paginados (`paginate(8)` como en Figma) con búsqueda por query string (`?q=`) y estado vacío.
- Modales con Alpine: se abren con `$dispatch('open-modal', { name, ...payload })`; cada formulario usa su
  propio error bag (`$errorBag` en el FormRequest) y el modal se reabre con `:show="$errors->bag->any()"`.
- **No** poner `@js(...)` ni otras directivas dentro de los atributos de un componente `<x-…>`: rompen el
  parser de Blade. Poner el `x-on:click` en una etiqueta HTML normal que envuelva al componente.
- Íconos: usar los SVG de Figma en `public/img/ui`. Si falta uno, descargarlo de Figma
  (`get_design_context`) en lugar de dibujarlo.
- Después de cambiar clases de Tailwind: `npm run build` (o `npm run dev`).

## Pruebas y calidad

- Feature tests por cada caso de uso del módulo (éxito, validaciones, reglas RN y aislamiento entre
  clínicas). Corren contra la BD `dentalsaas_testing` (MySQL 8, ver `phpunit.xml`) con
  `RefreshDatabase`; los helpers `member()`, `actingAsMember()` y `createPatient()` están en
  `tests/TestCase.php`.
- `php artisan test` y `./vendor/bin/pint` deben pasar antes de cada commit.

## Git

- `main` es la base del equipo. Una rama por módulo: `feature/<modulo>` (ej. `feature/agenda-citas`),
  creada desde `main`, y se integra con un Pull Request revisado por otro integrante.
- Commits pequeños en español, en imperativo: `Agrega registro de pacientes con expediente en blanco`.
- No hacer commit de `.env`, credenciales ni archivos de `storage/`.

## Módulos

1. Gestión de usuarios · 2. Gestión de roles · 3. Gestión de pacientes y contactos ·
4. Agenda y citas · 5. Servicios odontológicos · 6. Expedientes · 7. Tratamientos ·
8. Recetas médicas · 9. Pagos · 10. Inventario · 11. Reportes y estadísticas
