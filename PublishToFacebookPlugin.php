<?php

/**
 * @file plugins/generic/publishToFacebook/PublishToFacebookPlugin.php
 *
 * @class PublishToFacebookPlugin
 *
 * @brief Publish article or issue links to a configured Facebook Page.
 */

namespace APP\plugins\generic\publishToFacebook;

use APP\core\Application;
use APP\plugins\generic\publishToFacebook\classes\Constants;
use APP\plugins\generic\publishToFacebook\classes\FacebookService;
use APP\plugins\generic\publishToFacebook\classes\PostLog;
use APP\plugins\generic\publishToFacebook\classes\PostLogDAO;
use APP\issue\Issue;
use APP\plugins\generic\publishToFacebook\classes\IssuePostBuilder;
use APP\plugins\generic\publishToFacebook\classes\PublicationPostBuilder;
use APP\plugins\generic\publishToFacebook\PostController;
use Carbon\Carbon;
use PKP\core\APIRouter;
use PKP\core\PKPRequest;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\VueModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

class PublishToFacebookPlugin extends GenericPlugin
{
    private SettingsController $controller;
    private PostController $postController;

    /**
     * Register the plugin.
     *
     * @param string $category
     * @param string $path
     * @param int|null $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!$success) {
            return false;
        }

        // Register the postLog schema so PKPSchemaService can load it
        $this->registerPostLogSchema();

        // Register the PostLogDAO for dependency injection
        app()->singleton(PostLogDAO::class, function () {
            return new PostLogDAO(app(PKPSchemaService::class));
        });

        // Instantiate controllers (needed for API hook registration below)
        $this->controller = new SettingsController($this);
        $this->postController = new PostController($this);

        // Register API endpoints unconditionally per OJS best practices
        Hook::add('APIHandler::endpoints::plugin', function (string $hookName, APIRouter $apiRouter): bool {
            $apiRouter->registerPluginApiControllers([
                $this->controller,
                $this->postController,
            ]);
            return Hook::CONTINUE;
        });

        // Register hooks unconditionally; runtime getEnabled() checks inside each handler
        $this->addPublishButtonHook();
        $this->addAutoPublishHook();
        $this->addAutoPublishIssueHook();

        return true;
    }

    /**
     * Register the postLog schema via the Schema::get:: hook so that
     * PKPSchemaService can load it when EntityDAO needs it.
     */
    private function registerPostLogSchema(): void
    {
        $pluginPath = $this->getPluginPath();
        Hook::add('Schema::get::postLog', function (string $hookName, array $args) use ($pluginPath): bool {
            /** @var \stdClass $schema */
            $schema =& $args[0];
            $schemaFile = $pluginPath . '/schema/log.json';
            if (file_exists($schemaFile)) {
                $schema = json_decode(file_get_contents($schemaFile));
            }
            return Hook::CONTINUE;
        });
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration(): \APP\plugins\generic\publishToFacebook\classes\migrations\PostLogMigration
    {
        return new \APP\plugins\generic\publishToFacebook\classes\migrations\PostLogMigration();
    }

    /**
     * Persist an auto-publish failure without breaking the publication flow.
     */
    private function recordAutoPublishFailure(?int $submissionId, ?int $issueId, int $contextId, \Throwable $exception): void
    {
        try {
            $postLog = new PostLog();
            $postLog->setData('submissionId', $submissionId);
            $postLog->setData('issueId', $issueId);
            $postLog->setData('contextId', $contextId);
            $postLog->setData('status', PostLog::STATUS_ERROR);
            $postLog->setData('errorMessage', $exception->getMessage());
            $postLog->setData('datePosted', Carbon::now()->format('Y-m-d H:i:s'));
            app(PostLogDAO::class)->insert($postLog);
        } catch (\Throwable $loggingException) {
            error_log(sprintf(
                '[PublishToFacebookPlugin] Failed to persist auto-publish error log for context %d (submissionId=%s, issueId=%s). Original error: %s. Logging error: %s',
                $contextId,
                $submissionId === null ? 'null' : (string) $submissionId,
                $issueId === null ? 'null' : (string) $issueId,
                $exception->getMessage(),
                $loggingException->getMessage()
            ));
        }
    }

    /**
     * Get the display name of this plugin.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.publishToFacebook.displayName');
    }

    /**
     * Get the description of this plugin.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.publishToFacebook.description');
    }

    /**
     * Register the template hook that adds the Publish to Facebook button
     * on the submission detail page.
     */
    private function addPublishButtonHook(): void
    {
        Hook::add('Templates::Submission::SubmissionDetails::Main', function (string $hookName, array $params): bool {
            $output =& $params[2];
            $smarty = $params[0];
            $templateMgr = $params[3] ?? $smarty;

            if (!$this->getEnabled()) {
                return Hook::CONTINUE;
            }

            $submission = $smarty->getTemplateVars('submission');
            if (!$submission) {
                return Hook::CONTINUE;
            }

            // Only show for published submissions
            $publication = $submission->getCurrentPublication();
            if (!$publication || !$publication->getData('datePublished')) {
                return Hook::CONTINUE;
            }

            $request = \Application::get()->getRequest();
            $context = $request->getContext();

            // Generate the API URL for the post endpoint
            $apiUrl = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $context->getPath(),
                $this->postController->getHandlerPath()
            );

            $submissionId = $submission->getId();
            $publishUrl = $apiUrl;
            $historyUrl = $apiUrl . '/history/' . $submissionId;
            $buttonLabel = __('plugins.generic.publishToFacebook.post.button');
            $retryLabel = __('plugins.generic.publishToFacebook.post.retry');
            $postingLabel = __('plugins.generic.publishToFacebook.post.posting');
            $postedLabel = __('plugins.generic.publishToFacebook.post.status.posted');

            $output .= <<<HTML
<div class="pkp_structure_narrow" style="margin-top:1rem">
    <div id="publishToFacebookStatus" style="margin-bottom:0.5rem"></div>
    <button
        type="button"
        class="pkpButton"
        id="publishToFacebookBtn"
        style="display:none"
        onclick="publishToFacebook()"
    >{$buttonLabel}</button>
    <div id="publishToFacebookResult" style="margin-top:0.5rem"></div>
</div>
<script>
var publishToFacebookSubmissionId = {$submissionId};
var publishToFacebookApiUrl = '{$publishUrl}';
var publishToFacebookHistoryUrl = '{$historyUrl}';

// Load post status on page load
(function() {
    fetch(publishToFacebookHistoryUrl)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var btn = document.getElementById('publishToFacebookBtn');
        var status = document.getElementById('publishToFacebookStatus');
        if (!data.exists) {
            btn.style.display = '';
            btn.textContent = '{$buttonLabel}';
            return;
        }
        if (data.status === 'success') {
            status.innerHTML = '<div class="pkp_notification success">' +
                '<span class="pkp_notification_content">{$postedLabel}</span></div>';
            return;
        }
        // Error state — show error + retry button
        status.innerHTML = '<div class="pkp_notification error">' +
            '<span class="pkp_notification_content">' +
            (data.errorMessage || 'Unknown error') + '</span></div>';
        btn.style.display = '';
        btn.textContent = '{$retryLabel}';
    })
    .catch(function() {
        // Silently hide the button if history fetch fails
        document.getElementById('publishToFacebookBtn').style.display = '';
    });
})();

function publishToFacebook() {
    var btn = document.getElementById('publishToFacebookBtn');
    var result = document.getElementById('publishToFacebookResult');
    btn.disabled = true;
    btn.textContent = '{$postingLabel}';
    result.innerHTML = '';
    fetch(publishToFacebookApiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({submissionId: publishToFacebookSubmissionId})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        if (data.success) {
            btn.style.display = 'none';
            document.getElementById('publishToFacebookStatus').innerHTML =
                '<div class="pkp_notification success">' +
                '<span class="pkp_notification_content">' + data.message + '</span></div>';
        } else {
            btn.textContent = '{$retryLabel}';
            result.innerHTML = '<div class="pkp_notification error">' +
                '<span class="pkp_notification_content">' + (data.error || 'Unknown error') + '</span></div>';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = '{$retryLabel}';
        result.innerHTML = '<div class="pkp_notification error">' +
            '<span class="pkp_notification_content">Request failed: ' + err.message + '</span></div>';
    });
}
</script>
HTML;
            return Hook::CONTINUE;
        });
    }

    /**
     * Register the hook that auto-posts articles to Facebook when they are
     * published in OJS.
     *
     * The Publication::publish hook fires after the publication is saved, so
     * a failure here never prevents the article from being published.
     *
     * Only first-time publications trigger an auto-post (re-publishes skip).
     * The autoPublishArticles setting controls whether auto-posting is active.
     */
    private function addAutoPublishHook(): void
    {
        Hook::add('Publication::publish', function (string $hookName, array $params): bool {
            try {
                /** @var \PKP\publication\PKPPublication $newPublication */
                $newPublication =& $params[0];
                /** @var \PKP\publication\PKPPublication $publication */
                $publication = $params[1];
                /** @var \APP\submission\Submission $submission */
                $submission = $params[2];

                // Only auto-post for first-time publications (status actually changed)
                if ($newPublication->getData('status') !== \PKPSubmission::STATUS_PUBLISHED) {
                    return Hook::CONTINUE;
                }
                if ($publication->getData('status') === \PKPSubmission::STATUS_PUBLISHED) {
                    return Hook::CONTINUE; // Re-publish — skip
                }

                $contextId = $submission->getData('contextId');

                // Runtime enabled check (hook registered unconditionally)
                if (!$this->getEnabled($contextId)) {
                    return Hook::CONTINUE;
                }

                // Check auto-publish setting
                if (!$this->getSetting($contextId, Constants::AUTO_PUBLISH_ARTICLES)) {
                    return Hook::CONTINUE;
                }

                // Check for duplicate
                $postLogDAO = app(PostLogDAO::class);
                $submissionId = $submission->getId();
                if ($postLogDAO->hasExistingPost($submissionId, $contextId)) {
                    return Hook::CONTINUE;
                }

                // Get configured settings
                $pageId = $this->getSetting($contextId, Constants::PAGE_ID);
                $accessToken = $this->getSetting($contextId, Constants::ACCESS_TOKEN);
                $messageFormat = $this->getSetting($contextId, Constants::MESSAGE_FORMAT_ARTICLE);

                if (empty($pageId) || empty($accessToken)) {
                    return Hook::CONTINUE;
                }

                // Resolve the Context object for the post builder
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($contextId);
                if (!$context) {
                    return Hook::CONTINUE;
                }

                // Build the message and URL
                $builder = new PublicationPostBuilder($submission, $context, $messageFormat ?: '');
                $message = $builder->buildMessage();
                $articleUrl = $builder->getArticleUrl();

                // Post to Facebook
                $service = new FacebookService();
                $result = $service->postLink($pageId, $accessToken, $message, $articleUrl);

                // Log the result
                $postLog = new PostLog();
                $postLog->setData('submissionId', $submissionId);
                $postLog->setData('contextId', $contextId);
                $postLog->setData('message', $message);
                $postLog->setData('link', $articleUrl);
                $postLog->setData('datePosted', Carbon::now()->format('Y-m-d H:i:s'));

                if ($result['success']) {
                    $postLog->setData('status', PostLog::STATUS_SUCCESS);
                    $postLog->setData('facebookPostId', $result['postId']);
                } else {
                    $postLog->setData('status', PostLog::STATUS_ERROR);
                    $postLog->setData('errorMessage', $result['error']);
                }
                $postLogDAO->insert($postLog);

            } catch (\Throwable $e) {
                // Never let an auto-post failure break the publication workflow
                $this->recordAutoPublishFailure($params[2]->getId(), null, $params[2]->getData('contextId'), $e);
            }

            return Hook::CONTINUE;
        });
    }

    /**
     * Register the hook that auto-posts issues to Facebook when they are
     * published via the issue grid handler.
     *
     * The IssueGridHandler::publishIssue hook fires after the issue's
     * published flag and date are set, but before Repo::issue()->updateCurrentUrl().
     * A failure here never prevents the issue from being published.
     */
    private function addAutoPublishIssueHook(): void
    {
        Hook::add('IssueGridHandler::publishIssue', function (string $hookName, array $params): bool {
            try {
                /** @var Issue $issue */
                $issue =& $params[0];
                $contextId = $issue->getJournalId();

                // Runtime enabled check (hook registered unconditionally)
                if (!$this->getEnabled($contextId)) {
                    return Hook::CONTINUE;
                }

                // Check auto-publish setting
                if (!$this->getSetting($contextId, Constants::AUTO_PUBLISH_ISSUES)) {
                    return Hook::CONTINUE;
                }

                // Check for duplicate issue post
                $postLogDAO = app(PostLogDAO::class);
                $issueId = (int) $issue->getId();
                if ($postLogDAO->hasExistingIssuePost($issueId, $contextId)) {
                    return Hook::CONTINUE;
                }

                // Get configured settings
                $pageId = $this->getSetting($contextId, Constants::PAGE_ID);
                $accessToken = $this->getSetting($contextId, Constants::ACCESS_TOKEN);
                $messageFormat = $this->getSetting($contextId, Constants::MESSAGE_FORMAT_ISSUE);

                if (empty($pageId) || empty($accessToken)) {
                    return Hook::CONTINUE;
                }

                // Resolve the Context object for the post builder
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($contextId);
                if (!$context) {
                    return Hook::CONTINUE;
                }

                // Build the message and URL
                $builder = new IssuePostBuilder($issue, $context, $messageFormat ?: '');
                $message = $builder->buildMessage();
                $issueUrl = $builder->getIssueUrl();

                // Post to Facebook
                $service = new FacebookService();
                $result = $service->postLink($pageId, $accessToken, $message, $issueUrl);

                // Log the result (submissionId is null for issue posts)
                $postLog = new PostLog();
                $postLog->setData('submissionId', null);
                $postLog->setData('issueId', $issueId);
                $postLog->setData('contextId', $contextId);
                $postLog->setData('message', $message);
                $postLog->setData('link', $issueUrl);
                $postLog->setData('datePosted', Carbon::now()->format('Y-m-d H:i:s'));

                if ($result['success']) {
                    $postLog->setData('status', PostLog::STATUS_SUCCESS);
                    $postLog->setData('facebookPostId', $result['postId']);
                } else {
                    $postLog->setData('status', PostLog::STATUS_ERROR);
                    $postLog->setData('errorMessage', $result['error']);
                }
                $postLogDAO->insert($postLog);

            } catch (\Throwable $e) {
                // Never let an auto-post failure break the issue publishing workflow
                $issue = $params[0];
                $this->recordAutoPublishFailure(null, $issue->getId(), $issue->getJournalId(), $e);
            }

            return Hook::CONTINUE;
        });
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $context = $request->getContext();
        $apiUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            $this->controller->getHandlerPath()
        );
        $form = new SettingsForm($apiUrl);
        array_unshift($actions, new LinkAction(
            'settings',
            new VueModal(
                'PkpFormModal',
                [
                    'id' => 'publishToFacebookSettings',
                    'title' => $this->getDisplayName(),
                    'formConfig' => $form->getConfig(),
                    'getApiUrl' => $apiUrl,
                ]
            ),
            __('manager.plugins.settings'),
            null
        ));
        return $actions;
    }
}
