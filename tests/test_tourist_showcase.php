<?php

require __DIR__ . '/../plugins/tourist-showcase/tourist-showcase-lib.php';
require __DIR__ . '/../plugins/tourist-identity/tourist-identity-lib.php';
require __DIR__ . '/../plugins/tourist-identity/tourist-identity-tree.php';

function expect_true($condition, $message) {
  if (!$condition) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
  }
}

function expect_throws($callable, $message) {
  try {
    $callable();
  } catch (\Throwable $e) {
    return;
  }
  fwrite(STDERR, "FAIL: {$message}\n");
  exit(1);
}

$definitions = tourist_showcase_definitions();
expect_true(count($definitions) === 5, 'the showcase exposes five accommodation metadata fields');
expect_true($definitions[0]['slug'] === 'tourist_accommodation_type', 'accommodation type has a stable slug');
expect_true($definitions[1]['searchable'] === true, 'guest capacity is searchable');
expect_true($definitions[4]['searchable'] === false, 'amenities are display metadata, not a misleading exact filter');
expect_true(tourist_showcase_normalize_positive_integer('4') === '4', 'positive guest count is accepted');
expect_true(tourist_showcase_normalize_positive_integer('0') === '', 'zero is rejected');
expect_true(tourist_showcase_normalize_positive_integer('4.5') === '', 'decimal input is rejected');
expect_true(tourist_showcase_normalize_positive_integer('101') === '', 'unbounded input is rejected');
expect_true(tourist_showcase_is_spanish('es_ES') === true, 'Spanish locale is detected');
expect_true(tourist_showcase_is_spanish('en_US') === false, 'English locale preserves fallback');
expect_true(
  tourist_showcase_configure_url('https://example.test/oc-admin/index.php', '/srv/osclass/oc-content/plugins/tourist-showcase.php', '/srv/osclass/oc-content/plugins/')
    === 'https://example.test/oc-admin/index.php?page=plugins&action=admin&plugin=tourist-showcase.php',
  'configure form posts back to the admin hook with the plugin path relative to the plugins directory'
);

// --- tourist-identity: Phase 1.1 - version, target prefs, override map/text (incl. OC_ADMIN guard) ---

expect_true(is_string(tourist_identity_version()) && tourist_identity_version() !== '', 'the identity plugin exposes a non-empty version string');

$target_prefs = tourist_identity_target_prefs();
expect_true(count($target_prefs) === 5, 'the identity plugin targets five preference rows');
expect_true(
  $target_prefs[0] === array('osclass', 'pageTitle', 'Alquileres Temporarios'),
  'the first target pref sets the Osclass core page title (section osclass)'
);
expect_true(
  in_array(array('osclass', 'currency', 'ARS'), $target_prefs, true),
  'the target prefs set the default currency to ARS'
);
expect_true(
  in_array(array('sigma', 'footer_link', '0'), $target_prefs, true),
  'the target prefs disable the sigma footer credit link'
);

$override_map = tourist_identity_override_map();
expect_true(isset($override_map['es_ES']) && isset($override_map['en_US']), 'the override map defines es_ES and en_US locales');
expect_true(
  $override_map['es_ES']['¿Qué estás buscando hoy?'] === 'Encontrá tu alojamiento temporario en Argentina',
  'the es_ES map overrides the hero question with the portal H1'
);
expect_true(
  $override_map['es_ES']['Publicar anuncio'] === 'Publicar alojamiento',
  'the es_ES map overrides the owner CTA'
);

expect_true(
  tourist_identity_override_text('¿Qué estás buscando hoy?', 'es_ES', false, $override_map) === 'Encontrá tu alojamiento temporario en Argentina',
  'a public es_ES request receives the overridden hero H1'
);
expect_true(
  tourist_identity_override_text('¿Qué estás buscando hoy?', 'es_ES', true, $override_map) === '¿Qué estás buscando hoy?',
  'an OC_ADMIN request keeps the original hero string untouched'
);
expect_true(
  tourist_identity_override_text('Some unrelated core string', 'es_ES', false, $override_map) === 'Some unrelated core string',
  'strings absent from the override map pass through unchanged'
);
expect_true(
  tourist_identity_override_text('Publish Ad', 'en_US', false, $override_map) === $override_map['en_US']['Publish Ad'],
  'a public en_US request receives the overridden owner CTA'
);

// --- tourist-identity: Phase 1.2 - category plan (changed-rows-only, idempotent) and currency helpers ---

