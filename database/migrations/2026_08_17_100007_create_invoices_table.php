<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('associate_id')->constrained()->restrictOnDelete();
            $table->char('period', 7)->comment('YYYY-MM');
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_total', 12, 2)->default(0)
                ->comment('Denormalized SUM(payments.amount), kept in sync transactionally when a payment is registered');
            $table->date('issue_date');
            $table->date('due_date');
            $table->enum('status', ['PENDIENTE', 'PARCIAL', 'PAGADA', 'VENCIDA'])->default('PENDIENTE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['associate_id', 'period']);
            $table->index('period');
            $table->index('status');
            $table->index('due_date');
        });

        // CHECK constraints as defense-in-depth beyond Form Request validation.
        // SQLite (used for the test suite) doesn't support ALTER TABLE ADD
        // CONSTRAINT, so this only runs against the real MySQL database.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_paid_total_nonneg CHECK (paid_total >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
