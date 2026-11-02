<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_item_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('loan_count')->default(0);
            $table->unsignedInteger('qty_borrowed')->default(0);
            $table->unsignedInteger('unique_borrowers')->default(0);
            $table->decimal('avg_duration_days', 6, 2)->default(0);
            $table->unsignedInteger('unmet_requests')->default(0);
            $table->unique(['stat_date', 'item_id']);
        });

        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('predicted_qty', 10, 3);
            $table->decimal('lower_bound', 10, 3);
            $table->decimal('upper_bound', 10, 3);
            $table->string('model_name', 40);
            $table->dateTime('generated_at');
            $table->index(['item_id', 'forecast_date']);
        });

        Schema::create('overdue_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->decimal('risk_probability', 6, 4);
            $table->string('model_version', 60);
            $table->dateTime('scored_at');
            $table->unique('loan_id');
        });

        Schema::create('unmet_demand_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('requested_qty');
            $table->unsignedInteger('available_qty')->default(0);
            $table->dateTime('requested_start_at');
            $table->dateTime('requested_end_at');
            $table->string('reason', 20); // out_of_stock | date_conflict
            // context captured at the moment of failure; the diagnosis of "why" depends on it
            $table->float('util')->default(0);
            $table->float('maint_share')->default(0);
            $table->unsignedSmallInteger('overdue_units')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['item_id', 'created_at']);
        });

        Schema::create('procurement_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('unmet_count')->default(0);
            $table->unsignedInteger('affected_users')->default(0);
            $table->decimal('fill_rate', 6, 4)->default(1);
            $table->unsignedInteger('peak_shortage_qty')->default(0);
            $table->unsignedInteger('recommended_add_qty')->default(0);
            $table->string('cause', 20);
            $table->decimal('priority_score', 6, 4)->default(0);
            $table->string('status', 12)->default('open'); // open | approved | deferred | rejected | fulfilled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rationale')->nullable();
            $table->string('window_text')->nullable();
            $table->integer('trend')->default(0);
            $table->dateTime('fulfilled_at')->nullable();
            $table->decimal('fill_before', 6, 4)->nullable();
            $table->unsignedInteger('added_qty')->nullable();
            $table->dateTime('generated_at');
            $table->timestamps();
            $table->index(['item_id', 'status']);
        });

        Schema::create('analysis_outputs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->index();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->longText('payload');
            $table->dateTime('generated_at');
        });
    }

    public function down(): void
    {
        foreach (['analysis_outputs', 'procurement_recommendations', 'unmet_demand_logs', 'overdue_risk_scores', 'demand_forecasts', 'daily_item_stats'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
