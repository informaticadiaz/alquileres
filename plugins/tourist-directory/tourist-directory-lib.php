<?php

// Pure contracts for the tourist-directory plugin.
//
// Every function here is loadable standalone (no Osclass calls at load
// time) and side-effect free, so it can be exercised directly by
// tests/test_tourist_showcase.php without bootstrapping Osclass. Glue that
// touches the database, Osclass hooks, or the filesystem lives in
// index.php and bin/tourist-directory-import.php (Phase 2/3), never here.

function tourist_directory_version() {
  return '0.2.0';
}

// The seed CSV header whitelist: the control key (id), the routing state
// (estado_catalogo), and the five public fields. Every other seed column
// (contacto, notas, fuente, prospect-only state, ...) is never read.
function tourist_directory_seed_columns() {
  return array('id', 'estado_catalogo', 'nombre', 'localidad', 'destino', 'tipo', 'web');
}

// Maps a seed tipo key to its showcase accommodation-type dropdown label.
function tourist_directory_type_map() {
  return array(
    'apart_hotel' => 'Apart hotel',
    'complejo_departamentos' => 'Complejo de departamentos',
    'departamentos_con_servicios' => 'Apartamento',
    'cabanas' => 'Cabaña',
  );
}

// Parses raw CSV rows (each a flat, header-ordered array of values) into associative rows keyed
// by tourist_directory_seed_columns(), reading columns by header name rather than position, and
// silently dropping every column not on the whitelist. A whitelist column absent from $header is
// simply absent from every parsed row.
function tourist_directory_parse_rows(array $header, array $rows) {
  $whitelist = tourist_directory_seed_columns();
  $normalized_header = array_map('trim', $header);

  $indices = array();
  foreach ($whitelist as $column) {
    $index = array_search($column, $normalized_header, true);
    if ($index !== false) {
      $indices[$column] = $index;
    }
  }

  $parsed = array();
  foreach ($rows as $row) {
    $entry = array();
    foreach ($indices as $column => $index) {
      $entry[$column] = array_key_exists($index, $row) ? $row[$index] : '';
    }
    $parsed[] = $entry;
  }

  return $parsed;
}

// Validates one already-parsed seed row (see tourist_directory_parse_rows) against the
// destination tree's leaf keys, the persisted tourist_identity.category_map (leaf key => category
// id), and a title length cap. $seenIds carries the ids already processed earlier in the same
// seed pass, so a repeated id is rejected as a duplicate. Returns
// array('ok'=>true,'entry'=>[...]) on success, or array('ok'=>false,'error'=>CODE,'id'=>id) on
// rejection. Never touches the database or the filesystem.
function tourist_directory_validate_row(array $row, array $leafKeys, array $catMap, $titleMax, array $seenIds = array()) {
  $id = isset($row['id']) ? trim((string)$row['id']) : '';
  $estado = isset($row['estado_catalogo']) ? trim((string)$row['estado_catalogo']) : '';
  $nombre = isset($row['nombre']) ? trim((string)$row['nombre']) : '';
  $localidad = isset($row['localidad']) ? trim((string)$row['localidad']) : '';
  $destino = isset($row['destino']) ? trim((string)$row['destino']) : '';
  $tipo_key = isset($row['tipo']) ? trim((string)$row['tipo']) : '';
  $web = isset($row['web']) ? trim((string)$row['web']) : '';

  if ($id !== '' && in_array($id, $seenIds, true)) {
    return array('ok' => false, 'error' => 'duplicate_id', 'id' => $id);
  }

  if ($nombre === '' || mb_strlen($nombre) > (int)$titleMax) {
    return array('ok' => false, 'error' => 'invalid_title', 'id' => $id);
  }

  if ($destino === '' || !in_array($destino, $leafKeys, true) || !array_key_exists($destino, $catMap)) {
    return array('ok' => false, 'error' => 'unknown_destino', 'id' => $id);
  }

  $type_map = tourist_directory_type_map();
  if ($tipo_key === '' || !array_key_exists($tipo_key, $type_map)) {
    return array('ok' => false, 'error' => 'unknown_tipo', 'id' => $id);
  }

  if ($estado === 'candidato' && $web === '') {
    return array('ok' => false, 'error' => 'missing_web', 'id' => $id);
  }

  $safe_web = '';
  if ($web !== '') {
    $safe_web = tourist_directory_safe_url($web);
    if ($safe_web === false) {
      return array('ok' => false, 'error' => 'unsafe_url', 'id' => $id);
    }
  }

  return array(
    'ok' => true,
    'entry' => array(
      'id' => $id,
      'estado_catalogo' => $estado,
      'nombre' => $nombre,
      'localidad' => $localidad,
      'destino' => $destino,
      'tipo' => $type_map[$tipo_key],
      'tipo_key' => $tipo_key,
      'web' => $safe_web,
    ),
  );
}

