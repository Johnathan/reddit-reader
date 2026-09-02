<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which top-level feed sources the user has switched off on the home
     * timeline. Null/empty means everything is on; entries are source keys:
     * 'reddit', 'hn', 'news' (news covers every configured RSS feed).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('disabled_sources')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('disabled_sources');
        });
    }
};
