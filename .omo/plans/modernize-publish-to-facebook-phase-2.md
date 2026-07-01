# modernize-publish-to-facebook-phase-2 - Work Plan

## TL;DR (For humans)

**What you'll get:** A settings page accessible from the plugin list. You can configure Facebook Page ID, Access Token (password-masked), default message templates for articles/issues, and auto-publish toggles.

**Why this approach:** OJS 3.5 uses a Vue.js-based Form API with FormComponent and PluginSettingsController. No Smarty templates needed — the settings form renders as a modal dialog via VueModal('PkpFormModal'). This matches the OJS 3.5 main-branch pluginTemplate pattern exactly.

**What it will NOT do:** No Facebook API calls (Phase 3), no hooks for auto-publishing (Phase 5), no encryption of the stored token (enhancement), no logging/duplicate prevention (Phase 4).

**Effort:** Short
**Risk:** Low — standard OJS settings form, no new external integration
**Decisions to sanity-check:** Setting key names, validation rules, VueModal config URL

**Current implementation checkpoint:** Wave 1 is already created and verified in the working tree: `locale/en_US/locale.po`, `src/classes/Constants.php`, and `src/formRequests/EditSettingsRequest.php`. Remaining implementation is Wave 2 (`SettingsForm.php`), Wave 3 (`SettingsController.php`), Wave 4 (`PublishToFacebookPlugin.php` integration), then the final verification wave.

Your next move: Approve, then `$start-work` for execution.

---

> TL;DR (machine): Short | Low | 6 files total (3 already created, 2 remaining CREATE, 1 remaining MODIFY) | OJS 3.5.x+ FormComponent + PluginSettingsController

## Reference decision
- Target version: **OJS 3.5.x+**.
- Chosen reference: direct pkp/pluginTemplate main-branch / OJS 3.5 pattern — `FormComponent`, `PluginSettingsController`, Laravel `FormRequest`, `VueModal('PkpFormModal')`, and `Hook::add('APIHandler::endpoints::plugin', ...)`.
- Rejected reference for this phase: older Smarty/`PKP\form\Form`/`manage()` examples, including stale examples returned by general OJS documentation searches. Those are OJS 3.4-era patterns and must not be used for this OJS 3.5.x+ implementation.

## Scope
### Must have
- Settings form with 6 fields: Page ID (text), Access Token (password), Article message format (textarea), Issue message format (textarea), Auto-publish articles (checkbox), Auto-publish issues (checkbox)
- Settings persist via `getSetting()`/`updateSetting()` (context-aware, per-journal)
- Settings link in plugin list via `getActions()` returning LinkAction with VueModal('PkpFormModal')
- Form validation via FormRequest (required Page ID + Access Token, string/bool types)
- Token displayed as masked input (password type)
- Controller registered via `Hook::add('APIHandler::endpoints::plugin', ...)` in plugin's `register()`
- Locale file with all required translation keys

### Must NOT have (guardrails, anti-slop, scope boundaries)
- No Facebook API calls — Phase 3
- No automatic posting hooks — Phase 5
- No encryption of stored token — deferred enhancement
- No logging / duplicate prevention — Phase 4
- No Smarty templates — OJS 3.5 FormComponent handles rendering
- No `manage()` method — OJS 3.5 uses PluginSettingsController instead
- No database schema changes — all settings use plugin settings table

## Verification strategy
> Zero human intervention - all verification is agent-executed.
- Test decision: tests-after (syntax check + file completeness + locale cross-references)
- Evidence: .omo/evidence/task-<N>-modernize-publish-to-facebook-phase-2

## Execution strategy
### Current state before resumed execution
- Completed and verified in Wave 1:
  - `locale/en_US/locale.po`
  - `src/classes/Constants.php`
  - `src/formRequests/EditSettingsRequest.php`
- Missing code files:
  - `src/classes/SettingsForm.php`
  - `src/classes/SettingsController.php`
- Existing file still requiring modification:
  - `src/PublishToFacebookPlugin.php` — currently has only `register()`, `getDisplayName()`, and `getDescription()`; it does not yet expose settings UI or register the settings API controller.

