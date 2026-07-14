<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Linking a Reddit account is what unlocks age-gated (18+) content —
     * Reddit serves anonymous clients an empty listing for those subs.
     * The refresh token is long-lived, so it is stored encrypted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('reddit_refresh_token')->nullable();
            $table->string('reddit_username')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reddit_refresh_token', 'reddit_username']);
        });
    }
};
