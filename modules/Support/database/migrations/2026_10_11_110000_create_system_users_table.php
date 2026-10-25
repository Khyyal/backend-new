<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('system_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('system_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('system_permission_role', function (Blueprint $table) {
            $table->foreignId('system_permission_id')->constrained('system_permissions')->cascadeOnDelete();
            $table->foreignId('system_role_id')->constrained('system_roles')->cascadeOnDelete();

            $table->primary(['system_permission_id', 'system_role_id']);
        });

        Schema::create('system_role_user', function (Blueprint $table) {
            $table->foreignId('system_role_id')->constrained('system_roles')->cascadeOnDelete();
            $table->foreignId('system_user_id')->constrained('system_users')->cascadeOnDelete();

            $table->primary(['system_role_id', 'system_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_role_user');
        Schema::dropIfExists('system_permission_role');
        Schema::dropIfExists('system_permissions');
        Schema::dropIfExists('system_roles');
        Schema::dropIfExists('system_users');
    }
};
