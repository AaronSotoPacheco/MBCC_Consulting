#!/bin/bash
# Uso: restaurar.sh /respaldos/bd-AAAAMMDD-HHMMSS.sql.gz [/respaldos/archivos-AAAAMMDD-HHMMSS.tar.gz]
# REEMPLAZA la base de datos (y los archivos, si das el segundo parámetro). Pide CONFIRMAR=SI.
set -euo pipefail
BD="${1:-}"; ARCH="${2:-}"
[ -f "$BD" ] || { echo "Uso: restaurar.sh <bd.sql.gz> [archivos.tar.gz]" >&2; exit 1; }
[ "${CONFIRMAR:-}" = "SI" ] || { echo "Esto reemplaza los datos actuales. Repite con:  -e CONFIRMAR=SI" >&2; exit 1; }
gzip -t "$BD"
export MYSQL_PWD="$DB_PASSWORD"
gunzip -c "$BD" | mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE"
echo "Base de datos restaurada desde $BD"
if [ -n "$ARCH" ]; then
  [ -f "$ARCH" ] || { echo "No existe $ARCH" >&2; exit 1; }
  gzip -t "$ARCH"
  tar -xzf "$ARCH" -C /datos/storage-app
  echo "Archivos restaurados desde $ARCH"
fi
