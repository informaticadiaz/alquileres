#!/usr/bin/env php
<?php

// Tourist Directory Importer CLI.
//
// Reads a seed CSV (see esquema-seed-complejos.md and the "Seed Contract" section of
// openspec/changes/tourist-directory-entries/design.md) and creates/updates/retires directory
// entries for commercial tourist complexes. Lives outside the web root on purpose: it is never
// synced to app/osclass/oc-content/plugins/, and it is a standalone operator tool, not a plugin.
//
// Usage:
//   php bin/tourist-directory-import.php --file=<path> [--osclass-root=<path>] [--apply]
//     [--allow-placeholder-contact] [--allow-reactivate]
//
// Flags:
//   --file=<path>                 Required. Path to the seed CSV.
//   --osclass-root=<path>         Default: app/osclass. Root of the target Osclass installation
//                                  (the directory that contains config.php and oc-load.php).
//   --apply                       Writes changes. Without it, the importer only reports the plan
//                                  and never touches the database.
//   --allow-placeholder-contact   Overrides the production mail gate (see below). Only use this on
//                                  a non-production/test install.
//   --allow-reactivate            Lets a previously retired (baja/anuncio_propio) entry whose seed
//                                  row is back to candidato be reactivated. Without it, a retired
//                                  entry always stays retired.
//
// Seed contract: only the columns id, estado_catalogo, nombre, localidad, destino, tipo, web are
// ever read (tourist_directory_seed_columns()); every other column (contacto, notas, fuente,
// estado_prospecto, fecha_ultimo_contacto, ...) is dropped by tourist_directory_parse_rows() before
// this script ever sees it, and none of them are ever printed, logged, or written anywhere.
//
// Production mail gate: with --apply, the importer refuses to run while osclass.contactEmail is
// still a placeholder value (.invalid/.test/.example/.localhost, bare localhost, or example.*),
// unless --allow-placeholder-contact is passed. A dry run (no --apply) is never gated: it never
// writes, regardless of mail configuration.
//
// Before any --apply write, the importer also refuses if a registered Osclass user already owns
// this install's per-plugin placeholder contact address: ItemActions::prepareData() auto-links an
// admin-created item to a matching user by email (ItemActions.php:1785) and would silently replace
// the placeholder contactName/contactEmail with that user's real identity. See design.md's "Item
// contact" architecture decision.

if (PHP_SAPI !== 'cli') {
  fwrite(STDERR, "This script must be run from the command line (php-cli).\n");
  exit(1);
}

require_once __DIR__ . '/../plugins/tourist-directory/tourist-directory-lib.php';
require_once __DIR__ . '/../plugins/tourist-identity/tourist-identity-tree.php';

function tourist_directory_cli_print_usage() {
  fwrite(STDERR, "Usage: php bin/tourist-directory-import.php --file=<path> [--osclass-root=<path>] [--apply] [--allow-placeholder-contact] [--allow-reactivate]\n");
}

// Prints seed id + name only for every planned action -- never contacto, notas,
// estado_prospecto, or fecha_ultimo_contacto, none of which ever reach a validated entry in the
// first place (tourist_directory_parse_rows() already drops them at the CSV-reading step).
function tourist_directory_cli_print_report(array $plan, array $errorRows, $applying) {
  $counts = array('create' => 0, 'update' => 0, 'noop' => 0, 'retire' => 0, 'reactivate' => 0, 'skip' => 0);
  $byAction = array();

  foreach ($plan['actions'] as $action) {
    $counts[$action['action']] = (isset($counts[$action['action']]) ? $counts[$action['action']] : 0) + 1;
    $byAction[$action['action']][] = $action;
  }

  fwrite(STDOUT, $applying ? "=== Tourist Directory Importer: applying ===\n" : "=== Tourist Directory Importer: dry run (no --apply) ===\n");
  fwrite(STDOUT, sprintf(
    "Plan: %d create, %d update, %d noop, %d retire, %d reactivate, %d skip. %d validation error(s). %d marker id(s) not present in this seed pass.\n",
    $counts['create'], $counts['update'], $counts['noop'], $counts['retire'], $counts['reactivate'], $counts['skip'],
    count($errorRows), count($plan['not_in_seed'])
  ));

  foreach (array('create', 'update', 'retire', 'reactivate', 'skip') as $actionName) {
    if (empty($byAction[$actionName])) {
      continue;
    }

    fwrite(STDOUT, "\n-- " . strtoupper($actionName) . " --\n");
    foreach ($byAction[$actionName] as $action) {
      $suffix = ($action['reason'] !== null) ? " ({$action['reason']})" : '';
      fwrite(STDOUT, "  [{$action['id']}] {$action['entry']['nombre']}{$suffix}\n");
    }
  }

  if (!empty($errorRows)) {
    fwrite(STDOUT, "\n-- VALIDATION ERRORS --\n");
    foreach ($errorRows as $error) {
      fwrite(STDOUT, "  [{$error['id']}] {$error['error']}\n");
    }
  }

  if (!empty($plan['not_in_seed'])) {
    fwrite(STDOUT, "\n-- NOT IN THIS SEED PASS (reported only, never retired by absence alone) --\n");
    foreach ($plan['not_in_seed'] as $id) {
      fwrite(STDOUT, "  [{$id}]\n");
    }
  }
}

