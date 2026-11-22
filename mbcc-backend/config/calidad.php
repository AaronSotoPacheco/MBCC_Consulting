<?php

return [
    // Alerta de rechazos: se avisa al supervisor cuando el % de NG de las últimas piezas rebasa el umbral.
    'umbral_ng' => (float) env('NG_UMBRAL', 5),          // porcentaje
    'ventana_ng' => (int) env('NG_VENTANA', 20),          // últimas N piezas de la orden
    'muestra_minima_ng' => (int) env('NG_MUESTRA_MINIMA', 10), // piezas mínimas para evaluar
    // Asistencia. Jornada(s) de la planta como "HH:MM-HH:MM" separadas por coma (por defecto 08:00-16:00).
    // Con varios turnos: ASISTENCIA_TURNOS="06:00-14:00,14:00-22:00,22:00-06:00". Un turno puede cruzar la medianoche.
    'asistencia' => [
        'turnos' => (function () {
            $t = array_values(array_filter(array_map(function ($x) {
                [$ini, $fin] = array_pad(array_map('trim', explode('-', $x, 2)), 2, null);
                if (! preg_match('/^\d{1,2}:\d{2}$/', (string) $ini)) {
                    return null;
                }
                if (! preg_match('/^\d{1,2}:\d{2}$/', (string) $fin)) {
                    $fin = date('H:i', strtotime($ini) + 8 * 3600);
                }

                return ['inicio' => $ini, 'fin' => $fin];
            }, explode(',', (string) env('ASISTENCIA_TURNOS', '08:00-16:00')))));

            return $t ?: [['inicio' => '08:00', 'fin' => '16:00']];
        })(),
        'tolerancia_min' => (int) env('ASISTENCIA_TOLERANCIA', 10),   // minutos de gracia después del inicio antes de contar retardo
        'anticipo_min' => (int) env('ASISTENCIA_ANTICIPO', 60),       // cuánto antes del inicio abre el registro de entrada
        'dias' => array_map('intval', explode(',', (string) env('ASISTENCIA_DIAS', '1,2,3,4,5'))), // días laborables (1 = lunes … 7 = domingo)
        'horas_max_turno' => (int) env('ASISTENCIA_HORAS_MAX', 18),   // una entrada sin salida pasada esta antigüedad se considera olvidada
    ],

    // Una captura hecha sin conexión se acepta si se hizo dentro de estas horas
    'horas_max_captura_offline' => (int) env('OFFLINE_MAX_HORAS', 72),
];
