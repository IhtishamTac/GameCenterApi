<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $hidden = [
        'id', 'created_by', 'created_at', 'updated_at', 'deleted_at', 'users'
    ];
    public function users()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function gameVersions()
    {
        return $this->hasMany(GameVersion::class);
    }

    public function latestGameVersion()
    {
        return $this->hasOne(GameVersion::class)->latestOfMany();
    }
}
