<?php

use App\Http\Controllers\Api\ClientController;
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
