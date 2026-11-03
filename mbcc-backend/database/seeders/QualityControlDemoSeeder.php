<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\PartNumber;
use App\Models\SerialRecord;
use App\Models\SortOrder;
use App\Models\User;
use App\Models\WorkInstruction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QualityControlDemoSeeder extends Seeder
{
    private const ORDERS = [
        ['so' => 'SO-45872', 'client' => 'TechSolutions Inc.', 'part' => 'MB-7301-AX', 'description' => 'Carcasa superior - Ensamble', 'wi' => 'WI-MB-7301-AX', 'revision' => '3', 'title' => 'Ensamble y revisión de carcasa superior', 'target' => 1200, 'status' => 'OPEN', 'count' => 248, 'reject_every' => 15],
        ['so' => 'SO-45874', 'client' => 'Global Motors', 'part' => 'MB-9710-CT', 'description' => 'Módulo de control - Final', 'wi' => 'WI-MB-9710-CT', 'revision' => '1', 'title' => 'Inspección final de módulo de control', 'target' => 850, 'status' => 'OPEN', 'count' => 310, 'reject_every' => 20],
        ['so' => 'SO-45873', 'client' => 'NorthTech', 'part' => 'MB-8203-GR', 'description' => 'Sensor de posición', 'wi' => 'WI-MB-8203-GR', 'revision' => '2', 'title' => 'Inspección de sensor de posición', 'target' => 600, 'status' => 'PAUSED', 'count' => 120, 'reject_every' => 12],
        ['so' => 'SO-45875', 'client' => 'AutoParts Co.', 'part' => 'MB-6650-TR', 'description' => 'Soporte de montaje', 'wi' => 'WI-MB-6650-TR', 'revision' => '1', 'title' => 'Revisión de soporte de montaje', 'target' => 1500, 'status' => 'OPEN', 'count' => 0, 'reject_every' => 0],
        ['so' => 'SO-45876', 'client' => 'Vision Tech', 'part' => 'MB-9021-CV', 'description' => 'Conjunto de engranes', 'wi' => 'WI-MB-9021-CV', 'revision' => '4', 'title' => 'Revisión de conjunto de engranes', 'target' => 950, 'status' => 'OPEN', 'count' => 85, 'reject_every' => 25],
        ['so' => 'SO-45877', 'client' => 'ElectroLabs', 'part' => 'MB-3342-PL', 'description' => 'Placa electrónica', 'wi' => 'WI-MB-3342-PL', 'revision' => '2', 'title' => 'Inspección visual de placa electrónica', 'target' => 720, 'status' => 'PAUSED', 'count' => 0, 'reject_every' => 0],
    ];

    public function run(): void
    {
        $inspectorId = User::query()->where('is_active', true)->orderBy('id')->value('id');
        if (! $inspectorId) {
            throw new RuntimeException('Crea al menos un usuario activo antes de cargar las órdenes demo.');
        }

        DB::transaction(function () use ($inspectorId) {
            foreach (self::ORDERS as $data) {
                $client = Client::firstOrCreate(['name' => $data['client']]);
                $part = PartNumber::firstOrCreate(
                    ['client_id' => $client->id, 'part_number' => $data['part']],
                    ['description' => $data['description']],
                );
                $instruction = WorkInstruction::firstOrCreate(
                    ['code' => $data['wi'], 'revision' => $data['revision']],
                    [
                        'part_number_id' => $part->id,
                        'title' => $data['title'],
                        'file_path' => 'wi/'.$data['wi'].'_R'.$data['revision'].'.pdf',
                        'status' => 'ACTIVE',
                    ],
                );
                $order = SortOrder::firstOrCreate(
                    ['order_number' => $data['so']],
                    [
                        'part_number_id' => $part->id,
                        'work_instruction_id' => $instruction->id,
                        'target_quantity' => $data['target'],
                        'status' => $data['status'],
                        'shift_hours' => '8:00 AM - 4:00 PM',
                    ],
                );

                for ($number = 1; $number <= $data['count']; $number++) {
                    $rejected = $data['reject_every'] > 0 && $number % $data['reject_every'] === 0;
                    $order->serialRecords()->firstOrCreate(
                        ['serial_number' => sprintf('SN-%s-%03d', substr($data['so'], 3), $number)],
                        [
                            'status' => $rejected ? 'FAILED' : 'PASSED',
                            'inspected_by' => $inspectorId,
                            'station_identifier' => 'Station-01',
                        ],
                    );
                }
            }
        });
    }
}