$category_rows = array(
  array('pk_i_id' => 4, 'b_enabled' => 1, 'fk_i_parent_id' => null),
  array('pk_i_id' => 47, 'b_enabled' => 1, 'fk_i_parent_id' => 4),
  array('pk_i_id' => 12, 'b_enabled' => 0, 'fk_i_parent_id' => 4),
);
$category_plan = tourist_identity_category_plan($category_rows, 47);
expect_true(count($category_plan) === 2, 'only categories that actually change appear in the plan');
expect_true(
  $category_plan[4] === array('b_enabled' => 0),
  'a non-kept enabled category is disabled'
);
expect_true(
  $category_plan[47] === array('fk_i_parent_id' => null),
  'the kept category is re-parented to root, and its enabled flag is left alone since it already was enabled'
);
expect_true(!isset($category_plan[12]), 'an already-disabled non-kept category produces no change');

$applied_category_rows = array(
  array('pk_i_id' => 4, 'b_enabled' => 0, 'fk_i_parent_id' => null),
  array('pk_i_id' => 47, 'b_enabled' => 1, 'fk_i_parent_id' => null),
  array('pk_i_id' => 12, 'b_enabled' => 0, 'fk_i_parent_id' => 4),
);
expect_true(
  tourist_identity_category_plan($applied_category_rows, 47) === array(),
  'a second pass against already-applied category state produces an empty plan'
);

$currency_rows = array(
  array('pk_c_code' => 'USD', 'b_enabled' => 1),
  array('pk_c_code' => 'EUR', 'b_enabled' => 0),
);
expect_true(
  tourist_identity_enabled_currencies($currency_rows) === array(array('pk_c_code' => 'USD', 'b_enabled' => 1)),
  'only rows flagged b_enabled=1 are returned as enabled currencies'
);
expect_true(
  tourist_identity_enabled_currencies(array(array('pk_c_code' => 'EUR', 'b_enabled' => 0))) === array(),
  'no enabled rows yields an empty enabled-currencies list'
);

$currency_plan = tourist_identity_currency_plan($currency_rows, 'ARS');
expect_true($currency_plan['USD'] === array('b_enabled' => 0), 'a non-target enabled currency is disabled');
expect_true(!isset($currency_plan['EUR']), 'an already-disabled non-target currency produces no change');
expect_true(
  $currency_plan['ARS'] === array('b_enabled' => 1, 'insert' => true),
  'a missing target currency is planned for insertion and enabling'
);

$applied_currency_rows = array(
  array('pk_c_code' => 'USD', 'b_enabled' => 0),
  array('pk_c_code' => 'EUR', 'b_enabled' => 0),
  array('pk_c_code' => 'ARS', 'b_enabled' => 1),
);
expect_true(
  tourist_identity_currency_plan($applied_currency_rows, 'ARS') === array(),
  'a second pass against already-applied currency state produces an empty plan'
);

// --- tourist-identity: Phase 1.3 - snapshot build (null-vs-empty pref) and restore plan (round-trip, invalid JSON) ---

$snapshot_prefs = array(
  'osclass.pageTitle' => 'Anuncios de Osclass',
  'sigma.keyword_placeholder' => '',
);
$snapshot_categories = array(array('pk_i_id' => 47, 'b_enabled' => 1, 'fk_i_parent_id' => 4));
$snapshot_currencies = array(array('pk_c_code' => 'USD', 'b_enabled' => 1));

$snapshot_keys = tourist_identity_snapshot_pref_keys();
expect_true(
  in_array(array('sigma', 'logo'), $snapshot_keys, true),
  'the snapshot also captures the sigma logo preference so uninstall can restore the original logo'
);
foreach (tourist_identity_target_prefs() as $target_pref) {
  expect_true(
    in_array(array($target_pref[0], $target_pref[1]), $snapshot_keys, true),
    'every target preference is part of the snapshot keys'
  );
}
$logo_snapshot = json_decode(tourist_identity_build_snapshot(array('sigma.logo' => 'sigma_logo.png'), array(), array()), true);
expect_true(
  $logo_snapshot['prefs']['sigma.logo'] === 'sigma_logo.png',
  'the original logo filename is preserved in the snapshot'
);

$snapshot_json = tourist_identity_build_snapshot($snapshot_prefs, $snapshot_categories, $snapshot_currencies);
expect_true(is_string($snapshot_json) && $snapshot_json !== '', 'the snapshot is serialized as a non-empty JSON string');

$decoded_snapshot = json_decode($snapshot_json, true);
expect_true(
  $decoded_snapshot['prefs']['osclass.pageTitle'] === 'Anuncios de Osclass',
  'a present pref value is preserved verbatim in the snapshot'
);
expect_true(
  $decoded_snapshot['prefs']['sigma.keyword_placeholder'] === '',
  'a pref explicitly set to an empty string stays an empty string, not null'
);
expect_true(
  array_key_exists('osclass.pageDesc', $decoded_snapshot['prefs']) && $decoded_snapshot['prefs']['osclass.pageDesc'] === null,
  'a pref absent from the source map is recorded as null, distinct from an empty string'
);

