<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_rules', function (Blueprint $t) {
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('availability_rules', fn (Blueprint $t) => $t->dropColumn(['effective_from', 'effective_to']));
    }
};
