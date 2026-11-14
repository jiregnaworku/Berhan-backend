<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ComputerWorkbookController;
use App\Http\Controllers\ItAwarenessController;
use App\Http\Controllers\ItRelatedInfoController;
use App\Http\Controllers\KasperskyStatusController;
use App\Http\Controllers\MtoStatusController;
use App\Http\Controllers\NetworkRegistrationController;
use App\Http\Controllers\PreventiveOperationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::middleware(StartSession::class)->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('auth');
    Route::get('/user', function (Request $request) {
        return response()->json(['user' => $request->user()]);
    })->middleware('auth');

    // Branch list (branch_directory table): dropdown, add, edit, delete
    Route::get('/branch-directory', [BranchController::class, 'directory'])
        ->middleware('auth');
    Route::post('/branch-directory', [BranchController::class, 'storeDirectory'])
        ->middleware('auth');
    Route::put('/branch-directory/{code}', [BranchController::class, 'updateDirectory'])
        ->middleware('auth');
    Route::delete('/branch-directory/{code}', [BranchController::class, 'destroyDirectory'])
        ->middleware('auth');

    Route::patch('/branches/{branch}/manager-edit', [BranchController::class, 'setManagerEdit'])
        ->middleware('auth');
    Route::apiResource('branches', BranchController::class)->middleware('auth');

    Route::get('/it-awareness/{record}/download', [ItAwarenessController::class, 'download'])
        ->middleware('auth');
    Route::apiResource('it-awareness', ItAwarenessController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['it-awareness' => 'record'])
        ->middleware('auth');

    Route::patch('/computer-workbook/{computer_workbook}/kaspersky', [ComputerWorkbookController::class, 'updateKaspersky'])
        ->middleware('auth');
    // Read-only list of every computer in the bank (ambassadors / service desk manager).
    // Keep this ABOVE the apiResource line below.
    Route::get('/computer-workbook/all', [ComputerWorkbookController::class, 'all'])
        ->middleware('auth');
    Route::apiResource('computer-workbook', ComputerWorkbookController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('auth');

    // MTO status: branch managers report, ambassadors accept, access is switched per branch.
    // The two "access" routes must stay ABOVE the {mto_status} route.
    Route::get('/mto-statuses', [MtoStatusController::class, 'index'])
        ->middleware('auth');
    Route::post('/mto-statuses', [MtoStatusController::class, 'store'])
        ->middleware('auth');
    Route::put('/mto-statuses/access', [MtoStatusController::class, 'setAllAccess'])
        ->middleware('auth');
    Route::put('/mto-statuses/access/{branch}', [MtoStatusController::class, 'setBranchAccess'])
        ->middleware('auth');
    Route::patch('/mto-statuses/{mto_status}/accept', [MtoStatusController::class, 'accept'])
        ->middleware('auth');

    Route::get('/network-registrations/export', [NetworkRegistrationController::class, 'export'])
        ->middleware('auth');
    Route::apiResource('network-registrations', NetworkRegistrationController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('auth');

    Route::apiResource('kaspersky-statuses', KasperskyStatusController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('auth');

    Route::apiResource('users', UserController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('auth');
    Route::get('/ambassadors', [UserController::class, 'ambassadors'])->middleware('auth');

    Route::apiResource('it-related-infos', ItRelatedInfoController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('auth');

    Route::get('/preventive-operations/{preventive_operation}/download', [PreventiveOperationController::class, 'download'])
        ->middleware('auth');
    Route::apiResource('preventive-operations', PreventiveOperationController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('auth');
});