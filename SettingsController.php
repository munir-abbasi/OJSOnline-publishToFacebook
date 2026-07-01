<?php

/**
 * @file plugins/generic/publishToFacebook/SettingsController.php
 *
 * @class SettingsController
 *
 * @brief OJS 3.5 plugin settings controller for the Publish to Facebook plugin.
 */

namespace APP\plugins\generic\publishToFacebook;

use APP\plugins\generic\publishToFacebook\classes\Constants;
use APP\plugins\generic\publishToFacebook\formRequests\EditSettingsRequest;
use PKP\plugins\PluginSettingsController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends PluginSettingsController
{
    /**
     * GET handler — returns the settings form configuration as JSON.
     */
    public function get(Request $request): JsonResponse
    {
        $contextId = $this->getRequest()->getContext()->getId();

        $form = new SettingsForm(
            $this->getApiUrl($request)
        );

        $form->setData(Constants::PAGE_ID, $this->plugin->getSetting($contextId, Constants::PAGE_ID));
        $form->setData(Constants::ACCESS_TOKEN, $this->plugin->getSetting($contextId, Constants::ACCESS_TOKEN));
        $form->setData(Constants::MESSAGE_FORMAT_ARTICLE, $this->plugin->getSetting($contextId, Constants::MESSAGE_FORMAT_ARTICLE));
        $form->setData(Constants::MESSAGE_FORMAT_ISSUE, $this->plugin->getSetting($contextId, Constants::MESSAGE_FORMAT_ISSUE));
        $form->setData(Constants::AUTO_PUBLISH_ARTICLES, (bool) $this->plugin->getSetting($contextId, Constants::AUTO_PUBLISH_ARTICLES));
        $form->setData(Constants::AUTO_PUBLISH_ISSUES, (bool) $this->plugin->getSetting($contextId, Constants::AUTO_PUBLISH_ISSUES));

        return response()->json($form->getConfig());
    }

    /**
     * PUT handler — validates input and persists settings.
     */
    public function edit(EditSettingsRequest $request): JsonResponse
    {
        $contextId = $this->getRequest()->getContext()->getId();

        $this->plugin->updateSetting($contextId, Constants::PAGE_ID, $request->get(Constants::PAGE_ID));
        $this->plugin->updateSetting($contextId, Constants::ACCESS_TOKEN, $request->get(Constants::ACCESS_TOKEN));
        $this->plugin->updateSetting($contextId, Constants::MESSAGE_FORMAT_ARTICLE, $request->get(Constants::MESSAGE_FORMAT_ARTICLE));
        $this->plugin->updateSetting($contextId, Constants::MESSAGE_FORMAT_ISSUE, $request->get(Constants::MESSAGE_FORMAT_ISSUE));
        $this->plugin->updateSetting($contextId, Constants::AUTO_PUBLISH_ARTICLES, (bool) $request->get(Constants::AUTO_PUBLISH_ARTICLES, false));
        $this->plugin->updateSetting($contextId, Constants::AUTO_PUBLISH_ISSUES, (bool) $request->get(Constants::AUTO_PUBLISH_ISSUES, false));

        return response()->json(['success' => true]);
    }

    /**
     * URL path segment for API routing.
     */
    public function getHandlerPath(): string
    {
        return 'publishToFacebook';
    }
}
