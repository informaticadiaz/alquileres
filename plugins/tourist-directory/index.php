<?php
/*
Plugin Name: Tourist Directory Entries
Plugin URI: https://alquileres.diazignacio.ar/
Description: Admin/importer-created "directory entries" for commercial tourist complexes, shown
  alongside owner listings with no contact form, no price, and a visible public-directory label.
  Fail-closed contact guards ensure no email, comment, or notification is ever sent for an entry.
Version: 0.1.0
Author: alquileres.diazignacio.ar
Short Name: tourist-directory
*/

if (!defined('ABS_PATH')) {
  exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

require_once __DIR__ . '/tourist-directory-lib.php';

define('TOURIST_DIRECTORY_PLUGIN', osc_plugin_path(__FILE__));
define('TOURIST_DIRECTORY_SECTION', 'tourist_directory');
// Relative to osc_plugins_url(): the public URL of this plugin's front-end stylesheet.
define('TOURIST_DIRECTORY_CSS_REL_PATH', 'tourist-directory/assets/tourist-directory.css');
// Route ids, reused by every glue function that redirects or links to either route.
define('TOURIST_DIRECTORY_REMOVAL_ROUTE', 'tourist-directory-removal');
define('TOURIST_DIRECTORY_ADMIN_ROUTE', 'tourist-directory-admin');

// Registered unconditionally at plugin load, i.e. only while the plugin is enabled --
// Plugins::init() (oc-load.php:326) only requires active plugins' index.php, and routes must be
// registered before Rewrite::newInstance()->init() (oc-load.php:366) matches the request URI. The
// removal route's regexp is the pure, directly-testable tourist_directory_removal_route_regexp();
// the admin route's regexp has no dynamic id segment, so it stays inline (design.md's Amendment
// "Public page"/"Admin page" architecture decisions).
osc_add_route(
  TOURIST_DIRECTORY_REMOVAL_ROUTE,
  tourist_directory_removal_route_regexp(),
  'directorio/solicitar-baja/{entry}/',
  'tourist-directory/views/removal-form.php'
);
osc_add_route(
  TOURIST_DIRECTORY_ADMIN_ROUTE,
  'tourist-directory-admin/?',
  'tourist-directory-admin/',
  'tourist-directory/admin/requests.php'
);

// ------------------------------------------------------------------------------------------------
// Osclass-bound reads/writes. Every DECISION (should this block? what should this render? what is
// the Params map for a row?) lives in tourist-directory-lib.php and is unit-tested there. This file
// only talks to Osclass state: the database, hooks, preferences, and output.
// ------------------------------------------------------------------------------------------------

// The marker table name, prefixed like every other Osclass table.
function tourist_directory_table() {
  return DB_TABLE_PREFIX . 't_directory_entry';
}

// A generic DAO instance for raw queries against our own table. Item's dao is used only because an
// Item-related plugin needs Item.php loaded anyway (ItemActions below); the connection itself is
// shared process-wide, exactly like Category::newInstance()->dao is reused for unrelated raw
// queries elsewhere in this codebase (see plugins/tourist-identity/index.php).
function tourist_directory_dao() {
  return Item::newInstance()->dao;
}

// The removal-request table name (Amendment, schema step 2).
function tourist_directory_removal_table() {
  return DB_TABLE_PREFIX . 't_directory_removal_request';
}

// Stored schema_version preference as an int; Osclass returns '' for a missing preference
// (Preference.php:155-159), which is treated as version 0, same as tourist_directory_schema_steps().
function tourist_directory_stored_schema_version() {
  $value = osc_get_preference('schema_version', TOURIST_DIRECTORY_SECTION);
  return ($value === '' || $value === false) ? 0 : (int) $value;
}

// The per-install IP-hashing salt; '' when it was never generated.
function tourist_directory_ip_salt() {
  $value = osc_get_preference('ip_salt', TOURIST_DIRECTORY_SECTION);
  return ($value === false) ? '' : $value;
}

// Generated once (32 random bytes, hex). Never rotated: rotating it would make every existing
// s_ip_hash unrecognizable for throttle lookups.
function tourist_directory_ensure_ip_salt() {
  if (tourist_directory_ip_salt() === '') {
    osc_set_preference('ip_salt', bin2hex(random_bytes(32)), TOURIST_DIRECTORY_SECTION);
  }
}

// Live probe backing tourist_directory_channel_ready()'s $tableProbeOk argument: a cheap SELECT
// against the removal-request table. False on any failure (table missing, DB error, exception) --
// never trust a probe that did not affirmatively succeed.
function tourist_directory_removal_table_probe_ok() {
  try {
    $result = tourist_directory_dao()->query('SELECT 1 FROM ' . tourist_directory_removal_table() . ' LIMIT 1');
    return $result !== false;
  } catch (\Throwable $e) {
    return false;
  }
}

// Combines the stored schema version with a live table probe via the pure
// tourist_directory_channel_ready() decision. Used by the (U7) public form and by the CLI importer.
function tourist_directory_is_channel_ready() {
  return tourist_directory_channel_ready(tourist_directory_stored_schema_version(), tourist_directory_removal_table_probe_ok());
}

// Idempotent schema migration -- the SOLE DDL entry point for this plugin. MUST run only from
// install/enable, NEVER from a public request. tourist_directory_schema_steps() (pure) decides
// which steps are still needed from the stored preference; each step is its own idempotent
// CREATE TABLE IF NOT EXISTS, so re-running after a partial failure is always safe. The version
// preference is bumped only after every needed step has run without throwing.
function tourist_directory_ensure_schema() {
  $steps = tourist_directory_schema_steps(osc_get_preference('schema_version', TOURIST_DIRECTORY_SECTION));

  foreach ($steps as $step) {
    if ($step === 1) {
      tourist_directory_create_marker_table();
    } elseif ($step === 2) {
      tourist_directory_create_removal_request_table();
    }
  }

  if (!empty($steps)) {
    osc_set_preference('schema_version', (string) tourist_directory_schema_target_version(), TOURIST_DIRECTORY_SECTION);
  }

  tourist_directory_ensure_ip_salt();
}

function tourist_directory_create_marker_table() {
  tourist_directory_dao()->query(
    'CREATE TABLE IF NOT EXISTS ' . tourist_directory_table() . ' (' .
    'pk_i_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, ' .
    'fk_i_item_id INT(10) UNSIGNED NOT NULL, ' .
    's_seed_id VARCHAR(191) NOT NULL, ' .
    's_official_website VARCHAR(255) NOT NULL DEFAULT \'\', ' .
    's_fingerprint CHAR(40) NOT NULL, ' .
    'dt_imported DATETIME NOT NULL, ' .
    'dt_updated DATETIME NOT NULL, ' .
    'dt_retired DATETIME NULL, ' .
    's_retired_reason VARCHAR(32) NULL, ' .
    'PRIMARY KEY (pk_i_id), ' .
    'UNIQUE KEY idx_directory_item (fk_i_item_id), ' .
    'UNIQUE KEY idx_directory_seed (s_seed_id)' .
    ') ENGINE=InnoDB DEFAULT CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_unicode_ci\''
  );
}

// t_directory_removal_request: see design.md's Amendment "Table" architecture decision. No FK to
// the marker table (same cascade-risk rationale as the marker table itself); s_seed_id is copied in
// so precedence survives a lost marker.
function tourist_directory_create_removal_request_table() {
  tourist_directory_dao()->query(
    'CREATE TABLE IF NOT EXISTS ' . tourist_directory_removal_table() . ' (' .
    'pk_i_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, ' .
    'fk_i_item_id INT(10) UNSIGNED NOT NULL, ' .
    's_seed_id VARCHAR(191) NOT NULL, ' .
    's_relation VARCHAR(16) NOT NULL, ' .
    's_reply_contact VARCHAR(190) NULL, ' .
    's_reason VARCHAR(1000) NULL, ' .
    's_ip_hash CHAR(64) NULL, ' .
    's_status VARCHAR(16) NOT NULL DEFAULT \'pending\', ' .
    'dt_requested DATETIME NOT NULL, ' .
    'dt_processed DATETIME NULL, ' .
    'PRIMARY KEY (pk_i_id), ' .
    'KEY idx_directory_removal_item (fk_i_item_id), ' .
    'KEY idx_directory_removal_seed (s_seed_id), ' .
    'KEY idx_directory_removal_ip (s_ip_hash, dt_requested)' .
    ') ENGINE=InnoDB DEFAULT CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_unicode_ci\''
  );
}

// The per-install placeholder contact address; '' when it was never generated.
function tourist_directory_contact_email() {
  $value = osc_get_preference('contact_email', TOURIST_DIRECTORY_SECTION);
  return tourist_directory_needs_contact_email($value) ? '' : $value;
}

// Generates the placeholder once. Osclass returns '' (not false) for a missing preference
// (Preference.php:155-159), so the check must treat '' as missing. Never rotates a valid address.
function tourist_directory_ensure_contact_email() {
  if (tourist_directory_needs_contact_email(osc_get_preference('contact_email', TOURIST_DIRECTORY_SECTION))) {
    osc_set_preference('contact_email', tourist_directory_generate_placeholder_email(), TOURIST_DIRECTORY_SECTION);
  }
}

// Every locale code actually installed on this site (mirrors tourist_identity_installed_tree_locales()).
function tourist_directory_installed_locale_codes() {
  return array_keys(osc_get_locales_all('ALL', true));
}

// ------------------------------------------------------------------------------------------------
// Lifecycle: install / uninstall / enable / disable
// ------------------------------------------------------------------------------------------------

// MUST echo nothing: Plugins::install() treats any output buffer content as an install failure
// (oc-includes/osclass/classes/Plugins.php:422).
function tourist_directory_install() {
  tourist_directory_ensure_schema();

  // Generated exactly once per install: a re-install (uninstall keeps this pref) never rotates the
  // address, so existing entries keep matching it.
  tourist_directory_ensure_contact_email();

  // A directory-only install/reinstall must also pick up the current showcase dropdown vocabulary,
  // exactly like tourist-showcase's own install does for itself (tourist-showcase.php:41).
  if (function_exists('tourist_showcase_sync_options')) {
    tourist_showcase_sync_options();
  }
}

function tourist_directory_generate_placeholder_email() {
  return 'directorio-' . bin2hex(random_bytes(8)) . '@directorio.invalid';
}

// Deactivates (never deletes) every item this plugin manages, retired or not. Used by both disable
// and uninstall, matching the Reversible Uninstall requirement.
function tourist_directory_deactivate_every_managed_item() {
  $actions = new ItemActions(true);
  foreach (tourist_directory_marker_item_ids('1=1') as $itemId) {
    $actions->deactivate($itemId);
  }
}

// Reactivates only entries whose marker was never retired (dt_retired IS NULL) AND that carry no
// blocking removal request (pending or processed) -- a NOT EXISTS guard, since an item can be
// deactivated by an accepted removal request without its marker ever being touched
// (tourist_directory_retire() is never called by the removal-form handler; see design.md's
// Amendment "Decision" row). A retired entry, or one with a blocking request, stays deactivated
// across enable/disable/reinstall cycles until an explicit admin/CLI action reverses it.
function tourist_directory_reactivate_non_retired_items() {
  $actions = new ItemActions(true);
  $where = 'dt_retired IS NULL AND NOT EXISTS (SELECT 1 FROM ' . tourist_directory_removal_table() .
    ' r WHERE r.fk_i_item_id = ' . tourist_directory_table() . '.fk_i_item_id AND r.s_status IN (\'pending\', \'processed\'))';
  foreach (tourist_directory_marker_item_ids($where) as $itemId) {
    $actions->activate($itemId);
  }
}

// $whereSql is always one of the fixed literals above -- never user input -- so string
// concatenation here carries no injection risk.
function tourist_directory_marker_item_ids($whereSql) {
  $result = tourist_directory_dao()->query('SELECT fk_i_item_id FROM ' . tourist_directory_table() . ' WHERE ' . $whereSql);

  if ($result === false) {
    return array();
  }

  $ids = array();
  foreach ($result->result() as $row) {
    $ids[] = (int) $row['fk_i_item_id'];
  }

  return $ids;
}

function tourist_directory_enable() {
  tourist_directory_ensure_schema();
  tourist_directory_ensure_contact_email();
  tourist_directory_reactivate_non_retired_items();
}

function tourist_directory_disable() {
  tourist_directory_deactivate_every_managed_item();
}

// Runs after Plugins::deactivate() already ran (oc-includes/osclass/classes/Plugins.php:449), so
// every managed item is already inactive by the time this fires; kept anyway so uninstall is safe
// even if that ordering ever changes. The table and the contact_email pref are intentionally never
// dropped: a later reinstall must re-link the same items instead of orphaning them.
function tourist_directory_uninstall() {
  tourist_directory_deactivate_every_managed_item();
}

// ------------------------------------------------------------------------------------------------
// Fail-closed contact guards
// ------------------------------------------------------------------------------------------------

// Fresh, uncached, per-call SELECT: the guard must never trust a stale cached "not an entry"
// result. Returns true (confirmed entry), false (confirmed non-entry: a query that ran and found no
// row), or null (the query failed or threw -- fails closed via tourist_directory_should_block()).
function tourist_directory_lookup_entry($itemId) {
  $itemId = (int) $itemId;

  if ($itemId <= 0) {
    return null;
  }

  try {
    $dao = tourist_directory_dao();
    $dao->select('pk_i_id');
    $dao->from(tourist_directory_table());
    $dao->where('fk_i_item_id', $itemId);
    $result = $dao->get();

    if ($result === false) {
      return null;
    }

    return $result->numRows() > 0;
  } catch (\Throwable $e) {
    return null;
  }
}

// Shared guard used by both call sites (init_item early guard and the three pre_item_*_post
// hooks). $item is the hook-provided row when available (null at init_item time, since
// osc_run_hook('init_item') passes no arguments). Resolves the item id from Params::getParam('id')
// (the same param every guarded action reads, e.g. ItemActions.php prepareData()'s
// Params::getParam('id')) AND from the passed row's pk_i_id when present; a disagreement or a
// missing id blocks.
function tourist_directory_guard($item = null) {
  $paramId = (int) Params::getParam('id');
  $rowId = (is_array($item) && isset($item['pk_i_id'])) ? (int) $item['pk_i_id'] : null;

  if ($rowId !== null && $rowId !== $paramId) {
    tourist_directory_block_request($rowId > 0 ? $rowId : $paramId);
    return;
  }

  $id = ($rowId !== null) ? $rowId : $paramId;

  if ($id <= 0) {
    tourist_directory_block_request(0);
    return;
  }

  $isEntry = tourist_directory_lookup_entry($id);

  // The orphan check (an entry whose marker row is gone but whose contact email still carries the
  // placeholder) only needs a contact-email read when the marker lookup itself came back clean.
  $contactEmail = '';
  if ($isEntry === false) {
    if (is_array($item) && array_key_exists('s_contact_email', $item)) {
      $contactEmail = $item['s_contact_email'];
    } else {
      $row = Item::newInstance()->findByPrimaryKey($id);
      $contactEmail = (is_array($row) && isset($row['s_contact_email'])) ? $row['s_contact_email'] : '';
    }
  }

  if (tourist_directory_should_block($isEntry, $contactEmail, tourist_directory_contact_email())) {
    tourist_directory_block_request($id);
  }
}

function tourist_directory_block_request($id) {
  osc_add_flash_error_message(tourist_directory_text(
    'Esta ficha es información pública de directorio: no admite consultas, envíos ni comentarios. Consulte el sitio oficial del complejo.',
    'This is a public directory listing: it does not accept enquiries, referrals, or comments. Please visit the official website.',
    osc_current_user_locale()
  ));

  osc_redirect_to($id > 0 ? osc_item_url_ns($id) : osc_base_url());
}

// Covers the GET contact/send-friend pages (and, defensively, every guarded POST action) before
// doModel()'s action switch even runs (oc-includes/osclass/controller/item.php:39). No item row is
// available yet at this point, so the guard resolves everything from Params::getParam('id').
function tourist_directory_init_item_guard() {
  $guarded_actions = array('contact', 'contact_post', 'send_friend', 'send_friend_post', 'add_comment');

  if (!in_array(Params::getParam('action'), $guarded_actions, true)) {
    return;
  }

  tourist_directory_guard(null);
}

// Wired to all three pre_item_*_post hooks. Each fires strictly after osc_csrf_check() already ran
// for that action (controller/item.php:521 send_friend_post, :591 contact_post, :645 add_comment),
// so a forged POST must already carry a valid CSRF token before this guard ever runs -- and still
// gets blocked here.
function tourist_directory_pre_post_guard($item = null) {
  tourist_directory_guard($item);
}

osc_add_hook('init_item', 'tourist_directory_init_item_guard');
osc_add_hook('pre_item_contact_post', 'tourist_directory_pre_post_guard');
osc_add_hook('pre_item_send_friend_post', 'tourist_directory_pre_post_guard');
osc_add_hook('pre_item_add_comment_post', 'tourist_directory_pre_post_guard');

// ------------------------------------------------------------------------------------------------
// Rendering
// ------------------------------------------------------------------------------------------------

// Memoized per item id, per request: rendering hooks may run several times for the same item
// (title, sidebar, show_item) and must never re-query for each one.
function tourist_directory_entry_row($itemId) {
  static $cache = array();

  $itemId = (int) $itemId;

  if ($itemId <= 0) {
    return false;
  }

  if (array_key_exists($itemId, $cache)) {
    return $cache[$itemId];
  }

  $result = tourist_directory_dao()->query(
    'SELECT fk_i_item_id, s_official_website FROM ' . tourist_directory_table() .
    ' WHERE fk_i_item_id = ' . $itemId . ' AND dt_retired IS NULL'
  );

  $row = ($result !== false && $result->numRows() > 0) ? $result->row() : false;
  $cache[$itemId] = $row;

  return $row;
}

function tourist_directory_render_notice_html($id, array $row) {
  $locale = osc_current_user_locale();
  $label = tourist_directory_text('Información pública, no gestionada por el complejo', 'Public listing, not managed by the property', $locale);
  $visitLabel = tourist_directory_text('Visitar sitio oficial', 'Visit official website', $locale);
  $removalLabel = tourist_directory_text('Solicitar baja', 'Request removal', $locale);

  $website = (isset($row['s_official_website']) && $row['s_official_website'] !== '')
    ? tourist_directory_safe_url($row['s_official_website'])
    : false;

  $html = '<div class="tourist-directory-notice">';
  $html .= '<p class="tourist-directory-label">' . osc_esc_html($label) . '</p>';

  if ($website !== false) {
    $html .= '<a class="tourist-directory-official-link" href="' . osc_esc_html($website) . '" rel="nofollow noopener noreferrer" target="_blank">' . osc_esc_html($visitLabel) . '</a> ';
  }

  // Route-based, mail-independent removal link (Amendment U7). osc_route_url() returns '' only
  // when the route was never registered at all (e.g. the plugin is disabled, in which case this
  // hook would not even fire) -- so the link is omitted defensively rather than ever printing an
  // empty href. tourist_directory_removal_url() (the old page=contact-based link) was removed in
  // U6 along with the mail-dependent channel it pointed at -- see design.md's Amendment.
  $removalUrl = osc_route_url(TOURIST_DIRECTORY_REMOVAL_ROUTE, array('entry' => $id));
  if ($removalUrl !== '') {
    $html .= '<a class="tourist-directory-removal-link" href="' . osc_esc_html($removalUrl) . '">' . osc_esc_html($removalLabel) . '</a>';
  }

  $html .= '</div>';

  return $html;
}

// item_title is BOTH a void hook (theme calls osc_run_hook('item_title') with no args right after
// the H1, oc-content/themes/sigma/item.php:57) AND a value filter (title text sanitization at
// oc-includes/osclass/model/Item.php:1535,1649 and display-time escaping at
// oc-includes/osclass/controller/item.php:846) -- Plugins::runHook/applyFilter share one registry
// keyed by hook name (oc-includes/osclass/classes/Plugins.php:25-76), so one callback receives
// both call shapes. This function MUST return any received title argument completely unchanged
// (never corrupt item titles during save/render) and must ONLY echo when called with zero
// arguments (the theme's rendering call).
function tourist_directory_item_title_hook() {
  $args = func_get_args();

  if (count($args) > 0) {
    return $args[0];
  }

  $id = osc_item_id();
  $row = tourist_directory_entry_row($id);

  if (is_array($row)) {
    echo tourist_directory_render_notice_html($id, $row);
    return null;
  }

  $presentation = tourist_directory_listing_presentation('owner', osc_current_user_locale());
  echo '<p class="tourist-owner-status">' . osc_esc_html($presentation['status']) . '</p>';

  return null;
}

// Pure void hook (item-sidebar.php:20), single call shape: safe to always echo.
function tourist_directory_item_sidebar_top_hook() {
  $id = osc_item_id();
  $row = tourist_directory_entry_row($id);

  if (!is_array($row)) {
    return;
  }

  $notice = tourist_directory_text(
    'Esta ficha es información pública de directorio: no admite consultas directas.',
    'This is a public directory listing: it does not accept direct enquiries.',
    osc_current_user_locale()
  );

  echo '<div class="tourist-directory-sidebar-notice">' . osc_esc_html($notice) . '</div>';
}

// Pure void hook (loop-single.php:43, loop-single-premium.php:43 -- the latter passes an extra
// boolean this callback ignores): the listing-card badge.
function tourist_directory_item_loop_title_hook() {
  $id = osc_item_id();
  $row = tourist_directory_entry_row($id);

  $presentation = tourist_directory_listing_presentation(is_array($row) ? 'directory' : 'owner', osc_current_user_locale());
  echo ' <span class="tourist-listing-badge ' . osc_esc_html($presentation['class']) . '-badge">' . osc_esc_html($presentation['badge']) . '</span>';
}

// Pure void hook, echoed inline inside a class="..." attribute (loop-single.php:21): a trailing
// space keeps it from merging into the next class name.
function tourist_directory_highlight_class_hook() {
  $id = osc_item_id();
  $row = tourist_directory_entry_row($id);

  $presentation = tourist_directory_listing_presentation(is_array($row) ? 'directory' : 'owner', osc_current_user_locale());
  echo $presentation['class'] . ' ';
}

// item_price_null is a single-purpose value filter (only call site: hItems.php:1487, inside
// osc_format_price()) -- no dual-use risk, safe to register unconditionally.
function tourist_directory_price_null_filter($text) {
  $row = tourist_directory_entry_row(osc_item_id());
  return is_array($row) ? '' : $text;
}

function tourist_directory_body_class_filter($classes) {
  if (is_array($classes)) {
    $classes[] = 'tourist-directory-entry';
  }
  return $classes;
}

function tourist_directory_suppress_structured_data($show) {
  return false;
}

// Fires once per item detail page render (controller/item.php:862), after title/description
// filtering already ran. Registers the body-class and structured-data suppression filters only for
// a confirmed entry's own page, never globally.
function tourist_directory_show_item_hook($item) {
  $id = (is_array($item) && isset($item['pk_i_id'])) ? (int) $item['pk_i_id'] : osc_item_id();
  $row = tourist_directory_entry_row($id);

  if (!is_array($row)) {
    return;
  }

  osc_add_filter('sigma_bodyClass', 'tourist_directory_body_class_filter');
  osc_add_filter('structured_data_show_footer_filter', 'tourist_directory_suppress_structured_data');
  osc_add_filter('structured_data_show_header_filter', 'tourist_directory_suppress_structured_data');
}

osc_add_hook('item_title', 'tourist_directory_item_title_hook');
osc_add_hook('item_sidebar_top', 'tourist_directory_item_sidebar_top_hook');
osc_add_hook('item_loop_title', 'tourist_directory_item_loop_title_hook');
osc_add_hook('highlight_class', 'tourist_directory_highlight_class_hook');
osc_add_hook('show_item', 'tourist_directory_show_item_hook');
osc_add_filter('item_price_null', 'tourist_directory_price_null_filter');

// Front-end only, mirroring tourist_identity_footer()'s OC_ADMIN guard. Registered directly on
// 'header' (head.php:100), the same top-level pattern Osclass's own structured-data.php uses for
// its footer/header hooks (structured-data.php:117) -- 'init' fires too early in the request
// (BaseModel::__construct(), oc-includes/osclass/core/BaseModel.php:76) to safely echo <head> markup.
function tourist_directory_enqueue_css() {
  if (defined('OC_ADMIN') && OC_ADMIN === true) {
    return;
  }

  $file = __DIR__ . '/assets/tourist-directory.css';
  $version = is_file($file) ? (string) filemtime($file) : '1';
  osc_enqueue_style('tourist-directory', osc_plugins_url() . TOURIST_DIRECTORY_CSS_REL_PATH . '?v=' . rawurlencode($version));
}

osc_add_hook('header', 'tourist_directory_enqueue_css');

// Neither page needs to be indexed: the removal form is a single-use action page, and the admin
// screen requires a login and carries operator-facing data. 'header' (head.php:100) only fires for
// the front-end theme, so it covers the removal route; the admin backoffice never runs that hook
// (its own theme calls 'admin_header' instead, parts/header.php:41), so it is covered separately by
// tourist_directory_noindex_admin_header() below.
function tourist_directory_noindex_header() {
  if (Params::getParam('route') === TOURIST_DIRECTORY_REMOVAL_ROUTE) {
    echo '<meta name="robots" content="noindex, nofollow, noarchive" />' . "\n";
  }
}

osc_add_hook('header', 'tourist_directory_noindex_header');

// ------------------------------------------------------------------------------------------------
// Create / update / retire / reactivate (glue for the Phase 3 CLI importer; not wired to any
// controller or command in this change)
// ------------------------------------------------------------------------------------------------

function tourist_directory_apply_params(array $params) {
  foreach ($params as $key => $value) {
    Params::setParam($key, $value);
  }
}

// This deployment models destinations entirely through tourist-identity's category tree, not
// Osclass's native country/region/city geo tables, which prepareData() still validates with a
// minimum length regardless of admin/user context (ItemActions.php prepareData(), countryName/
// regionName/cityName). "Argentina" and the seed's own locality are used as non-empty, always-valid
// placeholders for that unused taxonomy.
function tourist_directory_apply_geo_placeholders(array $entry) {
  $locality = isset($entry['localidad']) ? trim((string) $entry['localidad']) : '';
  $place = ($locality !== '' && mb_strlen($locality) >= 2) ? $locality : 'Argentina';

  Params::setParam('country', 'Argentina');
  Params::setParam('countryId', '');
  Params::setParam('region', $place);
  Params::setParam('regionId', '');
  Params::setParam('city', $place);
  Params::setParam('cityId', '');
}

function tourist_directory_creation_context($catId) {
  return array(
    'catId' => (int) $catId,
    'contactEmail' => tourist_directory_contact_email(),
    'locales' => tourist_directory_locales(tourist_directory_installed_locale_codes()),
    'typeFieldId' => tourist_directory_type_field_id(),
  );
}

// Id of the showcase accommodation-type meta field, or 0 when the showcase plugin is absent.
function tourist_directory_type_field_id() {
  $field = Field::newInstance()->findBySlug('tourist_accommodation_type');
  return (is_array($field) && isset($field['pk_i_id'])) ? (int) $field['pk_i_id'] : 0;
}

// Creates one new item for a validated entry (see tourist_directory_validate_row) in $catId, then
// inserts its marker row. Returns array('ok'=>bool, 'item_id'=>int|null, 'error'=>string|null).
function tourist_directory_create(array $entry, $catId) {
  $ctx = tourist_directory_creation_context($catId);
  tourist_directory_apply_params(tourist_directory_item_params($entry, $ctx));
  tourist_directory_apply_geo_placeholders($entry);

  $itemActions = new ItemActions(true);
  $itemActions->prepareData(true);
  $result = $itemActions->add();

  if (is_string($result)) {
    return array('ok' => false, 'item_id' => null, 'error' => $result);
  }

  $itemId = (int) Params::getParam('itemId');
  tourist_directory_insert_marker($itemId, $entry);

  return array('ok' => true, 'item_id' => $itemId, 'error' => null);
}

// Updates an existing entry's item with the entry's current public fields. $catId is re-resolved by
// the caller from the (possibly changed) destino, since a re-import can move an entry to a
// different destination category.
function tourist_directory_update($itemId, array $entry, $catId) {
  $itemId = (int) $itemId;
  $ctx = tourist_directory_creation_context($catId);
  tourist_directory_apply_params(tourist_directory_item_params($entry, $ctx));
  tourist_directory_apply_geo_placeholders($entry);

  $item = Item::newInstance()->findByPrimaryKey($itemId);
  Params::setParam('id', $itemId);
  Params::setParam('secret', (is_array($item) && isset($item['s_secret'])) ? $item['s_secret'] : '');

  $itemActions = new ItemActions(true);
  $itemActions->prepareData(false);
  $result = $itemActions->edit();

  if (is_string($result) && $result !== '') {
    return array('ok' => false, 'error' => $result);
  }

  tourist_directory_touch_marker($itemId, $entry);

  return array('ok' => true, 'error' => null);
}

function tourist_directory_insert_marker($itemId, array $entry) {
  $now = date('Y-m-d H:i:s');

  tourist_directory_dao()->insert(tourist_directory_table(), array(
    'fk_i_item_id' => (int) $itemId,
    's_seed_id' => isset($entry['id']) ? $entry['id'] : '',
    's_official_website' => isset($entry['web']) ? $entry['web'] : '',
    's_fingerprint' => tourist_directory_fingerprint($entry),
    'dt_imported' => $now,
    'dt_updated' => $now,
  ));
}

function tourist_directory_touch_marker($itemId, array $entry) {
  tourist_directory_dao()->update(tourist_directory_table(), array(
    's_official_website' => isset($entry['web']) ? $entry['web'] : '',
    's_fingerprint' => tourist_directory_fingerprint($entry),
    'dt_updated' => date('Y-m-d H:i:s'),
  ), array('fk_i_item_id' => (int) $itemId));
}

// Every seed id (across the whole removal-request table, not scoped to one seed pass) that
// currently carries a blocking request (pending or processed). Used to build
// tourist_directory_plan()'s 'removal_seed_ids' flag from the importer.
function tourist_directory_blocking_removal_seed_ids() {
  $result = tourist_directory_dao()->query(
    'SELECT DISTINCT s_seed_id FROM ' . tourist_directory_removal_table() . ' WHERE s_status IN (\'pending\', \'processed\')'
  );

  // Fail closed: null tells the importer the removal state is unknown, so it must not write.
  if ($result === false) {
    return null;
  }

  $ids = array();
  foreach ($result->result() as $row) {
    $ids[] = $row['s_seed_id'];
  }

  return $ids;
}

// Race guard for the importer's apply loop: re-checks, fresh, immediately before a create/update/
// reactivate write, whether the target now carries a blocking removal request that was not yet on
// file when the plan was built (e.g. a visitor submitted one between planning and applying).
// Matches by item id (existing entries) OR seed id (a brand-new create has no item id yet).
function tourist_directory_has_blocking_removal_request($itemId, $seedId) {
  $conditions = array();
  if ((int) $itemId > 0) {
    $conditions[] = 'fk_i_item_id = ' . (int) $itemId;
  }
  if ($seedId !== '' && $seedId !== null) {
    $conditions[] = 's_seed_id = ' . tourist_directory_dao()->escape((string) $seedId);
  }

  if (empty($conditions)) {
    return false;
  }

  $result = tourist_directory_dao()->query(
    'SELECT 1 FROM ' . tourist_directory_removal_table() .
    ' WHERE s_status IN (\'pending\', \'processed\') AND (' . implode(' OR ', $conditions) . ') LIMIT 1'
  );

  // Fail closed: an unreadable removal state counts as blocking, so no write proceeds.
  return $result === false || $result->numRows() > 0;
}

function tourist_directory_marker_row($itemId) {
  $itemId = (int) $itemId;
  $result = tourist_directory_dao()->query(
    'SELECT pk_i_id, fk_i_item_id, s_seed_id, s_official_website, s_fingerprint, dt_retired FROM ' .
    tourist_directory_table() . ' WHERE fk_i_item_id = ' . $itemId
  );

  if ($result === false || $result->numRows() === 0) {
    return false;
  }

  return $result->row();
}

// Deactivates the item and stamps the marker retired. Reversible: the item is never deleted, and
// tourist_directory_reactivate() (gated by ctx['allow_reactivate']) can undo it.
function tourist_directory_retire($itemId, $reason = 'baja') {
  $itemId = (int) $itemId;

  if (!is_array(tourist_directory_marker_row($itemId))) {
    return array('ok' => false, 'error' => 'missing_marker');
  }

  $actions = new ItemActions(true);
  $actions->deactivate($itemId);

  tourist_directory_dao()->update(tourist_directory_table(), array(
    'dt_retired' => date('Y-m-d H:i:s'),
    's_retired_reason' => substr((string) $reason, 0, 32),
  ), array('fk_i_item_id' => $itemId));

  return array('ok' => true, 'error' => null);
}

// Never reactivates unless $ctx['allow_reactivate'] is explicitly truthy -- the CLI's
// --allow-reactivate flag is the only intended caller of that gate.
function tourist_directory_reactivate($itemId, array $ctx = array()) {
  if (empty($ctx['allow_reactivate'])) {
    return array('ok' => false, 'error' => 'reactivate_not_allowed');
  }

  $itemId = (int) $itemId;

  if (!is_array(tourist_directory_marker_row($itemId))) {
    return array('ok' => false, 'error' => 'missing_marker');
  }

  $actions = new ItemActions(true);
  $actions->activate($itemId);

  tourist_directory_dao()->update(tourist_directory_table(), array(
    'dt_retired' => null,
    's_retired_reason' => null,
  ), array('fk_i_item_id' => $itemId));

  return array('ok' => true, 'error' => null);
}

// ------------------------------------------------------------------------------------------------
// Amendment (U7): public removal-request channel -- DB reads/writes backing the pure
// tourist_directory_removal_decide()/_validate_removal() decisions. Every DECISION still lives in
// the lib; this section only resolves the inputs those decisions need and applies their outcome.
// ------------------------------------------------------------------------------------------------

// One removal-request row by its own primary key, or false. Used by the admin screen's per-row
// actions.
function tourist_directory_removal_request_row($requestId) {
  $requestId = (int) $requestId;

  if ($requestId <= 0) {
    return false;
  }

  $result = tourist_directory_dao()->query(
    'SELECT pk_i_id, fk_i_item_id, s_seed_id, s_relation, s_reply_contact, s_reason, s_status, dt_requested, dt_processed FROM ' .
    tourist_directory_removal_table() . ' WHERE pk_i_id = ' . $requestId
  );

  if ($result === false || $result->numRows() === 0) {
    return false;
  }

  return $result->row();
}

// Every removal-request row, newest first. No pagination: this is an operator screen for a
// low-volume table (one row per valid submission, deduplicated by tourist_directory_removal_decide()'s
// already_requested outcome), not a public listing.
function tourist_directory_removal_requests_all() {
  $result = tourist_directory_dao()->query(
    'SELECT pk_i_id, fk_i_item_id, s_seed_id, s_relation, s_reply_contact, s_reason, s_status, dt_requested, dt_processed FROM ' .
    tourist_directory_removal_table() . ' ORDER BY dt_requested DESC'
  );

  if ($result === false) {
    return array();
  }

  return $result->result();
}

// Prior (not counting the current attempt) submissions from the same hashed IP in the last hour.
// Fails closed: an unreadable count is treated as the maximum, so a DB hiccup throttles rather than
// silently allowing an unlimited rate.
function tourist_directory_removal_count_ip_last_hour($ipHash) {
  $result = tourist_directory_dao()->query(
    'SELECT COUNT(*) AS c FROM ' . tourist_directory_removal_table() .
    ' WHERE s_ip_hash = ' . tourist_directory_dao()->escape((string) $ipHash) .
    ' AND dt_requested >= (NOW() - INTERVAL 1 HOUR)'
  );

  if ($result === false || $result->numRows() === 0) {
    return PHP_INT_MAX;
  }

  $row = $result->row();
  return isset($row['c']) ? (int) $row['c'] : PHP_INT_MAX;
}

// Prior (not counting the current attempt) submissions for the same item in the last 24 hours.
// Same fail-closed contract as tourist_directory_removal_count_ip_last_hour().
function tourist_directory_removal_count_entry_last_24h($itemId) {
  $itemId = (int) $itemId;

  $result = tourist_directory_dao()->query(
    'SELECT COUNT(*) AS c FROM ' . tourist_directory_removal_table() .
    ' WHERE fk_i_item_id = ' . $itemId .
    ' AND dt_requested >= (NOW() - INTERVAL 24 HOUR)'
  );

  if ($result === false || $result->numRows() === 0) {
    return PHP_INT_MAX;
  }

  $row = $result->row();
  return isset($row['c']) ? (int) $row['c'] : PHP_INT_MAX;
}

// Inserts one pending removal-request row. $value is tourist_directory_validate_removal()'s
// already-validated 'value' (relation/reply_contact/reason, all trimmed); empty optional strings are
// stored as SQL NULL, not ''. Returns bool. Never throws: a failed insert must let the caller show a
// generic error and skip the retire step (insert-first contract, design.md's Amendment "Decision").
function tourist_directory_insert_removal_request($itemId, $seedId, array $value, $ipHash) {
  try {
    return tourist_directory_dao()->insert(tourist_directory_removal_table(), array(
      'fk_i_item_id' => (int) $itemId,
      's_seed_id' => (string) $seedId,
      's_relation' => $value['relation'],
      's_reply_contact' => ($value['reply_contact'] !== '') ? $value['reply_contact'] : null,
      's_reason' => ($value['reason'] !== '') ? $value['reason'] : null,
      's_ip_hash' => ($ipHash !== '') ? $ipHash : null,
      's_status' => 'pending',
      'dt_requested' => date('Y-m-d H:i:s'),
    )) === true;
  } catch (\Throwable $e) {
    return false;
  }
}

function tourist_directory_mark_request_processed($requestId) {
  tourist_directory_dao()->update(tourist_directory_removal_table(), array(
    's_status' => 'processed',
    'dt_processed' => date('Y-m-d H:i:s'),
  ), array('pk_i_id' => (int) $requestId));
}

// Rejects every currently-blocking (pending/processed) request for one item, not only the row the
// admin clicked -- in the ordinary case there is exactly one, but this stays correct even if more
// than one ever exists. Called only from the explicit admin 'reactivate' action (never automatic).
function tourist_directory_reject_all_blocking_requests($itemId) {
  $itemId = (int) $itemId;

  tourist_directory_dao()->query(
    'UPDATE ' . tourist_directory_removal_table() .
    ' SET s_status = \'rejected\', dt_processed = ' . tourist_directory_dao()->escape(date('Y-m-d H:i:s')) .
    ' WHERE fk_i_item_id = ' . $itemId . ' AND s_status IN (\'pending\', \'processed\')'
  );
}

// Retention housekeeping (design.md's Amendment "Retention" row), run once per admin page load
// (task 7.10) -- never from a public request. The status row itself is kept forever, for importer
// precedence; only the IP hash and the optional free-text fields are pruned.
function tourist_directory_removal_apply_retention() {
  $dao = tourist_directory_dao();
  $table = tourist_directory_removal_table();

  $dao->query(
    'UPDATE ' . $table . ' SET s_ip_hash = NULL' .
    ' WHERE s_ip_hash IS NOT NULL AND dt_requested < (NOW() - INTERVAL 30 DAY)'
  );

  $dao->query(
    'UPDATE ' . $table . ' SET s_reply_contact = NULL, s_reason = NULL' .
    ' WHERE dt_processed IS NOT NULL AND dt_processed < (NOW() - INTERVAL 180 DAY)' .
    ' AND (s_reply_contact IS NOT NULL OR s_reason IS NOT NULL)'
  );
}

// One shared, non-leaking confirmation shown for honeypot (fake success -- nothing was written),
// already_requested (must look identical to a fresh accept), accept, and accept_retired. It never
// asserts a specific before/after state, so it is truthful for all four outcomes without
// distinguishing them from one another.
function tourist_directory_removal_confirmation_message($locale) {
  return tourist_directory_text(
    'Recibimos tu solicitud. Si corresponde, procesaremos la baja de la ficha.',
    'We received your request. If applicable, we will process the listing removal.',
    $locale
  );
}

function tourist_directory_removal_invalid_message($locale) {
  return tourist_directory_text(
    'Revisá los datos del formulario (relación con el complejo obligatoria) e intentá nuevamente.',
    'Please check the form (relation to the property is required) and try again.',
    $locale
  );
}

// Shared by 'not_found' and 'throttled' so neither response discloses which of the two actually
// happened -- an invalid id and a rate-limited one look identical.
function tourist_directory_removal_unavailable_message($locale) {
  return tourist_directory_text(
    'No pudimos procesar tu solicitud en este momento. Intentá nuevamente más tarde.',
    'We could not process your request right now. Please try again later.',
    $locale
  );
}

function tourist_directory_removal_channel_unavailable_message($locale) {
  return tourist_directory_text(
    'El canal de solicitudes de baja está temporalmente no disponible. Intentá nuevamente más tarde.',
    'The removal request channel is temporarily unavailable. Please try again later.',
    $locale
  );
}

// The init_custom POST handler (design.md's Amendment "POST handler" architecture decision). Acts
// only for our own route and only on POST -- a GET to the same route just renders the view, doing
// nothing here. osc_csrf_check() runs first and exits on failure (hSecurity.php:85-133), so every
// path below it already carries a valid token. The entry id comes ONLY from Params::getParam('entry')
// (the route param set by Rewrite::init()), never from a posted field -- the view never renders an
// id input, so there is nothing for a client to override even if it tried.
function tourist_directory_init_custom_removal_post() {
  if (Params::getParam('route') !== TOURIST_DIRECTORY_REMOVAL_ROUTE) {
    return;
  }

  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
  }

  osc_csrf_check();

  $locale = osc_current_user_locale();
  $entryId = (int) Params::getParam('entry');
  $redirectUrl = osc_route_url(TOURIST_DIRECTORY_REMOVAL_ROUTE, array('entry' => $entryId));

  if (!tourist_directory_is_channel_ready()) {
    osc_add_flash_error_message(tourist_directory_removal_channel_unavailable_message($locale));
    osc_redirect_to($redirectUrl);
    return;
  }

  $marker = tourist_directory_marker_row($entryId);
  $entryExists = is_array($marker);
  $seedId = $entryExists ? $marker['s_seed_id'] : '';
  $entryRetired = $entryExists && $marker['dt_retired'] !== null;
  $blocking = $entryExists ? tourist_directory_has_blocking_removal_request($entryId, $seedId) : false;

  $ip = tourist_directory_client_ip($_SERVER);
  $ipHash = tourist_directory_ip_hash($ip, tourist_directory_ip_salt());
  $ipCount = tourist_directory_removal_count_ip_last_hour($ipHash);
  $entryCount = $entryExists ? tourist_directory_removal_count_entry_last_24h($entryId) : 0;

  $decision = tourist_directory_removal_decide(array(
    // The view's honeypot input is named 'website' -- a plausible-looking field for a bot to fill,
    // never shown to a human (see views/removal-form.php and the CSS off-screen rule).
    'honeypot' => Params::getParam('website'),
    'relation' => Params::getParam('relation'),
    'reply_contact' => Params::getParam('reply_contact'),
    'reason' => Params::getParam('reason'),
    'entry_exists' => $entryExists,
    'blocking_request_exists' => $blocking,
    'entry_retired' => $entryRetired,
    'ip_count_last_hour' => $ipCount,
    'entry_count_last_24h' => $entryCount,
  ));

  if ($decision === 'invalid') {
    osc_add_flash_error_message(tourist_directory_removal_invalid_message($locale));
    osc_redirect_to($redirectUrl);
    return;
  }

  if ($decision === 'not_found' || $decision === 'throttled') {
    osc_add_flash_error_message(tourist_directory_removal_unavailable_message($locale));
    osc_redirect_to($redirectUrl);
    return;
  }

  if ($decision === 'honeypot' || $decision === 'already_requested') {
    // Fake success for the honeypot (nothing was written); the identical confirmation for
    // already_requested (nothing is written either -- see design.md's Amendment "Decision" row).
    osc_add_flash_ok_message(tourist_directory_removal_confirmation_message($locale));
    osc_redirect_to($redirectUrl);
    return;
  }

  // Only 'accept' and 'accept_retired' remain: both insert; only 'accept' also retires. Insert
  // first -- if it fails, nothing changes and the entry is never retired unrecorded.
  $validated = tourist_directory_validate_removal(array(
    'relation' => Params::getParam('relation'),
    'reply_contact' => Params::getParam('reply_contact'),
    'reason' => Params::getParam('reason'),
  ));

  $inserted = $validated['ok']
    ? tourist_directory_insert_removal_request($entryId, $seedId, $validated['value'], $ipHash)
    : false;

  if (!$inserted) {
    osc_add_flash_error_message(tourist_directory_removal_unavailable_message($locale));
    osc_redirect_to($redirectUrl);
    return;
  }

  if ($decision === 'accept') {
    // If retire fails here, the request row is already inserted and stays 'pending' -- visible to
    // the admin, who can investigate; it is never silently lost (design.md's Amendment "Decision").
    tourist_directory_retire($entryId, 'removal_request');
  }

  osc_add_flash_ok_message(tourist_directory_removal_confirmation_message($locale));
  osc_redirect_to($redirectUrl);
}

