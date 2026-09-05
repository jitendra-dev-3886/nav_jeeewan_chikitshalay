<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('kind')->default('text');
            $table->string('image_url')->nullable();
            $table->string('image_alt')->nullable();
        });
    }
    public function down(): void {Schema::table('banners', fn (Blueprint $table) => $table->dropColumn(['kind','image_url','image_alt']));}
};
