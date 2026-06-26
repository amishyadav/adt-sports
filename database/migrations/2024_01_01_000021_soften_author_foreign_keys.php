<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * articles.author_id and media.uploaded_by were ON DELETE CASCADE — deleting a
 * user HARD-deleted all of their articles/media (bypassing soft-delete). Switch
 * to nullOnDelete (and make the columns nullable) so a user's content survives
 * their account removal. UserController::destroy reassigns content to the acting
 * admin, so this is the defence-in-depth safety net.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->dropForeign(['author_id']);
        });
        Schema::table('articles', function (Blueprint $t) {
            $t->unsignedBigInteger('author_id')->nullable()->change();
            $t->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('media', function (Blueprint $t) {
            $t->dropForeign(['uploaded_by']);
        });
        Schema::table('media', function (Blueprint $t) {
            $t->unsignedBigInteger('uploaded_by')->nullable()->change();
            $t->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->dropForeign(['author_id']);
        });
        Schema::table('articles', function (Blueprint $t) {
            $t->unsignedBigInteger('author_id')->nullable(false)->change();
            $t->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('media', function (Blueprint $t) {
            $t->dropForeign(['uploaded_by']);
        });
        Schema::table('media', function (Blueprint $t) {
            $t->unsignedBigInteger('uploaded_by')->nullable(false)->change();
            $t->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
