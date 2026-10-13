<?php

declare(strict_types=1);

use App\Http\Controllers\Client\AppUserController;
use App\Http\Controllers\Client\AuthorizationController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\EmployeeController;
use App\Http\Controllers\Client\InstallationController;
use App\Http\Controllers\Client\MemberController;
use App\Http\Controllers\Client\MemberTypeController;
use App\Http\Controllers\Client\ObservatoryEventController;
use App\Http\Controllers\Client\ObservatoryReportTypeController;
use App\Http\Controllers\Client\PersonnelDocumentController;
use App\Http\Controllers\Client\PetController;
use App\Http\Controllers\Client\StructureController;
use App\Http\Controllers\Client\UserController;
use App\Http\Controllers\Client\VehicleController;
use App\Http\Controllers\Client\ZoneBookingController;
use App\Http\Controllers\Ops\OperationalAlertController;
use App\Http\Controllers\Ops\SigBoardLiveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed', 'active', 'tenancy.access', 'client.admin'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:client.structures.manage|ops.sig.view')
            ->name('dashboard');
        Route::get('/sig/live.json', [SigBoardLiveController::class, 'client'])
            ->middleware('permission:client.structures.manage|ops.sig.view')
            ->name('sig.live');

        Route::get('/ops/alerts.json', [OperationalAlertController::class, 'poll'])
            ->name('ops.alerts');
        Route::post('/ops/panic', [OperationalAlertController::class, 'panic'])
            ->name('ops.panic');

        Route::middleware('permission:company.documents.view')->group(function () {
            Route::get('/documents', [PersonnelDocumentController::class, 'index'])->name('personnel-documents.index');
            Route::get('/documents/file/{document}/preview', [PersonnelDocumentController::class, 'preview'])->name('personnel-documents.preview');
            Route::get('/documents/{employee}', [PersonnelDocumentController::class, 'folder'])->name('personnel-documents.folder');
        });
        Route::get('/documents/file/{document}/download', [PersonnelDocumentController::class, 'download'])
            ->middleware('permission:company.documents.manage')
            ->name('personnel-documents.download');

        Route::get('/employees', [EmployeeController::class, 'index'])
            ->middleware('permission:company.employees.view')
            ->name('employees.index');

        Route::get('/installations', [InstallationController::class, 'index'])
            ->middleware('permission:client.structures.manage')
            ->name('installations.index');
        Route::get('/installations/{installation}', [InstallationController::class, 'show'])
            ->middleware('permission:client.structures.manage')
            ->name('installations.show');

        Route::middleware(['permission:observatory.view', 'client.module:observatory'])->group(function () {
            Route::get('/observatory/events', [ObservatoryEventController::class, 'index'])
                ->name('observatory.events.index');
            Route::get('/observatory/live.json', [ObservatoryEventController::class, 'live'])
                ->name('observatory.live');
            Route::get('/observatory/tablero.pptx', [ObservatoryEventController::class, 'export'])
                ->name('observatory.board.export');
            Route::get('/observatory/reports/create', [ObservatoryEventController::class, 'create'])
                ->name('observatory.reports.create');
            Route::post('/observatory/reports', [ObservatoryEventController::class, 'store'])
                ->name('observatory.reports.store');
            Route::get('/observatory/events/{event}', [ObservatoryEventController::class, 'show'])
                ->name('observatory.events.show');
            Route::get('/observatory/types', [ObservatoryReportTypeController::class, 'index'])
                ->name('observatory.types.index');
            Route::post('/observatory/types', [ObservatoryReportTypeController::class, 'store'])
                ->middleware('permission:observatory.events.update')
                ->name('observatory.types.store');
            Route::put('/observatory/types/{type}', [ObservatoryReportTypeController::class, 'update'])
                ->middleware('permission:observatory.events.update')
                ->name('observatory.types.update');
            Route::delete('/observatory/types/{type}', [ObservatoryReportTypeController::class, 'destroy'])
                ->middleware('permission:observatory.events.update')
                ->name('observatory.types.destroy');
            Route::patch('/observatory/events/{event}/status', [ObservatoryEventController::class, 'updateStatus'])
                ->middleware('permission:observatory.events.update')
                ->name('observatory.events.status');
            Route::post('/observatory/events/{event}/merge', [ObservatoryEventController::class, 'merge'])
                ->middleware('permission:observatory.events.update')
                ->name('observatory.events.merge');
            Route::post('/observatory/events/{event}/reports/{report}/detach', [ObservatoryEventController::class, 'detach'])
                ->middleware('permission:observatory.events.update')
                ->name('observatory.events.reports.detach');
        });

        Route::get('/structures', [StructureController::class, 'index'])
            ->middleware('permission:client.structures.manage')
            ->name('structures.index');
        Route::post('/structures', [StructureController::class, 'store'])
            ->middleware('permission:client.structures.manage')
            ->name('structures.store');
        Route::get('/structures/{structure}', [StructureController::class, 'show'])
            ->middleware('permission:client.structures.manage')
            ->name('structures.show');

        Route::get('/members/export', [MemberController::class, 'export'])
            ->middleware('permission:client.members.manage')
            ->name('members.export');

        Route::middleware('permission:client.members.manage')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/member-types', [MemberTypeController::class, 'index'])->name('member-types.index');
            Route::post('/member-types', [MemberTypeController::class, 'store'])->name('member-types.store');
            Route::put('/member-types/{memberType}', [MemberTypeController::class, 'update'])->name('member-types.update');
            Route::delete('/member-types/{memberType}', [MemberTypeController::class, 'destroy'])->name('member-types.destroy');
        });

        Route::get('/members', [MemberController::class, 'index'])
            ->middleware('permission:client.members.manage')
            ->name('members.index');
        Route::get('/members/create', [MemberController::class, 'create'])
            ->middleware('permission:client.members.manage')
            ->name('members.create');
        Route::post('/members', [MemberController::class, 'store'])
            ->middleware('permission:client.members.manage')
            ->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])
            ->middleware('permission:client.members.manage')
            ->name('members.show');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])
            ->middleware('permission:client.members.manage')
            ->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])
            ->middleware('permission:client.members.manage')
            ->name('members.update');

        Route::middleware(['permission:client.vehicles.manage', 'client.module:vehicles'])->group(function () {
            Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
            Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
            Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        });

        Route::middleware(['permission:client.authorizations.manage', 'client.module:authorizations'])->group(function () {
            Route::get('/authorizations', [AuthorizationController::class, 'index'])->name('authorizations.index');
            Route::get('/authorizations/create', [AuthorizationController::class, 'create'])->name('authorizations.create');
            Route::post('/authorizations', [AuthorizationController::class, 'store'])->name('authorizations.store');
            Route::get('/authorizations/import', [AuthorizationController::class, 'importForm'])->name('authorizations.import');
            Route::post('/authorizations/import', [AuthorizationController::class, 'import'])->name('authorizations.import.store');
            Route::get('/authorizations/members', [AuthorizationController::class, 'members'])->name('authorizations.members');
            Route::get('/authorizations/{authorization}', [AuthorizationController::class, 'show'])->whereNumber('authorization')->name('authorizations.show');
            Route::patch('/authorizations/{authorization}/cancel', [AuthorizationController::class, 'cancel'])->whereNumber('authorization')->name('authorizations.cancel');
        });

        Route::middleware(['permission:client.pets.manage', 'client.module:pets'])->group(function () {
            Route::get('/pets', [PetController::class, 'index'])->name('pets.index');
            Route::get('/pets/create', [PetController::class, 'create'])->name('pets.create');
            Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
            Route::get('/pets/{pet}', [PetController::class, 'show'])->name('pets.show');
        });

        Route::get('/app-users', [AppUserController::class, 'index'])
            ->middleware('permission:client.app_users.manage')
            ->name('app-users.index');
        Route::get('/app-users/create', [AppUserController::class, 'create'])
            ->middleware('permission:client.app_users.manage')
            ->name('app-users.create');
        Route::post('/app-users', [AppUserController::class, 'store'])
            ->middleware('permission:client.app_users.manage')
            ->name('app-users.store');

        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:client.users.manage')
            ->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->middleware('permission:client.users.manage')
            ->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:client.users.manage')
            ->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:client.users.manage')
            ->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:client.users.manage')
            ->name('users.update');

        // Zonas comunes
        Route::middleware('permission:client.zones.book')->group(function () {
            Route::get('/zones', [ZoneBookingController::class, 'index'])->name('zones.index');
            Route::get('/zones/book', [ZoneBookingController::class, 'create'])->name('zones.book');
            Route::post('/zones', [ZoneBookingController::class, 'store'])->name('zones.store');
            Route::post('/zones/{booking}/cancel', [ZoneBookingController::class, 'cancel'])->name('zones.cancel');
        });
    });
