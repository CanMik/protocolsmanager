# Changelog — protocolsmanager

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.7.0.0] — 2026-04-19

### Added
- **Date format option** in template configuration: choose between numeric
  (`16.03.2026`) or written date (`16 de marzo de 2026`). Localization is
  handled by PHP's native `IntlDateFormatter` — no plugin-owned month strings.
  Falls back to numeric if the `intl` extension is not loaded.
- DB migration: `date_format TINYINT UNSIGNED DEFAULT 0` added to
  `glpi_plugin_protocolsmanager_config` for existing installs.
- `getTypeName()` added to `PluginProtocolsmanagerConfig` — page title now
  shows "Protocols Manager" instead of the raw PHP class name.
- Logo auto-fill dimensions on file select (JS FileReader). Height set to
  `width / 10` enforcing the required 10:1 aspect ratio.
- `recipients` column extended from `VARCHAR(255)` to `TEXT` in
  `glpi_plugin_protocolsmanager_emailconfig`. Migration included for existing
  installs.
- **`Folio:` prefix** in PDF header before the document number, translated in
  all 5 locales (es_MX: *Folio*, fr_FR: *Référence*, sk_SK: *Číslo*).
- **`PLUGIN_PROTOCOLSMANAGER_VERSION` constant** defined in `setup.php` as the
  single source of truth for the plugin version. `plugin_version_protocolsmanager()`
  and `pdfbuilder.class.php` both read from this constant — bumping a release
  now requires editing only `setup.php` and `plugin.xml`.
  - **PDF compression**: `setCompression(true)` enabled in TCPDF constructor.
  File size reduced approximately 40–60%.
- **try/catch around PDF generation**: if `generate()` or `file_put_contents()`
  fails, the reserved protocol row is deleted, a translated error message is
  shown, and the method returns cleanly. No more phantom "pending" rows.
- **Logo auto-fill dimensions**: selecting a logo file in the template form
  automatically fills the width and height fields via `FileReader`. Height is
  set to `width / 10` to enforce the required 10:1 aspect ratio.
- **`recipients` column extended to TEXT**: `glpi_plugin_protocolsmanager_emailconfig`
  `recipients` changed from `VARCHAR(255)` to `TEXT`. Migration added for
  existing installs.
- **Uninstall cleanup**: logo files are deleted from `GLPI_PICTURE_DIR` and
  generated PDFs from `GLPI_UPLOAD_DIR` before tables are dropped.
- **deleteConfigs() logo cleanup**: physical logo file is deleted from
  `GLPI_PICTURE_DIR` when a template is deleted.
- **Profile label i18n**: `'Plugin configuration'` and `'Protocols manager tab
  access'` added to all 5 locales.
- **`saveConfigs()` refactored**: three near-identical `DB->update()` blocks
  collapsed into one `$updateData` array. Logo field only included when a new
  file was uploaded.
  - `inc/pdfbuilder.class.php`: new TCPDF subclass replacing the bundled dompdf
  pipeline. Auto-sizing table, two-pass cell rendering, signature layout.
- States dropdown for manually-added equipment rows (queries `glpi_states`
  server-side, emitted as JSON for the JS `addNewRow` helper).
- Hard-delete implementation: `deleteDocs()` removes physical file,
  `glpi_documents_items` links, and the GLPI Document record.
- Complete i18n system: `.pot` template + `.po`/`.mo` files for es_MX, en_US,
  en_GB, fr_FR, sk_SK.
- `glpi_assets_assets` support (GLPI 11 dynamic asset table).
- GLPI card/nav-tab/btn UI replacing raw HTML tables and inline CSS.
- Em-dash placeholder in UI table for empty optional fields.
- Help button (`btn btn-info`) in template and email card headers.
- "Select all" checkbox label in Generated documents card header.
- Logo width/height fields with preview in template config.

### Fixed
- **`$pm_total` undefined warning** on Generated Documents table: the pagination
  clamp ran before `countDocsForUser()` was called. Reordered to calculate total
  first, then clamp the offset.
- **"Written date" label showing in English** despite correct Spanish locale:
  the old orphan msgid `"Text (localized)"` remained in the `.pot`, causing
  GLPI's gettext loader to miss the new translation. Orphan removed and all
  `.mo` files recompiled.
- **PDF Creator metadata** was showing `GLPI_VERSION` (the GLPI version) instead
  of the plugin version. Now reads `PLUGIN_PROTOCOLSMANAGER_VERSION` — properties
  panel shows `protocolsmanager v1.7.1.0`.
- **Footer** label in template config was not using the plugin i18n domain;
  now translated in all 5 locales.
- **`$date_format` undefined** when editing an existing template: variable was
  only assigned in the create-mode branch, not loaded from the DB row on edit.
- **`generate.form.php` missing access control**: `Session::checkRight()` was
  absent, allowing any authenticated GLPI user to POST directly to
  `makeProtocol()`, `deleteDocs()`, or `sendOneMail()` regardless of plugin
  profile permissions.
- **`checkRights()` unguarded SESSION access**: `$_SESSION['glpiactiveprofile']['id']`
  accessed without null-check. Now uses `?? 0` with early return if not set.
- **Old logo not deleted when replaced**: uploading a new logo left the
  previous file orphaned in `GLPI_PICTURE_DIR`. The old file is now removed
  before the new one is written.
- **`$pm_start` pagination offset unbound**: a crafted GET parameter could
  produce an offset beyond the total row count. Now clamped to
  `max(0, min($pm_start, $pm_total - 1))`.