$flags = tourist_directory_cli_parse_args(array_slice($argv, 1));

if (!empty($flags['unknown'])) {
  fwrite(STDERR, 'Unknown argument(s): ' . implode(', ', $flags['unknown']) . "\n");
  tourist_directory_cli_print_usage();
  exit(1);
}

if ($flags['file'] === null || $flags['file'] === '') {
  fwrite(STDERR, "Missing required --file=<path>.\n");
  tourist_directory_cli_print_usage();
  exit(1);
}

if (!is_readable($flags['file'])) {
  fwrite(STDERR, "Cannot read seed file: {$flags['file']}\n");
  exit(1);
}

// --- Bootstrap Osclass exactly like the vendor CLI/cron entry point does (app/osclass/index.php:19-
// 25 defines ABS_PATH and CLI, then requires oc-load.php), but skip the page-dispatch switch below
// that point entirely -- this is a standalone operator tool, not an HTTP request to route. ---
$osclassRoot = rtrim($flags['osclass_root'], '/\\') . '/';

if (!is_file($osclassRoot . 'oc-load.php')) {
  fwrite(STDERR, "No Osclass installation found at --osclass-root={$flags['osclass_root']} (oc-load.php missing).\n");
  exit(1);
}

define('ABS_PATH', $osclassRoot);
define('CLI', true);

// oc-includes/osclass/default-constants.php:47-48 reads $_SERVER['HTTPS']/['SERVER_PORT']/
// ['HTTP_HOST'] unguarded, and helpers such as osc_get_ip() (utils.php) read REMOTE_ADDR. A CLI
// process has none of these; default them so bootstrap runs warning-free under PHP 8.5's stricter
// undefined-array-key notices, exactly as design.md's importer decision specifies.
$_SERVER += array(
  'HTTP_HOST' => 'localhost',
  'REQUEST_URI' => '/',
  'REMOTE_ADDR' => '127.0.0.1',
  'SERVER_PORT' => '80',
);

require_once ABS_PATH . 'oc-load.php';
// OC_ADMIN defaults to false when undefined (oc-includes/osclass/default-constants.php:22-23) --
// this importer never touches the admin backoffice, so that default is exactly what is wanted; it
// is never redefined here.

// The plugin's glue functions (tourist_directory_contact_email(), _create(), _update(), _retire(),
// _reactivate(), _dao(), _table()) only exist once Plugins::init() (inside oc-load.php, above) has
// loaded the DEPLOYED copy of plugins/tourist-directory/index.php from this Osclass installation --
// i.e. only when the plugin is actually installed and active there.
$installed = function_exists('tourist_directory_contact_email');
$contactEmail = $installed ? osc_contact_email() : '';
$refusal = tourist_directory_cli_should_refuse(
  $flags['apply'],
  $installed,
  tourist_directory_is_placeholder_email($contactEmail),
  $flags['allow_placeholder_contact'],
  $installed && tourist_directory_contact_email() !== ''
);

if ($refusal !== false) {
  $messages = array(
    'not_installed' => 'The tourist-directory plugin is not installed/active on this Osclass site. Nothing was read or written.',
    'directory_contact_email_missing' => 'The directory placeholder contact email is missing. Disable and enable Tourist Directory Entries in oc-admin to generate it. Nothing was read or written.',
    'placeholder_contact_email' => 'Refusing --apply: osclass.contactEmail is still a placeholder value. Verify mail works, set a real contactEmail, then re-run (or pass --allow-placeholder-contact on a non-production install).',
  );
  fwrite(STDERR, (isset($messages[$refusal]) ? $messages[$refusal] : $refusal) . "\n");
  exit(1);
}

// --- Read & parse the CSV. tourist_directory_parse_rows() reads columns by header name and keeps
// only the public whitelist (tourist_directory_seed_columns()); contacto/notas/fuente/
// estado_prospecto/fecha_ultimo_contacto and any other column are dropped right here, before a
// single row is validated, planned, or printed. ---
$handle = fopen($flags['file'], 'r');
if ($handle === false) {
  fwrite(STDERR, "Failed to open seed file: {$flags['file']}\n");
  exit(1);
}

$header = fgetcsv($handle);
if ($header === false) {
  fwrite(STDERR, "Seed file is empty or unreadable: {$flags['file']}\n");
  fclose($handle);
  exit(1);
}

$rawRows = array();
while (($row = fgetcsv($handle)) !== false) {
  $rawRows[] = $row;
}
fclose($handle);

$parsedRows = tourist_directory_parse_rows($header, $rawRows);

// --- Validate against the destination tree (this repo's own pure tourist_identity_tree(), not
// whatever happens to be installed live) and the persisted tourist_identity.category_map pref. ---
$leafKeys = array();
foreach (tourist_identity_tree() as $region) {
  foreach ($region['leaves'] as $leaf) {
    $leafKeys[] = $leaf[0];
  }
}

