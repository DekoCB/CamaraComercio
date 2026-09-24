<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('channel', 20);
            $table->string('instrument_type', 30)->nullable();
            $table->string('debtor_name');
            $table->string('debtor_document', 20)->nullable();
            $table->string('creditor_name');
            $table->string('creditor_document', 20)->nullable();
            // The CCH member requesting the registration, if any — not
            // every requester is a member (the service is also open to
            // non-members, just without the member discount), so this is
            // deliberately nullable rather than required like Invoice's.
            $table->foreignId('associate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('registered_at');
            $table->string('status', 20)->default('REGISTRADO');
            $table->dateTime('regularized_at')->nullable();
            $table->foreignId('regularized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('regularization_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protests');
    }
};
