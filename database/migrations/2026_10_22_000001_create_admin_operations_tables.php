<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminOperationsTables extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('auth_version')->default(1);
        });

        Schema::create('admin_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('role_name', 32);
            $table->json('permissions');
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->unsignedBigInteger('affected_user_id')->nullable()->index();
            $table->string('action', 100)->index();
            $table->text('reason')->nullable();
            $table->string('reference', 191)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('admin_wallet_adjustments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('actor_user_id')->index();
            $table->string('action', 8);
            $table->decimal('amount', 15, 4);
            $table->decimal('balance_before', 15, 4);
            $table->decimal('balance_after', 15, 4);
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_wallet_adjustments');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_accounts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('auth_version');
        });
    }
}
