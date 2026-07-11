<?php

use App\Http\Controllers\LedgerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ledger Routes
|--------------------------------------------------------------------------
|
| Immutable audit journal endpoints.
| - Admin: full read access with filters.
| - Rider: read-only view of own ledger history.
|
*/

// ── Admin Ledger ──────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:role:admin'])->prefix('admin')->group(function () {
    Route::get('/ledgers', [LedgerController::class, 'adminIndex']);
    Route::get('/ledgers/{id}', [LedgerController::class, 'adminShow']);
});

// ── Rider Ledger History ──────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:role:rider,driver'])->group(function () {
    Route::get('/wallet/ledger', [LedgerController::class, 'riderLedger']);
});