### Parallel execution waves
- Wave 1 (3 parallel): locale, Constants, FormRequest — fully independent
- Wave 2 (1 task): SettingsForm — depends on Constants + locale
- Wave 3 (1 task): SettingsController — depends on SettingsForm
- Wave 4 (1 task): Plugin modification — depends on SettingsController + FormRequest

### Dependency matrix
| Todo | Depends on | Blocks | Can parallelize with |
| --- | --- | --- | --- |
| 1. Locale | — | — | 2, 5 |
| 2. Constants | — | 3 | 1, 5 |
| 3. SettingsForm | 2 | 4 | — |
| 4. SettingsController | 3 | 6 | — |
| 5. FormRequest | — | 6 | 1, 2 |
| 6. Plugin modification | 4, 5 | Final wave | — |

## Todos

### Wave 1 — Independent base files

- [x] 1. Create `locale/en_US/locale.po`
  What to do / Must NOT do: Write a `.po` file with correct headers and all required msgid/msgstr pairs. Do not include keys for other locales.
  Parallelization: Wave 1 | Blocked by: — | Blocks: —
  References (executor has NO interview context - be exhaustive):
  - Target path: `locale/en_US/locale.po`
  - Phase 1 already created `locale/en_US/` directory (empty)
  - OJS 3.5 uses `.po` format for locale files
  **Headers:**
  ```
  msgid ""
  msgstr ""
  "Project-Id-Version: publishToFacebook 1.0.0.0\n"
  "Language: en_US\n"
  "MIME-Version: 1.0\n"
  "Content-Type: text/plain; charset=utf-8\n"
  "Content-Transfer-Encoding: 8bit\n"
  ```
  **Required locale keys:**
  | Key | Value |
  |-----|-------|
  | `plugins.generic.publishToFacebook.displayName` | "Publish to Facebook" |
  | `plugins.generic.publishToFacebook.description` | "Publish article or issue links to a configured Facebook Page." |
  | `plugins.generic.publishToFacebook.settings.pageId` | "Facebook Page ID" |
  | `plugins.generic.publishToFacebook.settings.accessToken` | "Facebook Page Access Token" |
  | `plugins.generic.publishToFacebook.settings.messageFormat.article` | "Default article message format" |
  | `plugins.generic.publishToFacebook.settings.messageFormat.issue` | "Default issue message format" |
  | `plugins.generic.publishToFacebook.settings.autoPublishArticles` | "Automatically publish newly published articles" |
  | `plugins.generic.publishToFacebook.settings.autoPublishIssues` | "Automatically publish newly published issues" |
  | `plugins.generic.publishToFacebook.settings.pageId.help` | "The numeric ID of the Facebook Page to post to." |
  | `plugins.generic.publishToFacebook.settings.accessToken.help` | "A long-lived Facebook Page Access Token with publish_pages permission." |
  | `plugins.generic.publishToFacebook.settings.messageFormat.article.help` | "Available placeholders: {$articleTitle}, {$articleUrl}, {$journalName}" |
  | `plugins.generic.publishToFacebook.settings.messageFormat.issue.help` | "Available placeholders: {$issueTitle}, {$issueUrl}, {$journalName}" |
  Acceptance criteria (agent-executable): `head -30 locale/en_US/locale.po` — verify 13 msgid/msgstr pairs present
  QA scenarios:
  - Happy: `grep -c '^msgid' locale/en_US/locale.po` — returns 13
  - Failure: `php -r 'echo file_get_contents("locale/en_US/locale.po");'` — file readable, valid .po structure
  Commit: `Y` | `feat(locale): add settings locale keys`

