<?php

namespace Database\Seeders;

use App\Models\PartNumber;
use App\Models\WiReferenceImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Carga la foto de referencia OK de las piezas "Soporte de montaje" para la comparación con IA.
 * Uso: php artisan db:seed --class=ReferenciasSoporteMontajeSeeder
 * Es idempotente: si la foto ya está cargada en una instrucción, no la duplica.
 */
class ReferenciasSoporteMontajeSeeder extends Seeder
{
    private const ARCHIVO = __DIR__.'/referencias/soporte_de_montaje.webp';

    private const RUTA = 'wi-referencias/soporte-de-montaje-ok.webp';

    public function run(): void
    {
        if (! is_file(self::ARCHIVO)) {
            throw new RuntimeException('Falta database/seeders/referencias/soporte_de_montaje.webp');
        }

        $partes = PartNumber::query()
            ->where('description', 'like', '%Soporte de montaje%')
            ->with(['workInstructions' => fn ($q) => $q->whereIn('status', ['ACTIVE', 'DRAFT'])])
            ->get();

        if ($partes->isEmpty()) {
            throw new RuntimeException('No hay partes con descripción "Soporte de montaje". Carga primero las órdenes demo: php artisan db:seed --class=QualityControlDemoSeeder');
        }

        Storage::disk('local')->put(self::RUTA, file_get_contents(self::ARCHIVO));

        foreach ($partes as $parte) {
            foreach ($parte->workInstructions as $wi) {
                WiReferenceImage::firstOrCreate(
                    ['work_instruction_id' => $wi->id, 'kind' => 'OK', 'file_path' => self::RUTA],
                    ['label' => null, 'mime' => 'image/webp'],
                );
                $this->command?->info("Referencia OK cargada en {$wi->code} Rev. {$wi->revision} ({$parte->part_number}).");
            }
        }
    }
}
