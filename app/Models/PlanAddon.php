<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'price_monthly',
        'currency',
        'limits_delta',
        'modules_delta',
        'is_active'];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'is_active' => 'boolean',
            'limits_delta' => 'array',
            'modules_delta' => 'array'];
    }
}
