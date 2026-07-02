<?php

/**
 * @file plugins/generic/publishToFacebook/SettingsForm.php
 *
 * @class SettingsForm
 *
 * @brief OJS 3.5 Vue.js FormComponent for plugin settings.
 */

namespace APP\plugins\generic\publishToFacebook;

use APP\plugins\generic\publishToFacebook\classes\Constants;
use PKP\components\forms\FormComponent;
use PKP\components\forms\fields\FieldText;
use PKP\components\forms\fields\FieldTextarea;
use PKP\components\forms\fields\FieldOptions;

class SettingsForm extends FormComponent
{
    public $id = 'publishToFacebookSettings';
    public $method = 'PUT';

    public function __construct(string $action)
    {
        $this->action = $action;

        $this->addField(new FieldText(Constants::PAGE_ID, [
            'label' => __('plugins.generic.publishToFacebook.settings.pageId'),
            'description' => __('plugins.generic.publishToFacebook.settings.pageId.help'),
            'size' => 'medium',
            'isRequired' => true,
        ]));

        $this->addField(new FieldText(Constants::ACCESS_TOKEN, [
            'label' => __('plugins.generic.publishToFacebook.settings.accessToken'),
            'description' => __('plugins.generic.publishToFacebook.settings.accessToken.help'),
            'size' => 'large',
            'isRequired' => false,
            'inputType' => 'password',
        ]));

        $this->addField(new FieldTextarea(Constants::MESSAGE_FORMAT_ARTICLE, [
            'label' => __('plugins.generic.publishToFacebook.settings.messageFormat.article'),
            'description' => __('plugins.generic.publishToFacebook.settings.messageFormat.article.help'),
            'size' => 'medium',
            'rows' => 4,
        ]));

        $this->addField(new FieldTextarea(Constants::MESSAGE_FORMAT_ISSUE, [
            'label' => __('plugins.generic.publishToFacebook.settings.messageFormat.issue'),
            'description' => __('plugins.generic.publishToFacebook.settings.messageFormat.issue.help'),
            'size' => 'medium',
            'rows' => 4,
        ]));

        $this->addField(new FieldOptions(Constants::AUTO_PUBLISH_ARTICLES, [
            'label' => __('plugins.generic.publishToFacebook.settings.autoPublishArticles'),
            'type' => 'checkbox',
            'options' => [
                ['value' => true, 'label' => __('plugins.generic.publishToFacebook.settings.autoPublishArticles')],
            ],
        ]));

        $this->addField(new FieldOptions(Constants::AUTO_PUBLISH_ISSUES, [
            'label' => __('plugins.generic.publishToFacebook.settings.autoPublishIssues'),
            'type' => 'checkbox',
            'options' => [
                ['value' => true, 'label' => __('plugins.generic.publishToFacebook.settings.autoPublishIssues')],
            ],
        ]));
    }
}
