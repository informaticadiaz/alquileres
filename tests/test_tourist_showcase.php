<?php

require __DIR__ . '/../plugins/tourist-showcase/tourist-showcase-lib.php';
require __DIR__ . '/../plugins/tourist-identity/tourist-identity-lib.php';

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

echo "Tourist showcase checks passed.\n";
