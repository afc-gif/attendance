<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('passport_photo_path')->nullable()->after('email');
            $table->string('passport_photo_original_name')->nullable()->after('passport_photo_path');
            $table->string('passport_photo_mime')->nullable()->after('passport_photo_original_name');
            $table->unsignedBigInteger('passport_photo_size')->nullable()->after('passport_photo_mime');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'passport_photo_path',
                'passport_photo_original_name',
                'passport_photo_mime',
                'passport_photo_size',
            ]);
        });
    }
};
