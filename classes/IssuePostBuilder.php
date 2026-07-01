<?php

/**
 * @file plugins/generic/publishToFacebook/classes/IssuePostBuilder.php
 *
 * @class IssuePostBuilder
 *
 * @brief Builds formatted Facebook post messages from issue metadata.
 *   Replaces template variables with safe, OJS-resolved values.
 *   Mirrors PublicationPostBuilder for issues.
 */

namespace APP\plugins\generic\publishToFacebook\classes;

use APP\issue\Issue;
use PKP\context\Context;

class IssuePostBuilder
{
    private Issue $issue;
    private Context $context;
    private string $messageFormat;

    /**
     * @param Issue   $issue         The published issue
     * @param Context $context       The journal/context
     * @param string  $messageFormat Raw template from plugin settings, or ''
     */
    public function __construct(
        Issue $issue,
        Context $context,
        string $messageFormat = ''
    ) {
        $this->issue = $issue;
        $this->context = $context;
        $this->messageFormat = $messageFormat;
    }

    /**
     * Build the message by replacing template variables with actual values.
     *
     * Supported placeholders:
     *   {$issueTitle}    — Localized issue title
     *   {$volume}        — Issue volume number
     *   {$number}        — Issue number
     *   {$year}          — Issue year
     *   {$datePublished} — Issue publication date
     *   {$journalName}   — Localized journal/context name
     *   {$issueUrl}      — Public issue URL (generated via OJS dispatcher)
     *
     * @return string The formatted message
     */
    public function buildMessage(): string
    {
        return str_replace(
            ['{$issueTitle}', '{$volume}', '{$number}', '{$year}', '{$datePublished}', '{$journalName}', '{$issueUrl}'],
            [
                $this->issue->getLocalizedTitle(),
                (string) $this->issue->getVolume(),
                (string) $this->issue->getNumber(),
                (string) $this->issue->getYear(),
                (string) $this->issue->getDatePublished(),
                $this->context->getLocalizedName(),
                $this->getIssueUrl(),
            ],
            $this->messageFormat ?: ''
        );
    }

    /**
     * Generate the public-facing URL for the issue using the OJS dispatcher.
     *
     * Uses getBestIssueId() to handle both custom URL paths and internal IDs.
     * Returns empty string if no active request (e.g., CLI context).
     */
    public function getIssueUrl(): string
    {
        $request = \Application::get()->getRequest();
        if ($request === null) {
            return '';
        }

        return $request->getDispatcher()->url(
            $request,
            \APP\core\Application::ROUTE_PAGE,
            $this->context->getPath(),
            'issue',
            'view',
            [$this->issue->getBestIssueId()]
        );
    }
}
