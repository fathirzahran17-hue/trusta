<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notifikasi: pengumuman dari super admin + penanda "sudah dibaca" per pengguna. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $t) {
                $t->id();
                $t->string('title', 150);
                $t->text('body')->nullable();
                $t->string('level', 10)->default('info'); // info | warning | success
                $t->string('url')->nullable();
                $t->unsignedBigInteger('company_id')->nullable()->index(); // null = semua klien
                $t->date('starts_at')->nullable();
                $t->date('ends_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('users', 'notifications_read_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->timestamp('notifications_read_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notifications_read_at'));
    }
};