$restore_plan = tourist_identity_restore_plan($snapshot_json);
expect_true(
  $restore_plan['prefs']['osclass.pageTitle'] === 'Anuncios de Osclass' && $restore_plan['prefs']['osclass.pageDesc'] === null,
  'restoring the snapshot round-trips present and null pref values'
);
expect_true(
  $restore_plan['categories'] === $snapshot_categories,
  'restoring the snapshot round-trips the category rows'
);
expect_true(
  $restore_plan['currencies'] === $snapshot_currencies,
  'restoring the snapshot round-trips the currency rows'
);

expect_throws(function () {
  tourist_identity_restore_plan('{not valid json');
}, 'restoring an invalid JSON snapshot throws instead of silently returning garbage');

// --- tourist-identity: Phase 1.4 - footer disclaimer (es_ES/en_US) and configure URL ---

expect_true(
  tourist_identity_disclaimer('es_ES') === 'Alquileres Temporarios es una vitrina de anuncios: conectamos propietarios y huéspedes, pero no procesamos reservas ni pagos ni intervenimos como intermediarios en la contratación. Todo acuerdo se realiza directamente entre las partes.',
  'the es_ES disclaimer matches the researched Spanish copy'
);
expect_true(
  tourist_identity_disclaimer('en_US') === 'Alquileres Temporarios is a listings showcase: we connect owners and guests, but we do not process bookings or payments and do not act as an intermediary in the agreement. All arrangements are made directly between the parties.',
  'the en_US disclaimer matches the researched English copy'
);
expect_true(
  tourist_identity_disclaimer('fr_FR') === tourist_identity_disclaimer('es_ES'),
  'an unrecognized locale falls back to the Spanish disclaimer'
);

expect_true(
  tourist_identity_configure_url('https://example.test/oc-admin/index.php', '/srv/osclass/oc-content/plugins/tourist-identity/index.php', '/srv/osclass/oc-content/plugins/')
    === 'https://example.test/oc-admin/index.php?page=plugins&action=admin&plugin=tourist-identity%2Findex.php',
  'the configure form posts back to the admin hook with the subfolder plugin path relative to the plugins directory'
);

// --- tourist-identity: Phase 2.1 - pref plan (changed-rows-only, null-vs-string aware, idempotent) ---

$pref_target = array(
  array('osclass', 'pageTitle', 'Alquileres Temporarios'),
  array('sigma', 'keyword_placeholder', 'Buscá por ciudad, provincia o tipo de alojamiento'),
);

$pref_current_before = array(
  'osclass.pageTitle' => 'Osclass Anuncios Clasificados',
  'sigma.keyword_placeholder' => null,
);
$pref_plan = tourist_identity_pref_plan($pref_current_before, $pref_target);
expect_true(count($pref_plan) === 2, 'both differing prefs appear in the plan');
expect_true(
  $pref_plan['osclass.pageTitle'] === array('section' => 'osclass', 'name' => 'pageTitle', 'value' => 'Alquileres Temporarios'),
  'a changed pref plans its section/name/value for osc_set_preference'
);
expect_true(
  $pref_plan['sigma.keyword_placeholder'] === array('section' => 'sigma', 'name' => 'keyword_placeholder', 'value' => 'Buscá por ciudad, provincia o tipo de alojamiento'),
  'a pref absent before (null) is included since null !== the target string'
);

$pref_current_after = array(
  'osclass.pageTitle' => 'Alquileres Temporarios',
  'sigma.keyword_placeholder' => 'Buscá por ciudad, provincia o tipo de alojamiento',
);
expect_true(
  tourist_identity_pref_plan($pref_current_after, $pref_target) === array(),
  'a second pass against already-applied pref state produces an empty plan'
);

// --- tourist-identity: showcase category linkage decision ---

expect_true(tourist_identity_showcase_link_needed(array(), 47) === true, 'an empty showcase linkage must be relinked to the kept category');
expect_true(tourist_identity_showcase_link_needed(array(47), 47) === false, 'a linkage already pointing only at the kept category needs no change');
expect_true(tourist_identity_showcase_link_needed(array('47'), 47) === false, 'string category ids from preferences are compared as integers');
expect_true(tourist_identity_showcase_link_needed(array(47, 44), 47) === true, 'extra linked categories are replaced by the kept category only');
expect_true(tourist_identity_showcase_link_needed(array(44), 47) === true, 'a linkage to another category is replaced');

