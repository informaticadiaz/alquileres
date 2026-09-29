<?php

function tourist_showcase_definitions() {
  return array(
    array('name' => 'Tipo de alojamiento', 'slug' => 'tourist_accommodation_type', 'type' => 'DROPDOWN', 'options' => 'Apartamento|Casa|Cabaña|Habitación privada|Hostería|Otro', 'searchable' => true),
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

function tourist_showcase_is_spanish($locale) {
  return strpos((string)$locale, 'es_') === 0 || strpos((string)$locale, 'es-') === 0;
}

function tourist_showcase_configure_url($admin_base_url, $plugin_file, $plugins_path) {
  $plugin = str_replace(str_replace('\\', '/', $plugins_path), '', str_replace('\\', '/', $plugin_file));
  return $admin_base_url . '?' . http_build_query(array('page' => 'plugins', 'action' => 'admin', 'plugin' => $plugin));
}
