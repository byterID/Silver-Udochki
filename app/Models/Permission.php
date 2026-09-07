<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = ['name', 'title', 'action_group_id', 'guard_name'];

    /**
     * @return BelongsTo<ActionGroup, $this>
     */
    public function actionGroup(): BelongsTo
    {
        return $this->belongsTo(ActionGroup::class);
    }
}