// --- tourist-identity: Phase 1 - destination tree shape (6 regions, 51 leaves) ---

$destination_tree = tourist_identity_tree();

expect_true(count($destination_tree) === 6, 'the destination tree defines exactly six regions');

$expected_region_keys = array('buenos-aires', 'cordoba', 'cuyo', 'litoral', 'norte', 'patagonia');
$actual_region_keys = array_map(function ($region) { return $region['key']; }, $destination_tree);
expect_true(
  $actual_region_keys === $expected_region_keys,
  'the six regions appear in the confirmed order: Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia'
);

$expected_region_names = array(
  'buenos-aires' => 'Buenos Aires',
  'cordoba' => 'Córdoba',
  'cuyo' => 'Cuyo',
  'litoral' => 'Litoral',
  'norte' => 'Norte',
  'patagonia' => 'Patagonia',
);
foreach ($destination_tree as $region) {
  expect_true(
    $region['names']['es_ES'] === $expected_region_names[$region['key']] && $region['names']['en_US'] === $expected_region_names[$region['key']],
    'region "' . $region['key'] . '" carries its es_ES and en_US name'
  );
}

$buenos_aires_region = $destination_tree[0];
expect_true(
  $buenos_aires_region['key'] === 'buenos-aires' && $buenos_aires_region['anchor'] === 47,
  'the Buenos Aires region is anchored to the pre-existing category 47'
);
foreach ($destination_tree as $region) {
  if ($region['key'] !== 'buenos-aires') {
    expect_true(!array_key_exists('anchor', $region), 'only the Buenos Aires region carries an anchor id');
  }
}

$leaf_counts = array();
foreach ($destination_tree as $region) {
  $leaf_counts[$region['key']] = count($region['leaves']);
}
expect_true(
  $leaf_counts === array(
    'buenos-aires' => 10,
    'cordoba' => 8,
    'cuyo' => 7,
    'litoral' => 8,
    'norte' => 9,
    'patagonia' => 9,
  ),
  'each region declares the confirmed leaf count from research.md (45 destinations + 6 catch-alls)'
);

$total_leaves = array_sum($leaf_counts);
expect_true($total_leaves === 51, 'the tree defines exactly 51 leaf destinations across all regions');

$expected_first_leaf = array(
  'buenos-aires' => array('caba', 'Ciudad Autónoma de Buenos Aires (CABA)', 'Buenos Aires City'),
  'cordoba' => array('villa-carlos-paz', 'Villa Carlos Paz', 'Villa Carlos Paz'),
  'cuyo' => array('ciudad-de-mendoza', 'Ciudad de Mendoza', 'Mendoza City'),
  'litoral' => array('puerto-iguazu', 'Puerto Iguazú (Cataratas)', 'Puerto Iguazú (Iguazú Falls)'),
  'norte' => array('salta', 'Salta (ciudad y Valles Calchaquíes)', 'Salta (city & Calchaquí Valleys)'),
  'patagonia' => array('san-carlos-de-bariloche', 'San Carlos de Bariloche', 'San Carlos de Bariloche'),
);
foreach ($destination_tree as $region) {
  expect_true(
    $region['leaves'][0] === $expected_first_leaf[$region['key']],
    'region "' . $region['key'] . '" lists its first research.md destination first, in research.md order'
  );
}

$expected_catch_all = array(
  'buenos-aires' => array('otros-destinos-buenos-aires', 'Otros destinos de Buenos Aires', 'Other Buenos Aires destinations'),
  'cordoba' => array('otros-destinos-cordoba', 'Otros destinos de Córdoba', 'Other Córdoba destinations'),
  'cuyo' => array('otros-destinos-cuyo', 'Otros destinos de Cuyo', 'Other Cuyo destinations'),
  'litoral' => array('otros-destinos-litoral', 'Otros destinos del Litoral', 'Other Litoral destinations'),
  'norte' => array('otros-destinos-norte', 'Otros destinos del Norte', 'Other Norte destinations'),
  'patagonia' => array('otros-destinos-patagonia', 'Otros destinos de Patagonia', 'Other Patagonia destinations'),
);
foreach ($destination_tree as $region) {
  $last_leaf = $region['leaves'][count($region['leaves']) - 1];
  expect_true(
    $last_leaf === $expected_catch_all[$region['key']],
    'region "' . $region['key'] . '" lists its "Otros destinos" catch-all leaf last'
  );
}

