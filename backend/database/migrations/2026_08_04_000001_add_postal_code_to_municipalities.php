<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->string('postal_code', 5)->nullable()->index()->after('istat_code');
        });

        foreach (['070006' => '86100', '070078' => '86039', '070003' => '86021', '094023' => '86170', '094002' => '86081'] as $istatCode => $postalCode) {
            DB::table('municipalities')->where('istat_code', $istatCode)->update(['postal_code' => $postalCode]);
        }
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropColumn('postal_code');
        });
    }
};
