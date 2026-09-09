<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;

class ClientController extends QualityResourceController
{
    protected string $modelClass = Client::class;

    protected array $rules = ['name' => 'required|string|max:150', 'contact_email' => 'nullable|email|max:100'];
}