$all_keys = array();
foreach ($destination_tree as $region) {
  $all_keys[] = $region['key'];
  foreach ($region['leaves'] as $leaf) {
    expect_true(preg_match('/^[a-z0-9-]+$/', $leaf[0]) === 1, 'leaf key "' . $leaf[0] . '" is a lowercase ASCII slug matching ^[a-z0-9-]+$');
    expect_true($leaf[1] !== '' && $leaf[2] !== '', 'leaf "' . $leaf[0] . '" carries both an es_ES and an en_US name');
    $all_keys[] = $leaf[0];
  }
}
expect_true(count($all_keys) === count(array_unique($all_keys)), 'every region and leaf key is unique across the whole tree');
expect_true(count($all_keys) === 57, 'the tree carries 6 region keys plus 51 leaf keys');

// --- tourist-identity: Phase 2.1 - tree_plan (regions+leaves insert/update/describe, idempotent, map-drift reinsert) ---

$full_tree = tourist_identity_tree();
$locales = array('es_ES', 'en_US');

// Case A: empty map + a stale anchor row -> anchor gets describe/update only, every other region
// and leaf is planned for insertion (regions first, then leaves).
$anchor_row_stale = array('pk_i_id' => 47, 'b_enabled' => 1, 'fk_i_parent_id' => 4, 'i_position' => 3);
$stale_rows = array(47 => $anchor_row_stale);
$stale_descs = array(
  47 => array(
    'es_ES' => array('s_name' => 'Alquiler Vacacional', 's_description' => 'Descripción vieja', 's_slug' => 'alquiler-vacacional'),
    'en_US' => array('s_name' => 'Vacation Rental', 's_description' => 'Old description', 's_slug' => 'vacation-rental'),
  ),
);

$plan_empty_map = tourist_identity_tree_plan($full_tree, $stale_rows, $stale_descs, array(), $locales, $anchor_row_stale);

expect_true(count($plan_empty_map['insert']) === 56, 'an empty map plans 5 region inserts + 51 leaf inserts (Buenos Aires stays anchored)');
$insert_keys = array_map(function ($entry) { return $entry['key']; }, $plan_empty_map['insert']);
expect_true(!in_array('buenos-aires', $insert_keys, true), 'the anchored Buenos Aires region is never planned for insertion');
expect_true(
  array_slice($insert_keys, 0, 5) === array('cordoba', 'cuyo', 'litoral', 'norte', 'patagonia'),
  'the five non-anchor regions are planned for insertion before any leaf'
);
expect_true($insert_keys[5] === 'caba', 'leaves are planned for insertion only after every region, starting with the first Buenos Aires leaf');
expect_true(
  $plan_empty_map['insert'][0]['parent_key'] === null && $plan_empty_map['insert'][0]['position'] === 1,
  'a region insert carries no parent_key and its region index as position'
);
expect_true(
  $plan_empty_map['insert'][5]['parent_key'] === 'buenos-aires' && $plan_empty_map['insert'][5]['position'] === 0,
  'a leaf insert carries its region key as parent_key and its leaf index as position'
);
expect_true(
  isset($plan_empty_map['update'][47]) && $plan_empty_map['update'][47]['fk_i_parent_id'] === null,
  'the anchor row is planned to move to the root of the tree'
);
expect_true(
  isset($plan_empty_map['describe'][47]['es_ES']) && $plan_empty_map['describe'][47]['es_ES']['s_name'] === 'Buenos Aires' && $plan_empty_map['describe'][47]['es_ES']['s_slug'] === 'buenos-aires',
  'the anchor description is planned to become "Buenos Aires" with slug "buenos-aires"'
);

// Case B: a fully-applied tree (idempotent second pass) -> empty plan.
$applied_rows = array(47 => array('pk_i_id' => 47, 'b_enabled' => 1, 'fk_i_parent_id' => null, 'i_position' => 0));
$applied_descs = array(
  47 => array(
    'es_ES' => array('s_name' => 'Buenos Aires', 's_description' => '', 's_slug' => 'buenos-aires'),
    'en_US' => array('s_name' => 'Buenos Aires', 's_description' => '', 's_slug' => 'buenos-aires'),
  ),
);
$applied_map = array();
$next_id = 100;
foreach ($full_tree as $region_index => $region) {
  if (!isset($region['anchor'])) {
    $region_id = $next_id++;
    $applied_map[$region['key']] = $region_id;
    $applied_rows[$region_id] = array('pk_i_id' => $region_id, 'b_enabled' => 1, 'fk_i_parent_id' => null, 'i_position' => $region_index);
    $applied_descs[$region_id] = array(
      'es_ES' => array('s_name' => $region['names']['es_ES'], 's_description' => '', 's_slug' => $region['key']),
      'en_US' => array('s_name' => $region['names']['en_US'], 's_description' => '', 's_slug' => $region['key']),
    );
    $region_parent_id = $region_id;
  } else {
    $region_parent_id = 47;
  }

  foreach ($region['leaves'] as $leaf_index => $leaf) {
    list($leaf_key, $es_name, $en_name) = $leaf;
    $leaf_id = $next_id++;
    $applied_map[$leaf_key] = $leaf_id;
    $applied_rows[$leaf_id] = array('pk_i_id' => $leaf_id, 'b_enabled' => 1, 'fk_i_parent_id' => $region_parent_id, 'i_position' => $leaf_index);
    $applied_descs[$leaf_id] = array(
      'es_ES' => array('s_name' => $es_name, 's_description' => '', 's_slug' => $leaf_key),
      'en_US' => array('s_name' => $en_name, 's_description' => '', 's_slug' => $leaf_key),
    );
  }
}

