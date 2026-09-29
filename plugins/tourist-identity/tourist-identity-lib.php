<?php

// Pure contracts for the tourist-identity plugin.
//
// Every function here is loadable standalone (no Osclass calls at load
// time) and side-effect free, so it can be exercised directly by
// tests/test_tourist_showcase.php without bootstrapping Osclass. The
// OC_ADMIN guard is a parameter ($isAdmin), never a read of the constant,
// so the guard itself is testable in isolation.

function tourist_identity_version() {
  return '1.0.0';
}

function tourist_identity_target_prefs() {
  return array(
    array('osclass', 'pageTitle', 'Alquileres Temporarios'),
    array('osclass', 'pageDesc', 'Encontrá alojamientos temporarios en toda Argentina: casas, cabañas, departamentos y hosterías. Contactá directamente al propietario.'),
    array('osclass', 'currency', 'ARS'),
    array('sigma', 'keyword_placeholder', 'Buscá por ciudad, provincia o tipo de alojamiento'),
    array('sigma', 'footer_link', '0'),
  );
}

function tourist_identity_override_map() {
  return array(
    'es_ES' => array(
      '¿Qué estás buscando hoy?' => 'Encontrá tu alojamiento temporario en Argentina',
      'Publicar anuncio' => 'Publicar alojamiento',
      'Últimos anuncios' => 'Últimos alojamientos',
    ),
    'en_US' => array(
      'What are you looking for today?' => 'Find your short-term rental in Argentina',
      'Publish Ad' => 'Publish your listing',
      'Latest Listings' => 'Latest listings',
      'Buscá por ciudad, provincia o tipo de alojamiento' => 'Search by city, province or property type',
    ),
  );
}

function tourist_identity_override_text($text, $locale, $isAdmin, array $map) {
  if ($isAdmin) {
    return $text;
  }

  if (isset($map[$locale]) && array_key_exists($text, $map[$locale])) {
    return $map[$locale][$text];
  }

  return $text;
}

function tourist_identity_category_plan(array $rows, $keepId) {
  $plan = array();

  foreach ($rows as $row) {
    $id = (int)$row['pk_i_id'];
    $changes = array();

    if ($id === (int)$keepId) {
      if ((int)$row['b_enabled'] !== 1) {
        $changes['b_enabled'] = 1;
      }
      if ($row['fk_i_parent_id'] !== null) {
        $changes['fk_i_parent_id'] = null;
      }
    } elseif ((int)$row['b_enabled'] !== 0) {
      $changes['b_enabled'] = 0;
    }

    if (!empty($changes)) {
      $plan[$id] = $changes;
    }
  }

  return $plan;
}

function tourist_identity_enabled_currencies(array $rows) {
  $enabled = array();

  foreach ($rows as $row) {
    if ((int)$row['b_enabled'] === 1) {
      $enabled[] = $row;
    }
  }

  return $enabled;
}

function tourist_identity_currency_plan(array $rows, $target) {
  $plan = array();
  $found = false;

  foreach ($rows as $row) {
    $code = $row['pk_c_code'];

    if ($code === $target) {
      $found = true;
      if ((int)$row['b_enabled'] !== 1) {
        $plan[$code] = array('b_enabled' => 1);
      }
    } elseif ((int)$row['b_enabled'] !== 0) {
      $plan[$code] = array('b_enabled' => 0);
    }
  }

  if (!$found) {
    $plan[$target] = array('b_enabled' => 1, 'insert' => true);
  }

  return $plan;
}

function tourist_identity_pref_plan(array $current, array $target_prefs) {
  $plan = array();

  foreach ($target_prefs as $target_pref) {
    list($section, $name, $value) = $target_pref;
    $key = $section . '.' . $name;
    $existing = array_key_exists($key, $current) ? $current[$key] : null;

    if ($existing === null || (string)$existing !== (string)$value) {
      $plan[$key] = array('section' => $section, 'name' => $name, 'value' => $value);
    }
  }

  return $plan;
}

// Preferences captured before install: every target preference plus the sigma
// logo, which the install replaces outside the target preference plan.
function tourist_identity_snapshot_pref_keys() {
  $keys = array();
  foreach (tourist_identity_target_prefs() as $target_pref) {
    $keys[] = array($target_pref[0], $target_pref[1]);
  }
  $keys[] = array('sigma', 'logo');

  return $keys;
}

function tourist_identity_build_snapshot(array $prefs, array $categories, array $currencies) {
  $pref_snapshot = array();

  foreach (tourist_identity_snapshot_pref_keys() as $pref_key) {
    $key = $pref_key[0] . '.' . $pref_key[1];
    $pref_snapshot[$key] = array_key_exists($key, $prefs) ? $prefs[$key] : null;
  }

  return json_encode(array(
    'prefs' => $pref_snapshot,
    'categories' => $categories,
    'currencies' => $currencies,
  ));
}

function tourist_identity_restore_plan($json) {
  $decoded = json_decode($json, true);

  if ($decoded === null || json_last_error() !== JSON_ERROR_NONE) {
    throw new \InvalidArgumentException('tourist_identity_restore_plan: invalid snapshot JSON');
  }

  return array(
    'prefs' => isset($decoded['prefs']) ? $decoded['prefs'] : array(),
    'categories' => isset($decoded['categories']) ? $decoded['categories'] : array(),
    'currencies' => isset($decoded['currencies']) ? $decoded['currencies'] : array(),
  );
}

function tourist_identity_disclaimer($locale) {
  if ($locale === 'en_US') {
    return 'Alquileres Temporarios is a listings showcase: we connect owners and guests, but we do not process bookings or payments and do not act as an intermediary in the agreement. All arrangements are made directly between the parties.';
  }

  return 'Alquileres Temporarios es una vitrina de anuncios: conectamos propietarios y huéspedes, pero no procesamos reservas ni pagos ni intervenimos como intermediarios en la contratación. Todo acuerdo se realiza directamente entre las partes.';
}

function tourist_identity_configure_url($admin_base_url, $plugin_file, $plugins_path) {
  $plugin = str_replace(str_replace('\\', '/', $plugins_path), '', str_replace('\\', '/', $plugin_file));
  return $admin_base_url . '?' . http_build_query(array('page' => 'plugins', 'action' => 'admin', 'plugin' => $plugin));
}

// True when the showcase plugin must be relinked so its fields apply only to the kept category.
function tourist_identity_showcase_link_needed(array $selected, $keep_category_id) {
  $normalized = array_values(array_unique(array_map('intval', $selected)));

  return $normalized !== array((int)$keep_category_id);
}
