<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('peminjam')->index();
            $table->string('nim', 40)->nullable();
            $table->string('organization')->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedSmallInteger('trust_score')->default(70);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'nim', 'organization', 'phone', 'trust_score', 'is_active']);
        });
    }
};