$plan_applied = tourist_identity_tree_plan($full_tree, $applied_rows, $applied_descs, $applied_map, $locales, $applied_rows[47]);
expect_true(
  $plan_applied === array('insert' => array(), 'update' => array(), 'describe' => array()),
  'a second pass against a fully-applied tree plans no inserts, updates, or descriptions'
);

// Case C: a map entry whose row was deleted out from under the plugin is re-inserted, not silently skipped.
$drifted_map = array('cordoba' => 999);
$drifted_rows = array(47 => $applied_rows[47]);
$drifted_descs = array(47 => $applied_descs[47]);
$plan_drifted = tourist_identity_tree_plan($full_tree, $drifted_rows, $drifted_descs, $drifted_map, $locales, $drifted_rows[47]);
$drifted_insert_keys = array_map(function ($entry) { return $entry['key']; }, $plan_drifted['insert']);
expect_true(
  in_array('cordoba', $drifted_insert_keys, true),
  'a map id missing from the current rows is re-inserted instead of being treated as already applied'
);

// --- tourist-identity: Phase 2.2 - leaf ids resolved from a full map, in tree order ---

$full_map_for_leaf_ids = array();
$expected_leaf_ids = array();
$leaf_id_counter = 200;
foreach ($full_tree as $region) {
  foreach ($region['leaves'] as $leaf) {
    $full_map_for_leaf_ids[$leaf[0]] = $leaf_id_counter;
    $expected_leaf_ids[] = $leaf_id_counter;
    $leaf_id_counter++;
  }
}
// Region keys also live in the same production map; leaf_ids must ignore them.
$full_map_for_leaf_ids['cordoba'] = 9999;

expect_true(
  tourist_identity_leaf_ids($full_tree, $full_map_for_leaf_ids) === $expected_leaf_ids,
  'leaf ids resolve to the 51 mapped leaf ids in tree order, ignoring region keys in the same map'
);

$partial_map_for_leaf_ids = array($full_tree[0]['leaves'][0][0] => 500);
expect_true(
  tourist_identity_leaf_ids($full_tree, $partial_map_for_leaf_ids) === array(500),
  'leaf ids skips any leaf key not yet present in the map instead of inserting a placeholder'
);

// --- tourist-identity: Phase 2.3 - showcase link needed, array-target set comparison (scalar kept) ---

expect_true(
  tourist_identity_showcase_link_needed(array(1, 2, 3), array(3, 2, 1)) === false,
  'an array target matches selected ids regardless of order'
);
expect_true(
  tourist_identity_showcase_link_needed(array(1, 2), array(1, 2, 3)) === true,
  'a selected set missing an id from the array target still needs relinking'
);
expect_true(
  tourist_identity_showcase_link_needed(array(47), 47) === false,
  'the previously-supported scalar target behavior is preserved'
);

// --- tourist-identity: Phase 2.4 - slug resolver (free base, -2/-3 collision, self-exclusion) ---

$owners_free = array('tandil' => null);
expect_true(
  tourist_identity_resolve_slug('tandil', function ($slug) use ($owners_free) {
    return isset($owners_free[$slug]) ? $owners_free[$slug] : null;
  }, 0) === 'tandil',
  'a free base slug is returned unchanged'
);

$owners_collision = array('tandil' => 99, 'tandil-2' => null);
expect_true(
  tourist_identity_resolve_slug('tandil', function ($slug) use ($owners_collision) {
    return isset($owners_collision[$slug]) ? $owners_collision[$slug] : null;
  }, 0) === 'tandil-2',
  'a base slug owned by another category resolves to the first free numbered suffix'
);

$owners_double_collision = array('tandil' => 99, 'tandil-2' => 98, 'tandil-3' => null);
expect_true(
  tourist_identity_resolve_slug('tandil', function ($slug) use ($owners_double_collision) {
    return isset($owners_double_collision[$slug]) ? $owners_double_collision[$slug] : null;
  }, 0) === 'tandil-3',
  'two consecutive collisions resolve to the second numbered suffix'
);

