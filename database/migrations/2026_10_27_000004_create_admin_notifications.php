<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* In-app notification feed for the admin dashboard. Separate from
   notification_logs, which records SMS / email / maps delivery attempts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // payment | account | request | chat | certificate
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('meta')->nullable(); // links back to the record (request_id, payment_id, …)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'read_at']);
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
