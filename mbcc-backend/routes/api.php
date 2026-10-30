<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ControlCalidadAuthController;
use App\Http\Controllers\Api\ControlCalidadInspectionController;
use App\Http\Controllers\Api\ControlCalidadOrderController;
use App\Http\Controllers\Api\ControlCalidadReportController;
use App\Http\Controllers\Api\DefectCatalogController;
use App\Http\Controllers\Api\PartNumberController;
use App\Http\Controllers\Api\ReworkLogController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SerialDefectLogController;
use App\Http\Controllers\Api\SerialRecordController;
use App\Http\Controllers\Api\SortOrderController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkInstructionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResources([
    'roles' => RoleController::class,
    'users' => UserController::class,
    'clients' => ClientController::class,
    'part-numbers' => PartNumberController::class,
    'work-instructions' => WorkInstructionController::class,
    'sort-orders' => SortOrderController::class,
    'defect-catalog' => DefectCatalogController::class,
    'serial-records' => SerialRecordController::class,
    'serial-defect-logs' => SerialDefectLogController::class,
    'rework-logs' => ReworkLogController::class,
]);

Route::post('/login', [ControlCalidadAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ControlCalidadAuthController::class, 'logout']);
    Route::get('/ordenes', [ControlCalidadOrderController::class, 'index']);
    Route::get('/defectos', [ControlCalidadInspectionController::class, 'defectos']);
    Route::get('/ordenes/{orden}/inspecciones', [ControlCalidadInspectionController::class, 'index']);
    Route::post('/ordenes/{orden}/inspecciones', [ControlCalidadInspectionController::class, 'store']);
    Route::get('/reportes/resumen', [ControlCalidadReportController::class, 'resumen']);
    Route::get('/reportes/datos.csv', [ControlCalidadReportController::class, 'csv']);
});
