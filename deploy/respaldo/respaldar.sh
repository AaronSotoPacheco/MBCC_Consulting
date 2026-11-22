#!/bin/bash
# Un respaldo completo: base de datos (SQL comprimido) + archivos (PDF de instrucciones, fotos de referencia y evidencias NG).
set -euo pipefail
DEST=/respaldos
RETENCION_DIAS="${RETENCION_DIAS:-30}"
SELLO=$(date +%Y%m%d-%H%M%S)
BD="$DEST/bd-$SELLO.sql.gz"
ARCH="$DEST/archivos-$SELLO.tar.gz"
mkdir -p "$DEST"
limpiar() { rm -f "$BD.tmp" "$ARCH.tmp"; }
trap limpiar EXIT

export MYSQL_PWD="$DB_PASSWORD"
mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" \
  --single-transaction --routines --no-tablespaces --default-character-set=utf8mb4 "$DB_DATABASE" | gzip > "$BD.tmp"
tar -czf "$ARCH.tmp" -C /datos/storage-app .
gzip -t "$BD.tmp"
gzip -t "$ARCH.tmp"
mv "$BD.tmp" "$BD"
mv "$ARCH.tmp" "$ARCH"

# Borra los respaldos más viejos que RETENCION_DIAS (siempre conserva los 3 más recientes de cada tipo)
for patron in 'bd-*.sql.gz' 'archivos-*.tar.gz'; do
  ls -1t "$DEST"/$patron 2>/dev/null | tail -n +4 | while read -r f; do
    if [ -n "$(find "$f" -mtime +"$RETENCION_DIAS" 2>/dev/null)" ]; then rm -f "$f"; fi
  done
done

echo "$SELLO OK  bd=$(du -h "$BD" | cut -f1)  archivos=$(du -h "$ARCH" | cut -f1)" | tee "$DEST/ultimo-respaldo.txt"
