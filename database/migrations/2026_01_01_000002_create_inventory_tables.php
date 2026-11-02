<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('name');
            $table->string('sku', 20)->unique();
            $table->string('tracking_type', 10)->default('unit'); // unit | bulk
            $table->unsignedInteger('total_stock')->default(0);   // bulk items only
            $table->unsignedSmallInteger('max_loan_days')->default(3);
            $table->boolean('requires_approval')->default(false);
            $table->decimal('replacement_value', 14, 2)->default(0);
            $table->unsignedTinyInteger('tile')->default(0);
            $table->string('photo_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('asset_code', 40)->unique();
            $table->string('condition', 12)->default('good');  // good | fair | damaged
            $table->string('status', 14)->default('available'); // available | loaned | maintenance | lost
            $table->date('acquired_at')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_units');
        Schema::dropIfExists('items');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('categories');
    }
};
