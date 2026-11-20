{{-- Confirmación de baja lógica de un documento. --}}
<x-ui.modal name="delete-document" title="¿Eliminar documento?" width="max-w-[440px]">
    <p class="text-body text-slate-600">
        <span class="font-semibold text-slate-900" x-text="payload.label"></span>
        dejará de aparecer en el historial. El archivo se conserva para la bitácora.
    </p>
    <form method="POST" :action="payload.action">
        @csrf
        @method('DELETE')
        <x-ui.modal-footer type="danger" submit="Eliminar" />
    </form>
</x-ui.modal>
