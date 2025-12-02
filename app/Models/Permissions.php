<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permissions extends Model
{
     use HasFactory;

    protected $fillable = [
        'permission_name',
    ];

    protected $table = 'permissions';

    /**
     * Get the roles that have been assigned this permission.
     */
    public function roles(): BelongsToMany
    {
        // This defines the Many-to-Many relationship, completing the link.
        // It points to the Role model through the same pivot table.
        // Note: The foreign keys are swapped from the Role model's definition.
        return $this->belongsToMany(Role::class, 'role_has_permissions', 'permission_id', 'role_id');
    }
}
