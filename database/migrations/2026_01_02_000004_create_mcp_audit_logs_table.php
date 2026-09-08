<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('client_id', 64)->nullable();
            $table->string('token_id', 100)->nullable()->index();
            $table->string('tool', 100)->index();
            $table->json('arguments')->nullable();
            $table->string('status', 32)->index();
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('result_bytes')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
