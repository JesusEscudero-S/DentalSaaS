<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Treatment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuración (Figma): contenedor de Usuarios y roles (módulo 1), Tratamientos, Mi perfil, etc.
 */
class SettingsController extends Controller
{
    /** La primera sección con pantalla es Usuarios y roles. */
    public function index(): RedirectResponse
    {
        return redirect()->route('users.index');
    }

    /**
     * Catálogo de tratamientos (Figma: Configuración → Tratamientos). Solo consulta: el alta y la
     * edición del catálogo son del módulo 5 (Servicios odontológicos).
     */
    public function treatments(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $treatments = Treatment::query()
            ->leftJoin('treatment_categories', 'treatment_categories.id', '=', 'treatments.category_id')
            ->select('treatments.*', 'treatment_categories.name as category_name')
            ->where('treatments.status', 'active')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('treatments.name', 'like', $like)
                ->orWhere('treatment_categories.name', 'like', $like)))
            ->orderBy('treatments.name')
            ->paginate(8)
            ->withQueryString();

        return view('settings.treatments', ['treatments' => $treatments, 'q' => $q]);
    }
}