$owners_self = array('tandil' => 42);
expect_true(
  tourist_identity_resolve_slug('tandil', function ($slug) use ($owners_self) {
    return isset($owners_self[$slug]) ? $owners_self[$slug] : null;
  }, 42) === 'tandil',
  'a slug already owned by the category being described is not treated as a collision'
);

// --- tourist-identity: Phase 2.5 - supplementary tree snapshot build + restore round-trip ---

$anchor_row_for_snapshot = array('pk_i_id' => 47, 'i_position' => 3, 'b_enabled' => 1, 'fk_i_parent_id' => 4);
$anchor_descs_for_snapshot = array(
  'es_ES' => array('s_name' => 'Alquiler Vacacional', 's_description' => 'Descripción original', 's_slug' => 'alquiler-vacacional'),
  'en_US' => array('s_name' => 'Vacation Rental', 's_description' => 'Original description', 's_slug' => 'vacation-rental'),
);

$tree_snapshot_json = tourist_identity_build_tree_snapshot($anchor_row_for_snapshot, $anchor_descs_for_snapshot);
expect_true(is_string($tree_snapshot_json) && $tree_snapshot_json !== '', 'the supplementary tree snapshot serializes as a non-empty JSON string');

$decoded_tree_snapshot = json_decode($tree_snapshot_json, true);
expect_true($decoded_tree_snapshot['anchor']['id'] === 47, 'the tree snapshot records the anchor category id');
expect_true($decoded_tree_snapshot['anchor']['i_position'] === 3, 'the tree snapshot records the anchor position before it moves to the root');

$tree_restore_plan = tourist_identity_tree_restore_plan($tree_snapshot_json);
expect_true($tree_restore_plan['id'] === 47, 'the tree restore plan round-trips the anchor id');
expect_true($tree_restore_plan['i_position'] === 3, 'the tree restore plan round-trips the anchor position');
expect_true(
  $tree_restore_plan['descriptions'] === $anchor_descs_for_snapshot,
  'the tree restore plan round-trips the anchor descriptions verbatim'
);

expect_throws(function () {
  tourist_identity_tree_restore_plan('{not valid json');
}, 'restoring an invalid tree snapshot JSON throws instead of silently returning garbage');

// --- tourist-identity: Phase 2.6 - uninstall plan (delete empty, disable in-use, leaves before regions, null=disable) ---

$uninstall_map_simple = array('leaf-empty' => 10);
$uninstall_rows_simple = array(10 => array('fk_i_parent_id' => 1));
expect_true(
  tourist_identity_uninstall_plan($uninstall_map_simple, $uninstall_rows_simple, array(10 => 0))
    === array('delete' => array(10), 'disable' => array(), 'map' => array()),
  'a plugin-created row with zero linked items is deleted and dropped from the map'
);

$uninstall_map_in_use = array('leaf-in-use' => 11);
$uninstall_rows_in_use = array(11 => array('fk_i_parent_id' => 1));
expect_true(
  tourist_identity_uninstall_plan($uninstall_map_in_use, $uninstall_rows_in_use, array(11 => 3))
    === array('delete' => array(), 'disable' => array(11), 'map' => array('leaf-in-use' => 11)),
  'a plugin-created row holding items is disabled and kept in the map, never deleted'
);

$uninstall_map_null_count = array('leaf-unknown' => 12);
$uninstall_rows_null_count = array(12 => array('fk_i_parent_id' => 1));
expect_true(
  tourist_identity_uninstall_plan($uninstall_map_null_count, $uninstall_rows_null_count, array())
    === array('delete' => array(), 'disable' => array(12), 'map' => array('leaf-unknown' => 12)),
  'an unknown (null) item count disables rather than deletes, since deletion cannot be proven safe'
);

$uninstall_map_region_deletable = array('region' => 1, 'leaf' => 10);
$uninstall_rows_region_deletable = array(
  1 => array('fk_i_parent_id' => null),
  10 => array('fk_i_parent_id' => 1),
);
$plan_region_deletable = tourist_identity_uninstall_plan($uninstall_map_region_deletable, $uninstall_rows_region_deletable, array(1 => 0, 10 => 0));
expect_true(
  $plan_region_deletable['delete'] === array(10, 1),
  'an empty leaf is deleted before its now-empty parent region, never the reverse'
);
expect_true($plan_region_deletable['map'] === array(), 'both deleted ids are dropped from the kept map');

