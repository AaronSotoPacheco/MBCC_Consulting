<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;

class RoleController extends QualityResourceController
{
    protected string $modelClass = Role::class;

    protected array $rules = ['name' => 'required|string|max:50', 'description' => 'nullable|string|max:255'];
}
