<?php

namespace APP\plugins\generic\publishToFacebook\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIssueIdToPostLogMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_to_facebook_post_logs') || Schema::hasColumn('publish_to_facebook_post_logs', 'issue_id')) {
            return;
        }

        Schema::table('publish_to_facebook_post_logs', function (Blueprint $table) {
            $table->bigInteger('issue_id')->nullable()->after('submission_id');
            $table->index(['context_id', 'issue_id'], 'post_logs_context_issue_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('publish_to_facebook_post_logs') || !Schema::hasColumn('publish_to_facebook_post_logs', 'issue_id')) {
            return;
        }

        Schema::table('publish_to_facebook_post_logs', function (Blueprint $table) {
            $table->dropIndex('post_logs_context_issue_idx');
            $table->dropColumn('issue_id');
        });
    }
}
