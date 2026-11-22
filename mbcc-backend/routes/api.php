<?php

use App\Http\Controllers\Api\AlertaController;
use App\Http\Controllers\Api\AsistenciaController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ControlCalidadAuthController;
use App\Http\Controllers\Api\ControlCalidadComparisonController;
use App\Http\Controllers\Api\ControlCalidadInspectionController;
use App\Http\Controllers\Api\ControlCalidadOrderController;
use App\Http\Controllers\Api\ControlCalidadReportController;
use App\Http\Controllers\Api\ControlCalidadShiftController;
use App\Http\Controllers\Api\DefectCatalogController;
use App\Http\Controllers\Api\MonitoreoController;
use App\Http\Controllers\Api\PartNumberController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TrazabilidadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserQrController;
use App\Http\Controllers\Api\WiReferenceController;
use App\Http\Controllers\Api\WorkInstructionController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Niveles de uso (roles.name): inspector, supervisor, admin
|--------------------------------------------------------------------------
| El frontend solo oculta secciones; la seguridad real está aquí.
| Todo requiere token de Sanctum salvo POST /login y POST /login/qr.
*/

Route::post('/login', [ControlCalidadAuthController::class, 'login'])->middleware('throttle:10,1');
// Monitoreo: estado del sistema (sin datos sensibles) y errores del navegador
Route::get('/salud', [MonitoreoController::class, 'salud'])->middleware('throttle:60,1');
Route::post('/errores-cliente', [MonitoreoController::class, 'errorCliente'])->middleware('throttle:10,1');

Route::post('/login/qr', [ControlCalidadAuthController::class, 'loginQr'])->middleware('throttle:10,1');

// ---- Cualquier usuario con sesión (inspector, supervisor, admin)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [ControlCalidadAuthController::class, 'logout']);
    Route::post('/me/password', [PerfilController::class, 'cambiarPassword'])->middleware('throttle:5,1');

    Route::get('/ordenes', [ControlCalidadOrderController::class, 'index']);
    Route::get('/defectos', [ControlCalidadInspectionController::class, 'defectos']);
    Route::get('/ordenes/{orden}/inspecciones', [ControlCalidadInspectionController::class, 'index']);
    Route::post('/ordenes/{orden}/inspecciones', [ControlCalidadInspectionController::class, 'store']);
    Route::get('/turno', [ControlCalidadShiftController::class, 'index']);

    // Check-in de asistencia (cada persona registra la suya)
    Route::get('/asistencia/hoy', [AsistenciaController::class, 'hoy']);
    Route::post('/asistencia/entrada', [AsistenciaController::class, 'entrada'])->middleware('throttle:20,1');
    Route::post('/asistencia/salida', [AsistenciaController::class, 'salida'])->middleware('throttle:20,1');

    // Trazabilidad por número de serie: historial, evidencias y retrabajos
    Route::get('/trazabilidad', [TrazabilidadController::class, 'buscar']);
    Route::get('/trazabilidad/pendientes', [TrazabilidadController::class, 'pendientes']);
    Route::get('/evidencias/{id}', [TrazabilidadController::class, 'evidencia'])->whereNumber('id');
    Route::post('/series/{id}/retrabajo', [TrazabilidadController::class, 'retrabajo'])->whereNumber('id')->middleware('throttle:60,1');

    // Comparación con IA (Claude visión): fotos de referencia de la WI de la orden y análisis de una foto
    Route::get('/ordenes/{orden}/referencias', [WiReferenceController::class, 'deOrden']);
    Route::post('/ordenes/{orden}/comparar', [ControlCalidadComparisonController::class, 'comparar'])->middleware('throttle:20,1');
    Route::get('/ordenes/{orden}/instruccion/archivo', [WorkInstructionController::class, 'archivoDeOrden']);
    Route::get('/referencias/{id}/imagen', [WiReferenceController::class, 'imagen'])->whereNumber('id');
});

