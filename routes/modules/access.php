<?php

use App\Http\Controllers\Access\AccessLogController;
use App\Http\Controllers\Access\AuditController;
use App\Http\Controllers\Access\BlocklistController;
use App\Http\Controllers\Access\BuildingController;
use App\Http\Controllers\Access\CorrespondenceController;
use App\Http\Controllers\Access\DashboardController;
use App\Http\Controllers\Access\GuardLogController;
use App\Http\Controllers\Access\HousingUnitController;
use App\Http\Controllers\Access\LocationController;
use App\Http\Controllers\Access\OperationsController;
use App\Http\Controllers\Access\PorteriaAuthorizationController;
use App\Http\Controllers\Access\PorteriaDirectoryController;
use App\Http\Controllers\Access\PreAuthorizationController;
use App\Http\Controllers\Access\ReportController;
use App\Http\Controllers\Access\ResidentController;
use App\Http\Controllers\Access\SupervisionCodeController;
use App\Http\Controllers\Access\SupervisionController;
use App\Http\Controllers\Access\TurnoController;
use App\Http\Controllers\Access\VehicleAccessController;
use App\Http\Controllers\Access\VehicleController;
use App\Http\Controllers\Access\VisitorController;
use App\Http\Controllers\Access\ZoneController;
use App\Http\Controllers\Ops\OperationalAlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed', 'active', 'tenancy.access', 'client.module:doors', 'porteria.door'])->prefix('access')->name('access.')->group(function () {
    Route::get('/ops/alerts.json', [OperationalAlertController::class, 'poll'])->name('ops.alerts');
    Route::post('/ops/panic', [OperationalAlertController::class, 'panic'])->name('ops.panic');

    // Operations Hub
    Route::get('/operations', [OperationsController::class, 'index'])
        ->middleware('permission:access.operations')
        ->name('operations');

    Route::middleware('permission:access.dashboard')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/people', [PorteriaDirectoryController::class, 'people'])->name('people.index');
        Route::get('/pets', [PorteriaDirectoryController::class, 'pets'])->name('pets.index');
    });
    Route::get('/census-vehicles', [PorteriaDirectoryController::class, 'vehicles'])
        ->middleware('permission:access.manage.vehicles')
        ->name('census-vehicles.index');
    Route::get('/sites', [PorteriaDirectoryController::class, 'sites'])
        ->middleware('permission:access.manage.locations')
        ->name('sites.index');
    Route::get('/authorizations', [PorteriaAuthorizationController::class, 'index'])
        ->middleware('permission:access.manage.pre_authorizations')
        ->name('authorizations.index');

    Route::resource('locations', LocationController::class)->except(['show'])
        ->middleware('permission:access.manage.locations');

    Route::resource('buildings', BuildingController::class)->except(['show'])
        ->middleware('permission:access.manage.buildings');

    Route::middleware('permission:access.manage.housing_units')->group(function () {
        Route::resource('housing_units', HousingUnitController::class)->except(['show']);
        Route::get('housing_units/by-building/{building}', [HousingUnitController::class, 'searchByBuildingJson'])->name('housing_units.by_building');
    });

    Route::middleware('permission:access.manage.visitors')->group(function () {
        Route::resource('visitors', VisitorController::class);
        Route::get('visitors/search/json', [VisitorController::class, 'searchJson'])->name('visitors.search.json');
        Route::post('visitors/scan-register', [VisitorController::class, 'scanRegister'])->name('visitors.scan-register');
    });

    Route::middleware('permission:access.manage.residents')->group(function () {
        Route::resource('residents', ResidentController::class);
        Route::get('residents/search/json', [ResidentController::class, 'searchJson'])->name('residents.search.json');
        Route::get('residents/housing-units/json', [ResidentController::class, 'searchHousingUnitsJson'])->name('residents.housing_units.json');
        Route::post('residents/{resident}/vehicles', [ResidentController::class, 'addVehicle'])->name('residents.vehicles.store');
        Route::delete('residents/{resident}/vehicles/{vehicle}', [ResidentController::class, 'removeVehicle'])->name('residents.vehicles.destroy');
    });

    Route::middleware('permission:access.manage.vehicles')->group(function () {
        Route::resource('vehicles', VehicleController::class)->except(['show']);
        Route::get('vehicles/search/json', [VehicleController::class, 'searchJson'])->name('vehicles.search.json');
        Route::get('vehicles/search/resident/json', [VehicleController::class, 'searchResidentVehicleJson'])->name('vehicles.search.resident.json');
    });

    Route::middleware(['shift.open', 'permission:access.manage.vehicle_access'])->group(function () {
        Route::get('/vehicle-access', [VehicleAccessController::class, 'index'])->name('vehicle_access.index');
        Route::get('/vehicle-access/entry', [VehicleAccessController::class, 'entry'])->name('vehicle_access.entry');
        Route::post('/vehicle-access/entry', [VehicleAccessController::class, 'storeEntry'])->name('vehicle_access.entry.store');
        Route::patch('/vehicle-access/{accessLog}/exit', [VehicleAccessController::class, 'markExit'])->name('vehicle_access.exit');
        Route::get('/vehicle-access/search', [VehicleAccessController::class, 'searchVehicleJson'])->name('vehicle_access.search');
    });

    // Access Logs (ingreso/salida)
    Route::middleware(['shift.open', 'permission:access.register.entry'])->group(function () {
        Route::get('/logs/lookup', [AccessLogController::class, 'lookup'])->name('logs.lookup');
        Route::get('/logs/hosts', [AccessLogController::class, 'hosts'])->name('logs.hosts');
        Route::get('/logs/history', [AccessLogController::class, 'history'])->name('logs.history');
        Route::get('/logs', [AccessLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/entry', [AccessLogController::class, 'entry'])->name('logs.entry');
        Route::post('/logs/entry', [AccessLogController::class, 'storeEntry'])->name('logs.entry.store');
        Route::post('/logs/move', [AccessLogController::class, 'storeMove'])->name('logs.move');
        Route::post('/logs/register', [AccessLogController::class, 'storeRegister'])->name('logs.register');

        Route::middleware('permission:access.register.exit')->group(function () {
            Route::get('/logs/exit', [AccessLogController::class, 'exitPage'])->name('logs.exit.page');
            Route::patch('/logs/{accessLog}/exit', [AccessLogController::class, 'markExit'])->name('logs.exit');
            Route::post('/logs/scan-exit', [AccessLogController::class, 'scanExit'])->name('logs.scan-exit');
            Route::post('/logs/bulk-exit', [AccessLogController::class, 'bulkExit'])->name('logs.bulk-exit');
        });
    });

    Route::middleware('permission:access.manage.pre_authorizations')->group(function () {
        Route::resource('pre_authorizations', PreAuthorizationController::class)->except(['edit', 'update']);
        Route::get('pre_authorizations/{preAuthorization}/qr', [PreAuthorizationController::class, 'qr'])->name('pre_authorizations.qr');
    });

    Route::middleware('permission:access.manage.correspondence')->group(function () {
        Route::resource('correspondence', CorrespondenceController::class)->except(['edit', 'update']);
        Route::patch('correspondence/{correspondence}/deliver', [CorrespondenceController::class, 'markDelivered'])->name('correspondence.deliver');
    });

    Route::resource('guard_logs', GuardLogController::class)->except(['edit', 'update'])
        ->middleware(['shift.open', 'permission:access.manage.guard_logs']);

    // Supervision (módulo con acceso por código único de supervisor)
    Route::middleware('permission:access.manage.supervision')->group(function () {
        Route::get('/supervision/unlock', [SupervisionController::class, 'unlockForm'])->name('supervision.unlock');
        Route::post('/supervision/unlock', [SupervisionController::class, 'unlock'])->name('supervision.unlock.submit');
        Route::get('/supervision/exit', [SupervisionController::class, 'exit'])->name('supervision.exit');

        Route::get('/supervision/codes', [SupervisionCodeController::class, 'index'])
            ->middleware('permission:access.manage.supervision_codes')
            ->name('supervision.codes.index');
        Route::post('/supervision/codes', [SupervisionCodeController::class, 'store'])
            ->middleware('permission:access.manage.supervision_codes')
            ->name('supervision.codes.store');
        Route::patch('/supervision/codes/{code}/toggle', [SupervisionCodeController::class, 'toggle'])
            ->middleware('permission:access.manage.supervision_codes')
            ->name('supervision.codes.toggle');
        Route::delete('/supervision/codes/{code}', [SupervisionCodeController::class, 'destroy'])
            ->middleware('permission:access.manage.supervision_codes')
            ->name('supervision.codes.destroy');

        Route::middleware('supervision.unlocked')->group(function () {
            Route::get('/supervision', [SupervisionController::class, 'index'])->name('supervision.index');
            Route::get('/supervision/create', [SupervisionController::class, 'create'])->name('supervision.create');
            Route::post('/supervision', [SupervisionController::class, 'store'])->name('supervision.store');
            Route::get('/supervision/{supervision}', [SupervisionController::class, 'show'])->name('supervision.show');
            Route::delete('/supervision/{supervision}', [SupervisionController::class, 'destroy'])->name('supervision.destroy');
        });
    });

    Route::middleware('permission:access.view.reports')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/printable', [ReportController::class, 'printable'])->name('reports.printable');
    });

    Route::middleware('permission:access.manage.blocklist')->group(function () {
        Route::get('/blocklist', [BlocklistController::class, 'index'])->name('blocklist.index');
        Route::get('/blocklist/create', [BlocklistController::class, 'create'])->name('blocklist.create');
        Route::post('/blocklist', [BlocklistController::class, 'store'])->name('blocklist.store');
        Route::get('/blocklist/search', [BlocklistController::class, 'searchJson'])->name('blocklist.search');
    });
    Route::delete('/blocklist/{blocklist}', [BlocklistController::class, 'destroy'])
        ->middleware('permission:access.blocklist.remove')
        ->name('blocklist.destroy');

    // Turnos de guardia
    Route::get('/turnos', [TurnoController::class, 'index'])
        ->middleware('permission:access.manage.turnos')
        ->name('turnos.index');
    Route::get('/turnos/open', [TurnoController::class, 'open'])
        ->middleware('permission:access.manage.turnos')
        ->name('turnos.open');
    Route::post('/turnos', [TurnoController::class, 'store'])
        ->middleware('permission:access.manage.turnos')
        ->name('turnos.store');
    Route::post('/turnos/close', [TurnoController::class, 'close'])
        ->middleware('permission:access.manage.turnos')
        ->name('turnos.close');

    // Auditoría de seguridad
    Route::get('/audit', [AuditController::class, 'index'])
        ->middleware('permission:access.view.audit')
        ->name('audit.index');

    // Zonas comunes (portería)
    Route::middleware('permission:access.manage.zones')->group(function () {
        Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');
        Route::post('/zones/checkin', [ZoneController::class, 'checkin'])->name('zones.checkin');
        Route::post('/zones/{booking}/complete', [ZoneController::class, 'complete'])->name('zones.complete');
        Route::post('/zones/{booking}/cancel', [ZoneController::class, 'cancel'])->name('zones.cancel');

        Route::middleware('permission:access.manage.locations')->group(function () {
            Route::get('/zones/create', [ZoneController::class, 'create'])->name('zones.create');
            Route::post('/zones', [ZoneController::class, 'store'])->name('zones.store');
            Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');
        });
    });
});
