<?php

use App\Http\Controllers\Api\SmartSchoolPeopleEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('id-sync/people', SmartSchoolPeopleEndpointController::class)
    ->name('api.id-sync.people');
