<?php

require __DIR__ . '/../plugins/tourist-showcase/tourist-showcase-lib.php';
require __DIR__ . '/../plugins/tourist-identity/tourist-identity-lib.php';
require __DIR__ . '/../plugins/tourist-identity/tourist-identity-tree.php';
require __DIR__ . '/../plugins/tourist-directory/tourist-directory-lib.php';

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

// --- tourist-showcase: dropdown vocabulary extension + merge_options idempotence (U1) ---

expect_true(
  strpos(tourist_showcase_definitions()[0]['options'], 'Apart hotel') !== false
    && strpos(tourist_showcase_definitions()[0]['options'], 'Complejo de departamentos') !== false,
  'the accommodation type definition offers the two new values alongside the previous ones'
);

$showcase_current_options = array('Apartamento', 'Casa', 'Cabaña', 'Habitación privada', 'Hostería', 'Otro');
$showcase_target_options = explode('|', tourist_showcase_definitions()[0]['options']);
$showcase_merged_options = tourist_showcase_merge_options($showcase_current_options, $showcase_target_options);
expect_true(
  $showcase_merged_options === array('Apartamento', 'Casa', 'Cabaña', 'Habitación privada', 'Hostería', 'Otro', 'Apart hotel', 'Complejo de departamentos'),
  'merging a pre-existing options list with the target keeps the existing order and appends only the missing values'
);
expect_true(
  tourist_showcase_merge_options($showcase_merged_options, $showcase_target_options) === $showcase_merged_options,
  'merging an already-merged options list with the same target is idempotent'
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

// --- tourist-destination-categories: re-apply relinks every leaf after a showcase reinstall ---

$full_tree_map = array();
$next_leaf_id = 200;
foreach (tourist_identity_tree() as $tree_region) {
  foreach ($tree_region['leaves'] as $tree_leaf) {
    $full_tree_map[$tree_leaf[0]] = $next_leaf_id++;
  }
}
$reinstall_leaf_ids = tourist_identity_leaf_ids(tourist_identity_tree(), $full_tree_map);
expect_true(count($reinstall_leaf_ids) === 51, 'all 51 destination leaves are resolved from the created-id map');
expect_true(
  tourist_identity_showcase_link_needed(array(), $reinstall_leaf_ids) === true,
  'after a showcase reinstall empties category_ids, re-apply must relink the destinations'
);
expect_true(
  tourist_identity_showcase_link_needed(array_reverse($reinstall_leaf_ids), $reinstall_leaf_ids) === false,
  'once relinked, a further re-apply leaves the showcase linkage untouched'
);

// --- tourist-directory: Phase 1.3/1.4 - version, seed columns, type map, parse_rows, validate_row (U1) ---

expect_true(is_string(tourist_directory_version()) && tourist_directory_version() !== '', 'the directory plugin exposes a non-empty version string');

expect_true(
  tourist_directory_seed_columns() === array('id', 'estado_catalogo', 'nombre', 'localidad', 'destino', 'tipo', 'web'),
  'the seed whitelist keeps only the control key, the routing state, and the five public fields'
);

$directory_type_map = tourist_directory_type_map();
expect_true(
  $directory_type_map === array(
    'apart_hotel' => 'Apart hotel',
    'complejo_departamentos' => 'Complejo de departamentos',
    'departamentos_con_servicios' => 'Apartamento',
    'cabanas' => 'Cabaña',
  ),
  'the seed tipo map resolves every seed key to its showcase dropdown label'
);

$seed_header = array('id', 'nombre', 'localidad', 'destino', 'tipo', 'web', 'estado_catalogo', 'contacto', 'notas', 'fuente');
$seed_rows = array(
  array('101', 'Cabañas del Lago', 'tandil', 'tandil', 'cabanas', 'https://cabanasdellago.example.com', 'candidato', '+54 11 555', 'nota interna', 'planilla-2026'),
);
$parsed_seed_rows = tourist_directory_parse_rows($seed_header, $seed_rows);
expect_true(count($parsed_seed_rows) === 1, 'parse_rows returns one parsed row per seed row');
expect_true(
  $parsed_seed_rows[0] === array(
    'id' => '101',
    'estado_catalogo' => 'candidato',
    'nombre' => 'Cabañas del Lago',
    'localidad' => 'tandil',
    'destino' => 'tandil',
    'tipo' => 'cabanas',
    'web' => 'https://cabanasdellago.example.com',
  ),
  'parse_rows keeps only the seed whitelist columns, dropping contacto/notas/fuente entirely'
);

$directory_leaf_keys = array('tandil', 'caba');
$directory_cat_map = array('tandil' => 200, 'caba' => 201);

$valid_row_result = tourist_directory_validate_row($parsed_seed_rows[0], $directory_leaf_keys, $directory_cat_map, 120);
expect_true($valid_row_result['ok'] === true, 'a well-formed candidato row with a known destino/tipo and a safe URL validates');
expect_true(
  $valid_row_result['entry']['tipo'] === 'Cabaña' && $valid_row_result['entry']['web'] === 'https://cabanasdellago.example.com',
  'a validated row maps its raw tipo key to the showcase label and keeps the safe URL'
);

$duplicate_result = tourist_directory_validate_row($parsed_seed_rows[0], $directory_leaf_keys, $directory_cat_map, 120, array('101'));
expect_true(
  $duplicate_result === array('ok' => false, 'error' => 'duplicate_id', 'id' => '101'),
  'a row whose id was already seen earlier in this seed pass is rejected as a duplicate'
);

$unknown_destino_row = $parsed_seed_rows[0];
$unknown_destino_row['destino'] = 'atlantida-perdida';
expect_true(
  tourist_directory_validate_row($unknown_destino_row, $directory_leaf_keys, $directory_cat_map, 120)['error'] === 'unknown_destino',
  'a destino that is not a known destination-tree leaf is rejected'
);

expect_true(
  tourist_directory_validate_row($parsed_seed_rows[0], array('tandil'), array(), 120)['error'] === 'unknown_destino',
  'a destino that is a tree leaf but missing from the tourist_identity.category_map pref is rejected'
);

$unknown_tipo_row = $parsed_seed_rows[0];
$unknown_tipo_row['tipo'] = 'yate_flotante';
expect_true(
  tourist_directory_validate_row($unknown_tipo_row, $directory_leaf_keys, $directory_cat_map, 120)['error'] === 'unknown_tipo',
  'a tipo key absent from the type map is rejected'
);

$unsafe_url_row = $parsed_seed_rows[0];
$unsafe_url_row['web'] = 'javascript:alert(1)';
expect_true(
  tourist_directory_validate_row($unsafe_url_row, $directory_leaf_keys, $directory_cat_map, 120)['error'] === 'unsafe_url',
  'a non-http(s) URL scheme is rejected as unsafe'
);

$missing_web_row = $parsed_seed_rows[0];
$missing_web_row['web'] = '';
expect_true(
  tourist_directory_validate_row($missing_web_row, $directory_leaf_keys, $directory_cat_map, 120)['error'] === 'missing_web',
  'a candidato row without a web address is rejected'
);

$long_title_row = $parsed_seed_rows[0];
$long_title_row['nombre'] = str_repeat('a', 121);
expect_true(
  tourist_directory_validate_row($long_title_row, $directory_leaf_keys, $directory_cat_map, 120)['error'] === 'invalid_title',
  'a title longer than the configured max length is rejected'
);

// --- tourist-directory: Phase 1.5/1.6 - fingerprint, description, locales/text, placeholder email, safe url, removal url, guard decision (U1) ---

$fingerprint_entry_a = array('nombre' => 'Cabañas del Lago', 'localidad' => 'tandil', 'destino' => 'tandil', 'tipo' => 'Cabaña', 'web' => 'https://cabanasdellago.example.com');
$fingerprint_entry_b = $fingerprint_entry_a;
$fingerprint_entry_b['localidad'] = 'caba';

expect_true(
  tourist_directory_fingerprint($fingerprint_entry_a) === tourist_directory_fingerprint($fingerprint_entry_a),
  'the same entry data always produces the same fingerprint'
);
expect_true(
  tourist_directory_fingerprint($fingerprint_entry_a) !== tourist_directory_fingerprint($fingerprint_entry_b),
  'a changed public field produces a different fingerprint, so an update can be detected'
);
expect_true(
  strlen(tourist_directory_fingerprint($fingerprint_entry_a)) === 40,
  'the fingerprint is a 40-character sha1 hex digest, matching the s_fingerprint CHAR(40) column'
);

expect_true(
  tourist_directory_description('Cabañas del Lago', 'cabanas', 'Tandil', 'es_ES')
    === 'Cabañas del Lago es un Cabaña en Tandil. Ficha informativa con datos públicos; para consultas y reservas visite el sitio oficial.',
  'the es_ES description is a factual generated sentence built from the mapped type label'
);
expect_true(
  tourist_directory_description('Cabañas del Lago', 'cabanas', 'Tandil', 'en_US')
    === 'Cabañas del Lago is a Cabaña in Tandil. Informational public-data listing; for enquiries and bookings, please visit the official website.',
  'the en_US description is generated from the same facts, never a translation of copied marketing text'
);

expect_true(
  tourist_directory_locales(array('es_ES', 'en_US', 'fr_FR')) === array('es_ES', 'en_US'),
  'both known description locales are kept, in fixed order, alongside an unrelated installed locale'
);
expect_true(
  tourist_directory_locales(array('en_US')) === array('en_US'),
  'a site missing es_ES only plans the description locale it actually has installed'
);

expect_true(tourist_directory_text('Hola', 'Hello', 'es_ES') === 'Hola', 'an es_ES locale selects the Spanish text');
expect_true(tourist_directory_text('Hola', 'Hello', 'en_US') === 'Hello', 'a non-Spanish locale selects the English text');

expect_true(tourist_directory_is_placeholder_email('directorio-abc123@directorio.invalid') === true, 'the .invalid placeholder domain is recognized');
expect_true(tourist_directory_is_placeholder_email('admin@example.com') === true, 'an example.* domain is recognized as a placeholder');
expect_true(tourist_directory_is_placeholder_email('webmaster@localhost') === true, 'the bare localhost domain is recognized as a placeholder');
expect_true(tourist_directory_is_placeholder_email('not-an-email') === true, 'a malformed address is treated as a placeholder, never trusted for delivery');
expect_true(tourist_directory_is_placeholder_email('contacto@alquileres.diazignacio.ar') === false, 'a real, working-looking address is not flagged as a placeholder');

expect_true(tourist_directory_safe_url('https://cabanasdellago.example.com/') === 'https://cabanasdellago.example.com/', 'a well-formed https URL is returned unchanged');
expect_true(tourist_directory_safe_url('http://cabanasdellago.example.com') === 'http://cabanasdellago.example.com', 'a well-formed http URL is also accepted');
expect_true(tourist_directory_safe_url('javascript:alert(1)') === false, 'a non-http(s) scheme is rejected');
expect_true(tourist_directory_safe_url('ftp://example.com/file') === false, 'an ftp URL is rejected');
expect_true(tourist_directory_safe_url('') === false, 'an empty URL is rejected');

// tourist_directory_removal_url() was removed in the Amendment (U6): the page=contact removal
// link it built is superseded by a plugin-owned route-based removal form (see design.md
// Amendment, tasks.md 6.4/7.1-7.5).

expect_true(tourist_directory_should_block(null, 'anything@example.com', 'directorio-abc@directorio.invalid') === true, 'a failed marker lookup (null) fails closed and blocks');
expect_true(tourist_directory_should_block(true, 'anything@example.com', 'directorio-abc@directorio.invalid') === true, 'a confirmed directory entry always blocks');
expect_true(tourist_directory_should_block(false, 'directorio-abc@directorio.invalid', 'directorio-abc@directorio.invalid') === true, 'a non-entry item whose contact email still matches the placeholder (an orphan) blocks too');
expect_true(tourist_directory_should_block(false, 'owner@example.com', 'directorio-abc@directorio.invalid') === false, 'a normal owner item with a real contact email does not block');

// --- tourist-directory: guard fails closed for any lookup result other than an explicit false ---

foreach (array('1', 1, 'true', 0, '0', '', array()) as $ambiguous_lookup) {
  expect_true(
    tourist_directory_should_block($ambiguous_lookup, 'owner@complejo.com.ar', 'directorio-abc@directorio.invalid') === true,
    'an ambiguous marker lookup result (' . var_export($ambiguous_lookup, true) . ') blocks the request'
  );
}
expect_true(
  tourist_directory_should_block(false, 'owner@complejo.com.ar', 'directorio-abc@directorio.invalid') === false,
  'only an explicit false lookup lets an owner listing through'
);

// --- tourist-directory: Phase 2.1/2.2 - item_params (price NULL, placeholder contactEmail, admin owner fields) (U2) ---

$item_params_entry = array(
  'id' => '101',
  'estado_catalogo' => 'candidato',
  'nombre' => 'Cabañas del Lago',
  'localidad' => 'Tandil',
  'destino' => 'tandil',
  'tipo' => 'Cabaña',
  'tipo_key' => 'cabanas',
  'web' => 'https://cabanasdellago.example.com',
);
$item_params_ctx = array(
  'catId' => 200,
  'contactEmail' => 'directorio-abc123@directorio.invalid',
  'locales' => array('es_ES', 'en_US'),
);

$item_params_result = tourist_directory_item_params($item_params_entry, $item_params_ctx);

expect_true($item_params_result['price'] === '', 'item_params sets an empty-string price so ItemActions stores i_price as NULL, never 0');
expect_true($item_params_result['contactEmail'] === 'directorio-abc123@directorio.invalid', 'item_params uses the per-install placeholder contact email from ctx, never a real address');
expect_true($item_params_result['contactName'] === 'Directorio público', 'item_params sets the public-directory contact name, not an owner name');
expect_true($item_params_result['showEmail'] === 0 && $item_params_result['showPhone'] === 0, 'item_params never exposes email or phone for a directory entry');
expect_true($item_params_result['dt_expiration'] === '-1', 'item_params requests a non-expiring listing (admin-only sentinel, per prepareData)');
expect_true($item_params_result['catId'] === 200, 'item_params carries the resolved category id from ctx unchanged');
expect_true(
  $item_params_result['title']['es_ES'] === 'Cabañas del Lago' && $item_params_result['title']['en_US'] === 'Cabañas del Lago',
  'item_params keeps the entry name as-is for both installed locales (title is not translated)'
);
expect_true(
  $item_params_result['description']['es_ES'] === tourist_directory_description('Cabañas del Lago', 'cabanas', 'Tandil', 'es_ES')
    && $item_params_result['description']['en_US'] === tourist_directory_description('Cabañas del Lago', 'cabanas', 'Tandil', 'en_US'),
  'item_params generates the factual per-locale description via tourist_directory_description, never copied text'
);

$item_params_single_locale = tourist_directory_item_params($item_params_entry, array('catId' => 5, 'contactEmail' => 'x@directorio.invalid', 'locales' => array('en_US')));
expect_true(
  array_keys($item_params_single_locale['title']) === array('en_US') && array_keys($item_params_single_locale['description']) === array('en_US'),
  'item_params only writes title/description for the locales actually passed in ctx, never a locale the site lacks'
);

// --- tourist-directory: Phase 3.1/3.2 - plan(valid, existing, flags) transitions (U3) ---

function directory_plan_entry($id, $estado, $localidad = 'Tandil') {
  return array(
    'id' => $id,
    'estado_catalogo' => $estado,
    'nombre' => 'Cabañas del Lago',
    'localidad' => $localidad,
    'destino' => 'tandil',
    'tipo' => 'Cabaña',
    'tipo_key' => 'cabanas',
    'web' => 'https://cabanasdellago.example.com',
  );
}

$plan_entry_create = directory_plan_entry('c1', 'candidato');
$plan_entry_noop = directory_plan_entry('c2', 'candidato');
$plan_entry_update = directory_plan_entry('c3', 'candidato', 'Tandil Centro');
$plan_entry_gated = directory_plan_entry('c4', 'candidato');
$plan_entry_reactivate = directory_plan_entry('c5', 'candidato');
$plan_entry_missing = directory_plan_entry('c6', 'candidato');
$plan_entry_missing_retired = directory_plan_entry('c7', 'candidato');
$plan_entry_baja_active = directory_plan_entry('c8', 'baja');
$plan_entry_anuncio_active = directory_plan_entry('c9', 'anuncio_propio');
$plan_entry_baja_retired = directory_plan_entry('c10', 'baja');
$plan_entry_baja_no_existing = directory_plan_entry('c11', 'baja');
$plan_entry_other_state = directory_plan_entry('c12', 'publicado');

$plan_valid = array(
  $plan_entry_create,
  $plan_entry_noop,
  $plan_entry_update,
  $plan_entry_gated,
  $plan_entry_reactivate,
  $plan_entry_missing,
  $plan_entry_missing_retired,
  $plan_entry_baja_active,
  $plan_entry_anuncio_active,
  $plan_entry_baja_retired,
  $plan_entry_baja_no_existing,
  $plan_entry_other_state,
);

$plan_existing = array(
  'c2' => array('item_id' => 302, 'fingerprint' => tourist_directory_fingerprint($plan_entry_noop), 'retired' => false, 'item_missing' => false),
  'c3' => array('item_id' => 303, 'fingerprint' => 'stale-fingerprint-does-not-match', 'retired' => false, 'item_missing' => false),
  'c4' => array('item_id' => 304, 'fingerprint' => 'irrelevant', 'retired' => true, 'item_missing' => false),
  'c5' => array('item_id' => 305, 'fingerprint' => 'irrelevant', 'retired' => true, 'item_missing' => false),
  'c6' => array('item_id' => 306, 'fingerprint' => 'irrelevant', 'retired' => false, 'item_missing' => true),
  'c7' => array('item_id' => 307, 'fingerprint' => 'irrelevant', 'retired' => true, 'item_missing' => true),
  'c8' => array('item_id' => 308, 'fingerprint' => 'irrelevant', 'retired' => false, 'item_missing' => false),
  'c9' => array('item_id' => 309, 'fingerprint' => 'irrelevant', 'retired' => false, 'item_missing' => false),
  'c10' => array('item_id' => 310, 'fingerprint' => 'irrelevant', 'retired' => true, 'item_missing' => false),
  'gone1' => array('item_id' => 399, 'fingerprint' => 'irrelevant', 'retired' => false, 'item_missing' => false),
);

$plan_result_gated = tourist_directory_plan($plan_valid, $plan_existing, array('allow_reactivate' => false));
$plan_actions_gated = array();
foreach ($plan_result_gated['actions'] as $row) {
  $plan_actions_gated[$row['id']] = $row;
}

expect_true($plan_actions_gated['c1']['action'] === 'create' && $plan_actions_gated['c1']['item_id'] === null, 'a candidato row with no existing marker plans a create');
expect_true($plan_actions_gated['c2']['action'] === 'noop', 'a candidato row whose fingerprint matches the existing marker plans no change');
expect_true($plan_actions_gated['c3']['action'] === 'update' && $plan_actions_gated['c3']['item_id'] === 303, 'a candidato row whose fingerprint changed plans an update against the existing item id');
expect_true(
  $plan_actions_gated['c4']['action'] === 'skip' && $plan_actions_gated['c4']['reason'] === 'reactivate_not_allowed',
  'a retired candidato row without --allow-reactivate is skipped, never silently reactivated'
);
expect_true(
  $plan_actions_gated['c6']['action'] === 'retire' && $plan_actions_gated['c6']['reason'] === 'missing_item',
  'a marker whose underlying item is gone is planned for retirement with reason missing_item, not recreated'
);
expect_true(
  $plan_actions_gated['c7']['action'] === 'noop',
  'a marker whose item is gone but is already retired needs no further action'
);
expect_true(
  $plan_actions_gated['c8']['action'] === 'retire' && $plan_actions_gated['c8']['reason'] === 'baja',
  'a baja row for an active existing entry plans a retire with reason baja'
);
expect_true(
  $plan_actions_gated['c9']['action'] === 'retire' && $plan_actions_gated['c9']['reason'] === 'anuncio_propio',
  'an anuncio_propio row for an active existing entry plans a retire with reason anuncio_propio'
);
expect_true($plan_actions_gated['c10']['action'] === 'noop', 'a baja row for an already-retired entry plans no further action');
expect_true(
  $plan_actions_gated['c11']['action'] === 'skip' && $plan_actions_gated['c11']['reason'] === 'no_entry',
  'a baja row with no existing entry has nothing to retire and is skipped'
);
expect_true(
  $plan_actions_gated['c12']['action'] === 'skip' && $plan_actions_gated['c12']['reason'] === 'not_importable',
  'a row whose estado_catalogo is neither candidato, baja, nor anuncio_propio is skipped'
);
expect_true(
  $plan_result_gated['not_in_seed'] === array('gone1'),
  'an existing marker whose seed id is absent from this pass is only reported, never retired by absence alone'
);

$plan_result_allowed = tourist_directory_plan(array($plan_entry_reactivate), array('c5' => $plan_existing['c5']), array('allow_reactivate' => true));
expect_true(
  $plan_result_allowed['actions'][0]['action'] === 'reactivate' && $plan_result_allowed['actions'][0]['item_id'] === 305,
  'a retired candidato row is planned for reactivation only when --allow-reactivate is explicitly set'
);

$plan_result_default_flags = tourist_directory_plan(array($plan_entry_create), array());
expect_true(
  $plan_result_default_flags['actions'][0]['action'] === 'create',
  'plan() defaults allow_reactivate to false when flags is empty, without erroring'
);

// --- tourist-directory: Phase 3.3/3.4 - CLI argument parsing and production mail gate (U3) ---

$cli_args_full = tourist_directory_cli_parse_args(array('--file=data/prospeccion/seed/complejos.csv', '--osclass-root=app/osclass', '--apply', '--allow-placeholder-contact', '--allow-reactivate'));
expect_true(
  $cli_args_full === array(
    'file' => 'data/prospeccion/seed/complejos.csv',
    'osclass_root' => 'app/osclass',
    'apply' => true,
    'allow_placeholder_contact' => true,
    'allow_reactivate' => true,
    'unknown' => array(),
  ),
  'the CLI parses every recognized flag, including the required --file and an overridden --osclass-root'
);

$cli_args_defaults = tourist_directory_cli_parse_args(array('--file=seed.csv'));
expect_true(
  $cli_args_defaults['osclass_root'] === 'app/osclass' && $cli_args_defaults['apply'] === false
    && $cli_args_defaults['allow_placeholder_contact'] === false && $cli_args_defaults['allow_reactivate'] === false,
  'omitted flags default to a safe dry-run: no --apply, no --osclass-root override, no gate bypass'
);

$cli_args_unknown = tourist_directory_cli_parse_args(array('--file=seed.csv', '--bogus-flag'));
expect_true(
  $cli_args_unknown['unknown'] === array('--bogus-flag'),
  'an unrecognized argument is collected for reporting instead of being silently ignored'
);

// --- tourist-directory: Phase 6.15/6.16 - _cli_should_refuse(apply, installed, channelReady,
// hasDirectoryEmail) replaces the old placeholder-contactEmail hard gate with the removal-channel
// gate; _cli_warnings() (U6) ---

foreach (array(
  array(false, true, false, true, false),   // dry run never gated by channel readiness
  array(true, true, false, true, 'channel_not_ready'),
  array(true, true, true, true, false),     // apply proceeds once channel ready
  array(true, false, true, true, 'not_installed'),
  array(false, false, true, true, 'not_installed'), // not-installed gates dry run too
  array(false, true, true, false, 'directory_contact_email_missing'),
  array(true, true, true, false, 'directory_contact_email_missing'), // precedes channel check
) as $c) {
  expect_true(tourist_directory_cli_should_refuse($c[0], $c[1], $c[2], $c[3]) === $c[4], 'cli_should_refuse(' . implode(',', array_slice($c, 0, 4)) . ') => ' . var_export($c[4], true));
}
expect_true(tourist_directory_cli_warnings(true) === array('site_contact_email_placeholder'), 'a placeholder site contactEmail warns, never refuses');
expect_true(tourist_directory_cli_warnings(false) === array(), 'a real site contactEmail produces no warnings');

// --- tourist-directory: the per-install placeholder email must exist and be undeliverable ---

expect_true(tourist_directory_needs_contact_email('') === true, 'a missing preference (Osclass returns an empty string) requires generating the placeholder email');
expect_true(tourist_directory_needs_contact_email(false) === true, 'a false preference requires generating the placeholder email');
expect_true(tourist_directory_needs_contact_email('owner@complejo.com.ar') === true, 'a deliverable address is never accepted as the directory placeholder');
expect_true(tourist_directory_needs_contact_email('directorio-abc123@directorio.invalid') === false, 'an existing .invalid placeholder is kept');

// --- tourist-directory: Phase 6.1/6.2 - _schema_steps($stored); Phase 6.3/6.4 - _channel_ready
// ($storedVersion, $tableProbeOk) (U6) ---

foreach (array(array('', array(1, 2)), array(0, array(1, 2)), array('0', array(1, 2)), array(1, array(2)), array('1', array(2)), array(2, array()), array('2', array())) as $c) {
  expect_true(tourist_directory_schema_steps($c[0]) === $c[1], 'schema_steps(' . var_export($c[0], true) . ') => ' . var_export($c[1], true));
}
foreach (array(array(2, true, true), array(1, true, false), array(2, false, false), array('', true, false), array(3, true, true)) as $c) {
  expect_true(tourist_directory_channel_ready($c[0], $c[1]) === $c[2], 'channel_ready(' . var_export($c[0], true) . ',' . var_export($c[1], true) . ') => ' . var_export($c[2], true));
}

// --- tourist-directory: Phase 6.5/6.6 - _validate_removal($in) (U6) ---

expect_true(tourist_directory_validate_removal(array('relation' => 'propietario'))['ok'] === true, 'a bare valid relation with no optional fields validates');
expect_true(tourist_directory_validate_removal(array('relation' => ''))['error'] === 'invalid_relation', 'a missing relation is rejected');
expect_true(tourist_directory_validate_removal(array('relation' => 'inquilino'))['error'] === 'invalid_relation', 'a relation outside the enum is rejected');
expect_true(tourist_directory_validate_removal(array('relation' => 'administrador', 'reply_contact' => str_repeat('a', 190)))['ok'] === true, 'reply_contact at exactly 190 chars is accepted');
expect_true(tourist_directory_validate_removal(array('relation' => 'administrador', 'reply_contact' => str_repeat('a', 191)))['error'] === 'invalid_reply_contact', 'reply_contact over 190 chars is rejected');
expect_true(tourist_directory_validate_removal(array('relation' => 'otro', 'reason' => str_repeat('a', 1000)))['ok'] === true, 'reason at exactly 1000 chars is accepted');
expect_true(tourist_directory_validate_removal(array('relation' => 'otro', 'reason' => str_repeat('a', 1001)))['error'] === 'invalid_reason', 'reason over 1000 chars is rejected');
expect_true(tourist_directory_validate_removal(array('relation' => 'otro', 'reply_contact' => "abc\x00def"))['error'] === 'invalid_reply_contact', 'an embedded NUL byte is rejected as a control character');
expect_true(tourist_directory_validate_removal(array('relation' => 'otro', 'reason' => 'Ya no operamos: ñoño 🙂'))['ok'] === true, 'a multibyte reason is length-checked with mb_strlen, not byte length');
expect_true(
  mb_strlen(tourist_directory_validate_removal(array('relation' => 'otro', 'reply_contact' => '  contacto@x.com  '))['value']['reply_contact']) === mb_strlen('contacto@x.com'),
  'reply_contact is trimmed before length validation'
);

// --- tourist-directory: Phase 6.7/6.8 - _client_ip($server) / _ip_hash($ip, $salt) (U6) ---

expect_true(tourist_directory_client_ip(array('REMOTE_ADDR' => '203.0.113.9')) === '203.0.113.9', 'REMOTE_ADDR is used as-is when it is not loopback');
expect_true(tourist_directory_client_ip(array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1')) === '203.0.113.9', 'HTTP_CF_CONNECTING_IP is ignored when REMOTE_ADDR is not loopback');
expect_true(tourist_directory_client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1')) === '198.51.100.1', 'HTTP_CF_CONNECTING_IP is honored only when REMOTE_ADDR is loopback');
expect_true(tourist_directory_client_ip(array('REMOTE_ADDR' => '::1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1')) === '198.51.100.1', 'IPv6 loopback (::1) also honors HTTP_CF_CONNECTING_IP');
expect_true(tourist_directory_client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_CLIENT_IP' => '198.51.100.1')) === '127.0.0.1', 'HTTP_CLIENT_IP is never read at all, spoofed or not');
expect_true(tourist_directory_client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1')) === '127.0.0.1', 'HTTP_X_FORWARDED_FOR is never read at all, spoofed or not');
expect_true(tourist_directory_ip_hash('203.0.113.9', 'salt-a') === tourist_directory_ip_hash('203.0.113.9', 'salt-a'), 'ip_hash is deterministic for the same ip and salt');
expect_true(tourist_directory_ip_hash('203.0.113.9', 'salt-a') !== tourist_directory_ip_hash('203.0.113.9', 'salt-b'), 'ip_hash changes when the salt changes');
expect_true(strlen(tourist_directory_ip_hash('203.0.113.9', 'salt-a')) === 64, 'ip_hash is a 64-char sha256 hex digest, matching s_ip_hash CHAR(64)');

// --- tourist-directory: Phase 6.9/6.10 - _removal_decide($in) precedence order (U6) ---

function directory_removal_in($overrides = array()) {
  return array_merge(array(
    'honeypot' => '', 'relation' => 'propietario', 'reply_contact' => '', 'reason' => '',
    'entry_exists' => true, 'blocking_request_exists' => false, 'entry_retired' => false,
    'ip_count_last_hour' => 0, 'entry_count_last_24h' => 0,
  ), $overrides);
}

foreach (array(
  array(array('honeypot' => 'http://spam.example'), 'honeypot'),
  array(array('relation' => ''), 'invalid'),
  array(array('entry_exists' => false), 'not_found'),
  array(array('blocking_request_exists' => true), 'already_requested'),
  array(array('ip_count_last_hour' => 3), 'accept'),   // 4th ok (limit 5/hour)
  array(array('ip_count_last_hour' => 4), 'throttled'), // 5th throttled
  array(array('entry_count_last_24h' => 1), 'accept'),   // 2nd ok (limit 3/24h)
  array(array('entry_count_last_24h' => 2), 'throttled'), // 3rd throttled
  array(array('entry_retired' => true), 'accept_retired'),
  array(array(), 'accept'),
) as $c) {
  expect_true(tourist_directory_removal_decide(directory_removal_in($c[0])) === $c[1], 'removal_decide precedence: expected ' . $c[1]);
}

// --- tourist-directory: Phase 6.11/6.12 - _admin_transition($status, $action, $confirm) (U6) ---

foreach (array(
  array('pending', 'mark_processed', 0, 'processed'),
  array('processed', 'mark_processed', 0, 'invalid_status'),
  array('pending', 'reactivate', 1, 'rejected'),
  array('processed', 'reactivate', 1, 'rejected'),
  array('pending', 'reactivate', 0, 'confirm_required'),
  array('rejected', 'reactivate', 1, 'invalid_status'),
  array('pending', 'bogus_action', 1, 'invalid_action'),
) as $c) {
  $r = tourist_directory_admin_transition($c[0], $c[1], $c[2]);
  expect_true(($r['ok'] ? $r['status'] : $r['error']) === $c[3], "admin_transition({$c[0]},{$c[1]},{$c[2]}) => {$c[3]}");
}

// --- tourist-directory: Phase 6.13/6.14 - plan() removal-request precedence (U6) ---

$plan_removal_existing = array(
  'r1' => array('item_id' => 401, 'fingerprint' => 'irrelevant', 'retired' => false, 'item_missing' => false),
  'r2' => array('item_id' => 402, 'fingerprint' => 'irrelevant', 'retired' => true, 'item_missing' => false),
  'r4' => array('item_id' => 404, 'fingerprint' => 'irrelevant', 'retired' => false, 'item_missing' => false),
);

$plan_removal_result = tourist_directory_plan(
  array(directory_plan_entry('r1', 'candidato'), directory_plan_entry('r2', 'candidato'), directory_plan_entry('r3', 'candidato'), directory_plan_entry('r4', 'baja')),
  $plan_removal_existing,
  array('allow_reactivate' => true, 'removal_seed_ids' => array('r1', 'r2', 'r3'))
);

$plan_removal_actions = array();
foreach ($plan_removal_result['actions'] as $row) {
  $plan_removal_actions[$row['id']] = $row;
}

// r1: active marker + blocking request; r2: retired marker + blocking request, even with
// allow_reactivate; r3: no marker at all + blocking request -- all three skip/removal_requested,
// never created/updated/reactivated. r4 (baja) is unaffected by removal_seed_ids.
foreach (array('r1', 'r2', 'r3') as $id) {
  expect_true($plan_removal_actions[$id]['action'] === 'skip' && $plan_removal_actions[$id]['reason'] === 'removal_requested', "plan(): $id with a blocking removal request is skipped, never created/updated/reactivated");
}
expect_true($plan_removal_actions['r4']['action'] === 'retire' && $plan_removal_actions['r4']['reason'] === 'baja', 'a baja row is unaffected by removal_seed_ids');
expect_true($plan_removal_result['removal_blocked'] === array('r1', 'r2', 'r3'), 'every removal-blocked seed id is collected in removal_blocked, for the CLI report');

$plan_no_removal_result = tourist_directory_plan(array($plan_entry_create), array(), array());
expect_true($plan_no_removal_result['removal_blocked'] === array(), 'removal_blocked defaults to empty when flags carries no removal_seed_ids');

// --- tourist-directory: Phase 7.1 - removal route regexp (U7) ---

function directory_removal_route_match($path) {
  return preg_match('#^' . tourist_directory_removal_route_regexp() . '#', $path, $m) === 1 ? $m : false;
}

$m1 = directory_removal_route_match('directorio/solicitar-baja/12/');
expect_true($m1 !== false && $m1[1] === '12', 'the removal route regexp matches a numeric entry id with a trailing slash and captures it');

$m2 = directory_removal_route_match('directorio/solicitar-baja/12345');
expect_true($m2 !== false && $m2[1] === '12345', 'the removal route regexp matches a numeric entry id with no trailing slash');

expect_true(directory_removal_route_match('directorio/solicitar-baja/') === false, 'the removal route regexp rejects a path with no entry id at all');
expect_true(directory_removal_route_match('directorio/solicitar-baja/abc') === false, 'the removal route regexp rejects a non-numeric entry id');
expect_true(directory_removal_route_match('directorio/solicitar-baja/-5') === false, 'the removal route regexp rejects a negative-looking id');
expect_true(directory_removal_route_match('otra-ruta/12/') === false, 'the removal route regexp rejects an unrelated path');

// --- tourist-directory: accommodation type meta is written, and the fingerprint is versioned ---

$type_entry = array('nombre' => 'Demo', 'localidad' => 'Villa Gesell', 'destino' => 'x', 'tipo' => 'Apart hotel', 'tipo_key' => 'apart_hotel', 'web' => 'https://demo.example');
$type_params = tourist_directory_item_params($type_entry, array('catId' => 5, 'contactEmail' => 'd@directorio.invalid', 'locales' => array('es_ES'), 'typeFieldId' => 7));
expect_true(isset($type_params['meta']) && $type_params['meta'] === array(7 => 'Apart hotel'), 'the accommodation type is passed to Osclass as the type meta field value');
$no_type_params = tourist_directory_item_params($type_entry, array('catId' => 5, 'contactEmail' => 'd@directorio.invalid', 'locales' => array('es_ES')));
expect_true(!isset($no_type_params['meta']), 'without a known type field id no meta is sent');
expect_true(
  tourist_directory_fingerprint($type_entry) === sha1('v2|Demo|Villa Gesell|x|Apart hotel|https://demo.example'),
  'the fingerprint carries a format version so entries imported before the type fix are updated once'
);

// --- tourist-directory: admin forms never post a field named "action" ---
// Params merges $_GET and $_POST with POST winning, so a posted "action" overrides the
// action=renderplugin that oc-admin needs to reach the plugin's renderplugin_controller hook.

expect_true(tourist_directory_admin_action_param() !== 'action', 'the admin request action uses its own parameter, not the reserved "action"');
$admin_requests_view = file_get_contents(__DIR__ . '/../plugins/tourist-directory/admin/requests.php');
expect_true(strpos($admin_requests_view, 'name="action"') === false, 'the admin requests view posts no field named "action"');
$directory_index = file_get_contents(__DIR__ . '/../plugins/tourist-directory/index.php');
expect_true(
  strpos($directory_index, "\$action = (string) Params::getParam(tourist_directory_admin_action_param());") !== false,
  'the admin POST handler reads the request action from its own parameter'
);

echo "Tourist showcase checks passed.\n";
