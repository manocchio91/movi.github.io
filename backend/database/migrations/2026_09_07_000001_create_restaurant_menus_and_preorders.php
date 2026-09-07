<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('menu_sections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('menu_section_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('allergens')->nullable();
            $table->json('dietary_tags')->nullable();
            $table->boolean('allergen_information_verified')->default(false);
            $table->timestamps();
        });
        Schema::table('activity_services', function (Blueprint $table): void {
            $table->boolean('preorder_enabled')->default(false);
            $table->unsignedInteger('preorder_edit_deadline_minutes')->default(120);
            $table->foreignId('preorder_menu_id')->nullable()->constrained('menus')->nullOnDelete();
        });
        Schema::create('preorders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('confirmed');
            $table->unsignedInteger('total_known_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('preorder_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preorder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_snapshot')->nullable();
            $table->string('item_name_snapshot');
            $table->unsignedInteger('subtotal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preorder_items');
        Schema::dropIfExists('preorders');
        Schema::table('activity_services', fn (Blueprint $table) => $table->dropConstrainedForeignId('preorder_menu_id'));
        Schema::table('activity_services', fn (Blueprint $table) => $table->dropColumn(['preorder_enabled', 'preorder_edit_deadline_minutes']));
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_sections');
        Schema::dropIfExists('menus');
    }
};
