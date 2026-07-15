<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->unique()->after('id');
            $table->foreignId('organizer_profile_id')->nullable()->after('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unique('booking_id');
            $table->unsignedTinyInteger('experience_quality_rating')->default(0)->after('rating');
            $table->unsignedTinyInteger('organization_rating')->default(0)->after('experience_quality_rating');
            $table->unsignedTinyInteger('location_rating')->default(0)->after('organization_rating');
            $table->unsignedTinyInteger('value_rating')->default(0)->after('location_rating');
            $table->unsignedTinyInteger('schedule_respect_rating')->default(0)->after('value_rating');
            $table->unsignedTinyInteger('staff_rating')->default(0)->after('schedule_respect_rating');
            $table->boolean('suitable_for_children')->nullable()->after('staff_rating');
            $table->boolean('would_recommend')->default(true)->after('suitable_for_children');
            $table->timestamp('reviewed_at')->nullable()->after('comment');
            $table->timestamp('hidden_at')->nullable()->after('status');
        });

        Schema::create('review_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('review_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_profile_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->decimal('average_quality', 3, 2)->default(0);
            $table->decimal('average_organization', 3, 2)->default(0);
            $table->decimal('average_location', 3, 2)->default(0);
            $table->decimal('average_value', 3, 2)->default(0);
            $table->decimal('average_schedule_respect', 3, 2)->default(0);
            $table->decimal('average_staff', 3, 2)->default(0);
            $table->unsignedInteger('one_star')->default(0);
            $table->unsignedInteger('two_star')->default(0);
            $table->unsignedInteger('three_star')->default(0);
            $table->unsignedInteger('four_star')->default(0);
            $table->unsignedInteger('five_star')->default(0);
            $table->decimal('satisfied_rate', 5, 2)->default(0);
            $table->decimal('recommendation_rate', 5, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_statistics');
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_images');

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id']);
            $table->dropForeign(['organizer_profile_id']);
            $table->dropForeign(['booking_id']);
            $table->dropColumn([
                'ulid',
                'organizer_profile_id',
                'booking_id',
                'experience_quality_rating',
                'organization_rating',
                'location_rating',
                'value_rating',
                'schedule_respect_rating',
                'staff_rating',
                'suitable_for_children',
                'would_recommend',
                'reviewed_at',
                'hidden_at',
            ]);
        });
    }
};
