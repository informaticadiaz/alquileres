<?php

// Pure contracts for the tourist-identity plugin.
//
// Every function here is loadable standalone (no Osclass calls at load
// time) and side-effect free, so it can be exercised directly by
// tests/test_tourist_showcase.php without bootstrapping Osclass. The
// OC_ADMIN guard is a parameter ($isAdmin), never a read of the constant,
// so the guard itself is testable in isolation.

function tourist_identity_version() {
  return '1.1.0';
}

// Formats the inline "Re-apply" outcome shown on the configure screen, independent of the
// transient flash message.
function tourist_identity_reapply_message($changeCount) {
  return 'Re-apply complete: ' . (int)$changeCount . ' change(s).';
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

// True when the showcase plugin must be relinked so its fields apply to exactly the target
// category id(s). $target may be a single id (prior scalar contract) or an array of ids
// (destination-tree leaves), compared as a set regardless of order.
function tourist_identity_showcase_link_needed(array $selected, $target) {
  $normalized_selected = array_values(array_unique(array_map('intval', $selected)));
  sort($normalized_selected);

  $target_ids = is_array($target) ? $target : array($target);
  $normalized_target = array_values(array_unique(array_map('intval', $target_ids)));
  sort($normalized_target);

  return $normalized_selected !== $normalized_target;
}

// Removes $removed ids from $selected, de-duplicating along the way, while preserving the
// relative order of the remaining ids.
function tourist_identity_prune_ids(array $selected, array $removed) {
  $removed_ids = array_map('intval', $removed);
  $pruned = array();

  foreach ($selected as $id) {
    $id = (int)$id;
    if (!in_array($id, $removed_ids, true) && !in_array($id, $pruned, true)) {
      $pruned[] = $id;
    }
  }

  return $pruned;
}

// Plans which plugin-created category rows to delete vs. disable on uninstall. $map is
// key=>id for every plugin-created row; $rows is id=>['fk_i_parent_id'=>id|null] for those same
// ids (parent linkage, to process leaves before regions); $counts is id=>int|null (linked item
// count, null when unknown/unsafe to trust). A row deletes only when its own count is exactly 0
// AND none of its children survive (were not also deleted); everything else is disabled and
// kept in the map, including any row with an unknown (null) count.
// $protected_ids lists pre-existing categories (the anchor) that the plan must never delete
// or disable, even if they leak into the created-id map; their snapshot restores them.
function tourist_identity_uninstall_plan(array $map, array $rows, array $counts, array $protected_ids = array()) {
  $protected = array_flip(array_map('intval', $protected_ids));
  $children_of = array();
  foreach ($rows as $id => $row) {
    $parent = isset($row['fk_i_parent_id']) ? $row['fk_i_parent_id'] : null;
    if ($parent !== null) {
      $parent = (int)$parent;
      if (!isset($children_of[$parent])) {
        $children_of[$parent] = array();
      }
      $children_of[$parent][] = (int)$id;
    }
  }

  $leaf_ids = array();
  $region_ids = array();
  foreach (array_map('intval', array_values($map)) as $id) {
    if (isset($protected[$id])) {
      continue;
    }
    if (empty($children_of[$id])) {
      $leaf_ids[] = $id;
    } else {
      $region_ids[] = $id;
    }
  }

  $delete = array();
  $disable = array();
  $deleted = array();

  foreach ($leaf_ids as $id) {
    $count = array_key_exists($id, $counts) ? $counts[$id] : null;
    if ($count === 0) {
      $delete[] = $id;
      $deleted[$id] = true;
    } else {
      $disable[] = $id;
    }
  }

  foreach ($region_ids as $id) {
    $count = array_key_exists($id, $counts) ? $counts[$id] : null;
    $children = isset($children_of[$id]) ? $children_of[$id] : array();
    $all_children_deleted = true;
    foreach ($children as $child_id) {
      if (empty($deleted[$child_id])) {
        $all_children_deleted = false;
        break;
      }
    }

    if ($count === 0 && $all_children_deleted) {
      $delete[] = $id;
      $deleted[$id] = true;
    } else {
      $disable[] = $id;
    }
  }

  $kept_map = array();
  foreach ($map as $key => $id) {
    if (empty($deleted[(int)$id])) {
      $kept_map[$key] = $id;
    }
  }

  return array('delete' => $delete, 'disable' => $disable, 'map' => $kept_map);
}

// Builds the supplementary snapshot of the anchor category (47) captured only once, before the
// first destination-tree apply repurposes it. The main tourist_identity.snapshot already covers
// 47's b_enabled/parent state; this snapshot covers what that one lacks: position and per-locale
// description (name/description/slug), so uninstall can restore 47 to its pre-tree identity.
function tourist_identity_build_tree_snapshot(array $anchorRow, array $anchorDescs) {
  return json_encode(array(
    'version' => tourist_identity_version(),
    'anchor' => array(
      'id' => isset($anchorRow['pk_i_id']) ? (int)$anchorRow['pk_i_id'] : null,
      'i_position' => isset($anchorRow['i_position']) ? (int)$anchorRow['i_position'] : null,
      'descriptions' => $anchorDescs,
    ),
  ));
}

function tourist_identity_tree_restore_plan($json) {
  $decoded = json_decode($json, true);

  if ($decoded === null || json_last_error() !== JSON_ERROR_NONE) {
    throw new \InvalidArgumentException('tourist_identity_tree_restore_plan: invalid snapshot JSON');
  }

  $anchor = isset($decoded['anchor']) ? $decoded['anchor'] : array();

  return array(
    'id' => isset($anchor['id']) ? $anchor['id'] : null,
    'i_position' => isset($anchor['i_position']) ? $anchor['i_position'] : null,
    'descriptions' => isset($anchor['descriptions']) ? $anchor['descriptions'] : array(),
  );
}

// Resolves a stable base slug to a slug free for the given owner ($selfId): the base slug is
// used unchanged unless another category already owns it, in which case "-2", "-3", ... is
// appended until $ownerOf reports either no owner or $selfId itself.
function tourist_identity_resolve_slug($slug, callable $ownerOf, $selfId) {
  $candidate = $slug;
  $suffix = 1;

  while (true) {
    $owner = $ownerOf($candidate);
    if ($owner === null || (int)$owner === (int)$selfId) {
      return $candidate;
    }
    $suffix++;
    $candidate = $slug . '-' . $suffix;
  }
}

// Resolves every leaf's mapped category id, in tree order, skipping any leaf key not yet
// present in the map. Region keys that may also live in $map are ignored.
function tourist_identity_leaf_ids(array $tree, array $map) {
  $ids = array();

  foreach ($tree as $region) {
    foreach ($region['leaves'] as $leaf) {
      $key = $leaf[0];
      if (array_key_exists($key, $map)) {
        $ids[] = (int)$map[$key];
      }
    }
  }

  return $ids;
}

// Fills $update/$describe (by reference) for one already-existing tree row (region or leaf)
// against its target enabled/parent/position/description state. Used only by
// tourist_identity_tree_plan().
function tourist_identity_tree_plan_row($id, array $names, $slug, $parent_id, $position, array $rows, array $descs, array $locales, array &$update, array &$describe) {
  $row = $rows[$id];
  $changes = array();

  if ((int)$row['b_enabled'] !== 1) {
    $changes['b_enabled'] = 1;
  }

  $current_parent = (isset($row['fk_i_parent_id']) && $row['fk_i_parent_id'] !== null) ? (int)$row['fk_i_parent_id'] : null;
  $target_parent = ($parent_id !== null) ? (int)$parent_id : null;
  if ($current_parent !== $target_parent) {
    $changes['fk_i_parent_id'] = $target_parent;
  }

  $current_position = isset($row['i_position']) ? (int)$row['i_position'] : null;
  if ($current_position !== $position) {
    $changes['i_position'] = $position;
  }

  if (!empty($changes)) {
    $update[$id] = $changes;
  }

  foreach ($locales as $locale) {
    $target = array('s_name' => $names[$locale], 's_description' => '', 's_slug' => $slug);
    $current = isset($descs[$id][$locale]) ? $descs[$id][$locale] : null;

    if ($current !== $target) {
      if (!isset($describe[$id])) {
        $describe[$id] = array();
      }
      $describe[$id][$locale] = $target;
    }
  }
}

// Intersects the destination tree's two known description locales (es_ES, en_US, in that fixed
// order) with $installedCodes (whatever locale codes actually exist on this site). Any other
// installed locale is left untouched by the tree apply routine: it keeps whatever name/description
// it already had, since the tree only ever carries es_ES/en_US copy.
function tourist_identity_tree_locales(array $installedCodes) {
  return array_values(array_intersect(array('es_ES', 'en_US'), $installedCodes));
}

// Returns only the rows from $rows whose pk_i_id is not in $excludedIds, preserving relative
// order. Used to keep the legacy "disable every non-kept category" plan from re-disabling rows
// that the destination-tree apply routine manages on its own (the anchor plus every created id).
function tourist_identity_rows_excluding_ids(array $rows, array $excludedIds) {
  $excluded = array_flip(array_map('intval', $excludedIds));
  $kept = array();

  foreach ($rows as $row) {
    if (!isset($excluded[(int)$row['pk_i_id']])) {
      $kept[] = $row;
    }
  }

  return $kept;
}

// Plans the destination tree against current DB state, without touching the database.
// Buenos Aires reuses the pre-existing anchor row ($anchorRow); every other region/leaf is
// resolved through the persisted id map, and re-inserted if its mapped id no longer has a row.
function tourist_identity_tree_plan(array $tree, array $rows, array $descs, array $map, array $locales, array $anchorRow) {
  $insert = array();
  $update = array();
  $describe = array();
  $region_ids = array();
  $region_index = 0;

  foreach ($tree as $region) {
    $key = $region['key'];
    $is_anchor = array_key_exists('anchor', $region);

    if ($is_anchor) {
      $id = (int)$anchorRow['pk_i_id'];
      $region_ids[$key] = $id;
      tourist_identity_tree_plan_row($id, $region['names'], $key, null, $region_index, $rows, $descs, $locales, $update, $describe);
    } elseif (isset($map[$key]) && isset($rows[(int)$map[$key]])) {
      $id = (int)$map[$key];
      $region_ids[$key] = $id;
      tourist_identity_tree_plan_row($id, $region['names'], $key, null, $region_index, $rows, $descs, $locales, $update, $describe);
    } else {
      $insert[] = array(
        'key' => $key,
        'parent_key' => null,
        'position' => $region_index,
        'fields' => array('b_enabled' => 1, 'fk_i_parent_id' => null),
        'names' => $region['names'],
      );
      $region_ids[$key] = null;
    }

    $region_index++;
  }

  foreach ($tree as $region) {
    $region_key = $region['key'];
    $parent_id = $region_ids[$region_key];
    $leaf_index = 0;

    foreach ($region['leaves'] as $leaf) {
      list($leaf_key, $es_name, $en_name) = $leaf;
      $names = array('es_ES' => $es_name, 'en_US' => $en_name);

      if (isset($map[$leaf_key]) && isset($rows[(int)$map[$leaf_key]])) {
        $id = (int)$map[$leaf_key];
        tourist_identity_tree_plan_row($id, $names, $leaf_key, $parent_id, $leaf_index, $rows, $descs, $locales, $update, $describe);
      } else {
        $insert[] = array(
          'key' => $leaf_key,
          'parent_key' => $region_key,
          'position' => $leaf_index,
          'fields' => array('b_enabled' => 1, 'fk_i_parent_id' => null),
          'names' => $names,
        );
      }

      $leaf_index++;
    }
  }

  return array('insert' => $insert, 'update' => $update, 'describe' => $describe);
}
