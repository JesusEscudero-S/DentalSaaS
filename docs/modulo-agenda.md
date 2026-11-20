# Módulo 4: Agenda y citas, guía de aprendizaje

Esta guía explica **qué se hizo y por qué** en el módulo de Agenda. Las lecciones 1 a 4 se programaron paso a paso en clase; las lecciones 5 a 10 se terminaron de corrido y aquí se explican para estudiarlas.

**Mapa de archivos del módulo:**

| Archivo | Qué hace |
|---|---|
| `app/Enums/AppointmentStatus.php` | Los 6 estados, sus etiquetas y la **máquina de estados** |
| `app/Models/Appointment.php` | La cita: relaciones, duración, retraso (RN-C5) y `toDetailArray()` |
| `app/Models/AppointmentStatusHistory.php`, `Branch.php` | Historial de cambios y sucursal |
| `app/Services/Agenda/AgendaService.php` | **Toda la lógica**: armar el calendario, crear, reagendar y cambiar estados |
| `app/Http/Controllers/Agenda/AgendaController.php` | Muestra el calendario (Semana o Día) |
| `app/Http/Controllers/Agenda/AppointmentController.php` | Recibe las acciones sobre citas |
| `app/Http/Requests/Agenda/*.php` | Validaciones: crear, reagendar y cancelar |
| `routes/modules/agenda.php` | Las 7 rutas del módulo |
| `resources/views/agenda/index.blade.php` | La página del calendario |
| `resources/views/components/agenda/appointment-card.blade.php` | La tarjeta de cada cita |
| `resources/views/agenda/partials/*-modal.blade.php` | Los 5 modales |
| `database/seeders/AppointmentSeeder.php` | 15 citas de prueba en la semana actual |
| `tests/Feature/Agenda/*.php` | 21 pruebas automáticas |

---

## Lección 5: detalle de cita

**Objetivo:** al dar clic en una tarjeta se abre el modal "Detalle de cita" de Figma.

### 5.1 `toDetailArray()` en el modelo

El método quedó a medias. Estos eran los problemas:

| Problema | Por qué | Corrección |
|---|---|---|
| `'status' => $this->status->value` | `value` es el texto de la BD (`'pendiente'`). Al usuario se le muestra la etiqueta | `$status->label()` → "Pendiente" |
| `'editable' => $this->inArr` | `inArr` no existe: era la pista de usar `in_array(...)` | Se reemplazó por banderas más precisas (ver abajo) |
| El método estaba **antes** de `use BelongsToOrganization, HasUuids;` | Funciona, pero la convención es: traits, luego propiedades, luego métodos | Se reordenó la clase |
| `use ...\Relations\belongsTo` con **b** minúscula | En Windows funciona; en Mac/Linux falla (las mayúsculas importan en los nombres de archivo) | `BelongsTo` |

En lugar de un solo `editable`, el payload trae **una bandera por botón**. Cada acción tiene su propia regla: una cita *en curso* se puede cancelar o finalizar, pero no reagendar ni iniciar.

```php
'canReschedule' => $status->canReschedule(),
'canStart'  => $status->canTransitionTo(AppointmentStatus::EnCurso),
'canFinish' => $status->canTransitionTo(AppointmentStatus::Completada),
'canCancel' => $status->canTransitionTo(AppointmentStatus::Cancelada),
'canNoShow' => $this->isLate(),
```

Además trae `form` (valores para precargar "Reagendar") y `urls` (a dónde envía cada botón, con `route()`). Así la vista no tiene que armar nada.

### 5.2 La tarjeta se volvió un botón

`appointment-card.blade.php` ahora es un `<button>`. Se puede usar con el teclado (Tab + Enter) y los lectores de pantalla lo anuncian. Al darle clic:

```blade
x-on:click="$dispatch('open-modal', { name: 'appointment-detail', ...@js($appointment->toDetailArray()) })"
```

`...` "esparce" el payload dentro del evento. Aquí `@js` está permitido porque va en un `<button>` **dentro** del archivo del componente, no en la etiqueta `<x-...>`.

### 5.3 El modal (`detail-modal.blade.php`)

