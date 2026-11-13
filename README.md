# Dental SaaS

Sistema de gestión para consultorios dentales hecho con **Laravel 10**, **MySQL 8**, **Blade**, **Tailwind CSS** y **Alpine.js**.

Este repositorio es la **base del proyecto**: ya trae el login, la base de datos, el diseño de Figma y un módulo de ejemplo (**Pacientes**). Cada integrante del equipo programa sus propios módulos encima de esta base.

- Reglas y convenciones del proyecto: [`CLAUDE.md`](CLAUDE.md). Léanlo antes de empezar. También sirve como instrucciones si usan Claude Code.
- Diseño: Figma *Interfaces Graficas Dental Saas*, con la página `Componentes` y una página por módulo.
- Requerimientos: documento formal (PDF). **Si choca con la base de datos, manda la base de datos.**

---

## 1. Qué necesitas instalar

| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.1 o superior | Lenguaje de Laravel |
| Composer | 2.x | Instala las librerías de PHP |
| Node.js | 18 o superior | Compila los estilos (Tailwind) |
| **MySQL** | **8.0 o superior** | Base de datos |
| Git | cualquiera | Control de versiones |

### ¿Cómo tener MySQL 8? Elige una opción

La base de datos usa funciones de **MySQL 8**. Escoge la opción que te acomode:

- **Laragon** (la más sencilla en Windows): trae PHP, MySQL 8 y Node en un solo instalador. Descárgalo de laragon.org, instálalo y presiona **Start All**. Usuario `root`, sin contraseña.
- **Instalador oficial de MySQL** (MySQL Installer / MySQL Workbench, desde dev.mysql.com). Durante la instalación eliges la contraseña de `root`.
- **Docker**, si ya lo usas:
  ```bash
  docker run -d --name mysql8-dental -e MYSQL_ROOT_PASSWORD=secret -p 3306:3306 mysql:8.0
  ```
- **XAMPP** trae **MariaDB**, no MySQL 8. Puede dar errores con esta base de datos, así que no lo recomendamos.

---

## 2. Instalar el proyecto (paso a paso)

**1. Clona el repositorio** y entra a la carpeta:
```bash
git clone https://github.com/JesusEscudero-S/DentalSaaS.git
```
```bash
cd DentalSaaS
```

**2. Instala las librerías:**
```bash
composer install
```
```bash
npm install
```

**3. Crea tu archivo de configuración** `.env` y genera la llave de la aplicación:
```bash
cp .env.example .env
```
```bash
php artisan key:generate
```

**4. Configura la base de datos.**
- Crea en MySQL dos bases de datos vacías: `dentalsaas` (la de trabajo) y `dentalsaas_testing` (la que usan las pruebas). Puedes hacerlo con HeidiSQL (viene en Laragon), MySQL Workbench o phpMyAdmin.
  ```sql
  CREATE DATABASE dentalsaas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE dentalsaas_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```
- Abre el `.env` y revisa estas líneas. Ajusta el puerto y la contraseña a tu MySQL; en Laragon, `DB_PASSWORD` va vacío.
  ```
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=dentalsaas
  DB_USERNAME=root
  DB_PASSWORD=
  ```

**5. Crea las tablas y los datos de prueba:**
```bash
php artisan migrate --seed
```

**6. Compila los estilos y levanta el servidor:**
```bash
npm run build
```
```bash
php artisan serve
```

**7. Abre http://localhost:8000** e inicia sesión con alguno de los usuarios de prueba. **Todos tienen la contraseña `password`.**

| Usuario | Correo |
|---|---|
| Administrador | `admin@demo.test` |
| Secretaria | `karla.mendez@demo.test` |
| Odontóloga (jefa) | `mariana.reyes@demo.test` |
| Odontólogo | `andres.salazar@demo.test` |

> **Roles y permisos:** por ahora **no hay**. Cualquier usuario puede usar todo, para que nadie se bloquee mientras programa. Los permisos se hacen en el módulo 2 (Gestión de roles).

> **"Olvidé mi contraseña":** el correo no se envía de verdad. Se escribe en `storage/logs/laravel.log`; ahí está el enlace.

### Comandos útiles

| Comando | Para qué |
|---|---|
| `npm run dev` | Déjalo corriendo en otra terminal mientras editas vistas: recarga los estilos solo |
| `php artisan migrate:fresh --seed` | Borra **todas** las tablas y las vuelve a crear con los datos de prueba |
| `php artisan test` | Corre las pruebas automáticas (usa `dentalsaas_testing`) |
| `./vendor/bin/pint` | Ordena el formato del código PHP |

---

## 3. Cómo está armado

### La base (no la modifiquen sin avisar al equipo)

