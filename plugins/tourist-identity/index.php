<?php
/*
Plugin Name: Tourist Portal Identity
Plugin URI: https://alquileres.diazignacio.ar/
Description: Applies the Argentine short-term-rental portal identity: branding copy, a 6-region/51-leaf destination category tree, ARS-only currency, and removal of the two seeded test listings. Idempotent and reversible.
Version: 1.1.0
Author: alquileres.diazignacio.ar
Short Name: tourist-identity
*/

if (!defined('ABS_PATH')) {
  exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

require_once __DIR__ . '/tourist-identity-lib.php';
require_once __DIR__ . '/tourist-identity-tree.php';

define('TOURIST_IDENTITY_PLUGIN', osc_plugin_path(__FILE__));
define('TOURIST_IDENTITY_SECTION', 'tourist_identity');
define('TOURIST_IDENTITY_KEEP_CATEGORY', 47);
define('TOURIST_IDENTITY_TARGET_CURRENCY', 'ARS');
define('TOURIST_IDENTITY_LOGO_FILE', 'tourist_identity_logo.svg');

// --- Osclass-bound reads/writes. All decisions (what changed, what to plan)
// live in tourist-identity-lib.php; this file only talks to Osclass state.

function tourist_identity_current_prefs() {
  $sections = array();
  $current = array();

  foreach (tourist_identity_snapshot_pref_keys() as $pref_key) {
    list($section, $name) = $pref_key;

    if (!array_key_exists($section, $sections)) {
      $sections[$section] = osc_get_preference_section($section);
    }

    $key = $section . '.' . $name;
    $current[$key] = array_key_exists($name, $sections[$section]) ? $sections[$section][$name] : null;
  }

  return $current;
}

function tourist_identity_apply_prefs() {
  $changes = 0;
  $plan = tourist_identity_pref_plan(tourist_identity_current_prefs(), tourist_identity_target_prefs());

  foreach ($plan as $change) {
    osc_set_preference($change['name'], $change['value'], $change['section']);
    $changes++;
  }

  return $changes;
}

// Reads the persisted map of stable tree keys ("buenos-aires", "tandil", ...) to the category
// ids the tree apply routine created for them. The anchor (47) is never part of this map: it is
// a pre-existing row, not one the routine created, and its identity is tracked by its own
// snapshot_tree pref instead. Returns an empty array if the routine has never run yet.
function tourist_identity_read_category_map() {
  $raw = osc_get_preference('category_map', TOURIST_IDENTITY_SECTION);

  if ($raw === false || $raw === '') {
    return array();
  }

  $decoded = json_decode($raw, true);

  return is_array($decoded) ? $decoded : array();
}

function tourist_identity_save_category_map(array $map) {
  osc_set_preference('category_map', json_encode($map), TOURIST_IDENTITY_SECTION);
}

// Every non-tree category (the 94 seeded defaults, and anything else already in the DB) is
// disabled, exactly like before the destination tree existed. The tree's own rows -- the anchor
// plus every id in the created-id map -- are excluded from this plan and left entirely to
// tourist_identity_apply_tree(), so re-running this routine never fights the tree plan over the
// b_enabled flag of a row it does not own.
function tourist_identity_apply_categories() {
  $changes = 0;
  $tree_ids = array_merge(
    array(TOURIST_IDENTITY_KEEP_CATEGORY),
    array_map('intval', array_values(tourist_identity_read_category_map()))
  );

  $rows = tourist_identity_rows_excluding_ids(Category::newInstance()->listAll(), $tree_ids);
  $plan = tourist_identity_category_plan($rows, 0);

  foreach ($plan as $id => $fields) {
    Category::newInstance()->update($fields, array('pk_i_id' => $id));
    $changes++;
  }

  if ($changes > 0) {
    osc_update_cat_stats();
  }

  return $changes;
}

// Reads every installed locale's row from t_category_description for the given category ids, as
// $descs[$id][$locale] = ['s_name'=>.., 's_description'=>.., 's_slug'=>..], the exact shape
// tourist_identity_tree_plan() expects. Returns an empty array for an empty/invalid id list or on
// query failure, never false or null.
function tourist_identity_read_descriptions(array $ids) {
  $ids = array_values(array_unique(array_map('intval', $ids)));

  if (empty($ids)) {
    return array();
  }

  $dao = Category::newInstance()->dao;
  $dao->select('fk_i_category_id, fk_c_locale_code, s_name, s_description, s_slug');
  $dao->from(DB_TABLE_PREFIX . 't_category_description');
  $dao->whereIn('fk_i_category_id', $ids);
  $result = $dao->get();

  $descs = array();

  if ($result === false) {
    return $descs;
  }

  foreach ($result->result() as $row) {
    $descs[(int)$row['fk_i_category_id']][$row['fk_c_locale_code']] = array(
      's_name' => $row['s_name'],
      's_description' => $row['s_description'],
      's_slug' => $row['s_slug'],
    );
  }

  return $descs;
}

// Counts listings directly filed under $categoryId with a dedicated COUNT(*) query (never
// Item::totalItems(), which returns 0 -- indistinguishable from "actually empty" -- on query
// failure). Returns null, not 0, when the count itself cannot be trusted, so callers that use it
// as a delete guard (tourist_identity_uninstall_plan()) disable instead of deleting.
// Every category row with its parent, regardless of enabled flag or description locale.
// Returns null when the query fails so callers skip destructive work.
function tourist_identity_category_parent_rows() {
  $dao = Category::newInstance()->dao;
  $dao->select('pk_i_id, fk_i_parent_id');
  $dao->from(DB_TABLE_PREFIX . 't_category');
  $result = $dao->get();

  if ($result === false) {
    return null;
  }

  $rows = array();
  foreach ($result->result() as $row) {
    $rows[(int)$row['pk_i_id']] = array('fk_i_parent_id' => $row['fk_i_parent_id'] === null ? null : (int)$row['fk_i_parent_id']);
  }

  return $rows;
}

function tourist_identity_item_count($categoryId) {
  $dao = Category::newInstance()->dao;
  $dao->select('COUNT(*) as c');
  $dao->from(DB_TABLE_PREFIX . 't_item');
  $dao->where('fk_i_category_id', (int)$categoryId);
  $result = $dao->get();

  if ($result === false) {
    return null;
  }

  $row = $result->row();

  return isset($row['c']) ? (int)$row['c'] : null;
}

// Captures the anchor's (47) pre-tree position and per-locale description into
// tourist_identity.snapshot_tree, but only if that pref does not exist yet. This runs before any
// tree write on every apply/re-apply, so a site that already had the 1.0.0 plugin installed
// (whose original tourist_identity.snapshot lacks this data) still gets exactly one supplementary
// capture, taken before the tree ever repurposes category 47.
function tourist_identity_ensure_tree_snapshot() {
  $section = osc_get_preference_section(TOURIST_IDENTITY_SECTION);

  if (array_key_exists('snapshot_tree', $section)) {
    return;
  }

  $anchor_row = Category::newInstance()->findByPrimaryKey(TOURIST_IDENTITY_KEEP_CATEGORY);
  $anchor_descs = tourist_identity_read_descriptions(array(TOURIST_IDENTITY_KEEP_CATEGORY));
  $anchor_descs = isset($anchor_descs[TOURIST_IDENTITY_KEEP_CATEGORY]) ? $anchor_descs[TOURIST_IDENTITY_KEEP_CATEGORY] : array();

  $snapshot = tourist_identity_build_tree_snapshot(is_array($anchor_row) ? $anchor_row : array(), $anchor_descs);
  osc_set_preference('snapshot_tree', $snapshot, TOURIST_IDENTITY_SECTION);
}

// Resolves the locales the tree apply routine writes descriptions for: es_ES/en_US, restricted to
// whichever of those two are actually installed on this site (any other installed locale is left
// untouched, per tourist_identity_tree_locales()).
function tourist_identity_installed_tree_locales() {
  return tourist_identity_tree_locales(array_keys(osc_get_locales_all('ALL', true)));
}

// Writes or updates one category_description row (insert if the UPDATE matched no row, since
// t_category_description has no auto-generated key to react to -- its primary key is exactly
// (fk_i_category_id, fk_c_locale_code)).
function tourist_identity_write_description($categoryId, $locale, array $fields) {
  $affected = Category::newInstance()->dao->update(
    DB_TABLE_PREFIX . 't_category_description',
    $fields,
    array('fk_i_category_id' => (int)$categoryId, 'fk_c_locale_code' => $locale)
  );

  if ($affected === false || $affected === 0) {
    Category::newInstance()->insertDescription(array_merge($fields, array(
      'fk_i_category_id' => (int)$categoryId,
      'fk_c_locale_code' => $locale,
    )));
  }
}

// Resolves a free slug for $categoryId via Category::findBySlug(), the same lookup Osclass uses
// for public category URLs.
function tourist_identity_owner_of_slug($slug) {
  $owner = Category::newInstance()->findBySlug($slug);

  return isset($owner['pk_i_id']) ? (int)$owner['pk_i_id'] : null;
}

// Applies the 6-region/51-leaf destination tree against current DB state: inserts every region
// and leaf category row the created-id map does not yet resolve (regions before their leaves, so
// a leaf insert can always resolve its parent from the map), then updates/describes every
// already-existing tree row (the anchor and any previously created row) per
// tourist_identity_tree_plan(). Every insert saves the map immediately, before its description
// rows are written, so a crash mid-apply never loses track of a category the DB already has.
function tourist_identity_apply_tree() {
  $rows = array();
  foreach (Category::newInstance()->listAll() as $row) {
    $rows[(int)$row['pk_i_id']] = $row;
  }

  if (!isset($rows[TOURIST_IDENTITY_KEEP_CATEGORY])) {
    return 0;
  }

  $persisted_map = tourist_identity_read_category_map();
  $locales = tourist_identity_installed_tree_locales();
  $tree = tourist_identity_tree();

  $describe_ids = array_merge(array(TOURIST_IDENTITY_KEEP_CATEGORY), array_map('intval', array_values($persisted_map)));
  $descs = tourist_identity_read_descriptions($describe_ids);

  $plan = tourist_identity_tree_plan($tree, $rows, $descs, $persisted_map, $locales, $rows[TOURIST_IDENTITY_KEEP_CATEGORY]);

  // Resolves insert parents by key: seeded with the persisted map plus the anchor's own key (the
  // anchor is never written back into the persisted map -- it is a pre-existing row, not one this
  // routine created).
  $key_ids = $persisted_map;
  foreach ($tree as $region) {
    if (array_key_exists('anchor', $region)) {
      $key_ids[$region['key']] = (int)$region['anchor'];
    }
  }

  $changes = 0;

  foreach ($plan['insert'] as $entry) {
    $parent_id = ($entry['parent_key'] === null || !isset($key_ids[$entry['parent_key']]))
      ? null
      : (int)$key_ids[$entry['parent_key']];

    $fields = array_merge($entry['fields'], array(
      'fk_i_parent_id' => $parent_id,
      'i_position' => $entry['position'],
      'i_expiration_days' => (int)$rows[TOURIST_IDENTITY_KEEP_CATEGORY]['i_expiration_days'],
      'b_price_enabled' => (int)$rows[TOURIST_IDENTITY_KEEP_CATEGORY]['b_price_enabled'],
    ));

    $new_id = (int)Category::newInstance()->insert($fields, array());
    if ($new_id <= 0) {
      // Insert failed: record nothing so the next re-apply retries this node.
      continue;
    }

    $key_ids[$entry['key']] = $new_id;
    $persisted_map[$entry['key']] = $new_id;
    tourist_identity_save_category_map($persisted_map);
    $changes++;

    foreach ($locales as $locale) {
      $slug = tourist_identity_resolve_slug($entry['key'], 'tourist_identity_owner_of_slug', $new_id);
      tourist_identity_write_description($new_id, $locale, array(
        's_name' => isset($entry['names'][$locale]) ? $entry['names'][$locale] : $entry['key'],
        's_description' => '',
        's_slug' => $slug,
      ));
    }
  }

  foreach ($plan['update'] as $id => $fields) {
    Category::newInstance()->update($fields, array('pk_i_id' => (int)$id));
    $changes++;
  }

  foreach ($plan['describe'] as $id => $per_locale) {
    foreach ($per_locale as $locale => $target) {
      $slug = tourist_identity_resolve_slug($target['s_slug'], 'tourist_identity_owner_of_slug', (int)$id);
      tourist_identity_write_description((int)$id, $locale, array(
        's_name' => $target['s_name'],
        's_description' => $target['s_description'],
        's_slug' => $slug,
      ));
      $changes++;
    }
  }

  if ($changes > 0) {
    osc_update_cat_stats();
  }

  return $changes;
}

function tourist_identity_apply_currencies() {
  $changes = 0;
  $rows = Currency::newInstance()->listAll();
  $plan = tourist_identity_currency_plan($rows, TOURIST_IDENTITY_TARGET_CURRENCY);

  foreach ($plan as $code => $fields) {
    if (!empty($fields['insert'])) {
      Currency::newInstance()->insert(array(
        'pk_c_code' => $code,
        's_name' => 'Peso Argentino',
        's_description' => '$',
        'b_enabled' => 1,
      ));
    } else {
      Currency::newInstance()->update(array('b_enabled' => $fields['b_enabled']), array('pk_c_code' => $code));
    }
    $changes++;
  }

  return $changes;
}

function tourist_identity_apply_logo() {
  $source = __DIR__ . '/assets/logo.svg';
  $target_path = osc_uploads_path() . TOURIST_IDENTITY_LOGO_FILE;
  $already_installed = osc_get_preference('logo', 'sigma') === TOURIST_IDENTITY_LOGO_FILE && file_exists($target_path);

  if ($already_installed) {
    return 0;
  }

  if (file_exists($source)) {
    @copy($source, $target_path);
  }

  osc_set_preference('logo', TOURIST_IDENTITY_LOGO_FILE, 'sigma');

  return 1;
}

// Links tourist-showcase's metadata fields to exactly the 51 leaf destinations (never the
// regions, which are pure navigation nodes with no listings of their own).
function tourist_identity_apply_showcase_link() {
  if (!function_exists('tourist_showcase_save_categories') || !function_exists('tourist_showcase_selected_categories')) {
    return 0;
  }

  $leaf_ids = tourist_identity_leaf_ids(tourist_identity_tree(), tourist_identity_read_category_map());

  if (empty($leaf_ids) || !tourist_identity_showcase_link_needed(tourist_showcase_selected_categories(), $leaf_ids)) {
    return 0;
  }

  tourist_showcase_save_categories($leaf_ids);

  return 1;
}

function tourist_identity_delete_seed_items() {
  $changes = 0;
  $actions = new ItemActions(true);

  foreach (array(1, 2) as $item_id) {
    $item = Item::newInstance()->findByPrimaryKey($item_id);

    if ($item) {
      $actions->delete($item['s_secret'], $item_id);
      $changes++;
    }
  }

  return $changes;
}

function tourist_identity_apply() {
  // The supplementary tree snapshot MUST exist before any category write below, so an
  // already-installed 1.0.0 site still gets a pre-tree capture of 47's name/description.
  tourist_identity_ensure_tree_snapshot();

  $changes = 0;

  $changes += tourist_identity_delete_seed_items();
  $changes += tourist_identity_apply_categories();
  $changes += tourist_identity_apply_tree();
  $changes += tourist_identity_apply_currencies();
  $changes += tourist_identity_apply_prefs();
  $changes += tourist_identity_apply_logo();
  $changes += tourist_identity_apply_showcase_link();

  osc_set_preference('applied_version', tourist_identity_version(), TOURIST_IDENTITY_SECTION);
  // Persisted (not just held in this request's $changes) so the configure screen can render the
  // outcome of the last run on a plain page load too, independent of the transient flash message.
  osc_set_preference('last_reapply_changes', (string)$changes, TOURIST_IDENTITY_SECTION);
  osc_cache_flush();

  return $changes;
}

function tourist_identity_snapshot_exists() {
  $section = osc_get_preference_section(TOURIST_IDENTITY_SECTION);
  return array_key_exists('snapshot', $section);
}

function tourist_identity_capture_snapshot() {
  $prefs = tourist_identity_current_prefs();
  $categories = Category::newInstance()->listAll();
  $currencies = Currency::newInstance()->listAll();

  $snapshot = tourist_identity_build_snapshot($prefs, $categories, $currencies);
  osc_set_preference('snapshot', $snapshot, TOURIST_IDENTITY_SECTION);
}

function tourist_identity_install() {
  if (!tourist_identity_snapshot_exists()) {
    tourist_identity_capture_snapshot();
  }

  tourist_identity_apply();
}

function tourist_identity_restore_prefs(array $prefs) {
  foreach (tourist_identity_snapshot_pref_keys() as $pref_key) {
    list($section, $name) = $pref_key;
    $key = $section . '.' . $name;
    $value = array_key_exists($key, $prefs) ? $prefs[$key] : null;

    if ($value === null) {
      osc_delete_preference($name, $section);
    } else {
      osc_set_preference($name, $value, $section);
    }
  }
}

function tourist_identity_restore_categories(array $categories) {
  foreach ($categories as $row) {
    Category::newInstance()->update(
      array('b_enabled' => (int)$row['b_enabled'], 'fk_i_parent_id' => $row['fk_i_parent_id']),
      array('pk_i_id' => (int)$row['pk_i_id'])
    );
  }

  osc_update_cat_stats();
}

function tourist_identity_restore_currencies(array $currencies) {
  foreach ($currencies as $row) {
    Currency::newInstance()->update(array('b_enabled' => (int)$row['b_enabled']), array('pk_c_code' => $row['pk_c_code']));
  }

  // Our own ARS row is never part of the pre-existing snapshot: it stays
  // present but disabled, per the Restore on Uninstall requirement.
  Currency::newInstance()->update(array('b_enabled' => 0), array('pk_c_code' => TOURIST_IDENTITY_TARGET_CURRENCY));
}

// Reverts every category the destination-tree apply routine created: deletes the ones holding
// zero items, disables (never deletes) the ones still in use, restores the anchor's pre-tree
// position/description from snapshot_tree, and prunes any deleted id out of
// tourist_showcase.category_ids. Safe to call even if the tree was never applied (empty map ->
// empty plan -> no-op).
function tourist_identity_restore_tree() {
  // Read the parent structure straight from t_category: Category::listAll() inner-joins the
  // admin-locale descriptions and would hide children without that locale, making a region look
  // childless and letting the vendor delete cascade remove those children and their items.
  $rows = tourist_identity_category_parent_rows();
  $map = tourist_identity_read_category_map();

  if ($rows === null) {
    // Structure unknown: never delete; disable every created category and keep the map.
    $plan = array('delete' => array(), 'disable' => array(), 'map' => $map);
    foreach ($map as $id) {
      if ((int)$id !== TOURIST_IDENTITY_KEEP_CATEGORY) {
        $plan['disable'][] = (int)$id;
      }
    }
  } else {
    $counts = array();
    foreach ($map as $id) {
      $counts[(int)$id] = tourist_identity_item_count((int)$id);
    }

    $plan = tourist_identity_uninstall_plan($map, $rows, $counts, array(TOURIST_IDENTITY_KEEP_CATEGORY));
  }

  foreach ($plan['delete'] as $id) {
    Category::newInstance()->deleteByPrimaryKey((int)$id);
  }

  foreach ($plan['disable'] as $id) {
    Category::newInstance()->update(array('b_enabled' => 0), array('pk_i_id' => (int)$id));
  }

  if (!empty($plan['delete']) || !empty($plan['disable'])) {
    osc_update_cat_stats();
  }

  if (!empty($plan['map'])) {
    tourist_identity_save_category_map($plan['map']);
  } else {
    osc_delete_preference('category_map', TOURIST_IDENTITY_SECTION);
  }

  if (!empty($plan['delete']) && function_exists('tourist_showcase_selected_categories') && function_exists('tourist_showcase_save_categories')) {
    $selected = tourist_showcase_selected_categories();
    $pruned = tourist_identity_prune_ids($selected, $plan['delete']);

    if ($pruned !== $selected) {
      tourist_showcase_save_categories($pruned);
    }
  }

  $section = osc_get_preference_section(TOURIST_IDENTITY_SECTION);

  if (array_key_exists('snapshot_tree', $section)) {
    $restore = tourist_identity_tree_restore_plan($section['snapshot_tree']);

    if ($restore['id']) {
      Category::newInstance()->update(
        array('i_position' => (int)$restore['i_position']),
        array('pk_i_id' => (int)$restore['id'])
      );

      foreach ($restore['descriptions'] as $locale => $desc) {
        tourist_identity_write_description((int)$restore['id'], $locale, $desc);
      }
    }

    osc_delete_preference('snapshot_tree', TOURIST_IDENTITY_SECTION);
  }
}

function tourist_identity_uninstall() {
  $section = osc_get_preference_section(TOURIST_IDENTITY_SECTION);

  if (array_key_exists('snapshot', $section)) {
    $plan = tourist_identity_restore_plan($section['snapshot']);
    tourist_identity_restore_prefs($plan['prefs']);
    tourist_identity_restore_categories($plan['categories']);
    tourist_identity_restore_currencies($plan['currencies']);
  }

  tourist_identity_restore_tree();

  // The snapshot restored the original logo preference; remove only our file.
  if (file_exists(osc_uploads_path() . TOURIST_IDENTITY_LOGO_FILE)) {
    @unlink(osc_uploads_path() . TOURIST_IDENTITY_LOGO_FILE);
  }

  osc_delete_preference('snapshot', TOURIST_IDENTITY_SECTION);
  osc_delete_preference('applied_version', TOURIST_IDENTITY_SECTION);
  osc_delete_preference('last_reapply_changes', TOURIST_IDENTITY_SECTION);

  osc_cache_flush();
}

function tourist_identity_configure() {
  if (Params::getParam('tourist_identity_action') === 'reapply') {
    osc_csrf_check();
    $changes = tourist_identity_apply();
    osc_add_flash_ok_message(sprintf('Re-apply complete: %d change(s).', $changes), 'admin');
  }

  // Read back from the persisted pref, not a local variable: the plugin-admin page for
  // action=admin renders the flash message unreliably, so the last outcome must survive a plain
  // page load too, not only the request that triggered the re-apply.
  $last_changes = osc_get_preference('last_reapply_changes', TOURIST_IDENTITY_SECTION);
  ?>
  <div class="box">
    <div class="box-header"><h1>Tourist Portal Identity</h1></div>
    <div class="box-content">
      <p>Applies the Argentine short-term-rental identity: branding copy, a 6-region/51-leaf destination category tree, ARS-only currency, and removal of the two seeded test listings.</p>
      <p>Re-apply is safe to run again: it only touches values that still differ from the target and reports how many changes it made.</p>
      <?php if ($last_changes !== false && $last_changes !== '') { ?>
        <p><strong><?php echo osc_esc_html(tourist_identity_reapply_message((int)$last_changes)); ?></strong></p>
      <?php } ?>
      <form action="<?php echo osc_esc_html(tourist_identity_configure_url(osc_admin_base_url(true), TOURIST_IDENTITY_PLUGIN, osc_plugins_path())); ?>" method="post">
        <?php echo osc_csrf_token_form(); ?>
        <input type="hidden" name="tourist_identity_action" value="reapply" />
        <button type="submit" class="btn btn-submit">Re-apply</button>
      </form>
    </div>
  </div>
  <?php
}

function tourist_identity_gettext($text) {
  if (defined('OC_ADMIN') && OC_ADMIN === true) {
    return $text;
  }

  return tourist_identity_override_text($text, osc_current_user_locale(), false, tourist_identity_override_map());
}

function tourist_identity_footer() {
  if (defined('OC_ADMIN') && OC_ADMIN === true) {
    return;
  }

  echo '<div class="tourist-identity-disclaimer">' . osc_esc_html(tourist_identity_disclaimer(osc_current_user_locale())) . '</div>';
}

function tourist_identity_init_currencies() {
  if (defined('OC_ADMIN') && OC_ADMIN === true) {
    return;
  }

  $rows = Currency::newInstance()->listAll();
  $enabled = tourist_identity_enabled_currencies($rows);

  View::newInstance()->_exportVariableToView('currencies', $enabled);
}

osc_register_plugin(TOURIST_IDENTITY_PLUGIN, 'tourist_identity_install');
osc_add_hook(TOURIST_IDENTITY_PLUGIN . '_uninstall', 'tourist_identity_uninstall');
osc_add_hook(TOURIST_IDENTITY_PLUGIN . '_configure', 'tourist_identity_configure');
osc_add_hook('gettext', 'tourist_identity_gettext');
osc_add_hook('footer', 'tourist_identity_footer');
osc_add_hook('init', 'tourist_identity_init_currencies');
