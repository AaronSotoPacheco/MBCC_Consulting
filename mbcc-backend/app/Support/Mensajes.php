<?php

namespace App\Support;

/**
 * Mensajes de validación en español (Laravel no trae el idioma es por defecto).
 * Uso: $request->validate($reglas, array_merge(Mensajes::ES, [...propios]), $atributos)
 */
final class Mensajes
{
    public const ES = [
        'required' => 'El campo :attribute es obligatorio.',
        'string' => 'El campo :attribute debe ser texto.',
        'integer' => 'El campo :attribute debe ser un número entero.',
        'boolean' => 'El campo :attribute debe ser verdadero o falso.',
        'email' => 'El campo :attribute debe ser un correo válido.',
        'regex' => 'El formato de :attribute no es válido.',
        'date_format' => 'El campo :attribute no tiene un formato válido.',
        'after_or_equal' => 'El campo :attribute debe ser igual o posterior a la fecha inicial.',
        'unique' => 'Ese valor de :attribute ya está registrado.',
        'exists' => 'El valor de :attribute no es válido.',
        'in' => 'El valor de :attribute no es válido.',
        'file' => 'El campo :attribute debe ser un archivo.',
        'mimes' => 'El archivo :attribute debe ser un PDF.',
        'max.string' => 'El campo :attribute no puede superar :max caracteres.',
        'max.numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'max.file' => 'El archivo :attribute no puede pesar más de :max KB.',
        'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'min.numeric' => 'El campo :attribute debe ser al menos :min.',
    ];

    public static function con(array $propios = []): array
    {
        return array_merge(self::ES, $propios);
    }
}