// Fingerprints the public fields of a validated entry (see tourist_directory_validate_row) as a
// 40-character sha1 hex digest, matching the s_fingerprint CHAR(40) marker column. Re-importing an
// unchanged row reproduces the same fingerprint; any changed public field changes it, which is
// how the importer decides whether an existing entry needs an update.
function tourist_directory_fingerprint(array $entry) {
  $parts = array(
    isset($entry['nombre']) ? $entry['nombre'] : '',
    isset($entry['localidad']) ? $entry['localidad'] : '',
    isset($entry['destino']) ? $entry['destino'] : '',
    isset($entry['tipo']) ? $entry['tipo'] : '',
    isset($entry['web']) ? $entry['web'] : '',
  );

  // 'v2': entries imported before the type meta fix are planned as one-time updates.
  return sha1('v2|' . implode('|', $parts));
}

// Generates the factual, non-copied description sentence for a directory entry, in the requested
// locale. $typeKey is the raw seed tipo key (see tourist_directory_type_map); an unmapped key
// falls back to itself so the sentence still renders instead of throwing.
function tourist_directory_description($name, $typeKey, $locality, $locale) {
  $type_map = tourist_directory_type_map();
  $type_label = array_key_exists($typeKey, $type_map) ? $type_map[$typeKey] : $typeKey;

  $es = $name . ' es un ' . $type_label . ' en ' . $locality . '. Ficha informativa con datos públicos; para consultas y reservas visite el sitio oficial.';
  $en = $name . ' is a ' . $type_label . ' in ' . $locality . '. Informational public-data listing; for enquiries and bookings, please visit the official website.';

  return tourist_directory_text($es, $en, $locale);
}

// Intersects the two known description locales (es_ES, en_US, in that fixed order) with
// $installed (whatever locale codes actually exist on this site), so descriptions are only ever
// written for a locale the site has installed.
function tourist_directory_locales(array $installed) {
  return array_values(array_intersect(array('es_ES', 'en_US'), $installed));
}

// Selects $es for a Spanish-family locale (es_ES, es-AR, ...), $en otherwise.
function tourist_directory_text($es, $en, $locale) {
  return (strpos((string)$locale, 'es_') === 0 || strpos((string)$locale, 'es-') === 0) ? $es : $en;
}

// True when $email is not safe to trust for delivery: malformed, or a known placeholder domain
// (.invalid/.test/.example/.localhost suffix, bare localhost, or example.*). Used both for the
// per-entry placeholder contact address and for the production import mail gate.
function tourist_directory_is_placeholder_email($email) {
  if (!is_string($email) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return true;
  }

  $parts = explode('@', $email);
  $domain = strtolower(end($parts));

  if ($domain === 'localhost' || $domain === 'example') {
    return true;
  }

  $placeholder_suffixes = array('.invalid', '.test', '.example', '.localhost');
  foreach ($placeholder_suffixes as $suffix) {
    if (substr($domain, -strlen($suffix)) === $suffix) {
      return true;
    }
  }

  if (strpos($domain, 'example.') === 0) {
    return true;
  }

  return false;
}

// Returns $url unchanged when it is a well-formed, credential-free http(s) URL; false otherwise.
// Used to reject unsafe seed "web" values (any other scheme, missing host, embedded userinfo).
function tourist_directory_safe_url($url) {
  if (!is_string($url) || $url === '') {
    return false;
  }

  $parts = parse_url($url);
  if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
    return false;
  }

  $scheme = strtolower($parts['scheme']);
  if ($scheme !== 'http' && $scheme !== 'https') {
    return false;
  }

  if (isset($parts['user']) || isset($parts['pass'])) {
    return false;
  }

  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return false;
  }

  return $url;
}

