<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permiso del admin de instalaciones (admin / apoyo). Solo cambia el nombre; los valores se conservan. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_user_installation_assignments', 'site_permission')) {
            return;
        }

        Schema::table('client_user_installation_assignments', function (Blueprint $table): void {
            $table->renameColumn('site_permission', 'installation_permission');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('client_user_installation_assignments', 'installation_permission')) {
            return;
        }

        Schema::table('client_user_installation_assignments', function (Blueprint $table): void {
            $table->renameColumn('installation_permission', 'site_permission');
        });
    }
};