- **`email_template` selector always showed first option as selected**: the
  comparison used `$uid` (iterator index 0, 1, 2…) instead of `$list['id']`
  (DB row id). The correct template is now pre-selected on edit.
- **`sendOneMail()` proceeded with `doc_id = 0`**: now validates `$doc_id > 0`
  before querying `glpi_documents` and returns with a user-visible warning if
  no document is selected.
- **`getDocNumber()` dead method removed**: the race-condition-prone
  `MAX(id)+1` helper was already unreachable after the AUTO_INCREMENT
  reservation fix; its definition has been deleted.
- **Critical — `$owner` empty in generated PDFs**: `$id` was read from POST
  *after* `getFriendlyName()` was called, so owner/author always resolved as
  empty. Reordered POST declarations so `$id` is assigned first.
- **PDF metadata missing**: Title, Author, Subject, and Creator were all "Not
  available" in PDF properties. Now set via TCPDF before `AddPage()`.
- **Comments column in PDF**: always rendered regardless of whether any comment
  contains text. Empty cells show a blank bordered field for handwriting.
- **Em-dash placeholder**: empty optional fields (manufacturer, model, state,
  serial, inventory) now show `—` in both the UI table and the PDF, matching
  behaviour that was already implemented for the UI but missing from the PDF
  builder row assembly.
- **PDF filename collisions**: filenames are now sanitized with `preg_replace`
  and include the folio number plus full timestamp (`dmYHis`) to prevent
  overwrite when the same template is used twice in one day.
- **Manufacturer and state name truncation**: `explode(' ')[0]` that cut names
  like "Hewlett Packard" → "Hewlett" and "En uso" → "En" has been removed.
  Full names are now passed to both the UI and the PDF.
- **`glpi_assets_assets` wrong itemtype**: hardcoded `'Tablet'` replaced with
  an empty string, skipping the invalid `glpi_documents_items` entry for
  dynamic asset types.
- **Auto-send email not HTML**: `sendMail()` now calls `IsHtml(true)` and
  `nl2br()`, matching the behaviour of the manual send modal. `stripcslashes()`
  removed from `sendOneMail()`.
- **No recipient guard in `sendMail()`**: method now validates that at least one
  address was added before calling `Send()`.
- **Profile rights form CSRF**: `Html::hidden('_glpi_csrf_token', ...)` was
  missing from the profile rights form, causing silent POST rejection in
  GLPI 11.
- **`__(htmlescape($label))`** in `profile.class.php`: corrected to
  `htmlescape(__($label, 'protocolsmanager'))` — translate first, then escape.
- 5 critical GLPI 11 API incompatibilities: `$DB->doQuery()` second arg,
  `Session::addMessageAfterRedirect()` type constant, CSRF token in all forms,
  `Html::footer()` in front controllers, User object loop O(n) → O(1).
- Tab registered on User only (was: 7 item types — crashed all asset forms).
- Manual row array indices: explicit `name[N]` notation replacing implicit `[]`.
- Missing State column in JavaScript `addNewRow` handler (caused column shift).
- `classes[]/ids[]` lookup: `!empty()` replaces `!== null` to exclude empty
  strings from `glpi_documents_items` inserts.
- Comments column always present in PDF (was: conditionally omitted when empty).
- `getDocNumber()` MAX(id) race condition: now uses AUTO_INCREMENT + update.
- SQL `ORDER BY ['name' => 'ASC']` → `'name ASC'` string form.
- "Powered by TCPDF" footer: `setPrintFooter(true)` ensures plugin Footer()
  override is always called instead of the TCPDF default.
- Two-pass table rendering fixes row-number column (#) and column drift on
  wrapped text.

### Changed
- Updated Wiki links.
- i18n full audit: modal title "Send email", `'Asset'` fallback, default email
  subject, and `'Select all'` now use the plugin domain. Total: **77 msgids**
  across 5 locales.
- Date format label "Text (localized)" replaced with a descriptive label
  showing a concrete example in each locale:
  - es_MX: *Fecha escrita — ej. 16 de marzo de 2026*
  - fr_FR: *Date écrite — ex. 16 mars 2026*
  - en_US: *Written date — e.g. March 16, 2026*
  - en_GB: *Written date — e.g. 16 March 2026*
  - sk_SK: *Zapísaný dátum — napr. 16. marca 2026*
- i18n: 79 total msgids across 5 locales (up from 77).

### Security
- **Logo upload MIME validation**: `$_FILES['type']` (client-supplied) replaced
  with `finfo_file()` on the actual `tmp_name`. Extension is derived from the
  verified MIME type, not the original filename. Filename uses `uniqid()`.
- **owner/author from POST removed**: display names now resolved server-side in
  `makeProtocol()` and `sendOneMail()` using `$_POST['user_id']` and
  `Session::getLoginUserID()`. All per-row hidden `owner`/`author` fields
  removed from the equipment table.
- **Delete ID validation**: `deleteConfigs()` and `deleteEmailConfigs()` now
  cast the id to `int`, validate `> 0`, and confirm the record exists before
  executing DELETE.

### Removed
- `SoftwareLicense` removed from `$alwaysInclude` item types — software
  licenses do not carry the physical asset fields expected by the delivery
  certificate.
- Dead commented `<script>` block removed from `config.form.php`.
- `dompdf/` vendor bundle (21 MB, 5 packages).
- `inc/template.php` (HTML template used by dompdf).
- `public/css/styles.css` (single `.text-muted` rule duplicating Bootstrap 5).
- `public/css/` directory (left empty after `styles.css` deletion).

---

## [1.6.0.5] — original baseline

Original codebase by Mikail / mateusznitka. GLPI 9.x / 10.x compatible.