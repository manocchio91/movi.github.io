<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->index()->after('role');
        });

        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('verified_at');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('customer','organizer','admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','pending','suspended'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->dropColumn('suspended_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
