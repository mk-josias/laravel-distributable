<?php

use Foundation\Iam\Shadows\UserShadow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Microservices\Migrations\ShadowMigration;

return new class extends ShadowMigration
{
    protected function shadow(): string
    {
        return UserShadow::class;
    }

    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });
    }
};
