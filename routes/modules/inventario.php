<?php

use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use Illuminate\Support\Facades\Route;

// Módulo 10. Gestión de inventario: catálogo de materiales, entradas/salidas e historial.
Route::prefix('inventario')->name('inventory.')->group(function () {
    Route::get('/', [InventoryItemController::class, 'index'])->name('index');
    Route::post('/', [InventoryItemController::class, 'store'])->name('store');

    Route::get('/movimientos', [InventoryMovementController::class, 'index'])->name('movements.index');
    Route::post('/entradas', [InventoryMovementController::class, 'storeEntry'])->name('entries.store');
    Route::post('/salidas', [InventoryMovementController::class, 'storeExit'])->name('exits.store');

    Route::put('/{item}', [InventoryItemController::class, 'update'])->name('update');
    Route::patch('/{item}/desactivar', [InventoryItemController::class, 'deactivate'])->name('deactivate');
    Route::patch('/{item}/reactivar', [InventoryItemController::class, 'reactivate'])->name('reactivate');
});
