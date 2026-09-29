<?php
/*
Plugin Name: Tourist Portal Identity
Plugin URI: https://alquileres.diazignacio.ar/
Description: Applies the Argentine short-term-rental portal identity: branding copy, a single top-level category, ARS-only currency, and removal of the two seeded test listings. Idempotent and reversible.
Version: 1.0.0
Author: alquileres.diazignacio.ar
Short Name: tourist-identity
*/

if (!defined('ABS_PATH')) {
  exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

require_once __DIR__ . '/tourist-identity-lib.php';

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

function tourist_identity_apply_categories() {
  $changes = 0;
  $rows = Category::newInstance()->listAll();
  $plan = tourist_identity_category_plan($rows, TOURIST_IDENTITY_KEEP_CATEGORY);

  foreach ($plan as $id => $fields) {
    Category::newInstance()->update($fields, array('pk_i_id' => $id));
    $changes++;
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

function tourist_identity_apply_showcase_link() {
  if (!function_exists('tourist_showcase_save_categories') || !function_exists('tourist_showcase_selected_categories')) {
    return 0;
  }

  if (!tourist_identity_showcase_link_needed(tourist_showcase_selected_categories(), TOURIST_IDENTITY_KEEP_CATEGORY)) {
    return 0;
  }

  tourist_showcase_save_categories(array(TOURIST_IDENTITY_KEEP_CATEGORY));

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
  $changes = 0;

  $changes += tourist_identity_delete_seed_items();
  $changes += tourist_identity_apply_categories();
  $changes += tourist_identity_apply_currencies();
  $changes += tourist_identity_apply_prefs();
  $changes += tourist_identity_apply_logo();
  $changes += tourist_identity_apply_showcase_link();

  osc_set_preference('applied_version', tourist_identity_version(), TOURIST_IDENTITY_SECTION);
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

function tourist_identity_uninstall() {
  $section = osc_get_preference_section(TOURIST_IDENTITY_SECTION);

  if (array_key_exists('snapshot', $section)) {
    $plan = tourist_identity_restore_plan($section['snapshot']);
    tourist_identity_restore_prefs($plan['prefs']);
    tourist_identity_restore_categories($plan['categories']);
    tourist_identity_restore_currencies($plan['currencies']);
  }

  // The snapshot restored the original logo preference; remove only our file.
  if (file_exists(osc_uploads_path() . TOURIST_IDENTITY_LOGO_FILE)) {
    @unlink(osc_uploads_path() . TOURIST_IDENTITY_LOGO_FILE);
  }

  osc_delete_preference('snapshot', TOURIST_IDENTITY_SECTION);
  osc_delete_preference('applied_version', TOURIST_IDENTITY_SECTION);

  osc_cache_flush();
}

function tourist_identity_configure() {
  if (Params::getParam('tourist_identity_action') === 'reapply') {
    osc_csrf_check();
    $changes = tourist_identity_apply();
    osc_add_flash_ok_message(sprintf('Re-apply complete: %d change(s).', $changes), 'admin');
  }
  ?>
  <div class="box">
    <div class="box-header"><h1>Tourist Portal Identity</h1></div>
    <div class="box-content">
      <p>Applies the Argentine short-term-rental identity: branding copy, a single top-level category, ARS-only currency, and removal of the two seeded test listings.</p>
      <p>Re-apply is safe to run again: it only touches values that still differ from the target and reports how many changes it made.</p>
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
