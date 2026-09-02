<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MetaTemplate extends Model
{
    protected $fillable = [
        'type',
        'name',
        'subject',
        'body',
        'media_url',
        'variables',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    public function scopeEmail(Builder $query): Builder
    {
        return $query->where('type', 'email');
    }

    public function scopeWhatsapp(Builder $query): Builder
    {
        return $query->where('type', 'whatsapp');
    }

    /**
     * Automatically extract variable tokens {{variable_name}} from subject and body.
     */
    public static function extractVariables(string $text): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_?]+)\s*\}\}/', $text, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }
}
