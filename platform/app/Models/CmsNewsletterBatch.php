<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsNewsletterBatch extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(
            CmsNewsletterSubscriber::class,
            'cms_newsletter_batch_subscriber',
            'batch_id',
            'subscriber_id',
        )->withTimestamps();
    }
}