// Builds the full admin Params map for one validated entry (see tourist_directory_validate_row),
// ready to be fed one key at a time into Params::setParam() ahead of ItemActions(true)->prepareData()
// ->add()/->edit(). $ctx carries request-independent context the lib cannot resolve itself:
// 'catId' (already-resolved destination category id), 'contactEmail' (the per-install placeholder
// address), and 'locales' (the locales to write title/description for, see
// tourist_directory_locales()). Price is the empty string, never 0 or null directly: ItemActions
// ->prepareData() only stores i_price as NULL when Params::getParam('price') === '' (ItemActions.php
// prepareData(), price line). dt_expiration is the admin-only '-1' sentinel that keeps the item
// non-expiring (prepareData() only honors -1 when $this->is_admin is true). Never touches the
// database or Osclass state.
function tourist_directory_item_params(array $entry, array $ctx) {
  $cat_id = isset($ctx['catId']) ? $ctx['catId'] : '';
  $placeholder = isset($ctx['contactEmail']) ? $ctx['contactEmail'] : '';
  $locales = isset($ctx['locales']) && is_array($ctx['locales']) ? $ctx['locales'] : array();

  $name = isset($entry['nombre']) ? $entry['nombre'] : '';
  $type_key = isset($entry['tipo_key']) ? $entry['tipo_key'] : '';
  $locality = isset($entry['localidad']) ? $entry['localidad'] : '';

  $title = array();
  $description = array();
  foreach ($locales as $locale) {
    $title[$locale] = $name;
    $description[$locale] = tourist_directory_description($name, $type_key, $locality, $locale);
  }

  $params = array(
    'catId' => $cat_id,
    'title' => $title,
    'description' => $description,
    'contactName' => 'Directorio público',
    'contactEmail' => $placeholder,
    'showEmail' => 0,
    'showPhone' => 0,
    'price' => '',
    'dt_expiration' => '-1',
    'city' => $locality,
  );

  // Osclass reads meta field values from the 'meta' param keyed by field id
  // (ItemActions.php:169); the showcase type filter needs this value.
  $type_field_id = isset($ctx['typeFieldId']) ? (int) $ctx['typeFieldId'] : 0;
  if ($type_field_id > 0 && isset($entry['tipo']) && $entry['tipo'] !== '') {
    $params['meta'] = array($type_field_id => $entry['tipo']);
  }

  return $params;
}

// Plans the transitions for one validated seed pass against the currently known directory markers.
// $valid is the list of already-validated entries (see tourist_directory_validate_row's 'entry'
// output), each still carrying its seed 'id' and 'estado_catalogo'. $existing is keyed by seed id,
// one row per already-imported marker: 'item_id' (int), 'fingerprint' (string), 'retired' (bool),
// and 'item_missing' (bool -- true when the underlying t_item row for this marker no longer exists;
// resolved by the caller before calling this function, since checking the database is not a pure
// operation). $flags carries 'allow_reactivate' (bool, default false) and 'removal_seed_ids'
// (array of seed ids that have a blocking removal request -- pending or processed -- default
// empty).
//
// Returns array('actions'=>[...], 'not_in_seed'=>[ids], 'removal_blocked'=>[ids]): 'actions' has
// one row per valid entry -- array('id','action','entry','item_id','reason') -- with action one of
// 'create', 'update', 'noop', 'retire', 'reactivate', or 'skip' (reason one of
// 'reactivate_not_allowed', 'missing_item', 'no_entry', 'not_importable', 'removal_requested').
// 'not_in_seed' lists existing marker ids absent from this seed pass entirely, reported only --
// absence from the file alone never retires an entry; only an explicit baja/anuncio_propio row
// does. A 'candidato' row whose seed id carries a blocking removal request is skipped before any
// other check (with or without a marker, retired or not, even with allow_reactivate set) and its id
// is also collected in 'removal_blocked'. A 'baja'/'anuncio_propio' row is unaffected.
function tourist_directory_plan(array $valid, array $existing, array $flags = array()) {
  $allow_reactivate = !empty($flags['allow_reactivate']);
  $removal_ids = isset($flags['removal_seed_ids']) && is_array($flags['removal_seed_ids'])
    ? array_flip($flags['removal_seed_ids'])
    : array();
  $actions = array();
  $seen_ids = array();
  $removal_blocked = array();

  foreach ($valid as $entry) {
    $id = isset($entry['id']) ? $entry['id'] : '';
    $estado = isset($entry['estado_catalogo']) ? $entry['estado_catalogo'] : '';
    $seen_ids[$id] = true;
    $current = isset($existing[$id]) ? $existing[$id] : null;

    if ($estado === 'candidato' && isset($removal_ids[$id])) {
      $actions[] = array(
        'id' => $id,
        'action' => 'skip',
        'entry' => $entry,
        'item_id' => $current !== null ? $current['item_id'] : null,
        'reason' => 'removal_requested',
      );
      $removal_blocked[] = $id;
      continue;
    }

    if ($current !== null && !empty($current['item_missing'])) {
      $actions[] = tourist_directory_plan_missing_item($id, $entry, $current);
      continue;
    }

    if ($estado === 'candidato') {
      $actions[] = tourist_directory_plan_candidato($id, $entry, $current, $allow_reactivate);
    } elseif ($estado === 'baja' || $estado === 'anuncio_propio') {
      $actions[] = tourist_directory_plan_retirement($id, $entry, $current, $estado);
    } else {
      $actions[] = array(
        'id' => $id,
        'action' => 'skip',
        'entry' => $entry,
        'item_id' => $current !== null ? $current['item_id'] : null,
        'reason' => 'not_importable',
      );
    }
  }

  $not_in_seed = array();
  foreach ($existing as $id => $row) {
    if (!isset($seen_ids[$id])) {
      $not_in_seed[] = $id;
    }
  }

  return array('actions' => $actions, 'not_in_seed' => $not_in_seed, 'removal_blocked' => $removal_blocked);
}

