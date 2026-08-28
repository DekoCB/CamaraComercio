<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves docs/OPEN_BUSINESS_DECISIONS.md #21 (payment correction — the
 * highest-impact gap found by the 2026-08-19 production-readiness audit,
 * REQUIREMENTS_GAP_ANALYSIS.md INV-08): a payment could never be corrected
 * or reversed once registered. Rather than allowing the amount to be
 * edited in place (which would silently rewrite financial history with no
 * trace of the original value — inconsistent with the "no physical
 * deletes, no silent overwrites" policy already applied to every other
 * entity in this system, see docs/DATA_MODEL.md), a wrong payment is
 * voided (kept, flagged, excluded from sums) and a correct one is
 * registered separately. The row itself is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
