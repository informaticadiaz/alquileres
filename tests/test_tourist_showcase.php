<?php

require __DIR__ . '/../plugins/tourist-showcase/tourist-showcase-lib.php';

function expect_true($condition, $message) {
  if (!$condition) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
  }
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

echo "Tourist showcase checks passed.\n";
