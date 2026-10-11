<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The journal of the mails notifications sent: one row per notification, so a redelivered event changes nothing. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_mails', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_mails');
    }
};
