<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status')->default('pending'); // pending, online, offline, warning
            $table->uuid('agent_id')->nullable()->unique();
            $table->string('agent_secret_hash')->nullable();
            $table->string('agent_public_key', 2048)->nullable();
            $table->string('agent_version')->nullable();
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->unsignedBigInteger('memory_total')->nullable();
            $table->unsignedBigInteger('disk_total')->nullable();
            $table->json('discovery')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('agent_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash');
            $table->string('token_prefix', 16);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_by_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['token_prefix', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_installations');
        Schema::dropIfExists('servers');
    }
};
