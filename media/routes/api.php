<?php

use App\Http\Controllers\Api\v1\FolderController;
use App\Http\Controllers\Api\v1\MediaController;
use Illuminate\Support\Facades\Route;



Route::get('folders/tree/all', [FolderController::class, 'tree'])->name('folders.tree');
Route::post('folders/update-positions', [FolderController::class, 'updatePositions'])->name('folders.update-positions');
Route::post('folders/{id}/restore', [FolderController::class, 'restore'])->name('folders.restore');
Route::apiResource('folders',FolderController::class);

Route::post('media/{id}/restore', [MediaController::class, 'restore'])->name('media.restore');
Route::delete('media/trash/empty', [MediaController::class, 'emptyTrash'])->name('media.trash.empty');
Route::apiResource('media',MediaController::class);
