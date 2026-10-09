<?php
/*
Plugin Name: Tourist Accommodation Showcase
Plugin URI: https://alquileres.diazignacio.ar/
Description: Adds configurable tourist-accommodation metadata and public search filters. It does not implement bookings or payments.
Version: 0.1.0
Author: alquileres.diazignacio.ar
Short Name: tourist-showcase
*/

if (!defined('ABS_PATH')) {
  exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

require_once __DIR__ . '/tourist-showcase-lib.php';

define('TOURIST_SHOWCASE_PLUGIN', osc_plugin_path(__FILE__));
define('TOURIST_SHOWCASE_SECTION', 'tourist_showcase');
define('TOURIST_SHOWCASE_CSS_REL_PATH', 'tourist-showcase/assets/tourist-showcase.css');

function tourist_showcase_text($spanish, $english) {
  return tourist_showcase_is_spanish(osc_current_user_locale()) ? $spanish : $english;
}

function tourist_showcase_field($slug) {
  return Field::newInstance()->findBySlug($slug);
}

function tourist_showcase_install() {
  foreach (tourist_showcase_definitions() as $definition) {
    if (!tourist_showcase_field($definition['slug'])) {
      Field::newInstance()->insertField($definition['name'], $definition['type'], $definition['slug'], false, $definition['options'], array(), 900);
    }

    $field = tourist_showcase_field($definition['slug']);
    if ($field) {
      Field::newInstance()->dao->update(DB_TABLE_PREFIX . 't_meta_fields', array('b_searchable' => $definition['searchable'] ? 1 : 0), array('pk_i_id' => (int)$field['pk_i_id']));
    }
  }

  osc_set_preference('category_ids', '', TOURIST_SHOWCASE_SECTION);
  tourist_showcase_sync_options();
}

// Updates an already-installed site's stored dropdown options to include any new values from
// tourist_showcase_definitions(), without disturbing the existing option order. Writes to
// t_meta_fields.s_options only when the merged list actually differs from what is stored.
// Called from this plugin's own install (fresh install and every re-run), and from the
// tourist-directory plugin's install so a directory-only install/reinstall also picks up the
// current vocabulary.
function tourist_showcase_sync_options() {
  foreach (tourist_showcase_definitions() as $definition) {
    if ($definition['type'] !== 'DROPDOWN' || $definition['options'] === '') {
      continue;
    }

    $field = tourist_showcase_field($definition['slug']);
    if (!$field) {
      continue;
    }

    $current_options = ($field['s_options'] !== '' && $field['s_options'] !== null) ? explode('|', $field['s_options']) : array();
    $target_options = explode('|', $definition['options']);
    $merged_options = tourist_showcase_merge_options($current_options, $target_options);

    if ($merged_options !== $current_options) {
      Field::newInstance()->dao->update(DB_TABLE_PREFIX . 't_meta_fields', array('s_options' => implode('|', $merged_options)), array('pk_i_id' => (int)$field['pk_i_id']));
    }
  }
}

function tourist_showcase_selected_categories() {
  $raw = osc_get_preference('category_ids', TOURIST_SHOWCASE_SECTION);
  if ($raw === false || $raw === '') {
    return array();
  }

  return array_values(array_filter(array_map('intval', explode(',', $raw))));
}

function tourist_showcase_save_categories($category_ids) {
  $category_ids = is_array($category_ids) ? $category_ids : array();
  $category_ids = array_values(array_unique(array_filter(array_map('intval', $category_ids))));

  foreach (tourist_showcase_definitions() as $definition) {
    $field = tourist_showcase_field($definition['slug']);
    if (!$field) {
      continue;
    }

    Field::newInstance()->cleanCategoriesFromField((int)$field['pk_i_id']);
    if ($category_ids) {
      Field::newInstance()->insertCategories((int)$field['pk_i_id'], $category_ids);
    }
  }

  osc_set_preference('category_ids', implode(',', $category_ids), TOURIST_SHOWCASE_SECTION);
}

function tourist_showcase_configure() {
  if (Params::getParam('tourist_showcase_action') === 'save') {
    osc_csrf_check();
    tourist_showcase_save_categories(Params::getParam('tourist_category_ids'));
    osc_add_flash_ok_message('Tourist accommodation categories saved.', 'admin');
  }

  $selected = tourist_showcase_selected_categories();
  $categories = Category::newInstance()->listAll(false);
  ?>
  <div class="box">
    <div class="box-header"><h1>Tourist Accommodation Showcase</h1></div>
    <div class="box-content">
      <p>Choose the categories that represent tourist accommodation. The plugin adds its metadata only to listings in these categories.</p>
      <p>It provides a public showcase and enquiries through the existing Osclass contact form. It does not provide availability, reservations, payments, checkout, or guest records.</p>
      <form action="<?php echo osc_esc_html(tourist_showcase_configure_url(osc_admin_base_url(true), TOURIST_SHOWCASE_PLUGIN, osc_plugins_path())); ?>" method="post">
        <?php echo osc_csrf_token_form(); ?>
        <input type="hidden" name="tourist_showcase_action" value="save" />
        <?php foreach ($categories as $category) { ?>
          <p><label><input type="checkbox" name="tourist_category_ids[]" value="<?php echo (int)$category['pk_i_id']; ?>" <?php echo in_array((int)$category['pk_i_id'], $selected, true) ? 'checked' : ''; ?> /> <?php echo osc_esc_html($category['s_name']); ?></label></p>
        <?php } ?>
        <button type="submit" class="btn btn-submit">Save categories</button>
      </form>
    </div>
  </div>
  <?php
}

function tourist_showcase_search_filters() {
  $type = tourist_showcase_field('tourist_accommodation_type');
  $guests = tourist_showcase_field('tourist_max_guests');
  $bedrooms = tourist_showcase_field('tourist_bedrooms');

  if (!$type || !$guests || !$bedrooms) {
    return;
  }
  $category = tourist_showcase_category_param(osc_search_category_id());
  $selected_type = tourist_showcase_normalize_type(Params::getParam('tourist_type'), $type['s_options']);
  $minimum_guests = tourist_showcase_normalize_positive_integer(Params::getParam('tourist_min_guests'));
  $minimum_bedrooms = tourist_showcase_normalize_positive_integer(Params::getParam('tourist_min_bedrooms'));
  $has_active_filters = $selected_type !== '' || $minimum_guests !== '' || $minimum_bedrooms !== '';
  ?>
  <form class="tourist-showcase-filters tourist-showcase-filter-form" method="get" action="<?php echo osc_esc_html(osc_base_url()); ?>">
    <input type="hidden" name="page" value="search" />
    <?php if ($category !== '') { ?><input type="hidden" name="sCategory" value="<?php echo osc_esc_html($category); ?>" /><?php } ?>
    <fieldset>
      <legend><?php echo tourist_showcase_text('Alojamiento turístico', 'Tourist accommodation'); ?></legend>
      <label class="tourist-showcase-filter-field"><?php echo tourist_showcase_text('Tipo', 'Type'); ?>
        <select name="tourist_type"><option value=""><?php echo tourist_showcase_text('Cualquiera', 'Any'); ?></option><?php foreach (explode('|', $type['s_options']) as $option) { ?><option value="<?php echo osc_esc_html($option); ?>"<?php echo $option === $selected_type ? ' selected' : ''; ?>><?php echo osc_esc_html($option); ?></option><?php } ?></select>
      </label>
      <label class="tourist-showcase-filter-field"><?php echo tourist_showcase_text('Huéspedes mínimos', 'Minimum guests'); ?>
        <input type="number" min="1" max="100" name="tourist_min_guests" value="<?php echo osc_esc_html($minimum_guests); ?>" />
      </label>
      <label class="tourist-showcase-filter-field"><?php echo tourist_showcase_text('Dormitorios mínimos', 'Minimum bedrooms'); ?>
        <input type="number" min="1" max="100" name="tourist_min_bedrooms" value="<?php echo osc_esc_html($minimum_bedrooms); ?>" />
      </label>
      <div class="tourist-showcase-filter-actions">
        <button type="submit"><?php echo tourist_showcase_text('Aplicar filtros', 'Apply filters'); ?></button>
        <?php if ($has_active_filters) { ?><a href="<?php echo osc_esc_html(tourist_showcase_clear_filters_url(osc_base_url(), $category)); ?>"><?php echo tourist_showcase_text('Limpiar filtros', 'Clear filters'); ?></a><?php } ?>
      </div>
    </fieldset>
  </form>
  <?php
}

function tourist_showcase_search_conditions($search) {
  $filters = array('tourist_min_guests' => 'tourist_max_guests', 'tourist_min_bedrooms' => 'tourist_bedrooms');

  foreach ($filters as $parameter => $slug) {
    $value = tourist_showcase_normalize_positive_integer(Params::getParam($parameter));
    $field = tourist_showcase_field($slug);
    if ($value !== '' && $field) {
      $field_id = (int)$field['pk_i_id'];
      $search->addConditions(DB_TABLE_PREFIX . 't_item.pk_i_id IN (SELECT fk_i_item_id FROM ' . DB_TABLE_PREFIX . 't_item_meta WHERE fk_i_field_id = ' . $field_id . ' AND CAST(s_value AS UNSIGNED) >= ' . (int)$value . ')');
    }
  }

  // Applied here instead of through meta[...]: Osclass only honors meta for fields linked to the
  // searched category, which silently dropped the type in region and uncategorized searches.
  $type = tourist_showcase_field('tourist_accommodation_type');
  if ($type) {
    $value = tourist_showcase_normalize_type(Params::getParam('tourist_type'), $type['s_options']);
    if ($value !== '') {
      $search->addConditions(tourist_showcase_type_condition(DB_TABLE_PREFIX, (int)$type['pk_i_id'], $search->dao->escape($value)));
    }
  }
}

// The loop hook is shared by category, search and home cards. Memoizing avoids duplicate meta
// queries when a theme renders the same item more than once in a request.
function tourist_showcase_item_loop_attributes() {
  static $cache = array();

  $item_id = (int) osc_item_id();
  if ($item_id <= 0) {
    return;
  }

  if (!array_key_exists($item_id, $cache)) {
    $cache[$item_id] = Item::newInstance()->metaFields($item_id);
  }

  $attributes = tourist_showcase_card_attributes($cache[$item_id], osc_current_user_locale());
  if (!$attributes) {
    return;
  }

  echo '<ul class="tourist-showcase-card-attributes">';
  foreach ($attributes as $attribute) {
    echo '<li class="tourist-showcase-card-attribute tourist-showcase-card-attribute-' . osc_esc_html($attribute['key']) . '">';
    echo '<span class="tourist-showcase-card-attribute-label">' . osc_esc_html($attribute['label']) . '</span> ';
    echo '<span class="tourist-showcase-card-attribute-value">' . osc_esc_html($attribute['value']) . '</span>';
    echo '</li>';
  }
  echo '</ul>';
}

osc_register_plugin(TOURIST_SHOWCASE_PLUGIN, 'tourist_showcase_install');
osc_add_hook(TOURIST_SHOWCASE_PLUGIN . '_configure', 'tourist_showcase_configure');
osc_add_hook('search_items_filter', 'tourist_showcase_search_filters');
osc_add_hook('sql_search_conditions_before', 'tourist_showcase_search_conditions');
osc_add_hook('item_loop_description', 'tourist_showcase_item_loop_attributes');

function tourist_showcase_enqueue_css() {
  if (defined('OC_ADMIN') && OC_ADMIN === true) {
    return;
  }

  // This plugin's active PHP entry point lives at oc-content/plugins/, while its assets live in
  // oc-content/plugins/tourist-showcase/. Resolve from Osclass's plugin root, not __DIR__.
  $file = osc_plugins_path() . TOURIST_SHOWCASE_CSS_REL_PATH;
  $version = is_file($file) ? (string) filemtime($file) : '1';
  osc_enqueue_style('tourist-showcase', osc_plugins_url() . TOURIST_SHOWCASE_CSS_REL_PATH . '?v=' . rawurlencode($version));
}

osc_add_hook('header', 'tourist_showcase_enqueue_css');
