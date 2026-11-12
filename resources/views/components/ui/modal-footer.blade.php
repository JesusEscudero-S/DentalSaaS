{{-- ModalFooter de Figma: type=default (Guardar) | danger (Eliminar/Desactivar). --}}
@props(['type' => 'default', 'submit' => 'Guardar', 'cancel' => 'Cancelar'])

<div {{ $attributes->merge(['class' => 'flex items-center justify-end gap-3']) }}>
    <x-ui.button variant="secondary" x-on:click="open = false">{{ $cancel }}</x-ui.button>
    <x-ui.button :variant="$type === 'danger' ? 'danger' : 'primary'" type="submit" data-autofocus>{{ $submit }}</x-ui.button>
</div>
