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
        Schema::table('users', fn (Blueprint $table) => $table->ulid('ulid')->nullable()->unique()->after('id'));
        DB::table('users')->whereNull('ulid')->orderBy('id')->eachById(
            fn (object $user) => DB::table('users')->where('id', $user->id)->update(['ulid' => (string) Str::ulid()])
        );
        Schema::table('organizer_profiles', fn (Blueprint $table) => $table->string('youtube_url')->nullable());
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->string('billing_model')->default('unlimited');
            $table->string('billing_interval')->default('monthly');
            $table->unsignedInteger('booking_fee_cents')->default(0);
        });
        Schema::table('organizer_subscriptions', function (Blueprint $table): void {
            $table->date('agreement_starts_at')->nullable();
            $table->date('agreement_ends_at')->nullable();
            $table->text('agreement_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizer_subscriptions', fn (Blueprint $table) => $table->dropColumn(['agreement_starts_at', 'agreement_ends_at', 'agreement_notes']));
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn(['billing_model', 'billing_interval', 'booking_fee_cents']));
        Schema::table('organizer_profiles', fn (Blueprint $table) => $table->dropColumn('youtube_url'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ulid'));
    }
};
