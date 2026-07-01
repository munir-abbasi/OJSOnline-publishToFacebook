# Modernize 'Publish to Facebook' Plugin for OJS 3.5.x

## TL;DR (For humans)
This plan modernizes the legacy OJS "Publish to Facebook" plugin by moving from the old OJS 2/early OJS 3 skeleton to the current OJS 3.5+ namespace-based architecture. It emphasizes manual action first for safety, duplicate prevention, and logging before enabling automatic posting.

## Status
- [x] Phase 0: Prerequisite check
- [x] Phase 1: Modernize skeleton
- [x] Phase 2: Add settings
- [x] Phase 3: Add manual posting
- [x] Phase 4: Add logging/duplicate prevention
- [x] Phase 5: Add automatic posting
- [x] Phase 6: Add retry/error display

## Todos

### Phase 0: Prerequisite
0. [x] Check OJS 3.5+ environment for preferred HTTP client availability (Guzzle/curl/other) - **Decision:** Use Guzzle (core OJS 3.5 dependency, assumed available).

### Phase 1: Modernize skeleton (COMPLETED — executed via $start-work)
1. [x] `src/PublishToFacebookPlugin.php`: Created namespace `APP\plugins\generic\publishToFacebook`, class extends `GenericPlugin`, implements `register()`, `getDisplayName()`, `getDescription()` using locale keys.
2. [x] `src/index.php`: Created in `src/` with namespaced class initialization + root `index.php` loader.
3. [x] `src/version.xml`: Created with correct version/class/type. Root `version.xml` symlink added.
   - Legacy backup files preserved in `backup/` directory.

### Phase 2: Add settings
4. [x] `locale/en_US/locale.po` — 13 locale keys for settings labels/descriptions
5. [x] `classes/Constants.php` — Setting key constants
6. [x] `SettingsForm.php` — Vue FormComponent with 6 fields (no Smarty, no manage())
7. [x] `SettingsController.php` — PluginSettingsController GET/PUT
8. [x] `formRequests/EditSettingsRequest.php` — FormRequest validation
9. [x] `PublishToFacebookPlugin.php` — MODIFY: add Hook registration + VueModal getActions

### Phase 3: Add manual posting
7. [x] `classes/FacebookService.php`: Implement API client (Guzzle/curl) - expect API to reach Facebook.
8. [x] `classes/PublicationPostBuilder.php`: Implement post builder with template variable replacement - expect correctly formatted messages.
9. [x] `PostController.php` + `PublishToFacebookPlugin.php`: Add API controller + hook for manual action button - expect button to appear in UI.

### Phase 4: Logging and duplicate prevention
10. [x] `schema/log.json` + `classes/PostLog.php` + `classes/PostLogDAO.php` + `classes/migrations/PostLogMigration.php`: EntityDAO-based post log with dedup check - expect success/failure logs + 409 on duplicate.

### Phase 5: Automatic posting (Optional)
11. [x] `PublishToFacebookPlugin.php`: Register `Publication::publish` hook — check `autoPublishArticles` setting → dedup via `hasExistingPost()` → `FacebookService::postLink()` → log via `PostLogDAO`. Wrapped in try/catch to never break publish workflow.

### Phase 6: Error handling / Retry
12. [x] `PostController.php`: Add `GET /history/{submissionId}` endpoint returning latest post log status.
13. [x] `PublishToFacebookPlugin.php`: Enhance JS to fetch post history on page load — show success indicator or error message + retry button. Retry reuses existing POST endpoint.
14. [x] `locale/en_US/locale.po`: Add retry button and posted-status locale keys.

## References
- `context.md` modernization guide.
- [PKP Plugin Guide](https://docs.pkp.sfu.ca/dev/plugin-guide/en/examples-custom-page)