- [x] 2. Create `src/classes/Constants.php`
  What to do / Must NOT do: Create a final class with setting key string constants. No methods, no traits, no logic. Namespace: `APP\plugins\generic\publishToFacebook\classes`
  Parallelization: Wave 1 | Blocked by: — | Blocks: 3
  References: `src/classes/` directory exists (empty)
  **Content:**
  ```php
  <?php

  /**
   * @file plugins/generic/publishToFacebook/src/classes/Constants.php
   *
   * @class Constants
   *
   * @brief Plugin setting key constants.
   */

  namespace APP\plugins\generic\publishToFacebook\classes;

  final class Constants
  {
      public const PAGE_ID = 'pageId';
      public const ACCESS_TOKEN = 'accessToken';
      public const MESSAGE_FORMAT_ARTICLE = 'messageFormatArticle';
      public const MESSAGE_FORMAT_ISSUE = 'messageFormatIssue';
      public const AUTO_PUBLISH_ARTICLES = 'autoPublishArticles';
      public const AUTO_PUBLISH_ISSUES = 'autoPublishIssues';
  }
  ```
  Acceptance criteria: `php -l src/classes/Constants.php` exits with code 0
  QA scenarios:
  - Happy: `php -l src/classes/Constants.php` — "No syntax errors"
   - Failure: Verify class is final — `grep -q 'final class Constants' src/classes/Constants.php`
  Commit: `Y` | `feat(classes): add setting key constants`

- [x] 5. Create `src/formRequests/EditSettingsRequest.php`
  What to do / Must NOT do: Create FormRequest class extending `Illuminate\Foundation\Http\FormRequest`. Validation: pageId and accessToken required|string|max:255, message fields nullable|string|max:1000, auto-publish nullable|boolean. authorize() returns true. Namespace: `APP\plugins\generic\publishToFacebook\formRequests`. **Must import Constants** from `APP\plugins\generic\publishToFacebook\classes`.
  Parallelization: Wave 1 | Blocked by: — | Blocks: 6
  References:
  - OJS 3.5 uses Laravel-style FormRequest for validation
  - pkp/pluginTemplate main branch uses `formRequests/` directory for form request classes
  - Constants are in `src/classes/Constants.php` namespace `APP\plugins\generic\publishToFacebook\classes`
  **Content:**
  ```php
  <?php

  /**
   * @file plugins/generic/publishToFacebook/src/formRequests/EditSettingsRequest.php
   *
   * @class EditSettingsRequest
   *
   * @brief Form request for validating and saving plugin settings.
   */

  namespace APP\plugins\generic\publishToFacebook\formRequests;

  use APP\plugins\generic\publishToFacebook\classes\Constants;
  use Illuminate\Foundation\Http\FormRequest;

  class EditSettingsRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              Constants::PAGE_ID => 'required|string|max:255',
              Constants::ACCESS_TOKEN => 'required|string|max:255',
              Constants::MESSAGE_FORMAT_ARTICLE => 'nullable|string|max:1000',
              Constants::MESSAGE_FORMAT_ISSUE => 'nullable|string|max:1000',
              Constants::AUTO_PUBLISH_ARTICLES => 'nullable|boolean',
              Constants::AUTO_PUBLISH_ISSUES => 'nullable|boolean',
          ];
      }
  }
  ```
  Acceptance criteria: `php -l src/formRequests/EditSettingsRequest.php` exits with code 0
  QA scenarios:
  - Happy: `php -l src/formRequests/EditSettingsRequest.php` — "No syntax errors"
  - Failure: Verify imports Constants — `grep -q 'use .*Constants' src/formRequests/EditSettingsRequest.php`
  - Failure: Verify correct namespace — `head -12 src/formRequests/EditSettingsRequest.php | grep -q 'formRequests'`
  Commit: `Y` | `feat(formRequests): add settings form request validation`

### Wave 2 — SettingsForm (FormComponent)

