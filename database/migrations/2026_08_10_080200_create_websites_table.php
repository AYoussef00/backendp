<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('primary_domain');
            $table->json('domains')->nullable();
            $table->string('root_path')->nullable();
            $table->string('webserver')->nullable(); // nginx, apache
            $table->string('config_path')->nullable();
            $table->string('php_version')->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->string('status')->default('unknown'); // active, disabled, error, unknown
            $table->string('framework')->nullable(); // laravel, wordpress, etc.
            $table->string('framework_version')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'primary_domain', 'config_path']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
