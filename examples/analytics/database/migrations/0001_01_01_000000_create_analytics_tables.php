<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signups', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->timestamp('created_at');
        });

        Schema::create('mails', function (Blueprint $table) {
            $table->unsignedBigInteger('notification_id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->timestamp('created_at');
        });
    }
};
