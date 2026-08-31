<?php

use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\LearnerController;
use Illuminate\Support\Facades\Route;

/*
 * API v1 — superfície REST para o app móvel da v2.
 *
 * Não há app na v1: a API existe para o motor nascer com fronteira definida.
 * Todo endpoint de escrita chama o MESMO caso de uso que a web chama.
 */

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('api.auth.login');

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.me');

        Route::get('learners', [LearnerController::class, 'index'])->name('api.learners.index');
        Route::post('learners', [LearnerController::class, 'store'])->name('api.learners.store');
        Route::get('learners/{learner}', [LearnerController::class, 'show'])->name('api.learners.show');
        Route::put('learners/{learner}', [LearnerController::class, 'update'])->name('api.learners.update');
        Route::get('learners/{learner}/assessments', [LearnerController::class, 'assessments'])
            ->name('api.learners.assessments');

        Route::post('assessments', [AssessmentController::class, 'store'])->name('api.assessments.store');
        Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])->name('api.assessments.show');
        Route::post('assessments/{assessment}/levels/{level}/start', [AssessmentController::class, 'startLevel'])
            ->whereIn('level', ['1', '2', '3'])->name('api.assessments.levels.start');
        Route::get('assessments/{assessment}/levels/{level}/items', [AssessmentController::class, 'levelItems'])
            ->whereIn('level', ['1', '2', '3'])->name('api.assessments.levels.items');
        Route::put('assessments/{assessment}/responses/{itemId}', [AssessmentController::class, 'saveResponse'])
            ->name('api.assessments.responses.save');
        Route::post('assessments/{assessment}/levels/{level}/complete', [AssessmentController::class, 'completeLevel'])
            ->whereIn('level', ['1', '2', '3'])->name('api.assessments.levels.complete');
        Route::post('assessments/{assessment}/complete', [AssessmentController::class, 'complete'])
            ->name('api.assessments.complete');
        Route::post('assessments/{assessment}/cancel', [AssessmentController::class, 'cancel'])
            ->name('api.assessments.cancel');
        Route::get('assessments/{assessment}/report', [AssessmentController::class, 'report'])
            ->name('api.assessments.report');

        Route::get('catalog/levels/{level}', [CatalogController::class, 'show'])->name('api.catalog.level');
    });
});
