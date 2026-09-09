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
        Role::insert([
            ['name' => 'admin', 'description' => 'Administrador total del sistema y catálogos'],
            ['name' => 'supervisor', 'description' => 'Gestión de órdenes y aprobación documental'],
            ['name' => 'inspector', 'description' => 'Captura de piezas y retrabajos'],
        ]);

        DefectCatalog::insert([
            ['code' => 'DEF-SCRW-STRIP', 'category' => 'MECHANICAL', 'description' => 'Tornillo barrido o sin rosca'],
            ['code' => 'DEF-SCRW-MISS', 'category' => 'MECHANICAL', 'description' => 'Tornillo faltante o longitud incorrecta'],
            ['code' => 'DEF-SCRW-TORQ', 'category' => 'MECHANICAL', 'description' => 'Torque fuera de especificación'],
            ['code' => 'DEF-CHAS-SCRT', 'category' => 'COSMETIC', 'description' => 'Rayón o golpe en carcasa exterior'],
            ['code' => 'DEF-ELEC-CONN', 'category' => 'FUNCTIONAL', 'description' => 'Fallo de conexión o circuito'],
        ]);

        User::create([
            'role_id' => 1,
            'employee_number' => 'EMP-0001',
            'full_name' => 'Administrador',
            'password_hash' => Hash::make('password'),
        ]);
    }
}
