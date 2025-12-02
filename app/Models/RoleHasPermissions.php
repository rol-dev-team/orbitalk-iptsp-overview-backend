<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleHasPermissions extends Model
{
    use HasFactory;

    
    protected $table = 'role_has_permissions';

    
    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    /**
     * Get the role associated with this specific permission assignment.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the permission associated with this specific role assignment.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
