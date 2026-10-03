#!/usr/bin/env bash
# Verified backup of the Osclass instance: MySQL dump plus config.php and
# oc-content/uploads, compressed with zstd, checksummed and rotated.
# Runbook: osclass-backups.md
set -euo pipefail
umask 077

ROOT="${OSCLASS_ROOT:-$HOME/fewohbee}"
APP="$ROOT/app/osclass"
CNF="${OSCLASS_BACKUP_CNF:-$ROOT/data/osclass/backup.my.cnf}"
DEST="${OSCLASS_BACKUP_DIR:-$ROOT/data/osclass/backups/auto}"
DB="${OSCLASS_DB:-osclass_local}"
KEEP="${OSCLASS_BACKUP_KEEP:-14}"

log() { printf 'osclass-backup: %s\n' "$*"; }
die() { printf 'osclass-backup: ERROR: %s\n' "$*" >&2; exit 1; }

[[ -r "$CNF" ]] || die "missing MySQL option file $CNF"
[[ -r "$APP/config.php" ]] || die "missing $APP/config.php"
[[ "$KEEP" =~ ^[1-9][0-9]*$ ]] || die "OSCLASS_BACKUP_KEEP must be a positive integer"

mkdir -p "$DEST"
stamp="$(date +%Y%m%dT%H%M%S)"
base="osclass-$stamp"
tmp="$(mktemp -d "$DEST/.tmp-XXXXXX")"
trap 'rm -rf "$tmp"' EXIT

# Database: consistent InnoDB snapshot without locking the live site.
mysqldump --defaults-extra-file="$CNF" \
  --single-transaction --no-tablespaces --routines --triggers \
  --set-gtid-purged=OFF --default-character-set=utf8mb4 \
  "$DB" | zstd -q -T0 -o "$tmp/$base.sql.zst"
zstd -q -t "$tmp/$base.sql.zst"
zstd -q -dc "$tmp/$base.sql.zst" | tail -n 1 | grep -q '^-- Dump completed' \
  || die "dump is incomplete"

# Files that the database alone cannot restore.
tar -C "$APP" -cf - config.php oc-content/uploads | zstd -q -T0 -o "$tmp/$base.files.tar.zst"
zstd -q -t "$tmp/$base.files.tar.zst"

(cd "$tmp" && sha256sum "$base.sql.zst" "$base.files.tar.zst" > "$base.sha256")
mv "$tmp/$base.sql.zst" "$tmp/$base.files.tar.zst" "$tmp/$base.sha256" "$DEST/"
log "created $DEST/$base.{sql.zst,files.tar.zst,sha256}"

# Retention: keep the newest $KEEP complete sets (timestamps sort lexically).
mapfile -t old < <(find "$DEST" -maxdepth 1 -name 'osclass-*.sha256' -printf '%f\n' \
  | sort -r | tail -n +"$((KEEP + 1))")
for f in "${old[@]}"; do
  b="${f%.sha256}"
  rm -f "$DEST/$b.sql.zst" "$DEST/$b.files.tar.zst" "$DEST/$b.sha256"
  log "rotated $b"
done
