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

// Reactivates only entries whose marker was never retired (dt_retired IS NULL). A retired entry
// stays deactivated across enable/disable/reinstall cycles until an explicit --allow-reactivate
// import run reverses it (Phase 3 CLI).
function tourist_directory_reactivate_non_retired_items() {
  $actions = new ItemActions(true);
  foreach (tourist_directory_marker_item_ids('dt_retired IS NULL') as $itemId) {
    $actions->activate($itemId);
  }
}

// $whereSql is always one of the two fixed literals above -- never user input -- so string
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

// Pre-fills the generic site contact form's subject when a visitor follows a "Solicitar baja" link
// (which points at the SITE contact form, osc_contact_url(), never the guarded item contact form).
// init_contact fires at oc-includes/osclass/controller/contact.php:27, before that controller's own
// action switch.
function tourist_directory_init_contact_prefill() {
  $removalId = (int) Params::getParam('tourist_directory_removal');

  if ($removalId <= 0) {
    return;
  }

  if (!is_array(tourist_directory_entry_row($removalId))) {
    return;
  }

  $subject = tourist_directory_text('Solicitud de baja - Ficha de directorio #', 'Removal request - Directory listing #', osc_current_user_locale()) . $removalId;
  Session::newInstance()->_setForm('subject', $subject);
}

osc_add_hook('init_contact', 'tourist_directory_init_contact_prefill');

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
  $removalUrl = tourist_directory_removal_url(osc_contact_url(), $id);

  $website = (isset($row['s_official_website']) && $row['s_official_website'] !== '')
    ? tourist_directory_safe_url($row['s_official_website'])
    : false;

  $html = '<div class="tourist-directory-notice">';
  $html .= '<p class="tourist-directory-label">' . osc_esc_html($label) . '</p>';

  if ($website !== false) {
    $html .= '<a class="tourist-directory-official-link" href="' . osc_esc_html($website) . '" rel="nofollow noopener noreferrer" target="_blank">' . osc_esc_html($visitLabel) . '</a> ';
  }

  $html .= '<a class="tourist-directory-removal-link" href="' . osc_esc_html($removalUrl) . '">' . osc_esc_html($removalLabel) . '</a>';
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

  if (!is_array($row)) {
    return null;
  }

  echo tourist_directory_render_notice_html($id, $row);

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

  if (!is_array($row)) {
    return;
  }

  $badge = tourist_directory_text('Ficha de directorio', 'Directory listing', osc_current_user_locale());

  echo ' <span class="tourist-directory-badge">' . osc_esc_html($badge) . '</span>';
}

// Pure void hook, echoed inline inside a class="..." attribute (loop-single.php:21): a trailing
// space keeps it from merging into the next class name.
function tourist_directory_highlight_class_hook() {
  $id = osc_item_id();
  $row = tourist_directory_entry_row($id);

  if (is_array($row)) {
    echo 'tourist-directory-card ';
  }
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

  $url = osc_plugins_url() . TOURIST_DIRECTORY_CSS_REL_PATH;
  echo '<link rel="stylesheet" type="text/css" href="' . osc_esc_html($url) . '" />' . "\n";
}

osc_add_hook('header', 'tourist_directory_enqueue_css');

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
  );
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

osc_register_plugin(TOURIST_DIRECTORY_PLUGIN, 'tourist_directory_install');
osc_add_hook(TOURIST_DIRECTORY_PLUGIN . '_enable', 'tourist_directory_enable');
osc_add_hook(TOURIST_DIRECTORY_PLUGIN . '_disable', 'tourist_directory_disable');
osc_add_hook(TOURIST_DIRECTORY_PLUGIN . '_uninstall', 'tourist_directory_uninstall');
