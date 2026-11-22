#!/bin/bash
# Respalda al arrancar y después todos los días a HORA_RESPALDO.
HORA="${HORA_RESPALDO:-02:00}"
echo "Respaldos automáticos activos: diario a las $HORA, se conservan $RETENCION_DIAS días."
respaldar.sh || echo "FALLÓ el respaldo inicial" >&2
while true; do
  ahora=$(date +%s)
  objetivo=$(date -d "today $HORA" +%s)
  if [ "$objetivo" -le "$ahora" ]; then objetivo=$(date -d "tomorrow $HORA" +%s); fi
  sleep $((objetivo - ahora))
  respaldar.sh || echo "FALLÓ el respaldo de $(date)" >&2
done
