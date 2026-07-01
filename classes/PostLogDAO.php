<?php

/**
 * @file plugins/generic/publishToFacebook/classes/PostLogDAO.php
 *
 * @class PostLogDAO
 *
 * @brief EntityDAO for the post_log entity using the OJS 3.5 schema service.
 */

namespace APP\plugins\generic\publishToFacebook\classes;

use APP\plugins\generic\publishToFacebook\classes\PostLog;
use Illuminate\Support\Facades\DB;
use PKP\core\EntityDAO;
use PKP\services\PKPSchemaService;

class PostLogDAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = 'postLog';

    /** @copydoc EntityDAO::$table */
    public $table = 'publish_to_facebook_post_logs';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'post_log_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'post_log_id',
        'submissionId' => 'submission_id',
        'contextId' => 'context_id',
        'status' => 'status',
        'facebookPostId' => 'facebook_post_id',
        'message' => 'message',
        'errorMessage' => 'error_message',
        'link' => 'link',
        'datePosted' => 'date_posted',
    ];

    public function __construct(PKPSchemaService $schemaService)
    {
        parent::__construct($schemaService);
    }

    /**
     * @copydoc EntityDAO::newDataObject()
     */
    public function newDataObject(): PostLog
    {
        return app(PostLog::class);
    }

    /**
     * Insert a new post log entry.
     */
    public function insert(PostLog $postLog): int
    {
        return $this->_insert($postLog);
    }

    /**
     * Update an existing post log entry.
     */
    public function update(PostLog $postLog): void
    {
        $this->_update($postLog);
    }

    /**
     * Delete a post log entry by ID.
     */
    public function deleteById(int $id): void
    {
        $this->_deleteById($id);
    }

    /**
     * Check if a submission/issue has already been successfully posted.
     *
     * For issue posts (no submission), pass null as $submissionId
     * to match entries with submission_id IS NULL.
     *
     * @return bool True if a successful post log exists for this submission+context.
     */
    public function hasExistingPost(?int $submissionId, int $contextId): bool
    {
        $query = DB::table($this->table)
            ->where('context_id', $contextId)
            ->where('status', PostLog::STATUS_SUCCESS);

        if ($submissionId === null) {
            $query->whereNull('submission_id');
        } else {
            $query->where('submission_id', $submissionId);
        }

        return $query->exists();
    }

    /**
     * Get the most recent post log for a submission in a context.
     *
     * @return PostLog|null
     */
    public function getBySubmissionAndContext(int $submissionId, int $contextId): ?PostLog
    {
        $row = DB::table($this->table)
            ->where('submission_id', $submissionId)
            ->where('context_id', $contextId)
            ->orderBy('date_posted', 'desc')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->fromRow((array) $row);
    }

    /**
     * Get recent post logs for a context, with optional status filter.
     *
     * @return array<PostLog>
     */
    public function getByContext(int $contextId, ?string $status = null, int $limit = 50): array
    {
        $query = DB::table($this->table)
            ->where('context_id', $contextId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $rows = $query->orderBy('date_posted', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn ($row) => $this->fromRow((array) $row), $rows->all());
    }
}