osc_add_hook('init_custom', 'tourist_directory_init_custom_removal_post');

// The admin screen's POST handler (design.md's Amendment "Admin page" architecture decision).
// renderplugin_controller (oc-admin/plugins.php:456) fires for every renderplugin request
// regardless of route, so this checks its own route first, exactly like the public handler above.
// AdminSecBaseModel already enforced the admin session and the moderator-access allowlist before
// this ever runs (oc-includes/osclass/core/AdminSecBaseModel.php:35-45).
function tourist_directory_admin_requests_handle_post() {
  if (Params::getParam('route') !== TOURIST_DIRECTORY_ADMIN_ROUTE) {
    return;
  }

  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
  }

  osc_csrf_check();

  $locale = osc_current_admin_locale();
  $redirectUrl = osc_route_admin_url(TOURIST_DIRECTORY_ADMIN_ROUTE);
  $requestId = (int) Params::getParam('id');
  $action = (string) Params::getParam(tourist_directory_admin_action_param());
  $confirm = (int) Params::getParam('confirm');

  $row = tourist_directory_removal_request_row($requestId);

  if (!is_array($row)) {
    osc_add_flash_error_message(tourist_directory_text('Solicitud no encontrada.', 'Request not found.', $locale), 'admin');
    osc_redirect_to($redirectUrl);
    return;
  }

  $transition = tourist_directory_admin_transition($row['s_status'], $action, $confirm);

  if (!$transition['ok']) {
    $message = ($transition['error'] === 'confirm_required')
      ? tourist_directory_text('Confirmá la reactivación marcando la casilla.', 'Confirm the reactivation by checking the box.', $locale)
      : tourist_directory_text('Esa acción no es válida para el estado actual de la solicitud.', 'That action is not valid for the request\'s current status.', $locale);

    osc_add_flash_error_message($message, 'admin');
    osc_redirect_to($redirectUrl);
    return;
  }

  if ($action === 'mark_processed') {
    tourist_directory_mark_request_processed($requestId);
    osc_add_flash_ok_message(tourist_directory_text('Solicitud marcada como procesada.', 'Request marked as processed.', $locale), 'admin');
  } else {
    // 'reactivate': reject every blocking request for the item (not only this row), then
    // reactivate the item itself -- the only caller allowed to pass allow_reactivate=true outside
    // the CLI's --allow-reactivate flag, since this is the explicit admin action design.md's
    // "Removal Request Admin" requirement describes.
    tourist_directory_reject_all_blocking_requests($row['fk_i_item_id']);
    tourist_directory_reactivate($row['fk_i_item_id'], array('allow_reactivate' => true));
    osc_add_flash_ok_message(tourist_directory_text('Ficha reactivada.', 'Listing reactivated.', $locale), 'admin');
  }

  osc_redirect_to($redirectUrl);
}