$catMap = array();
$rawCatMap = osc_get_preference('category_map', 'tourist_identity');
if ($rawCatMap !== false && $rawCatMap !== '') {
  $decodedCatMap = json_decode($rawCatMap, true);
  $catMap = is_array($decodedCatMap) ? $decodedCatMap : array();
}

$titleMax = function_exists('osc_max_characters_per_title') ? osc_max_characters_per_title() : 100;

$validEntries = array();
$errorRows = array();
$seenIds = array();

foreach ($parsedRows as $row) {
  $result = tourist_directory_validate_row($row, $leafKeys, $catMap, $titleMax, $seenIds);

  if ($result['ok']) {
    $validEntries[] = $result['entry'];
    if ($result['entry']['id'] !== '') {
      $seenIds[] = $result['entry']['id'];
    }
  } else {
    $errorRows[] = $result;
  }
}

// --- Load existing markers and detect any whose underlying item was deleted out from under the
// plugin -- tourist_directory_plan() never recreates those, it only reports/retires them
// (reason missing_item; see tourist_directory_plan_missing_item()). ---
$existing = array();
$dao = tourist_directory_dao();
$markerResult = $dao->query('SELECT s_seed_id, fk_i_item_id, s_fingerprint, dt_retired FROM ' . tourist_directory_table());

$itemIds = array();
$markerRows = array();
if ($markerResult !== false) {
  foreach ($markerResult->result() as $row) {
    $markerRows[] = $row;
    $itemIds[] = (int) $row['fk_i_item_id'];
  }
}

$existingItemIds = array();
if (!empty($itemIds)) {
  // $itemIds comes only from our own marker table's fk_i_item_id column, cast to int above: never
  // user input, safe to interpolate into an IN(...) list.
  $itemResult = $dao->query('SELECT pk_i_id FROM ' . DB_TABLE_PREFIX . 't_item WHERE pk_i_id IN (' . implode(',', array_map('intval', $itemIds)) . ')');
  if ($itemResult !== false) {
    foreach ($itemResult->result() as $row) {
      $existingItemIds[(int) $row['pk_i_id']] = true;
    }
  }
}

foreach ($markerRows as $row) {
  $itemId = (int) $row['fk_i_item_id'];
  $existing[$row['s_seed_id']] = array(
    'item_id' => $itemId,
    'fingerprint' => $row['s_fingerprint'],
    'retired' => $row['dt_retired'] !== null,
    'item_missing' => !isset($existingItemIds[$itemId]),
  );
}

$plan = tourist_directory_plan($validEntries, $existing, array('allow_reactivate' => $flags['allow_reactivate']));

tourist_directory_cli_print_report($plan, $errorRows, $flags['apply']);

if (!$flags['apply']) {
  fwrite(STDOUT, "\nDry run only -- nothing was written. Re-run with --apply to write these changes.\n");
  exit(0);
}

// Mandatory: never create or update while a registered Osclass user already owns this install's
// placeholder contact address (ItemActions::prepareData() auto-link risk, ItemActions.php:1785).
// Checked once, globally, before any write -- not per row, since it is a static precondition.
$placeholderOwner = User::newInstance()->findByEmail(tourist_directory_contact_email());
if (is_array($placeholderOwner) && isset($placeholderOwner['pk_i_id']) && $placeholderOwner['pk_i_id'] > 0) {
  fwrite(STDERR, "\nRefusing to apply: a registered user already owns this install's placeholder contact email. Investigate before re-running --apply.\n");
  exit(1);
}

$applied = array('create' => 0, 'update' => 0, 'retire' => 0, 'reactivate' => 0, 'failed' => 0);

foreach ($plan['actions'] as $action) {
  switch ($action['action']) {
    case 'create':
      $catId = isset($catMap[$action['entry']['destino']]) ? (int) $catMap[$action['entry']['destino']] : 0;
      $result = tourist_directory_create($action['entry'], $catId);
      $applied[$result['ok'] ? 'create' : 'failed']++;
      break;

    case 'update':
      $catId = isset($catMap[$action['entry']['destino']]) ? (int) $catMap[$action['entry']['destino']] : 0;
      $result = tourist_directory_update($action['item_id'], $action['entry'], $catId);
      $applied[$result['ok'] ? 'update' : 'failed']++;
      break;

    case 'retire':
      $result = tourist_directory_retire($action['item_id'], $action['reason']);
      $applied[$result['ok'] ? 'retire' : 'failed']++;
      break;

    case 'reactivate':
      $result = tourist_directory_reactivate($action['item_id'], array('allow_reactivate' => true));
      $applied[$result['ok'] ? 'reactivate' : 'failed']++;
      break;
  }
}

osc_update_cat_stats();
osc_cache_flush();

fwrite(STDOUT, sprintf(
  "\nApplied: %d created, %d updated, %d retired, %d reactivated, %d failed.\n",
  $applied['create'], $applied['update'], $applied['retire'], $applied['reactivate'], $applied['failed']
));

exit(0);
