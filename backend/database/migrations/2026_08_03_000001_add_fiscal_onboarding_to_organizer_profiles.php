<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->string('street_number', 20)->nullable()->after('address');
            $table->string('postal_code', 10)->nullable()->after('street_number');
            $table->string('province', 2)->nullable()->after('postal_code');
            $table->string('country', 80)->default('Italia')->after('province');
            $table->string('vat_number', 20)->nullable()->after('country');
            $table->string('pec')->nullable()->after('vat_number');
            $table->string('sdi_code', 7)->nullable()->after('pec');
            $table->string('public_email')->nullable()->after('phone');
            $table->string('instagram_url')->nullable()->after('website_url');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('facebook_url');
            $table->timestamp('submitted_at')->nullable()->after('verification_status');
            $table->foreignId('reviewed_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'street_number', 'postal_code', 'province', 'country', 'vat_number', 'pec',
                'sdi_code', 'public_email', 'instagram_url', 'facebook_url', 'tiktok_url',
                'submitted_at', 'reviewed_at', 'review_notes',
            ]);
        });
    }
};
