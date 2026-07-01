# Publish to Facebook — OJS 3.5+ Plugin

**Version:** 1.0.1.0  
**Author:** Munir Abbasi  
**Website:** [syntaxhouse.com](https://syntaxhouse.com)  
**GitHub:** [munir-abbasi](https://github.com/munir-abbasi/)  
**License:** GNU General Public License v3  
**OJS Compatibility:** 3.5.0+

---

## Overview

Publish to Facebook is a [PKP](https://pkp.sfu.ca) [Open Journal Systems](https://pkp.sfu.ca/ojs/) (OJS) generic plugin that enables journal managers and editors to publish article links to a configured Facebook Page.

The plugin supports both **manual** (one-click from the submission detail page) and **automatic** (on article publication) posting, with full duplicate prevention, error logging, and retry capability.

---

## Features

| Feature | Description |
|---|---|
| **Manual posting** | One-click "Publish to Facebook" button on the submission detail page |
| **Auto-posting** | Automatically post when an article is published (toggle in settings) |
| **Duplicate prevention** | Prevents the same article from being posted twice |
| **Error logging** | Logs all post attempts (success and failure) with timestamps |
| **Status display** | Shows current post status (posted / error) on submission page |
| **Retry** | One-click retry for failed posts |
| **Settings UI** | Vue-based settings panel integrated via OJS 3.5 modal |
| **Custom message format** | Configurable message templates with placeholders |
| **Safe URL building** | Uses OJS dispatcher for canonical article URLs |

---

## Requirements

- OJS 3.5.0 or later
- PHP 8.0 or later
- A Facebook Page with a long-lived Page Access Token
- Facebook App with `pages_manage_posts` and `pages_read_engagement` permissions
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
| **Facebook Page Access Token** | A long-lived Page Access Token with `pages_manage_posts` permission |
| **Default article message format** | Message template for article posts (see placeholders below) |
| **Default issue message format** | Message template for issue posts |
| **Auto-publish articles** | When enabled, newly published articles are posted automatically |

### Message Format Placeholders

| Placeholder | Description |
|---|---|
| `{$articleTitle}` | The article title |
| `{$articleUrl}` | The canonical article URL (built via OJS dispatcher) |
| `{$issueTitle}` | The issue title |
| `{$issueUrl}` | The canonical issue URL |
| `{$journalName}` | The journal/press name |

**Default format:**
```
New article published: {$articleTitle}
{$articleUrl}

{$journalName}
```

---

## Usage

### Manual Posting

1. Open any published submission's detail page.
2. A **Publish to Facebook** button appears in the top action bar.
3. Click the button to post the article link to your configured Facebook Page.
4. A green **Published to Facebook** status indicator confirms the post.
5. If the post fails, a red error message and **Retry** button appear.

### Automatic Posting

When enabled in settings, any article that becomes published (status set to `STATUS_PUBLISHED`) is automatically posted to Facebook. Auto-posting:

- Runs after the publication workflow completes
- Never blocks the publication process (failures are logged silently)
- Respects duplicate prevention (already-posted articles are skipped)

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
├── classes/
│   ├── Constants.php                  # Setting key constants
│   ├── FacebookService.php            # Facebook Graph API client
│   ├── PostLog.php                    # PostLog data object
│   ├── PostLogDAO.php                 # PostLog CRUD + dedup queries
│   ├── PublicationPostBuilder.php     # Message + URL builder
│   └── migrations/
│       └── PostLogMigration.php       # Database migration
├── formRequests/
│   └── EditSettingsRequest.php        # Settings form validation
├── locale/
│   └── en_US/
│       └── locale.po                  # English locale strings
└── schema/
    └── log.json                       # PostLog JSON schema
```

---

## Architecture

### Component Diagram

```
PublishToFacebookPlugin
│
├── register()
│   ├── Hook::add(...) ──> registerSettingsForm()   [settings hook]
│   ├── Hook::add(...) ──> addPublishButtonHook()   [submission page JS]
│   ├── Hook::add(...) ──> registerPostLogSchema()  [schema registration]
│   ├── Hook::add(...) ──> registerApiController()  [API endpoint]
│   └── Hook::add(...) ──> addAutoPublishHook()     [auto-pub on publish]
│
├── SettingsController  ──> EditSettingsRequest ──> Constants
│
└── PostController
    ├── POST /{submissionId}           # Submit to Facebook
    ├── GET  /history/{submissionId}   # Get post status
    │
    ├── FacebookService                # Graph API call
    ├── PublicationPostBuilder         # Message + URL building
    └── PostLogDAO                     # Persistence + dedup
```

### Data Flow (Manual Post)

```
[Editor clicks button]
       │
       ▼
PostController::submit()
       │
       ├── PublicationPostBuilder::buildMessage()
       ├── PostLogDAO::hasExistingPost()  ──► 409 if duplicate
       ├── FacebookService::postLink()
       │       └── Graph API POST /{pageId}/feed
       ├── PostLogDAO::insert() (success or error)
       └── Returns JSON response
```

### Data Flow (Auto-Post)

```
[Publication::publish hook fires]
       │
       ▼
PublishToFacebookPlugin::handleArticlePublication()
       │
       ├── Check autoPublishArticles setting
       ├── PostLogDAO::hasExistingPost()  ──► skip if duplicate
       ├── FacebookService::postLink()
       ├── PostLogDAO::insert() (success or error)
       └── Never blocks publication
```

---

## Database

### `publish_to_facebook_post_logs`

| Column | Type | Description |
|---|---|---|
| `post_log_id` | bigint (PK) | Auto-increment primary key |
| `submission_id` | bigint | OJS submission ID (nullable) |
| `context_id` | bigint | Journal/context ID |
| `status` | varchar(20) | `success` or `error` |
| `facebook_post_id` | varchar(255) | Facebook Graph API post ID (nullable) |
| `message` | text | The message that was posted (nullable) |
| `error_message` | text | Error details on failure (nullable) |
| `link` | varchar(2048) | The posted URL (nullable) |
| `date_posted` | datetime | When the post attempt occurred |

**Indexes:**
- `post_logs_context_submission_idx` on `(context_id, submission_id)`
- `post_logs_status_idx` on `(status)`

---

## API Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/{submissionId}` | Post submission to Facebook |
| `GET` | `/history/{submissionId}` | Get latest post log for submission |

All endpoints are registered under the `publishToFacebook` API group and require logged-in users with appropriate permissions.

---

## Development & Contribution

1. Fork the repository.
2. Create a feature branch.
3. Make changes following OJS 3.5+ plugin conventions (namespace, strict types, locale keys).
4. Run `php -l` on all modified files for syntax check.
5. Submit a pull request.

---

## Version History

| Version | Date | Changes |
|---|---|---|
| 1.0.1.0 | 2026-07-01 | PostLog migration, auto-posting, retry/status display, PublicationPostBuilder |
| 1.0.0.0 | — | Initial modernization (namespace, settings, manual post, FacebookService) |

---

## License

This plugin is distributed under the GNU General Public License v3. See the `docs/COPYING` file in your OJS installation for the full license text.
