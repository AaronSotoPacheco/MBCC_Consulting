<?php

namespace App\Http\Controllers\Api;

use App\Models\User;

class UserController extends QualityResourceController
{
    protected string $modelClass = User::class;

    protected array $rules = [
        'role_id' => 'required|integer|exists:roles,id',
        'employee_number' => 'required|string|max:50',
        'full_name' => 'required|string|max:150',
        'password_hash' => 'required|string|min:8',
        'is_active' => 'boolean',
    ];
}
