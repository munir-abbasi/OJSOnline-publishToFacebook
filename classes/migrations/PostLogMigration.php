<?php

/**
 * @file plugins/generic/publishToFacebook/classes/migrations/PostLogMigration.php
 *
 * @class PostLogMigration
 *
 * @brief Migration to create the publish_to_facebook_post_logs table.
 */

namespace APP\plugins\generic\publishToFacebook\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PostLogMigration extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('publish_to_facebook_post_logs', function (Blueprint $table) {
            $table->bigIncrements('post_log_id');
            $table->bigInteger('submission_id')->nullable();
            $table->bigInteger('issue_id')->nullable();
            $table->bigInteger('context_id');
            $table->string('status', 20);
            $table->string('facebook_post_id', 255)->nullable();
            $table->text('message')->nullable();
            $table->text('error_message')->nullable();
            $table->string('link', 2048)->nullable();
            $table->datetime('date_posted');

            $table->index(['context_id', 'submission_id'], 'post_logs_context_submission_idx');
            $table->index(['context_id', 'issue_id'], 'post_logs_context_issue_idx');
            $table->index('status', 'post_logs_status_idx');
        });
    }

    /**
     * Reverse the migration.
     *
     * WARNING: This drops the publish_to_facebook_post_logs table and
     * permanently deletes all post log data (history of what was posted
     * to Facebook, including success/failure records and Facebook post IDs).
     * Ensure you have a database backup before rolling back in production.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_to_facebook_post_logs');
    }
}