- Los datos se muestran con `x-text="payload.patient"`, etc., en una lista `<dl>` / `<dt>` / `<dd>`.
- Cada botón se muestra con `x-show="payload.canStart"` (y sus equivalentes).
- **Encadenar modales:** "Reagendar" cierra el detalle y abre el siguiente con el mismo payload:
  ```blade
  x-on:click="$dispatch('close-modal', 'appointment-detail'); $dispatch('open-modal', { ...payload, name: 'reschedule-appointment' })"
  ```
  `{ ...payload, name: '...' }` copia el payload y **sobrescribe** el `name`.

---

## Lección 6: reagendar (RF 4.3)

### Validación sin repetir código

`RescheduleAppointmentRequest` **hereda** de `StoreAppointmentRequest`. Reutiliza las reglas de doctor, fecha, hora y duración y su `withValidator()` (no en el pasado, de 8 a 20 h). Solo cambia el error bag y quita lo que no se edita:

```php
class RescheduleAppointmentRequest extends StoreAppointmentRequest
{
    protected $errorBag = 'rescheduleAppointment';

    public function rules(): array
    {
        $rules = parent::rules();               // las reglas del padre
        unset($rules['patient_id'], $rules['notes']);

        return $rules;
    }
}
```

### El empalme no debe chocar consigo mismo

Si mueves una cita de 10:00 a 10:30, su horario viejo se cruza con el nuevo. Por eso la regla de empalmes ahora es un método privado con un parámetro `ignoreId`:

```php
->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
```

`whereKeyNot` significa "todas menos esta".

### Historial con fecha anterior y nueva

Al reagendar, el estado no cambia, pero queda la traza (RN-C4): `old_starts_at` y `new_starts_at` en `appointment_status_history`.

### El modal que se reabre con lo que escribiste

`reschedule-modal.blade.php` usa la misma técnica que "Editar paciente":
- Al abrirse desde el detalle, copia `payload.form` a un estado de Alpine `form` y los campos usan `x-model="form.date"`.
- Si la validación falla, la página recarga y el payload se pierde. Por eso el formulario manda en **campos ocultos** la URL (`_action`) y el nombre del paciente (`_patient`), y al reabrirse los lee con `old('_action')`.

Mensaje del PDF: **"Cita actualizada correctamente"**.

---

## Lección 7: cancelar, iniciar y finalizar

### La máquina de estados (en el enum)

```
pendiente / confirmada ──Iniciar──► en_curso ──Finalizar──► completada
        │                              │
        ├──Cancelar──► cancelada ◄─────┘
        └──No asistió──► no_asistio
```

Está en `AppointmentStatus::canTransitionTo()`. Usa `match`, que devuelve la lista de estados permitidos según el actual. Los estados finales (completada, cancelada, no_asistio) devuelven `[]`: ya no cambian.

**¿Por qué en el enum?** Porque es una regla **del estado**, no de una pantalla. El servicio la consulta, el modelo la usa para mostrar u ocultar botones y las pruebas la verifican. Hay una sola fuente de la verdad.

### Un solo método para todos los cambios de estado

`AgendaService::changeStatus()` hace siempre lo mismo:
1. Abre una **transacción**.
2. Vuelve a leer la cita con `lockForUpdate()`, por si otra persona la cambió hace un segundo.
3. Pregunta a la máquina de estados si el cambio se permite. Si no, lanza `DomainException`.
4. Actualiza el estado. `updated_at` se actualiza solo (RN-C4).
5. Guarda el **historial** (con el motivo, si es una cancelación) y la **bitácora**.

