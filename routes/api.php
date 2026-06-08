<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::prefix('batches')->group(function () {
            Route::get('/template', [\App\Http\Controllers\Api\BatchController::class, 'template']);
            Route::post('/', [\App\Http\Controllers\Api\BatchController::class, 'store']);
            Route::get('/', [\App\Http\Controllers\Api\BatchController::class, 'index']);
            Route::get('/{batch}', [\App\Http\Controllers\Api\BatchController::class, 'show']);
            Route::post('/{batch}/submit', [\App\Http\Controllers\Api\BatchController::class, 'submit']);
            Route::post('/{batch}/retry', [\App\Http\Controllers\Api\BatchController::class, 'retry']);
            Route::get('/{batch}/documents', [\App\Http\Controllers\Api\BatchController::class, 'documents']);
            Route::get('/{batch}/status', [\App\Http\Controllers\Api\BatchController::class, 'status']);
        });
        Route::prefix('documents')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\DocumentController::class, 'index']);
            Route::get('/{document}', [\App\Http\Controllers\Api\DocumentController::class, 'show']);
            Route::patch('/{document}', [\App\Http\Controllers\Api\DocumentController::class, 'update']);
        });

        Route::middleware('role:admin,approver')->prefix('approvals')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ApprovalController::class, 'index']);
            Route::get('/{approval}', [\App\Http\Controllers\Api\ApprovalController::class, 'show']);
            Route::post('/{approval}/approve', [\App\Http\Controllers\Api\ApprovalController::class, 'approve']);
            Route::post('/{approval}/reject', [\App\Http\Controllers\Api\ApprovalController::class, 'reject']);
        });

        Route::middleware('role:admin,auditor')->prefix('audit-logs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\AuditController::class, 'index']);
            Route::get('/{audit}', [\App\Http\Controllers\Api\AuditController::class, 'show']);
        });
    });
});