// A marker whose underlying item is gone is planned for retirement (reason missing_item) exactly
// once -- it is never recreated even if the row is still a live candidato -- and needs no further
// action once it is already retired.
function tourist_directory_plan_missing_item($id, array $entry, array $current) {
  if (!empty($current['retired'])) {
    return array('id' => $id, 'action' => 'noop', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => null);
  }

  return array('id' => $id, 'action' => 'retire', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => 'missing_item');
}

function tourist_directory_plan_candidato($id, array $entry, $current, $allow_reactivate) {
  if ($current === null) {
    return array('id' => $id, 'action' => 'create', 'entry' => $entry, 'item_id' => null, 'reason' => null);
  }

  if (!empty($current['retired'])) {
    if ($allow_reactivate) {
      return array('id' => $id, 'action' => 'reactivate', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => null);
    }

    return array('id' => $id, 'action' => 'skip', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => 'reactivate_not_allowed');
  }

  $fingerprint = tourist_directory_fingerprint($entry);
  if ($fingerprint !== $current['fingerprint']) {
    return array('id' => $id, 'action' => 'update', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => null);
  }

  return array('id' => $id, 'action' => 'noop', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => null);
}

// A row's estado_catalogo of baja/anuncio_propio retires the corresponding entry only when one
// exists and is not already retired; a row with nothing to retire, or already retired, changes
// nothing -- absence of a live entry is not itself an error.
function tourist_directory_plan_retirement($id, array $entry, $current, $estado) {
  if ($current === null) {
    return array('id' => $id, 'action' => 'skip', 'entry' => $entry, 'item_id' => null, 'reason' => 'no_entry');
  }

  if (!empty($current['retired'])) {
    return array('id' => $id, 'action' => 'noop', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => null);
  }

  return array('id' => $id, 'action' => 'retire', 'entry' => $entry, 'item_id' => $current['item_id'], 'reason' => $estado);
}

// ------------------------------------------------------------------------------------------------
// CLI decisions (bin/tourist-directory-import.php). Pure: no argv/env/Osclass reads happen here --
// the CLI script resolves the raw values and passes them in.
// ------------------------------------------------------------------------------------------------

