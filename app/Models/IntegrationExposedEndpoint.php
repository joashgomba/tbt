<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationExposedEndpoint extends Model
{
    protected $fillable = [
        'endpoint_code', 'name', 'name_en', 'name_fr', 'http_method', 'path',
        'api_version', 'resource_type', 'description_en', 'description_fr',
        'auth_required', 'scopes_required', 'is_published', 'documentation_url', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scopes_required' => 'array',
            'auth_required' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
