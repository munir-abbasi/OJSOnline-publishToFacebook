<?php

/**
 * @file plugins/generic/publishToFacebook/classes/PublicationPostBuilder.php
 *
 * @class PublicationPostBuilder
 *
 * @brief Builds formatted Facebook post messages from submission metadata.
 *   Replaces template variables with safe, OJS-resolved values.
 */

namespace APP\plugins\generic\publishToFacebook\classes;

use APP\submission\Submission;
use PKP\context\Context;

class PublicationPostBuilder
{
    private Submission $submission;
    private Context $context;
    private string $messageFormat;

    /**
     * @param Submission $submission  The published article submission
     * @param Context    $context     The journal/context
     * @param string     $messageFormat  Raw template from plugin settings, or ''
     */
    public function __construct(
        Submission $submission,
        Context $context,
        string $messageFormat = ''
    ) {
        $this->submission = $submission;
        $this->context = $context;
        $this->messageFormat = $messageFormat;
    }

    /**
     * Build the message by replacing template variables with actual values.
     *
     * Supported placeholders:
     *   {$articleTitle}  — Localized article title
     *   {$articleUrl}    — Public article URL (generated via OJS dispatcher)
     *   {$journalName}   — Localized journal/context name
     *
     * @return string The formatted message
     */
    public function buildMessage(): string
    {
        return str_replace(
            ['{$articleTitle}', '{$articleUrl}', '{$journalName}'],
            [
                $this->submission->getLocalizedTitle(),
                $this->getArticleUrl(),
                $this->context->getLocalizedName(),
            ],
            $this->messageFormat ?: ''
        );
    }

    /**
     * Generate the public-facing URL for the article using the OJS dispatcher.
     *
     * Uses getBestId() to handle both legacy (article_id) and current (submission_id) schemas.
     * Returns empty string if no active request (e.g., CLI context).
     */
    public function getArticleUrl(): string
    {
        $request = \Application::get()->getRequest();
        if ($request === null) {
            return '';
        }

        return $request->getDispatcher()->url(
            $request,
            \APP\core\Application::ROUTE_PAGE,
            $this->context->getPath(),
            'article',
            'view',
            [$this->submission->getBestId()]
        );
    }
}
