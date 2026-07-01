That plugin is an old OJS 2 / early OJS 3 style skeleton. For OJS 3.5.x, the modernization is not just cosmetic. The class loading model, namespace conventions, hook registration style, localization, settings, token handling, and external API behavior all need to change.

The old code:

```php
import("lib.pkp.classes.plugins.GenericPlugin");

class publishToFacebookPlugin extends GenericPlugin {
  function register($category, $path, $mainContextId = null) {
    $success = parent::register($category, $path, $mainContextId);
    if ($success && $this->getEnabled($mainContextId)) {
      // handlers here
    }
    $this->_registerTemplateResource();
    return $success;
  }

  function getDisplayName() {
    return "Publish To Facebook";
  }

  function getDescription() {
    return "Publish Issue or Article to Facebook";
  }
}
```

should become something closer to this for OJS 3.5.x:

```php
<?php

/**
 * @file plugins/generic/publishToFacebook/PublishToFacebookPlugin.php
 *
 * @class PublishToFacebookPlugin
 *
 * @brief Publish article or issue links to a configured Facebook Page.
 */

namespace APP\plugins\generic\publishToFacebook;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class PublishToFacebookPlugin extends GenericPlugin
{
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

        if ($this->getEnabled($mainContextId)) {
            // Register hooks here only after the plugin is enabled.
            // Example:
            // Hook::add('TemplateManager::display', $this->handleTemplateDisplay(...));
            // Hook::add('Publication::publish', $this->handlePublicationPublished(...));
        }

        return true;
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
}
```

The key changes are:

```text
Old import() call removed.
Namespace added.
Class name changed to StudlyCase.
GenericPlugin imported with use PKP\plugins\GenericPlugin.
Hook imported with use PKP\plugins\Hook only when needed.
Methods made public.
Return types added.
Hardcoded English strings replaced with locale keys.
_registerTemplateResource() removed unless the plugin actually provides templates.
```

PKP’s current plugin guide uses namespaced plugin classes under paths like `APP\plugins\generic\exampleCustomPage` and imports `PKP\plugins\GenericPlugin`, so this is the correct modernization direction for current OJS/PKP plugin development. ([PKP Documentation][1])

For a real plugin, you also need a modern plugin folder structure:

```text
plugins/generic/publishToFacebook/
├── index.php
├── PublishToFacebookPlugin.php
├── version.xml
├── locale/
│   └── en/
│       └── locale.po
├── templates/
│   └── settings.tpl
└── classes/
    └── FacebookPublisher.php
```

The `index.php` should be minimal:

```php
<?php

/**
 * @file plugins/generic/publishToFacebook/index.php
 */

require_once('PublishToFacebookPlugin.php');

return new \APP\plugins\generic\publishToFacebook\PublishToFacebookPlugin();
```

The locale file should hold the plugin labels instead of hardcoding them:

```po
msgid "plugins.generic.publishToFacebook.displayName"
msgstr "Publish to Facebook"

msgid "plugins.generic.publishToFacebook.description"
msgstr "Publish article or issue links to a configured Facebook Page."

msgid "plugins.generic.publishToFacebook.settings.pageId"
msgstr "Facebook Page ID"

msgid "plugins.generic.publishToFacebook.settings.accessToken"
msgstr "Facebook Page access token"

msgid "plugins.generic.publishToFacebook.settings.autoPublishArticles"
msgstr "Automatically publish newly published articles"

msgid "plugins.generic.publishToFacebook.settings.autoPublishIssues"
msgstr "Automatically publish newly published issues"
```

But the bigger modernization question is architectural. A 7-year-old “publish to Facebook” plugin likely assumed that posting to Facebook is a simple HTTP request with a stored token. That is no longer enough. A modern version should be designed around these constraints:

```text
1. Do not store Facebook tokens in plain visible settings unless you understand the risk.
2. Do not post automatically without journal-manager-controlled settings.
3. Do not post duplicate articles/issues.
4. Log success/failure.
5. Allow manual publish/retry.
6. Use OJS URL builders, not hardcoded URLs.
7. Use article/issue canonical URLs only.
8. Never post unpublished/private content.
9. Handle API failure without breaking publication workflow.
10. Keep Facebook publishing optional and isolated from article publishing.
```

I would split the plugin into four components:

```text
PublishToFacebookPlugin.php
- Registers plugin, settings form, hooks.

FacebookPublisher.php
- Responsible only for sending posts to Facebook Graph API.

PublicationPostBuilder.php
- Builds safe article/issue post text and canonical URL.

PostLogRepository.php or plugin settings/log table
- Records what has already been posted to avoid duplicates.
```

