<?php

/**
 * @file tests/Integration/PostLogDAOTest.php
 *
 * @class PostLogDAOTest
 *
 * @brief Integration tests for PostLogDAO against a test database.
 *
 * These tests require:
 * 1. OJS installed with a test database configured
 * 2. Plugin migration has been run to create publish_to_facebook_post_logs
 * 3. PKP application bootstrap loaded
 *
 * Run from OJS root:
 *   php plugins/generic/publishToFacebook/vendor/bin/phpunit
 *     -c plugins/generic/publishToFacebook/phpunit.xml
 *     --filter PostLogDAOTest
 *
 * @todo Implement with OJS test helpers once available:
 *       - Set up test context
 *       - Run migration in setUp()
 *       - Test insert() returns valid ID
 *       - Test hasExistingPost() with and without submission_id
 *       - Test getBySubmissionAndContext() returns correct ordering
 */

namespace APP\plugins\generic\publishToFacebook\tests\Integration;

use APP\plugins\generic\publishToFacebook\classes\PostLog;
use APP\plugins\generic\publishToFacebook\classes\PostLogDAO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use PKP\services\PKPSchemaService;

class PostLogDAOTest extends TestCase
{
    private const ARTICLE_CONTEXT_ID = 910001;
    private const ISSUE_CONTEXT_ID = 910002;

    private ?PostLogDAO $dao = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('app') || !class_exists(DB::class) || !class_exists(Schema::class) || !class_exists(PKPSchemaService::class)) {
            $this->markTestSkipped('Requires OJS bootstrap with Laravel container and database services.');
        }

        if (!Schema::hasTable('publish_to_facebook_post_logs')) {
            $this->markTestSkipped('publish_to_facebook_post_logs table is not available. Run the plugin migrations first.');
        }

        if (!Schema::hasColumn('publish_to_facebook_post_logs', 'issue_id')) {
            $this->markTestSkipped('issue_id column is not available. Run the plugin upgrade migration first.');
        }

        $this->dao = new PostLogDAO(app(PKPSchemaService::class));
        $this->deleteTestRows();
    }

    protected function tearDown(): void
    {
        if ($this->dao !== null && function_exists('app') && class_exists(DB::class)) {
            $this->deleteTestRows();
        }

        parent::tearDown();
    }

    public function test_hasExistingPost_matches_submission_successes_only(): void
    {
        $this->insertRow([
            'submission_id' => 1001,
            'issue_id' => null,
            'context_id' => self::ARTICLE_CONTEXT_ID,
            'status' => PostLog::STATUS_SUCCESS,
        ]);
        $this->insertRow([
            'submission_id' => 1002,
            'issue_id' => null,
            'context_id' => self::ARTICLE_CONTEXT_ID,
            'status' => PostLog::STATUS_ERROR,
        ]);

        $this->assertTrue($this->dao->hasExistingPost(1001, self::ARTICLE_CONTEXT_ID));
        $this->assertFalse($this->dao->hasExistingPost(1002, self::ARTICLE_CONTEXT_ID));
        $this->assertFalse($this->dao->hasExistingPost(1001, self::ISSUE_CONTEXT_ID));
    }

    public function test_hasExistingIssuePost_uses_issue_id_in_same_context(): void
    {
        $this->insertRow([
            'submission_id' => null,
            'issue_id' => 5001,
            'context_id' => self::ISSUE_CONTEXT_ID,
            'status' => PostLog::STATUS_SUCCESS,
        ]);
        $this->insertRow([
            'submission_id' => null,
            'issue_id' => 5002,
            'context_id' => self::ISSUE_CONTEXT_ID,
            'status' => PostLog::STATUS_ERROR,
        ]);

        $this->assertTrue($this->dao->hasExistingIssuePost(5001, self::ISSUE_CONTEXT_ID));
        $this->assertFalse($this->dao->hasExistingIssuePost(5002, self::ISSUE_CONTEXT_ID));

        $this->insertRow([
            'submission_id' => null,
            'issue_id' => 5002,
            'context_id' => self::ISSUE_CONTEXT_ID,
            'status' => PostLog::STATUS_SUCCESS,
        ]);

        $this->assertTrue($this->dao->hasExistingIssuePost(5002, self::ISSUE_CONTEXT_ID));
        $this->assertFalse($this->dao->hasExistingIssuePost(5999, self::ISSUE_CONTEXT_ID));
    }

    private function insertRow(array $overrides): void
    {
        DB::table('publish_to_facebook_post_logs')->insert(array_merge([
            'submission_id' => null,
            'issue_id' => null,
            'context_id' => self::ARTICLE_CONTEXT_ID,
            'status' => PostLog::STATUS_SUCCESS,
            'facebook_post_id' => null,
            'message' => 'Test message',
            'error_message' => null,
            'link' => 'https://example.com/test',
            'date_posted' => '2026-07-02 00:00:00',
        ], $overrides));
    }

    private function deleteTestRows(): void
    {
        DB::table('publish_to_facebook_post_logs')
            ->whereIn('context_id', [self::ARTICLE_CONTEXT_ID, self::ISSUE_CONTEXT_ID])
            ->delete();
    }
}