osc_add_hook('renderplugin_controller', 'tourist_directory_admin_requests_handle_post');

// Admin menu entry (task 7.9). osc_route_admin_url() always builds a
// ?page=plugins&action=renderplugin&route=... admin URL regardless of rewrite settings (hDefines.php
// osc_route_admin_url()), so no pretty-URL dependency exists for the admin screen.
function tourist_directory_admin_menu_init() {
  osc_admin_menu_plugins(
    'Tourist Directory Entries',
    osc_route_admin_url(TOURIST_DIRECTORY_ADMIN_ROUTE),
    'tourist_directory_requests'
  );
}

osc_add_hook('admin_menu_init', 'tourist_directory_admin_menu_init');

// Admin-side noindex counterpart to tourist_directory_noindex_header() above -- the admin theme
// never fires 'header', it fires 'admin_header' instead, inside <head> (omega/parts/header.php:41).
function tourist_directory_noindex_admin_header() {
  if (Params::getParam('page') === 'plugins'
    && Params::getParam('action') === 'renderplugin'
    && Params::getParam('route') === TOURIST_DIRECTORY_ADMIN_ROUTE
  ) {
    echo '<meta name="robots" content="noindex, nofollow, noarchive" />' . "\n";
  }
}

osc_add_hook('admin_header', 'tourist_directory_noindex_admin_header');

osc_register_plugin(TOURIST_DIRECTORY_PLUGIN, 'tourist_directory_install');
osc_add_hook(TOURIST_DIRECTORY_PLUGIN . '_enable', 'tourist_directory_enable');
osc_add_hook(TOURIST_DIRECTORY_PLUGIN . '_disable', 'tourist_directory_disable');
osc_add_hook(TOURIST_DIRECTORY_PLUGIN . '_uninstall', 'tourist_directory_uninstall');
