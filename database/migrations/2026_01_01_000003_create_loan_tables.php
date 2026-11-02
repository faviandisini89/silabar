<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code', 30)->unique();
            $table->foreignId('borrower_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('pending');
            $table->text('purpose');
            $table->dateTime('start_at');
            $table->dateTime('due_at');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('handed_over_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->unsignedSmallInteger('late_days')->default(0);
            $table->string('reject_reason')->nullable();
            $table->string('cancel_note')->nullable();
            $table->string('qr_token', 40)->unique();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['borrower_id', 'start_at']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('loan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('item_unit_id')->nullable()->constrained('item_units')->nullOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->index('item_id');
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('returned_at');
            $table->unsignedSmallInteger('late_days')->default(0);
            $table->text('notes')->nullable();
        });

        Schema::create('return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('loan_detail_id')->constrained('loan_details')->cascadeOnDelete();
            $table->string('condition_after', 12)->default('good'); // good | fair | damaged | lost
            $table->unsignedInteger('qty_returned')->default(1);
        });

        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 12);                      // late | damaged | lost
            $table->decimal('amount', 14, 2);
            $table->string('status', 12)->default('unpaid'); // unpaid | paid | waived
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_unit_id')->constrained('item_units')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('issue');
            $table->string('status', 10)->default('open'); // open | done
            $table->decimal('cost', 14, 2)->default(0);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedTinyInteger('days');
            $table->text('reason');
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->text('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'loan_extensions', 'maintenance_logs', 'penalties', 'return_details', 'returns', 'loan_details', 'loans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