// Parses raw CLI argument strings (argv, excluding the script name) into the importer's flag set.
// Recognizes --file=<path>, --osclass-root=<path> (default 'app/osclass'), --apply,
// --allow-placeholder-contact, --allow-reactivate. Every unrecognized argument is collected under
// 'unknown' so the caller can report it instead of silently ignoring a typo'd flag.
function tourist_directory_cli_parse_args(array $args) {
  $flags = array(
    'file' => null,
    'osclass_root' => 'app/osclass',
    'apply' => false,
    'allow_placeholder_contact' => false,
    'allow_reactivate' => false,
    'unknown' => array(),
  );

  foreach ($args as $arg) {
    if (strpos($arg, '--file=') === 0) {
      $flags['file'] = substr($arg, strlen('--file='));
    } elseif (strpos($arg, '--osclass-root=') === 0) {
      $flags['osclass_root'] = substr($arg, strlen('--osclass-root='));
    } elseif ($arg === '--apply') {
      $flags['apply'] = true;
    } elseif ($arg === '--allow-placeholder-contact') {
      $flags['allow_placeholder_contact'] = true;
    } elseif ($arg === '--allow-reactivate') {
      $flags['allow_reactivate'] = true;
    } else {
      $flags['unknown'][] = $arg;
    }
  }

  return $flags;
}

// Decides whether the importer must refuse to run, before touching anything. Refuses
// unconditionally when the plugin is not installed, or when the per-install directory placeholder
// contact email is missing (needed for every write's contactEmail Params value). A dry run
// ($apply === false) is otherwise always allowed, regardless of $channelReady -- it never writes,
// so the removal-channel gate does not apply to it. An --apply run is refused unless the removal
// channel is ready (schema_version >= 2 and the request table probes OK -- see
// tourist_directory_channel_ready()). The old osclass.contactEmail placeholder hard gate is REPLACED
// by this channel-availability gate; a placeholder site contact email is reported only as a warning
// (see tourist_directory_cli_warnings()), never blocks. Returns false (proceed) or a string reason
// code ('not_installed', 'directory_contact_email_missing', 'channel_not_ready').
function tourist_directory_cli_should_refuse($apply, $installed, $channelReady, $hasDirectoryEmail) {
  if (!$installed) {
    return 'not_installed';
  }

  if (!$hasDirectoryEmail) {
    return 'directory_contact_email_missing';
  }

  if ($apply && !$channelReady) {
    return 'channel_not_ready';
  }

  return false;
}

// Non-blocking warnings printed alongside the plan report. Currently reports only a placeholder
// site osclass.contactEmail -- the old hard gate on that value is gone (see
// tourist_directory_cli_should_refuse()), so this is the only remaining signal for it.
function tourist_directory_cli_warnings($siteContactEmailIsPlaceholder) {
  $warnings = array();

  if ($siteContactEmailIsPlaceholder) {
    $warnings[] = 'site_contact_email_placeholder';
  }

  return $warnings;
}

// Fail-closed contact-guard decision. $isEntry is the tri-state marker lookup result: true (a
// confirmed directory entry), false (a normal item), or null (the lookup errored/threw). Blocks
// on true or null (never trust a failed lookup), and also blocks a false result whose stored
// contact email still equals $placeholder (an orphaned entry whose marker row is gone).
function tourist_directory_should_block($isEntry, $contactEmail, $placeholder) {
  // Fail closed: only an explicit false (lookup succeeded, item is not an entry) lets
  // the request through; null, true, "1", 1 or any other value blocks it.
  if ($isEntry !== false) {
    return true;
  }

  return (string)$contactEmail === (string)$placeholder;
}

// True when the stored per-install directory contact email is missing (Osclass returns '' for an
// absent preference) or is not an undeliverable .invalid address, so it must be (re)generated.
function tourist_directory_needs_contact_email($value) {
  if (!is_string($value) || $value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
    return true;
  }

  return substr(strtolower($value), -strlen('.invalid')) !== '.invalid';
}

// ------------------------------------------------------------------------------------------------
// Amendment: removal-request pure decisions (schema migration, channel availability, validation,
// throttle, client IP, admin transitions). Every DB-touching step lives in index.php; these
// functions only decide.
// ------------------------------------------------------------------------------------------------

// The target schema version. Bumping this and adding a step here is the only change needed to add
// a future migration step.
function tourist_directory_schema_target_version() {
  return 2;
}

