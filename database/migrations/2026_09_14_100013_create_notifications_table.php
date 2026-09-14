<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared/broadcast notifications (not Laravel's built-in per-notifiable
 * notifications system, which doesn't fit here — every user with access
 * sees the same feed, so one row per event, not one per user). Who has
 * read it is tracked with a small `read_by` JSON array of user ids
 * rather than a pivot table — this system has a handful of users, so a
 * pivot table would be overhead without a real benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            $table->string('title', 190);
            $table->string('message', 255)->nullable();
            $table->string('entity_type', 60)->nullable();
            $table->string('entity_id', 60)->nullable();
            $table->string('link', 255)->nullable();
            $table->json('read_by');
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
