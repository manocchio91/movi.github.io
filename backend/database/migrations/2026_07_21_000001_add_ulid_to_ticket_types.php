<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        DB::table('ticket_types')->orderBy('id')->each(function (object $ticket): void {
            DB::table('ticket_types')->where('id', $ticket->id)->update(['ulid' => (string) Str::ulid()]);
        });

        DB::statement('ALTER TABLE ticket_types ALTER COLUMN ulid SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
