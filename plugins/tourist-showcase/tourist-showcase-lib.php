<?php

function tourist_showcase_definitions() {
  return array(
    array('name' => 'Tipo de alojamiento', 'slug' => 'tourist_accommodation_type', 'type' => 'DROPDOWN', 'options' => 'Apartamento|Casa|Cabaña|Habitación privada|Hostería|Apart hotel|Complejo de departamentos|Otro', 'searchable' => true),
    array('name' => 'Huéspedes máximos', 'slug' => 'tourist_max_guests', 'type' => 'NUMBER', 'options' => '', 'searchable' => true),
    array('name' => 'Dormitorios', 'slug' => 'tourist_bedrooms', 'type' => 'NUMBER', 'options' => '', 'searchable' => true),
    array('name' => 'Baños', 'slug' => 'tourist_bathrooms', 'type' => 'NUMBER', 'options' => '', 'searchable' => true),
    array('name' => 'Servicios y comodidades', 'slug' => 'tourist_amenities', 'type' => 'TEXTAREA', 'options' => '', 'searchable' => false),
  );
}

function tourist_showcase_normalize_positive_integer($value) {
  if (!is_scalar($value) || !preg_match('/^\d+$/', (string)$value)) {
    return '';
  }

  $number = (int)$value;
  return $number > 0 && $number <= 100 ? (string)$number : '';
}

// Accepts only a value that exactly matches one of the field's pipe-separated dropdown options.
function tourist_showcase_normalize_type($value, $options) {
  if (!is_scalar($value) || (string)$value === '') {
    return '';
  }

  return in_array((string)$value, explode('|', (string)$options), true) ? (string)$value : '';
}

// $escaped_value must already be quoted and escaped by the Osclass DAO.
function tourist_showcase_type_condition($table_prefix, $field_id, $escaped_value) {
  return $table_prefix . 't_item.pk_i_id IN (SELECT fk_i_item_id FROM ' . $table_prefix . 't_item_meta WHERE fk_i_field_id = ' . (int)$field_id . ' AND s_value = ' . $escaped_value . ')';
}

// The searched category the filter form must resubmit, so applying filters keeps the scope.
function tourist_showcase_category_param(array $category_ids) {
  $first = reset($category_ids);
  return is_scalar($first) && preg_match('/^[1-9]\d*$/', (string)$first) ? (string)$first : '';
}

// Builds the reset target for the public filters. The category remains selected so visitors do
// not lose their destination context when they clear type, guest, or bedroom criteria.
function tourist_showcase_clear_filters_url($base_url, $category) {
  $params = array('page' => 'search');
  if (is_scalar($category) && preg_match('/^[1-9]\d*$/', (string)$category)) {
    $params['sCategory'] = (string)$category;
  }

  return rtrim((string)$base_url, '/') . '/?' . http_build_query($params);
}

// Selects the concise metadata suitable for a result card. Amenities are deliberately excluded:
// they belong to the detail page and make a scan-oriented result list noisy.
function tourist_showcase_card_attributes(array $meta, $locale) {
  $values = array();
  foreach ($meta as $field) {
    if (!is_array($field) || !isset($field['s_slug']) || !isset($field['s_value']) || !is_scalar($field['s_value'])) {
      continue;
    }

    $value = trim((string)$field['s_value']);
    if ($value !== '') {
      $values[$field['s_slug']] = $value;
    }
  }

  $spanish = tourist_showcase_is_spanish($locale);
  $attributes = array();
  $definitions = array(
    'tourist_accommodation_type' => array('key' => 'type', 'label' => $spanish ? 'Tipo' : 'Type'),
    'tourist_max_guests' => array('key' => 'guests', 'label' => $spanish ? 'Hasta' : 'Up to'),
    'tourist_bedrooms' => array('key' => 'bedrooms', 'label' => $spanish ? 'Dormitorios' : 'Bedrooms'),
    'tourist_bathrooms' => array('key' => 'bathrooms', 'label' => $spanish ? 'Baños' : 'Bathrooms'),
  );

  foreach ($definitions as $slug => $definition) {
    if (!isset($values[$slug])) {
      continue;
    }

    $value = $values[$slug];
    if ($slug === 'tourist_max_guests') {
      $plural = $value === '1' ? ($spanish ? 'huésped' : 'guest') : ($spanish ? 'huéspedes' : 'guests');
      $value .= ' ' . $plural;
    }

    $attributes[] = array('key' => $definition['key'], 'label' => $definition['label'], 'value' => $value);
  }

  return $attributes;
}

function tourist_showcase_is_spanish($locale) {
  return strpos((string)$locale, 'es_') === 0 || strpos((string)$locale, 'es-') === 0;
}

function tourist_showcase_configure_url($admin_base_url, $plugin_file, $plugins_path) {
  $plugin = str_replace(str_replace('\\', '/', $plugins_path), '', str_replace('\\', '/', $plugin_file));
  return $admin_base_url . '?' . http_build_query(array('page' => 'plugins', 'action' => 'admin', 'plugin' => $plugin));
}

// Merges a field's currently stored dropdown options with the plugin's target options, keeping
// the current order and appending only the values missing from it. Idempotent: merging an
// already-merged list against the same target returns it unchanged. Used to upgrade an
// already-installed site's stored s_options without disturbing any admin customization order.
function tourist_showcase_merge_options(array $current, array $target) {
  $merged = $current;

  foreach ($target as $value) {
    if (!in_array($value, $merged, true)) {
      $merged[] = $value;
    }
  }

  return $merged;
}
