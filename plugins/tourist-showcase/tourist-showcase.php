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
  ?>
  <form class="tourist-showcase-filters" method="get" action="<?php echo osc_esc_html(osc_base_url()); ?>">
    <input type="hidden" name="page" value="search" />
    <fieldset>
      <legend><?php echo tourist_showcase_text('Alojamiento turístico', 'Tourist accommodation'); ?></legend>
      <label><?php echo tourist_showcase_text('Tipo', 'Type'); ?>
        <select name="meta[<?php echo (int)$type['pk_i_id']; ?>]"><option value=""><?php echo tourist_showcase_text('Cualquiera', 'Any'); ?></option><?php foreach (explode('|', $type['s_options']) as $option) { ?><option value="<?php echo osc_esc_html($option); ?>"><?php echo osc_esc_html($option); ?></option><?php } ?></select>
      </label>
      <label><?php echo tourist_showcase_text('Huéspedes mínimos', 'Minimum guests'); ?>
        <input type="number" min="1" max="100" name="tourist_min_guests" value="<?php echo osc_esc_html(tourist_showcase_normalize_positive_integer(Params::getParam('tourist_min_guests'))); ?>" />
      </label>
      <label><?php echo tourist_showcase_text('Dormitorios mínimos', 'Minimum bedrooms'); ?>
        <input type="number" min="1" max="100" name="tourist_min_bedrooms" value="<?php echo osc_esc_html(tourist_showcase_normalize_positive_integer(Params::getParam('tourist_min_bedrooms'))); ?>" />
      </label>
      <button type="submit"><?php echo tourist_showcase_text('Aplicar filtros', 'Apply filters'); ?></button>
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
}

osc_register_plugin(TOURIST_SHOWCASE_PLUGIN, 'tourist_showcase_install');
osc_add_hook(TOURIST_SHOWCASE_PLUGIN . '_configure', 'tourist_showcase_configure');
osc_add_hook('search_items_filter', 'tourist_showcase_search_filters');
osc_add_hook('sql_search_conditions_before', 'tourist_showcase_search_conditions');