// Decides which migration steps are still needed for a stored schema_version preference value.
// Osclass returns '' for a missing preference (Preference.php:155-159), which this function treats
// as version 0, same as an explicit 0/false/null. Step 1 is the marker table (t_directory_entry,
// already created by every install since U2); step 2 is the removal-request table
// (t_directory_removal_request, new in this Amendment). Returns the ordered list of step numbers
// still needed: version 0 -> [1, 2], version 1 -> [2], version 2 (or higher) -> [].
function tourist_directory_schema_steps($stored) {
  $version = ($stored === '' || $stored === false || $stored === null) ? 0 : (int) $stored;
  $steps = array();

  if ($version < 1) {
    $steps[] = 1;
  }
  if ($version < 2) {
    $steps[] = 2;
  }

  return $steps;
}

// The removal request/public-form channel is ready only once the schema migration reached its
// target version AND a live probe of the request table succeeded ($tableProbeOk is resolved by the
// caller -- checking the database is not a pure operation). A stored version below target, or a
// failed/false probe, means "temporarily unavailable": the public form must show that message and
// --apply must refuse (see tourist_directory_cli_should_refuse()).
function tourist_directory_channel_ready($storedVersion, $tableProbeOk) {
  $version = ($storedVersion === '' || $storedVersion === false || $storedVersion === null) ? 0 : (int) $storedVersion;
  return $version >= tourist_directory_schema_target_version() && $tableProbeOk === true;
}

// True when $value contains a raw control character (other than tab/LF/CR, which a free-text reason
// field may legitimately carry). Used to reject e.g. embedded NUL bytes or escape sequences in
// removal-form input. Byte-range check: ASCII control bytes are always single-byte in UTF-8 (every
// multibyte lead/continuation byte is >= 0x80), so no multibyte-aware regex flag is needed here.
function tourist_directory_has_control_chars($value) {
  return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', (string) $value) === 1;
}

// Validates one removal-form submission. 'relation' is required and must be one of propietario,
// administrador, otro. 'reply_contact' (<=190 chars) and 'reason' (<=1000 chars) are optional, but
// when present must contain no raw control characters and stay within their length cap, measured
// multibyte-safely (mb_strlen). Returns array('ok'=>true,'value'=>['relation','reply_contact',
// 'reason']) (all three trimmed) on success, or array('ok'=>false,'error'=>CODE) on rejection.
// Never touches the database.
function tourist_directory_validate_removal(array $in) {
  $relation = isset($in['relation']) ? trim((string) $in['relation']) : '';
  $reply_contact = isset($in['reply_contact']) ? trim((string) $in['reply_contact']) : '';
  $reason = isset($in['reason']) ? trim((string) $in['reason']) : '';

  $valid_relations = array('propietario', 'administrador', 'otro');
  if ($relation === '' || !in_array($relation, $valid_relations, true)) {
    return array('ok' => false, 'error' => 'invalid_relation');
  }

  if (tourist_directory_has_control_chars($reply_contact) || mb_strlen($reply_contact) > 190) {
    return array('ok' => false, 'error' => 'invalid_reply_contact');
  }

  if (tourist_directory_has_control_chars($reason) || mb_strlen($reason) > 1000) {
    return array('ok' => false, 'error' => 'invalid_reason');
  }

  return array('ok' => true, 'error' => null, 'value' => array(
    'relation' => $relation,
    'reply_contact' => $reply_contact,
    'reason' => $reason,
  ));
}

// Resolves the requester's IP from a $_SERVER-shaped array without ever trusting a spoofable header
// by default. HTTP_CLIENT_IP and HTTP_X_FORWARDED_FOR are never read (osc_get_ip() trusts them,
// utils.php:2482-2500 -- deliberately not reused here). HTTP_CF_CONNECTING_IP is honored only when
// REMOTE_ADDR itself is loopback (127.0.0.1 or ::1) -- i.e. only when the request really did arrive
// through the local Cloudflare tunnel -- otherwise REMOTE_ADDR is returned as-is.
function tourist_directory_client_ip(array $server) {
  $remote = isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : '';
  $is_loopback = in_array($remote, array('127.0.0.1', '::1'), true);

  if ($is_loopback && !empty($server['HTTP_CF_CONNECTING_IP'])) {
    return (string) $server['HTTP_CF_CONNECTING_IP'];
  }

  return $remote;
}

