# Publish to Facebook — OJS 3.5+ Plugin

**Version:** 1.0.4.0  
**Author:** Munir Abbasi  
**Website:** [syntaxhouse.com](https://syntaxhouse.com)  
**GitHub:** [munir-abbasi](https://github.com/munir-abbasi/)  
**License:** GNU General Public License v3  
**OJS Compatibility:** 3.5.0+

---

## Overview

Publish to Facebook is a [PKP](https://pkp.sfu.ca) [Open Journal Systems](https://pkp.sfu.ca/ojs/) (OJS) generic plugin that enables journal managers and site administrators to publish article links to a configured Facebook Page.

The plugin supports **automatic** posting (on article or issue publication) with full duplicate prevention, error logging, and retry capability. Manual posting via API is also available for programmatic use.

---

## Features

| Feature | Description |
|---|---|
| **Auto-posting (articles)** | Automatically post when an article is published (toggle in settings) |
| **Auto-posting (issues)** | Automatically post when an issue is published from the issue grid |
| **Duplicate prevention** | Prevents the same article or issue from being posted twice |
| **Error logging** | Logs all post attempts (success and failure) with timestamps |
| **API endpoint** | POST endpoint for programmatic manual posting |
| **Status API** | GET endpoint for checking post status and retrying |
| **Settings UI** | Vue-based settings panel integrated via OJS 3.5 modal |
| **Custom message format** | Configurable message templates with placeholders |
| **Safe URL building** | Uses OJS dispatcher for canonical article URLs |

---

## Requirements

- OJS 3.5.0 or later
- PHP 8.3 or later
- A Facebook Page with a long-lived Page Access Token authorized to publish to that Page
- Facebook App permissions required by the current Meta Graph API for Page publishing. Verify the exact permission set in Meta's current documentation before production use.
- Server with `allow_url_fopen` or `curl` enabled

---

## Installation

1. **Download** the plugin into `plugins/generic/publishToFacebook/` of your OJS installation.

2. **Enable** the plugin in OJS:
   - Go to **Website Settings → Plugins → Generic Plugin List**
   - Find **Publish to Facebook** and click **Enable**

3. **Run the migration** (table creation happens automatically on plugin upgrade):
   - Go to **Administration → System Info → Expire User Sessions & Upgrade**
   - This triggers the `PostLogMigration` that creates the `publish_to_facebook_post_logs` table

4. **Configure** the plugin (see below).

---

## Configuration

Go to **Website Settings → Plugins → Publish to Facebook → Settings** (gear icon).

| Setting | Description |
|---|---|
| **Facebook Page ID** | The numeric ID of your Facebook Page |
| **Facebook Page Access Token** | A long-lived Page Access Token authorized to publish to the configured Page |
| **Default article message format** | Message template for article posts (see placeholders below) |
| **Default issue message format** | Message template for issue posts |
| **Auto-publish articles** | When enabled, newly published articles are posted automatically |
| **Auto-publish issues** | When enabled, published issues are posted automatically |

### Article Message Format Placeholders

| Placeholder | Description |
|---|---|
| `{$articleTitle}` | The article title |
| `{$articleUrl}` | The canonical article URL (built via OJS dispatcher) |
| `{$journalName}` | The journal/press name |

**Default article format:**
```
New article published: {$articleTitle}
{$articleUrl}

{$journalName}
```

### Issue Message Format Placeholders

| Placeholder | Description |
|---|---|
| `{$issueTitle}` | The issue title |
| `{$volume}` | Issue volume number |
| `{$number}` | Issue number |
| `{$year}` | Issue year |
| `{$datePublished}` | Issue publication date |
| `{$issueUrl}` | The canonical issue URL (built via OJS dispatcher) |
| `{$journalName}` | The journal/press name |

**Default issue format:**
```
New issue published: {$issueTitle}
{$issueUrl}
```

---

## Usage

### Automatic Article Posting

When enabled in settings, any article that becomes published (status set to `STATUS_PUBLISHED`) is automatically posted to Facebook. Auto-posting:

- Runs after the publication workflow completes
- Never blocks the publication process (failures are logged silently)
- Respects duplicate prevention (already-posted articles are skipped)

### Automatic Issue Posting

When enabled in settings, any issue published via the issue grid handler is automatically posted to Facebook. Auto-posting:

- Fires when the **Publish** action is taken on an issue in **Issues → Future Issues**
- Never blocks the publishing workflow (failures are logged silently)
- Respects duplicate prevention (already-posted issues are skipped)

---

## File Structure

```
plugins/generic/publishToFacebook/
├── index.php                          # Plugin loader
├── PublishToFacebookPlugin.php        # Main plugin class (hooks, registration)
├── PostController.php                 # API controller (post, history, retry)
├── SettingsController.php             # Settings UI controller
├── SettingsForm.php                   # Vue FormComponent settings form
├── version.xml                        # Plugin version metadata
├── phpunit.xml                        # PHPUnit configuration
├── classes/
│   ├── Constants.php                  # Setting key constants
│   ├── FacebookService.php            # Facebook Graph API client
│   ├── PostLog.php                    # PostLog data object
│   ├── PostLogDAO.php                 # PostLog CRUD + dedup queries
│   ├── IssuePostBuilder.php           # Issue message + URL builder
│   ├── PublicationPostBuilder.php     # Article message + URL builder
│   └── migrations/
│       └── PostLogMigration.php       # Database migration
├── docs/
│   └── architectural-decisions.md     # Historical design notes (archival)
├── formRequests/
│   └── EditSettingsRequest.php        # Settings form validation
├── locale/
│   └── en_US/
│       └── locale.po                  # English locale strings
├── schema/
│   └── log.json                       # PostLog JSON schema
└── tests/
    ├── bootstrap.php                  # Test bootstrap
    ├── Unit/
    │   ├── ConstantsTest.php          # Constants unit tests
    │   └── PostLogTest.php            # PostLog DataObject unit tests
    └── Integration/
        └── PostLogDAOTest.php         # PostLogDAO integration tests
```

---

## Architecture

### Component Diagram

```
PublishToFacebookPlugin
│
├── register()
│   ├── Hook::add(APIHandler::endpoints::plugin)   [register API controllers]
│   ├── Hook::add(Schema::get::postLog)            [register custom schema]
│   ├── addAutoPublishHook()                        [article auto-pub]
│   └── addAutoPublishIssueHook()                   [issue auto-pub]
│
├── SettingsController  ──> EditSettingsRequest ──> Constants
│
├── PostController (manual article posts; API group: publishToFacebookPost)
│   ├── POST /                         # Submit to Facebook; body includes submissionId
│   ├── GET  /history/{submissionId}   # Get post status
│   │
│   ├── FacebookService                # Graph API call
│   ├── PublicationPostBuilder         # Article message + URL
│   └── PostLogDAO                     # Persistence + dedup
│
└── Auto hook handlers
    ├── addAutoPublishHook()
    │   ├── PublicationPostBuilder     # Article message + URL
    │   ├── FacebookService            # Graph API call
    │   └── PostLogDAO                 # Persistence + dedup
    │
    └── addAutoPublishIssueHook()
        ├── IssuePostBuilder           # Issue message + URL
        ├── FacebookService            # Graph API call
        └── PostLogDAO                 # Persistence + dedup
```

### Data Flow (API-Triggered Manual Post)

```
[POST /{contextPath}/api/{version}/publishToFacebookPost]
       │
       ▼
PostController::submit()
       │
       ├── Read submissionId from request body
       ├── PublicationPostBuilder::buildMessage()
       ├── PostLogDAO::hasExistingPost()  ──► 409 if duplicate
       ├── FacebookService::postLink()
       │       └── Graph API POST /{pageId}/feed
       ├── PostLogDAO::insert() (success or error)
       └── Returns JSON response
```

### Data Flow (Auto-Post Article)

```
[Publication::publish hook fires]
       │
       ▼
PublishToFacebookPlugin::addAutoPublishHook()
       │
       ├── Check autoPublishArticles setting
       ├── PostLogDAO::hasExistingPost()  ──► skip if duplicate
       ├── PublicationPostBuilder::buildMessage()
       ├── FacebookService::postLink()
       ├── PostLogDAO::insert() (success or error)
       └── Never blocks publication
```

### Data Flow (Auto-Post Issue)

```
[IssueGridHandler::publishIssue hook fires]
       │
       ▼
PublishToFacebookPlugin::addAutoPublishIssueHook()
       │
       ├── Check autoPublishIssues setting
       ├── PostLogDAO::hasExistingIssuePost($issueId, $contextId)  ──► skip if duplicate
       ├── IssuePostBuilder::buildMessage()
       ├── IssuePostBuilder::getIssueUrl()
       │       └── dispatcher->url() ──► issue/view/{bestIssueId}
       ├── FacebookService::postLink()
       ├── PostLogDAO::insert() (submissionId=null, contextId set)
       └── Catches plugin exceptions so Facebook failures do not abort issue publication
```

> **Workflow note:** In OJS 3.5, `IssueGridHandler::publishIssue` fires before the final `Repo::issue()->updateCurrent($contextId, $issue)` call. The plugin catches its own failures, but the Facebook post/log side effect is not transactional with OJS issue persistence. If a later OJS issue update fails, the Facebook post log may be ahead of the final OJS issue state.

---

## Database

### `publish_to_facebook_post_logs`

| Column | Type | Description |
|---|---|---|
| `post_log_id` | bigint (PK) | Auto-increment primary key |
| `submission_id` | bigint | OJS submission ID (nullable; `null` for issue posts) |
| `issue_id` | bigint | OJS issue ID (nullable; `null` for article posts) |
| `context_id` | bigint | Journal/context ID |
| `status` | varchar(20) | `success` or `error` |
| `facebook_post_id` | varchar(255) | Facebook Graph API post ID (nullable) |
| `message` | text | The message that was posted (nullable) |
| `error_message` | text | Error details on failure (nullable) |
| `link` | varchar(2048) | The posted URL (nullable) |
| `date_posted` | datetime | When the post attempt occurred |

**Indexes:**
- `post_logs_context_submission_idx` on `(context_id, submission_id)`
- `post_logs_context_issue_idx` on `(context_id, issue_id)`
- `post_logs_status_idx` on `(status)`

---

## API Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/{contextPath}/api/{version}/publishToFacebookPost` | Post submission to Facebook. Include `submissionId` in the request body. |
| `GET` | `/{contextPath}/api/{version}/publishToFacebookPost/history/{submissionId}` | Get latest post log for submission |

The settings endpoint is registered under the `publishToFacebook` API group. Manual posting/history endpoints are registered under the `publishToFacebookPost` API group. The manual posting/history controller requires a logged-in user in the current context with the OJS manager role, or a site administrator.

> **Note:** In OJS 3.5, the submission details page uses Vue.js, so the legacy manual posting button (via `Templates::Submission::SubmissionDetails::Main` hook) is not available. Manual posting is accessible via the API endpoint. Auto-posting via hooks remains fully functional.

---

## Local Development

### Setup

1. **Clone** the plugin into your OJS installation:
   ```bash
   cd /path/to/ojs
   git clone https://github.com/munir-abbasi/OJSOnline-publishToFacebook.git \
     plugins/generic/publishToFacebook
   ```

2. **Enable** the plugin in OJS: **Website Settings → Plugins → Generic Plugin List**.

3. **Run migrations**: **Administration → System Info → Expire User Sessions & Upgrade** (triggers `PostLogMigration` to create the `publish_to_facebook_post_logs` table).

### Syntax Checking

```bash
find plugins/generic/publishToFacebook -name '*.php' -exec php -l {} \;
```

All PHP files must pass `php -l` without errors or warnings.

### Running Tests

Tests require OJS to be bootstrapped with a test database. From the OJS root:

```bash
php lib/pkp/vendor/bin/phpunit \
  -c plugins/generic/publishToFacebook/phpunit.xml
```

**Test suites:**

| Suite | File | What it covers |
|---|---|---|
| Unit | `tests/Unit/ConstantsTest.php` | Setting key constants (no OJS deps) |
| Unit | `tests/Unit/PostLogTest.php` | PostLog DataObject getters/setters |
| Integration | `tests/Integration/PostLogDAOTest.php` | PostLogDAO DB queries (requires test DB) |

**Note:** The Constants test is self-contained and can run without OJS bootstrapping if the plugin namespace is autoloaded. The PostLog and DAO tests require OJS framework.

### Migration Rollback and Uninstall Safety

Rolling back the plugin migration drops the `publish_to_facebook_post_logs` table and permanently deletes all post history (success logs, Facebook post IDs, error records). The upgrade rollback that removes `issue_id` also removes the issue-post linkage from historical rows. Do not run destructive rollback/uninstall operations on production data without an explicit database backup, operator approval, and a restore plan.

### Code Style

- Namespace: `APP\plugins\generic\publishToFacebook`
- PHP 8.0+ strict types where applicable
- PSR-4 class loading via namespace
- Use `$this->plugin->getSetting()` / `updateSetting()` for context-scoped settings
- Use `__()` locale keys, never hardcoded strings
- Use OJS `dispatcher->url()` for canonical URLs
- Never let external API failures block OJS publication workflows

---

## Development & Contribution

1. Fork the repository.
2. Create a feature branch.
3. Make changes following OJS 3.5+ plugin conventions and the code style guide above.
4. Run syntax check and tests before committing.
5. Submit a pull request.

---

## Known Limitations

- **Manual posting button**: The "Publish to Facebook" button on the submission detail page does not appear in OJS 3.5 because the submission details page is now rendered by Vue.js. The legacy `Templates::Submission::SubmissionDetails::Main` hook no longer fires. Manual posting is available via `POST /{contextPath}/api/{version}/publishToFacebookPost` with `submissionId` in the request body.
- **Vue component approach**: A Vue component to restore the manual posting button is planned for a future release.
- **Runtime verification**: Static compatibility checks have passed, but production readiness still requires install/enable/settings/API/hook smoke tests in a real OJS 3.5 runtime.

---

## Version History

| Version | Date | Changes |
|---|---|---|
| 1.0.4.0 | 2026-07-02 | Fix OJS 3.5 compatibility: remove broken template hook, fix EntityDAO fromRow() type error, update documentation |
| 1.0.3.0 | 2026-07-02 | Hardened posting logs and plugin migrations |
| 1.0.2.0 | 2026-07-01 | Issue auto-posting (IssuePostBuilder, IssueGridHandler hook) |
| 1.0.1.0 | 2026-07-01 | PostLog migration, auto-posting, retry/status display, PublicationPostBuilder |
| 1.0.0.0 | — | Initial modernization (namespace, settings, manual post, FacebookService) |

---

## License

This plugin is distributed under the GNU General Public License v3. See the `docs/COPYING` file in your OJS installation for the full license text.
