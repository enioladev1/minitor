<?php

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::post('ingest', IngestController::class)->name('api.ingest');
