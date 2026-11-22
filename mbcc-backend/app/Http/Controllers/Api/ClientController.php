<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use Illuminate\Validation\Rule;

class ClientController extends QualityResourceController
{
    protected string $modelClass = Client::class;

    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('clients', 'name')->ignore($id)],
            'contact_email' => ['nullable', 'email', 'max:100'],
        ];
    }
}