`start()`, `finish()`, `cancel()` y `markNoShow()` solo llaman a `changeStatus()` con el estado destino. Es el principio **DRY** (*Don't Repeat Yourself*).

### Errores amigables: `DomainException` y el toast

Si alguien intenta finalizar una cita que no ha empezado (por ejemplo, con la página abierta desde hace rato), el servicio lanza `DomainException`. El controlador la atrapa en `attempt()` y regresa con un **toast de error**, en vez de mostrar una pantalla de error:

```php
private function attempt(Closure $action): RedirectResponse
{
    try {
        return $action();
    } catch (DomainException $e) {
        return back()->with('toast', ['type' => 'error', 'title' => 'No se pudo completar', 'message' => $e->getMessage()]);
    }
}
```

### Cancelar con motivo (RN-C3)

- El modal "¿Cancelar la cita?" de Figma **no tenía** el motivo, pero el PDF lo exige. Se agregó un `<textarea>` obligatorio, validado en `CancelAppointmentRequest`.
- La cita **no se borra**: queda con estado `cancelada` (baja lógica) y el motivo se guarda en `appointment_status_history.reason`.
- `weekAppointments()` excluye las canceladas, así que el horario queda libre en el calendario.
- Mensaje del PDF: **"Estatus de cita cambiado a Cancelada"**.

### Iniciar y finalizar

- **Iniciar** abre el modal de Figma "¿Iniciar la cita?" y pasa la cita a `en_curso`.
- **Finalizar** aparece cuando la cita está en curso y la pasa a `completada`.

---

## Lección 8: retrasos (RN-C5), "No asistió" y la vista Día

### RN-C5 en el modelo

```php
public function isLate(): bool
{
    return in_array($this->status, [AppointmentStatus::Pendiente, AppointmentStatus::Confirmada], true)
        && now()->greaterThan($this->starts_at->copy()->addMinutes(self::LATE_AFTER_MINUTES));
}
```

- Los 30 minutos están en la constante `LATE_AFTER_MINUTES`. Así no hay un "número mágico" regado por el código.
- La **tarjeta** se pinta en rojo con la etiqueta **RETRASO**.
- El **detalle** muestra un aviso y el botón **"No asistió"**. El servicio vuelve a verificar `isLate()`: nunca hay que confiar solo en que el botón esté oculto.
- El seeder deja las citas de **hoy** en "Pendiente", así se ve el retraso con datos de prueba.

### La vista Día

- La URL indica la vista: `/agenda?vista=dia&fecha=2026-11-19`. La semana sigue siendo `?semana=...`.
- El controlador calcula el **rango** según la vista (un día o de lunes a sábado), y el resto (consulta, agrupar por día, la cuadrícula) **es el mismo código**. La cuadrícula solo cambia el número de columnas.
- Las URLs de ‹ › Hoy, Día/Semana y los chips se arman en el controlador con una pequeña función `$url(...)`, y la vista solo las usa. La vista queda más simple y no se puede equivocar al armar un enlace.

*Mes* queda deshabilitado ("Próximamente"): es una vista distinta (cuadrícula de días sin horas) y puede ser una buena práctica para después.

---

## Lección 9: pruebas automáticas

Hay 21 pruebas en `tests/Feature/Agenda`:

- **`AgendaTestCase.php`**: una clase base con lo que todas comparten (clínica, sucursal, recepcionista, doctor y paciente) y dos ayudas: `appointment()` crea una cita directo en la BD y `newAppointmentData()` arma un formulario válido. Así cada prueba solo escribe lo que la hace distinta.
- **`AgendaCalendarTest.php`**: la semana, la navegación, las canceladas que no se muestran, el filtro por doctor, la vista Día, una URL inválida, el **aislamiento entre clínicas** (no ver ni operar citas de otra clínica) y la posición de la tarjeta.
- **`AppointmentActionsTest.php`**: crear (con historial), las validaciones, los empalmes (incluidas las citas pegadas, otro doctor y una cancelada), reagendar (y que no choque consigo misma), cancelar con motivo, la máquina de estados completa y RN-C5.

**Algo que se aprendió al escribirlas:** una prueba intentó crear una cita en otra clínica con un paciente de la primera, y MySQL la rechazó con `fk_appt_patient`. Las llaves foráneas **compuestas** `(organization_id, patient_id)` de las migraciones impiden mezclar datos de clínicas, incluso si el código tuviera un error. La prueba estaba mal; la BD estaba bien.

Para correrlas:

```bash
php artisan test --filter=Agenda
```

---

## Lección 10: calidad y Git

- `./vendor/bin/pint` (formato) y `php artisan test` (53 pruebas) pasan.
- `CLAUDE.md` tiene una sección nueva, **"Estados y acciones"**, con los patrones de este módulo para que los usen los demás.
- Los commits van en español, uno por bloque de trabajo, y se subieron a `dev-jesus`.

---

## Para practicar (opcional)

1. **Vista Mes:** una cuadrícula de 6 semanas × 7 días con el número de citas por día.
2. **Buscador de pacientes** en "Nueva cita" (Figma dice "Busca un paciente…"): un `<input>` con Alpine que filtre las opciones mientras escribes.
3. **Empalme por paciente:** que un mismo paciente no tenga dos citas a la misma hora con doctores distintos. Pista: es la misma consulta de `assertNoClash()`, filtrando por `patient_id`.
4. **Botón "Confirmar"** (pendiente → confirmada): agrégalo a la máquina de estados, crea su ruta, el método del controlador, el botón y una prueba.
