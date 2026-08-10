<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->string('result')->nullable(); // success, failed
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['server_id', 'created_at']);
        });

        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->decimal('memory_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('memory_used')->nullable();
            $table->decimal('disk_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('disk_used')->nullable();
            $table->decimal('load_1', 8, 2)->nullable();
            $table->decimal('load_5', 8, 2)->nullable();
            $table->decimal('load_15', 8, 2)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->json('network')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['server_id', 'collected_at']);
        });

        Schema::create('service_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('status')->default('unknown'); // running, stopped, failed, unknown
            $table->boolean('enabled')->default(false);
            $table->string('version')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_snapshots');
        Schema::dropIfExists('server_metrics');
        Schema::dropIfExists('audit_logs');
    }
};
