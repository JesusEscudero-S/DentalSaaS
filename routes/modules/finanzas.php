<?php

use App\Http\Controllers\Finances\CashRegisterController;
use App\Http\Controllers\Finances\CashSessionController;
use App\Http\Controllers\Finances\FinanceDashboardController;
use App\Http\Controllers\Finances\FinancialCategoryController;
use App\Http\Controllers\Finances\FinancialMovementController;
use Illuminate\Support\Facades\Route;

// Finanzas: ingresos, egresos, caja y categorías.
Route::prefix('finanzas')->name('finances.')->group(function () {
    Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');

    Route::get('/movimientos', [FinancialMovementController::class, 'index'])->name('movements.index');
    Route::post('/movimientos', [FinancialMovementController::class, 'store'])->name('movements.store');
    Route::get('/movimientos/exportar', [FinancialMovementController::class, 'export'])->name('movements.export');
    Route::get('/movimientos/{movement}/comprobante', [FinancialMovementController::class, 'receipt'])->name('movements.receipt');
    Route::post('/movimientos/{movement}/revertir', [FinancialMovementController::class, 'reverse'])->name('movements.reverse');

    Route::get('/caja', [CashRegisterController::class, 'index'])->name('cash.index');
    Route::post('/caja/cajas', [CashRegisterController::class, 'store'])->name('cash.registers.store');
    Route::put('/caja/cajas/{register}', [CashRegisterController::class, 'update'])->name('cash.registers.update');
    Route::patch('/caja/cajas/{register}/desactivar', [CashRegisterController::class, 'deactivate'])->name('cash.registers.deactivate');
    Route::patch('/caja/cajas/{register}/reactivar', [CashRegisterController::class, 'reactivate'])->name('cash.registers.reactivate');
    Route::post('/caja/cajas/{register}/abrir', [CashSessionController::class, 'open'])->name('cash.open');
    Route::get('/caja/sesiones/{session}', [CashSessionController::class, 'show'])->name('cash.sessions.show');
    Route::post('/caja/sesiones/{session}/cerrar', [CashSessionController::class, 'close'])->name('cash.close');
    Route::post('/caja/sesiones/{session}/ajustes', [CashSessionController::class, 'storeAdjustment'])->name('cash.adjustments.store');

    Route::get('/categorias', [FinancialCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categorias', [FinancialCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categorias/{category}', [FinancialCategoryController::class, 'update'])->name('categories.update');
    Route::patch('/categorias/{category}/desactivar', [FinancialCategoryController::class, 'deactivate'])->name('categories.deactivate');
    Route::patch('/categorias/{category}/reactivar', [FinancialCategoryController::class, 'reactivate'])->name('categories.reactivate');
});