// ---- Supervisor y administrador
Route::middleware(['auth:sanctum', EnsureRole::class.':supervisor,admin'])->group(function () {
    // Gestión de órdenes (reabrir una orden cerrada se valida dentro: solo admin)
    Route::get('/ordenes/opciones', [ControlCalidadOrderController::class, 'opciones']);
    Route::post('/ordenes', [ControlCalidadOrderController::class, 'store']);
    Route::patch('/ordenes/{orden}', [ControlCalidadOrderController::class, 'update']);
    Route::patch('/ordenes/{orden}/estado', [ControlCalidadOrderController::class, 'cambiarEstado']);

    // Reportes y exportación CSV (el inspector no entra: ve su Estación y la Trazabilidad)
    Route::get('/reportes/resumen', [ControlCalidadReportController::class, 'resumen']);
    Route::get('/reportes/datos.csv', [ControlCalidadReportController::class, 'csv']);

    // Asistencia del equipo: quién está presente, historial, exportación y correcciones
    Route::get('/asistencia', [AsistenciaController::class, 'dia']);
    Route::get('/asistencia/historial', [AsistenciaController::class, 'historial']);
    Route::get('/asistencia/datos.csv', [AsistenciaController::class, 'csv']);
    Route::post('/asistencia/manual', [AsistenciaController::class, 'manual'])->middleware('throttle:60,1');

    // Alertas de rechazos (NG) altos por orden
    Route::get('/alertas', [AlertaController::class, 'index']);

    // Control documental de instrucciones de trabajo
    Route::get('/work-instructions', [WorkInstructionController::class, 'index']);
    Route::post('/work-instructions', [WorkInstructionController::class, 'store']);
    Route::patch('/work-instructions/{id}', [WorkInstructionController::class, 'update'])->whereNumber('id');
    Route::patch('/work-instructions/{id}/contenido', [WorkInstructionController::class, 'contenido'])->whereNumber('id');
    Route::post('/work-instructions/{id}/aprobar', [WorkInstructionController::class, 'aprobar'])->whereNumber('id');
    Route::post('/work-instructions/{id}/obsoleta', [WorkInstructionController::class, 'obsoleta'])->whereNumber('id');
    Route::delete('/work-instructions/{id}', [WorkInstructionController::class, 'destroy'])->whereNumber('id');
    Route::get('/work-instructions/{id}/archivo', [WorkInstructionController::class, 'archivo'])->whereNumber('id');

    // Fotos de referencia OK / NG para la comparación con IA
    Route::get('/work-instructions/{id}/referencias', [WiReferenceController::class, 'index'])->whereNumber('id');
    Route::post('/work-instructions/{id}/referencias', [WiReferenceController::class, 'store'])->whereNumber('id');
    Route::delete('/referencias/{id}', [WiReferenceController::class, 'destroy'])->whereNumber('id');

    // Lista de partes (con el nombre del cliente) para el formulario de instrucciones
    Route::get('/part-numbers', [PartNumberController::class, 'index']);
});

// ---- Solo administrador
Route::middleware(['auth:sanctum', EnsureRole::class.':admin'])->group(function () {
    Route::get('/auditoria', [AuditLogController::class, 'index']);
    Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
    // Credenciales QR de empleados (gafetes)
    Route::post('/users/qr-masivo', [UserQrController::class, 'masivo']);
    Route::post('/users/{id}/qr', [UserQrController::class, 'generar'])->whereNumber('id');
    Route::delete('/users/{id}/qr', [UserQrController::class, 'revocar'])->whereNumber('id');
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'store', 'update']);
    Route::apiResource('clients', ClientController::class)->only(['index', 'show', 'store', 'update']);
    Route::apiResource('part-numbers', PartNumberController::class)->only(['show', 'store', 'update']);
    Route::apiResource('defect-catalog', DefectCatalogController::class)->only(['index', 'show', 'store', 'update']);
});