- [ ] 3. Create `src/classes/SettingsForm.php`
  What to do / Must NOT do: Create FormComponent extending `PKP\components\forms\FormComponent`. Contains all 6 fields. Namespace: `APP\plugins\generic\publishToFacebook\classes`. **Must NOT** extend `PKP\form\Form` (old OJS 3.4 pattern). **Must NOT** include Smarty fetch()/initData(). **Must NOT** call `parent::__construct()` — set `$this->action` directly. Must declare `$id` and `$method` as class properties. Constructor takes only `$action` (string).
  Parallelization: Wave 2 | Blocked by: 2 | Blocks: 4
  References:
  - Constants.php from TODO 2 provides setting key constants
  - Available field types: `PKP\components\forms\fields\FieldText`, `PKP\components\forms\fields\FieldTextarea`, `PKP\components\forms\fields\FieldOptions`
  - FieldText supports `inputType='password'` for masked input
  - FieldOptions with `type => 'checkbox'` creates single checkbox
  - OJS 3.5 FormComponent pattern: `$id` + `$method` as class properties, no parent constructor call, `$this->action = $action`
  - Locale keys for labels/descriptions from TODO 1
  **Content:**
  ```php
  <?php

  /**
   * @file plugins/generic/publishToFacebook/src/classes/SettingsForm.php
   *
   * @class SettingsForm
   *
   * @brief OJS 3.5 Vue.js FormComponent for plugin settings.
   */

  namespace APP\plugins\generic\publishToFacebook\classes;

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
              'isRequired' => true,
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
  ```
  Acceptance criteria: `php -l src/classes/SettingsForm.php` exits with code 0
  QA scenarios:
  - Happy: `php -l src/classes/SettingsForm.php` — "No syntax errors"
  - Failure: Verify extends FormComponent (not Form) — `grep -q 'extends FormComponent' src/classes/SettingsForm.php`
  - Failure: Verify no Smarty/FBV functions — `grep -c 'fbv' src/classes/SettingsForm.php` returns 0
  Commit: `Y` | `feat(classes): implement Vue FormComponent for settings`

### Wave 3 — SettingsController

- [ ] 4. Create `src/classes/SettingsController.php`
  What to do / Must NOT do: Create PluginSettingsController extending `PKP\plugins\PluginSettingsController`. Implements `get(Request): JsonResponse` (reads settings, returns form config) and `edit(EditSettingsRequest): JsonResponse` (validates and saves). Also `getHandlerPath(): string`. Namespace: `APP\plugins\generic\publishToFacebook\classes`.
  Parallelization: Wave 3 | Blocked by: 3 | Blocks: 6
  References:
  - SettingsForm from TODO 3 provides form rendering
  - EditSettingsRequest from TODO 5 provides validation (imported from `APP\plugins\generic\publishToFacebook\formRequests`)
  - Constants from TODO 2 provides setting key names
  - `getSetting($contextId, $key)` / `updateSetting($contextId, $key, $value)` on plugin
  - `$this->plugin` available from parent constructor
  - Context from request: use `$this->getRequest()->getContext()->getId()` (reference pattern, more reliable in API context)
  - `getApiUrl($request)` helper from parent PluginSettingsController (builds API URL)
  **Content:**
  ```php
  <?php

  /**
   * @file plugins/generic/publishToFacebook/src/classes/SettingsController.php
   *
   * @class SettingsController
   *
   * @brief OJS 3.5 plugin settings controller for the Publish to Facebook plugin.
   */

  namespace APP\plugins\generic\publishToFacebook\classes;

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
  ```
  Acceptance criteria: `php -l src/classes/SettingsController.php` exits with code 0
  QA scenarios:
  - Happy: `php -l src/classes/SettingsController.php` — "No syntax errors"
  - Failure: Verify extends PluginSettingsController — `grep -q 'extends PluginSettingsController' src/classes/SettingsController.php`
  Commit: `Y` | `feat(classes): implement settings API controller`

### Wave 4 — Plugin modification (final code task)