// Salted, deterministic IP hash for throttle lookups and retention. Never store a raw IP address.
function tourist_directory_ip_hash($ip, $salt) {
  return hash_hmac('sha256', (string) $ip, (string) $salt);
}

// The full removal-request decision, evaluated in this exact precedence order (see design.md's
// Amendment "Decision" row): a filled honeypot -> 'honeypot' (fake success, no write); a validation
// error -> 'invalid'; no entry marker -> 'not_found' (generic message, never reveals whether the id
// ever existed); an already-recorded blocking request (pending/processed) -> 'already_requested'
// (same confirmation as success, no insert); the per-IP or per-entry throttle exceeded ->
// 'throttled'; an already-retired entry -> 'accept_retired' (insert only, no re-retire); otherwise
// -> 'accept' (insert, then retire). $in carries the raw form fields ('honeypot', 'relation',
// 'reply_contact', 'reason') plus context the caller resolved: 'entry_exists' (bool),
// 'blocking_request_exists' (bool), 'entry_retired' (bool), 'ip_count_last_hour' and
// 'entry_count_last_24h' (int, PRIOR requests only, not counting this attempt). The per-IP limit is
// 5 requests/hour (4 allowed, the 5th throttled: prior count >= 4); the per-entry limit is
// 3 requests/24h (2 allowed, the 3rd throttled: prior count >= 2).
function tourist_directory_removal_decide(array $in) {
  $honeypot = isset($in['honeypot']) ? trim((string) $in['honeypot']) : '';
  if ($honeypot !== '') {
    return 'honeypot';
  }

  $validation = tourist_directory_validate_removal($in);
  if (!$validation['ok']) {
    return 'invalid';
  }

  if (empty($in['entry_exists'])) {
    return 'not_found';
  }

  if (!empty($in['blocking_request_exists'])) {
    return 'already_requested';
  }

  $ip_count = isset($in['ip_count_last_hour']) ? (int) $in['ip_count_last_hour'] : 0;
  $entry_count = isset($in['entry_count_last_24h']) ? (int) $in['entry_count_last_24h'] : 0;
  if ($ip_count >= 4 || $entry_count >= 2) {
    return 'throttled';
  }

  if (!empty($in['entry_retired'])) {
    return 'accept_retired';
  }

  return 'accept';
}

// Pure admin transition for one removal request. 'mark_processed' moves 'pending' -> 'processed'.
// 'reactivate' moves 'pending' or 'processed' -> 'rejected', but only when $confirm === 1 (the
// explicit confirmation the admin screen requires for this destructive-looking action). Any other
// status/action combination -- including an unrecognized $action -- is rejected. Returns
// array('ok'=>true,'status'=>NEW_STATUS) or array('ok'=>false,'error'=>CODE,'status'=>null).
// ------------------------------------------------------------------------------------------------
// Amendment (U7): public removal route/admin route wiring. The regexp is exposed here, not inlined
// in index.php's osc_add_route() call, so it is directly testable without an Osclass runtime. No
// $ anchor: Rewrite::init() matches it with preg_match('#^' . regexp . '#', $request_uri) (a prefix
// match), so a trailing slash or an unrelated query tail after the digits never breaks the match --
// see design.md's Amendment "Public page" architecture decision. The single capture group is the
// numeric entry id, matching the url template's one {entry} placeholder.
// ------------------------------------------------------------------------------------------------
function tourist_directory_removal_route_regexp() {
  return 'directorio/solicitar-baja/([0-9]+)';
}

function tourist_directory_admin_transition($status, $action, $confirm) {
  if ($action === 'mark_processed') {
    if ($status !== 'pending') {
      return array('ok' => false, 'error' => 'invalid_status', 'status' => null);
    }

    return array('ok' => true, 'error' => null, 'status' => 'processed');
  }

  if ($action === 'reactivate') {
    if ($status !== 'pending' && $status !== 'processed') {
      return array('ok' => false, 'error' => 'invalid_status', 'status' => null);
    }

    if ((int) $confirm !== 1) {
      return array('ok' => false, 'error' => 'confirm_required', 'status' => null);
    }

    return array('ok' => true, 'error' => null, 'status' => 'rejected');
  }

  return array('ok' => false, 'error' => 'invalid_action', 'status' => null);
}
