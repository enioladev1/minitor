<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Single-row settings table (this is single-admin, instance-wide
        // config, not per-project) — NotificationSetting::current() enforces
        // the singleton via firstOrCreate, same as BackupSetting.
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('provider')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->string('notify_email')->nullable();

            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->text('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable();

            $table->text('postmark_token')->nullable();

            $table->text('resend_api_key')->nullable();

            $table->string('mailgun_domain')->nullable();
            $table->text('mailgun_secret')->nullable();
            $table->string('mailgun_endpoint')->default('api.mailgun.net');

            $table->boolean('notify_on_reopen')->default(true);

            $table->timestamp('last_test_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
