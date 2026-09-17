<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('associate_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('size')->comment('Bytes of the stored PDF, not the original upload');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['associate_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('associate_documents');
    }
};
