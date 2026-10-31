<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FuelProductController;
use App\Http\Controllers\Api\StorageTankController;
use App\Http\Controllers\Api\FuelStockMovementController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });


    /*
    |--------------------------------------------------------------------------
    | Companies
    |--------------------------------------------------------------------------
    */

    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);

        Route::get('/{company}', [CompanyController::class, 'show'])
            ->whereNumber('company');

        Route::put('/{company}', [CompanyController::class, 'update'])
            ->whereNumber('company');

        Route::patch('/{company}', [CompanyController::class, 'update'])
            ->whereNumber('company');
    });


    /*
    |--------------------------------------------------------------------------
    | Stations
    |--------------------------------------------------------------------------
    */

    Route::prefix('stations')->group(function () {
        Route::get('/', [StationController::class, 'index']);
        Route::post('/', [StationController::class, 'store']);

        Route::get('/{station}', [StationController::class, 'show'])
            ->whereNumber('station');

        Route::put('/{station}', [StationController::class, 'update'])
            ->whereNumber('station');

        Route::patch('/{station}', [StationController::class, 'update'])
            ->whereNumber('station');
    });


    /*
    |--------------------------------------------------------------------------
    | Employees
    |--------------------------------------------------------------------------
    */

    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::post('/', [EmployeeController::class, 'store']);

        Route::get('/{employee}', [EmployeeController::class, 'show'])
            ->whereNumber('employee');

        Route::put('/{employee}', [EmployeeController::class, 'update'])
            ->whereNumber('employee');

        Route::patch('/{employee}', [EmployeeController::class, 'update'])
            ->whereNumber('employee');

        Route::patch('/{employee}/deactivate', [EmployeeController::class, 'deactivate'])
            ->whereNumber('employee');
    });


    /*
    |--------------------------------------------------------------------------
    | Fuel Products
    |--------------------------------------------------------------------------
    */

    Route::prefix('fuel-products')->group(function () {
        Route::get('/', [FuelProductController::class, 'index']);
        Route::post('/', [FuelProductController::class, 'store']);

        Route::get('/{fuelProduct}', [FuelProductController::class, 'show'])
            ->whereNumber('fuelProduct');

        Route::put('/{fuelProduct}', [FuelProductController::class, 'update'])
            ->whereNumber('fuelProduct');

        Route::patch('/{fuelProduct}', [FuelProductController::class, 'update'])
            ->whereNumber('fuelProduct');
    });


    /*
    |--------------------------------------------------------------------------
    | Storage Tanks
    |--------------------------------------------------------------------------
    */

    Route::prefix('storage-tanks')->group(function () {
        Route::get('/', [StorageTankController::class, 'index']);
        Route::post('/', [StorageTankController::class, 'store']);

        Route::get('/{storageTank}', [StorageTankController::class, 'show'])
            ->whereNumber('storageTank');

        Route::put('/{storageTank}', [StorageTankController::class, 'update'])
            ->whereNumber('storageTank');

        Route::patch('/{storageTank}', [StorageTankController::class, 'update'])
            ->whereNumber('storageTank');
    });


    /*
    |--------------------------------------------------------------------------
    | Fuel Stock Movement Ledger
    |--------------------------------------------------------------------------
    */

    Route::prefix('fuel-stock-movements')->group(function () {
        Route::get('/', [FuelStockMovementController::class, 'index']);

        Route::get('/{fuelStockMovement}', [FuelStockMovementController::class, 'show'])
            ->whereNumber('fuelStockMovement');
    });

});
