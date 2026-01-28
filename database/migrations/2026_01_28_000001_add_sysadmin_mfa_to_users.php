<?php

// database/migrations/2026_01_28_000001_add_sysadmin_mfa_to_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('sysadmin_totp_secret')->nullable();      // encrypted
            $t->timestamp('sysadmin_totp_enabled_at')->nullable();
            $t->timestamp('sysadmin_totp_confirmed_at')->nullable(); // opcional
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'sysadmin_totp_secret',
                'sysadmin_totp_enabled_at',
                'sysadmin_totp_confirmed_at',
            ]);
        });
    }
};