$uninstall_map_region_kept = array('region' => 2, 'leaf' => 20);
$uninstall_rows_region_kept = array(
  2 => array('fk_i_parent_id' => null),
  20 => array('fk_i_parent_id' => 2),
);
$plan_region_kept = tourist_identity_uninstall_plan($uninstall_map_region_kept, $uninstall_rows_region_kept, array(2 => 0, 20 => 5));
expect_true(
  $plan_region_kept['delete'] === array() && $plan_region_kept['disable'] === array(20, 2),
  'a region whose leaf survives (holds items) is disabled instead of deleted, even though the region itself has zero direct items'
);
expect_true(
  $plan_region_kept['map'] === array('region' => 2, 'leaf' => 20),
  'both the surviving leaf and its blocked-from-deletion region stay in the kept map'
);

// --- tourist-identity: Phase 2.7 - prune ids (remove given ids, keep order/uniqueness) ---

expect_true(
  tourist_identity_prune_ids(array(47, 12, 8), array(12)) === array(47, 8),
  'removed ids are dropped while the remaining order is preserved'
);
expect_true(
  tourist_identity_prune_ids(array(47, 12, 8, 12), array()) === array(47, 12, 8),
  'duplicate selected ids are deduplicated even when nothing is removed'
);
expect_true(
  tourist_identity_prune_ids(array(1, 2, 3), array(1, 2, 3)) === array(),
  'removing every selected id leaves an empty list'
);

// --- tourist-identity: Phase 2.8 - re-apply message formatter and version bump ---

expect_true(
  tourist_identity_reapply_message(0) === 'Re-apply complete: 0 change(s).',
  'a zero-change re-apply reports "0 change(s)"'
);
expect_true(
  tourist_identity_reapply_message(7) === 'Re-apply complete: 7 change(s).',
  'a re-apply with changes reports the exact count'
);
expect_true(tourist_identity_version() === '1.1.0', 'the identity plugin version is bumped for the destination-tree feature');

// --- tourist-destination-categories: uninstall never deletes or disables protected pre-existing categories ---

$protected_plan = tourist_identity_uninstall_plan(
  array('buenos-aires' => 47, 'tandil' => 200),
  array(47 => array('fk_i_parent_id' => null), 200 => array('fk_i_parent_id' => 47)),
  array(47 => 0, 200 => 0),
  array(47)
);
expect_true(!in_array(47, $protected_plan['delete'], true), 'a protected anchor category is never deleted even if it leaks into the created-id map');
expect_true(!in_array(47, $protected_plan['disable'], true), 'a protected anchor category is never disabled by the uninstall plan; its snapshot restores it');
expect_true(in_array(200, $protected_plan['delete'], true), 'an empty created leaf under the protected anchor is still deleted');

// --- tourist-destination-categories: Phase 3.1 - tree locales (fixed es_ES/en_US set, intersected with what is installed) ---

expect_true(
  tourist_identity_tree_locales(array('es_ES', 'en_US', 'fr_FR')) === array('es_ES', 'en_US'),
  'both known tree locales are kept, in fixed order, when installed alongside an unrelated locale'
);
expect_true(
  tourist_identity_tree_locales(array('en_US')) === array('en_US'),
  'a site missing es_ES only writes descriptions for the locale it actually has installed'
);
expect_true(
  tourist_identity_tree_locales(array('fr_FR')) === array(),
  'a site with neither tree locale installed plans no description locales at all'
);

// --- tourist-destination-categories: Phase 3.1 - rows excluding ids (tree-managed rows are not "others") ---

$all_category_rows = array(
  array('pk_i_id' => 4, 'b_enabled' => 1, 'fk_i_parent_id' => null),
  array('pk_i_id' => 47, 'b_enabled' => 1, 'fk_i_parent_id' => null),
  array('pk_i_id' => 200, 'b_enabled' => 1, 'fk_i_parent_id' => 47),
  array('pk_i_id' => 12, 'b_enabled' => 0, 'fk_i_parent_id' => 4),
);
$rows_excluding_tree = tourist_identity_rows_excluding_ids($all_category_rows, array(47, 200));
expect_true(
  count($rows_excluding_tree) === 2,
  'excluding the anchor and a tree-created leaf leaves only the non-tree rows'
);
$remaining_ids = array_map(function ($row) { return $row['pk_i_id']; }, $rows_excluding_tree);
expect_true(
  $remaining_ids === array(4, 12),
  'the remaining rows keep their original relative order after exclusion'
);
expect_true(
  tourist_identity_rows_excluding_ids($all_category_rows, array()) === $all_category_rows,
  'excluding no ids returns every row unchanged'
);

echo "Tourist showcase checks passed.\n";