For OJS 3.5.x, I would avoid writing a “post immediately when article is published” hook first. It is safer to add a manual button or admin action first, then add automatic posting after the manual path is stable. The phased plan should be:

```text
Phase 1: Modernize plugin skeleton.
Phase 2: Add settings page for Page ID, token, default message format, enable/disable flags.
Phase 3: Add manual “Publish to Facebook” action for article/issue pages.
Phase 4: Add duplicate-prevention log.
Phase 5: Add optional automatic posting when an article or issue becomes published.
Phase 6: Add retry/error display.
```

I would also rename the class and folder consistently. PHP class names should not start lowercase:

```text
Bad:
publishToFacebookPlugin

Better:
PublishToFacebookPlugin
```

Folder:

```text
plugins/generic/publishToFacebook/
```

Namespace:

```php
namespace APP\plugins\generic\publishToFacebook;
```

Class:

```php
class PublishToFacebookPlugin extends GenericPlugin
```

The `version.xml` should be updated for your target OJS branch. A placeholder structure would look like this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE version SYSTEM "../../../lib/pkp/dtd/pluginVersion.dtd">

<version>
    <application>publishToFacebook</application>
    <type>plugins.generic</type>
    <release>1.0.0.0</release>
    <date>2026-07-01</date>
    <lazy-load>1</lazy-load>
    <class>PublishToFacebookPlugin</class>
</version>
```

The exact `version.xml` format should be checked against a working OJS 3.5 plugin on your installation, because PKP plugin metadata is strict and a bad `version.xml` can prevent the plugin from loading.

The settings page should not be skipped. For a production plugin, at minimum:

```text
Facebook Page ID
Facebook Page access token
Default post format for articles
Default post format for issues
Enable manual posting
Enable automatic article posting
Enable automatic issue posting
Dry-run/debug mode
```

The post format should use safe placeholders:

```text
New article published: {$articleTitle}
{$articleUrl}

{$journalName}
```

or for issues:

```text
New issue published: {$issueTitle}
{$issueUrl}

{$journalName}
```

Avoid sending abstracts automatically unless the editor explicitly chooses that. Abstracts can be too long, contain HTML, or include sensitive wording.

A modernized post sender should look conceptually like this:

```php
<?php

namespace APP\plugins\generic\publishToFacebook\classes;

class FacebookPublisher
{
    public function publishToPage(string $pageId, string $accessToken, string $message, string $link): array
    {
        $endpoint = 'https://graph.facebook.com/v20.0/' . rawurlencode($pageId) . '/feed';

        $payload = http_build_query([
            'message' => $message,
            'link' => $link,
            'access_token' => $accessToken,
        ]);

        // Use a real HTTP client available in the OJS/PKP stack if one exists.
        // Otherwise use curl with strict timeout and error handling.

        return [
            'success' => false,
            'response' => null,
            'error' => 'Not implemented',
        ];
    }
}
```

But I would not finalize the Facebook API call until checking which HTTP client is available in your OJS 3.5 install. Do not use raw `file_get_contents()` for external posting.

For hooks, there are two possible directions:

```text
Manual/action-based:
Safer. Add a UI action or management page where an editor clicks “Publish to Facebook”.

Automatic/event-based:
More convenient but riskier. Hook into article/issue publication events and queue/post only once.
```

For NJMS, I would strongly prefer manual first. You have already seen how small plugin errors can pollute logs or break pages. A social-posting plugin should never be able to interrupt publication.

The minimum modernization checklist is:

```text
[ ] Namespace the plugin.
[ ] Replace import() with use statements.
[ ] Rename class to PublishToFacebookPlugin.
[ ] Use locale keys instead of hardcoded strings.
[ ] Remove _registerTemplateResource() unless templates are actually used.
[ ] Add strict public methods and return types.
[ ] Add version.xml compatible with OJS 3.5.
[ ] Add settings form.
[ ] Store settings context-specifically.
[ ] Avoid plaintext token exposure in templates/logs.
[ ] Use OJS URL dispatcher for article/issue URLs.
[ ] Add duplicate-prevention.
[ ] Add logging.
[ ] Add manual posting before automatic posting.
[ ] Never let Facebook API failure break OJS publication.
```

So the skeleton can be modernized immediately, but the plugin’s real functionality should be redesigned rather than merely ported. The old code is only a shell; the dangerous part will be token handling, hook selection, duplicate prevention, and failure isolation.

[1]: https://docs.pkp.sfu.ca/dev/plugin-guide/en/examples-custom-page "Add Custom Page - Plugin Guide for OJS, OMP and OPS"
