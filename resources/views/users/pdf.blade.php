{{-- PDF de la lista de usuarios (DomPDF: CSS 2.1, sin Tailwind). Variables: $organization, $members, $filters, $roleName --}}
@php use App\Support\Ui; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Usuarios</title>
    <style>
        @page { margin: 16mm 15mm 18mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; }
        .header { border-bottom: 2pt solid #2563eb; margin-bottom: 10pt; }
        .header td { vertical-align: bottom; padding-bottom: 6pt; }
        .clinic { font-size: 12pt; font-weight: bold; }
        .title { font-size: 13pt; font-weight: bold; color: #2563eb; text-align: right; }
        .muted { color: #64748b; font-size: 7.5pt; }
        .grid th { background: #f8fafc; color: #475569; font-size: 7.5pt; text-align: left; padding: 4pt; border: 0.75pt solid #cbd5e1; text-transform: uppercase; }
        .grid td { padding: 4pt; border: 0.75pt solid #cbd5e1; }
        .inactive { color: #64748b; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td><div class="clinic">{{ $organization?->commercial_name }}</div><div class="muted">Generado el {{ now()->format('d/m/Y H:i') }}</div></td>
            <td class="title">Lista de usuarios</td>
        </tr>
    </table>

    <p class="muted">
        Estado: {{ ['activos' => 'activos', 'inactivos' => 'inactivos', 'todos' => 'todos'][$filters['estado']] }}
        @if ($roleName) · Rol: {{ $roleName }} @endif
        @if ($filters['q'] !== '') · Búsqueda: “{{ $filters['q'] }}” @endif
        · {{ $members->count() }} {{ $members->count() === 1 ? 'usuario' : 'usuarios' }}
    </p>

    <table class="grid">
        <thead>
            <tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Rol</th><th>Último acceso</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($members as $member)
                <tr @class(['inactive' => ! $member->is_active])>
                    <td>{{ $member->user->full_name }}</td>
                    <td>{{ $member->user->email }}</td>
                    <td>{{ Ui::phone($member->user->phone) ?: '—' }}</td>
                    <td>{{ $member->role?->name ?? 'Sin rol' }}</td>
                    <td>{{ $member->user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                    <td>{{ $member->is_active ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No hay usuarios con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
