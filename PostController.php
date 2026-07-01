<?php

/**
 * @file plugins/generic/publishToFacebook/PostController.php
 *
 * @class PostController
 *
 * @brief OJS 3.5 API controller for manually posting articles to Facebook.
 */

namespace APP\plugins\generic\publishToFacebook;

use APP\plugins\generic\publishToFacebook\classes\Constants;
use APP\plugins\generic\publishToFacebook\classes\FacebookService;
use APP\plugins\generic\publishToFacebook\classes\PostLog;
use APP\plugins\generic\publishToFacebook\classes\PostLogDAO;
use APP\plugins\generic\publishToFacebook\classes\PublicationPostBuilder;
use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\plugins\Plugin;
use PKP\security\Role;

class PostController extends PKPBaseController
{
    protected Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        parent::__construct();
        $this->plugin = $plugin;
    }

    /**
     * URL path segment for API routing.
     */
    public function getHandlerPath(): string
    {
        return 'publishToFacebookPost';
    }

    /**
     * Middleware for the route group.
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]),
        ];
    }

    /**
     * Register routes: POST for submit, GET for history.
     */
    public function getGroupRoutes(): void
    {
        Route::post('', $this->submit(...))->name('plugin.publishToFacebook.post.submit');
        Route::get('history/{submissionId}', $this->history(...))
            ->where('submissionId', '[0-9]+')
            ->name('plugin.publishToFacebook.post.history');
    }

    /**
     * Whether this controller is site-wide.
     */
    public function isSiteWide(): bool
    {
        return $this->plugin->isSitePlugin();
    }

    /**
     * POST handler — validates the request and posts the article to Facebook.
     *
     * Expects JSON body: { "submissionId": <int> }
     */
    public function submit(Request $request): JsonResponse
    {
        $context = $request->getContext();
        $contextId = $context->getId();

        $submissionId = $request->input('submissionId');
        if (!$submissionId) {
            return response()->json([
                'success' => false,
                'error' => __('plugins.generic.publishToFacebook.post.error.noSubmissionId'),
            ], 400);
        }

        // Load the submission via current repo API
        $submission = Repo::submission()->get((int) $submissionId);
        if (!$submission || $submission->getData('contextId') !== $contextId) {
            return response()->json([
                'success' => false,
                'error' => __('plugins.generic.publishToFacebook.post.error.notFound'),
            ], 404);
        }

        // Verify submission is published
        $publication = $submission->getCurrentPublication();
        if (!$publication || !$publication->getData('datePublished')) {
            return response()->json([
                'success' => false,
                'error' => __('plugins.generic.publishToFacebook.post.error.notPublished'),
            ], 400);
        }

        // Check for duplicate posting
        $postLogDAO = app(PostLogDAO::class);
        if ($postLogDAO->hasExistingPost((int) $submissionId, $contextId)) {
            return response()->json([
                'success' => false,
                'error' => __('plugins.generic.publishToFacebook.post.alreadyPosted'),
            ], 409);
        }

        // Get configured settings
        $pageId = $this->plugin->getSetting($contextId, Constants::PAGE_ID);
        $accessToken = $this->plugin->getSetting($contextId, Constants::ACCESS_TOKEN);
        $messageFormat = $this->plugin->getSetting($contextId, Constants::MESSAGE_FORMAT_ARTICLE);

        if (empty($pageId) || empty($accessToken)) {
            return response()->json([
                'success' => false,
                'error' => __('plugins.generic.publishToFacebook.post.error.notConfigured'),
            ], 400);
        }

        // Build the message from the configured format
        $builder = new PublicationPostBuilder($submission, $context, $messageFormat ?: '');
        $message = $builder->buildMessage();
        $articleUrl = $builder->getArticleUrl();

        // Post to Facebook
        $service = new FacebookService();
        $result = $service->postLink($pageId, $accessToken, $message, $articleUrl);

        // Log the post attempt
        $postLog = new PostLog();
        $postLog->setData('submissionId', (int) $submissionId);
        $postLog->setData('contextId', $contextId);
        $postLog->setData('message', $message);
        $postLog->setData('link', $articleUrl);
        $postLog->setData('datePosted', Carbon::now()->format('Y-m-d H:i:s'));

        if ($result['success']) {
            $postLog->setData('status', PostLog::STATUS_SUCCESS);
            $postLog->setData('facebookPostId', $result['postId']);
            $postLogDAO->insert($postLog);

            return response()->json([
                'success' => true,
                'postId' => $result['postId'],
                'message' => __('plugins.generic.publishToFacebook.post.success'),
            ]);
        }

        $postLog->setData('status', PostLog::STATUS_ERROR);
        $postLog->setData('errorMessage', $result['error']);
        $postLogDAO->insert($postLog);

        return response()->json([
            'success' => false,
            'error' => $result['error'],
            'code' => $result['code'] ?? null,
        ], 500);
    }

    /**
     * GET handler — returns the latest post log for a submission.
     *
     * URL: /publishToFacebookPost/history/{submissionId}
     */
    public function history(Request $request, int $submissionId): JsonResponse
    {
        $contextId = $request->getContext()->getId();

        $postLogDAO = app(PostLogDAO::class);
        $log = $postLogDAO->getBySubmissionAndContext($submissionId, $contextId);

        if (!$log) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'status' => $log->getData('status'),
            'facebookPostId' => $log->getData('facebookPostId'),
            'errorMessage' => $log->getData('errorMessage'),
            'datePosted' => $log->getData('datePosted'),
        ]);
    }
}
