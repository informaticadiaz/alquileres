<?php

// Pure contracts for the tourist-directory plugin.
//
// Every function here is loadable standalone (no Osclass calls at load
// time) and side-effect free, so it can be exercised directly by
// tests/test_tourist_showcase.php without bootstrapping Osclass. Glue that
// touches the database, Osclass hooks, or the filesystem lives in
// index.php and bin/tourist-directory-import.php (Phase 2/3), never here.

function tourist_directory_version() {
  return '0.1.0';
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

  return sha1(implode('|', $parts));
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

// Appends the removal-request marker to a contact URL, choosing '?' or '&' depending on whether
// $contactUrl already carries a query string.
function tourist_directory_removal_url($contactUrl, $id) {
  $separator = (strpos((string)$contactUrl, '?') !== false) ? '&' : '?';
  return $contactUrl . $separator . 'tourist_directory_removal=' . (int)$id;
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