| Qué | Dónde |
|---|---|
| Login, logout y recuperar contraseña (Laravel Breeze) | `routes/auth.php`, `app/Http/Controllers/Auth/`, `resources/views/auth/` |
| Base de datos (todas las tablas) | `database/migrations/` |
| Clínica activa del usuario | `app/Http/Middleware/ResolveOrganization.php`, `app/Support/CurrentOrganization.php` |
| Filtro automático por clínica en los modelos | `app/Models/Concerns/BelongsToOrganization.php` |
| Folios (`PAC-0001`, `RX-0001`…) | `app/Services/FolioService.php` |
| Bitácora (`audit_log`) | `app/Services/AuditLogger.php` |
| Layout con sidebar y barra superior | `resources/views/components/layouts/app.blade.php` |
| Componentes de Figma (botón, input, select, modal, toast, tabla…) | `resources/views/components/ui/` |
| Íconos de Figma | `public/img/ui/*.svg` → `<x-ui.icon name="search" />` |
| Colores y tipografía de Figma | `tailwind.config.js` |
| Datos de prueba | `database/seeders/` |

### El módulo de ejemplo: Pacientes

Úsenlo como **guía** para hacer el suyo. Sigue el camino de una petición:

1. **Ruta** → `routes/modules/pacientes.php`, incluida en `routes/web.php`.
2. **Controlador** → `app/Http/Controllers/Patients/`. Solo recibe, llama al servicio y responde.
3. **Validación** → `app/Http/Requests/Patients/`. Reglas y mensajes en español.
4. **Lógica** → `app/Services/Patients/`. Guarda en varias tablas dentro de una transacción.
5. **Modelos** → `app/Models/Patient.php` y relacionados. Enums en `app/Enums/`.
6. **Vistas** → `resources/views/patients/`. El listado y el perfil; las altas y ediciones van en modales.
7. **Pruebas** → `tests/Feature/Patients/`.

---

## 4. Cómo trabajar en equipo con Git

1. Siempre parte de `main` actualizado:
   ```bash
   git checkout main
   ```
   ```bash
   git pull
   ```
2. Crea **tu rama** para tu módulo:
   ```bash
   git checkout -b feature/agenda-citas
   ```
3. Programa y haz commits pequeños, con mensajes en español:
   ```bash
   git add .
   ```
   ```bash
   git commit -m "Agrega listado de citas"
   ```
4. Sube tu rama:
   ```bash
   git push -u origin feature/agenda-citas
   ```
5. En GitHub abre un **Pull Request** hacia `main` y pide que otro compañero lo revise.
6. Para traer lo que otros ya integraron a `main`:
   ```bash
   git pull origin main
   ```

**Para no pisarse entre ustedes:**
- **Cada quien trabaja en sus propias carpetas:** `routes/modules/<modulo>.php`, `app/.../<Modulo>/`, `resources/views/<modulo>/` y `tests/Feature/<Modulo>/`.
- **Archivos que tocan varios.** Hagan cambios pequeños y avisen al equipo:
  - `routes/web.php`: una línea `require` por módulo.
  - El sidebar en `components/layouts/app.blade.php`: activar el enlace de su módulo.
  - `database/seeders/`: sus datos de prueba.
- **Cambios a la base de datos:** solo con **una migración nueva** (`php artisan make:migration ...`). **Nunca** editen las migraciones que ya existen.

---

## 5. Checklist para hacer tu módulo

1. Crea tu rama desde `main`.
2. **Plan antes de código:**
   - Qué tablas de la base de datos usa tu módulo (búscalas en `database/migrations/`).
   - Qué pantallas hay en Figma y cuáles faltan.
   - Qué pide el PDF.
   - Qué dudas tienes.

   Coméntalo con el equipo.
3. Modelos en `app/Models`, con `HasUuids` y `BelongsToOrganization`. Si tu módulo usa listas fijas (estatus, tipos), crea enums en `app/Enums`.
4. Rutas en `routes/modules/<modulo>.php` y agrégalas en `routes/web.php`.
5. FormRequests, Service y controlador, copiando la forma de `Patients`.
6. Vistas con `<x-layouts.app active="<modulo>">` y los componentes de `components/ui`. Activa tu enlace en el sidebar.
7. Datos de prueba en un seeder, si los necesitas.
8. Pruebas en `tests/Feature/<Modulo>/`.
9. `php artisan test` y `./vendor/bin/pint` sin errores. Después, abre tu Pull Request.

### Módulos

| # | Módulo | Responsable |
|---|---|---|
| 1 | Gestión de usuarios | |
| 2 | Gestión de roles (permisos) | |
| 3 | Gestión de pacientes y contactos | ✅ Ejemplo incluido en la base |
| 4 | Agenda y citas | |
| 5 | Servicios odontológicos | |
| 6 | Expedientes | |
| 7 | Tratamientos | |
| 8 | Recetas médicas | |
| 9 | Pagos | |
| 10 | Inventario | |
| 11 | Reportes y estadísticas | |