- [ ] 6. Modify `src/PublishToFacebookPlugin.php`
  What to do / Must NOT do: Add controller registration via `Hook::add('APIHandler::endpoints::plugin', ...)` inside `register()` after `$this->getEnabled()`. Add `getActions()` using `VueModal('PkpFormModal')`. **Must NOT** add `manage()` method (OJS 3.5 pattern uses controller, not manage). **Must NOT** modify existing `getDisplayName()` or `getDescription()`.
  Parallelization: Wave 4 | Blocked by: 4, 5 | Blocks: Final wave
  References:
  - Current file: `src/PublishToFacebookPlugin.php` (54 lines, GenericPlugin subclass)
  - SettingsController from TODO 4 at `APP\plugins\generic\publishToFacebook\classes\SettingsController`
  - SettingsForm from TODO 3 at `APP\plugins\generic\publishToFacebook\classes\SettingsForm`
  - Hook pattern (verified from pkp/pluginTemplate main branch): `Hook::add('APIHandler::endpoints::plugin', function(string $hookName, APIRouter $apiRouter): bool { $apiRouter->registerPluginApiControllers([$this->controller]); return Hook::CONTINUE; })`
  - VueModal import path (verified from pkp/pluginTemplate main branch): `PKP\linkAction\request\VueModal`
  - LinkAction: `PKP\linkAction\LinkAction`
  - APIRouter import: `PKP\core\APIRouter`
  - Hook import: `PKP\plugins\Hook`
  - VueModal constructor: `new VueModal(string $type, array $args)` — first arg is the type string (not an id). The id `'publishToFacebookSettings'` goes inside the args array as `'id'`.
  - getApiUrl: use `$this->controller->getHandlerPath()` instead of hardcoded string
  - `array_unshift($actions, ...)` to place settings button first in plugin list
  **New code to ADD (merge with existing file):**
  ```php
  // === NEW IMPORTS (add after existing use statements) ===
  use APP\plugins\generic\publishToFacebook\classes\SettingsController;
  use APP\plugins\generic\publishToFacebook\classes\SettingsForm;
  use PKP\core\APIRouter;
  use PKP\linkAction\LinkAction;
  use PKP\linkAction\request\VueModal;
  use PKP\plugins\Hook;

  // === NEW PROPERTY (add inside class body) ===
  private SettingsController $controller;

  // === MODIFIED register() — add after $this->getEnabled() check ===
  if ($this->getEnabled($mainContextId)) {
      $this->controller = new SettingsController($this);
      Hook::add('APIHandler::endpoints::plugin', function (string $hookName, APIRouter $apiRouter): bool {
          $apiRouter->registerPluginApiControllers([
              $this->controller,
          ]);
          return Hook::CONTINUE;
      });
  }

  // === NEW getActions() method ===
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
          \APP\core\Application::ROUTE_API,
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
  ```
  Acceptance criteria: `php -l src/PublishToFacebookPlugin.php` exits with code 0, and `grep -q 'getActions' src/PublishToFacebookPlugin.php` succeeds
  QA scenarios:
  - Happy: `php -l src/PublishToFacebookPlugin.php` — "No syntax errors"
  - Happy: `grep -c 'VueModal' src/PublishToFacebookPlugin.php` — at least 1 occurrence
  - Failure: Verify NO `manage()` method — `grep -c 'function manage' src/PublishToFacebookPlugin.php` returns 0
  - Failure: Verify NO `{fbv` Smarty references — `grep -c 'fbv' src/PublishToFacebookPlugin.php` returns 0
  Commit: `Y` | `feat(plugin): add Vue settings modal and controller registration`

## Final verification wave
> Runs in parallel after ALL todos. ALL must APPROVE.
- [ ] F1. Syntax check: `php -l` on all PHP files — src/PublishToFacebookPlugin.php, src/classes/Constants.php, src/classes/SettingsForm.php, src/classes/SettingsController.php, src/formRequests/EditSettingsRequest.php
- [ ] F2. File completeness: Verify all 6 files exist (5 created + 1 modified)
- [ ] F3. Locale consistency: Extract `__('plugins.generic.publishToFacebook.'` references from all PHP files, verify each key exists in locale/en_US/locale.po
- [ ] F4. No-regression check: Ensure `templates/` directory unchanged (no files created), `manage()` not added to plugin, no Facebook API calls
- [ ] F5. Git diff: `git diff --stat` shows only intended files changed (5 new, 1 modified)

## Commit strategy
Atomic commits, one per remaining todo, in dependency order. Wave 1 files may already be present in the working tree; do not duplicate them or rewrite them unless verification fails.
1. `feat(locale): add settings locale keys`
2. `feat(classes): add setting key constants`
3. `feat(formRequests): add settings form request validation`
4. `feat(classes): implement Vue FormComponent for settings`
5. `feat(classes): implement settings API controller`
6. `feat(plugin): add Vue settings modal and controller registration`

## Success criteria
- All 6 todos marked complete
- Final verification wave passes all 5 checks
- `.omo/start-work/ledger.jsonl` contains evidence for all tasks
