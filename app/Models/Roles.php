<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
     use HasFactory;

    protected $fillable = [
        'role_name',
    ];
    protected $table = 'roles';
    
    public function permissions(): BelongsToMany
    {
        // This defines a Many-to-Many relationship between Role and Permission.
        // It uses the 'role_has_permissions' pivot table.
        // The foreign keys used are 'role_id' and 'permission_id', matching our migration.
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
