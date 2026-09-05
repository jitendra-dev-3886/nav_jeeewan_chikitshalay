<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['services', 'content_pages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('image_url')->nullable();
                $table->string('image_alt', 200)->nullable();
            });
        }
    }
    public function down(): void
    {
        foreach (['services', 'content_pages'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['image_url', 'image_alt']));
        }
    }
};
