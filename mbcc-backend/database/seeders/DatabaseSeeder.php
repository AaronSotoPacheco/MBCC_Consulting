<?php

namespace Database\Seeders;

use App\Models\DefectCatalog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            'admin' => 'Administrador total del sistema y catálogos',
            'supervisor' => 'Gestión de órdenes y aprobación documental',
            'inspector' => 'Captura de piezas y retrabajos',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach ([
            ['code' => 'DEF-SCRW-STRIP', 'category' => 'MECHANICAL', 'description' => 'Tornillo barrido o sin rosca'],
            ['code' => 'DEF-SCRW-MISS', 'category' => 'MECHANICAL', 'description' => 'Tornillo faltante o longitud incorrecta'],
            ['code' => 'DEF-SCRW-TORQ', 'category' => 'MECHANICAL', 'description' => 'Torque fuera de especificación'],
            ['code' => 'DEF-CHAS-SCRT', 'category' => 'COSMETIC', 'description' => 'Rayón o golpe en carcasa exterior'],
            ['code' => 'DEF-ELEC-CONN', 'category' => 'FUNCTIONAL', 'description' => 'Fallo de conexión o circuito'],
        ] as $defect) {
            DefectCatalog::updateOrCreate(
                ['code' => $defect['code']],
                ['category' => $defect['category'], 'description' => $defect['description'], 'is_active' => true]
            );
        }

        $demoPassword = Hash::make('demo1234');

        $adminRoleId = Role::where('name', 'admin')->value('id');
        $supervisorRoleId = Role::where('name', 'supervisor')->value('id');
        $inspectorRoleId = Role::where('name', 'inspector')->value('id');

        User::updateOrCreate(
            ['employee_number' => '9001'],
            ['role_id' => $adminRoleId, 'full_name' => 'Administrador MBCC', 'password_hash' => $demoPassword, 'is_active' => true]
        );

        User::updateOrCreate(
            ['employee_number' => '2001'],
            ['role_id' => $supervisorRoleId, 'full_name' => 'Andrea López', 'password_hash' => $demoPassword, 'is_active' => true]
        );

        User::updateOrCreate(
            ['employee_number' => '1001'],
            ['role_id' => $inspectorRoleId, 'full_name' => 'Juan Pérez García', 'password_hash' => $demoPassword, 'is_active' => true]
        );
    }
}
