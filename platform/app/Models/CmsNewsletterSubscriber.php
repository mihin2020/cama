<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsNewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'full_name',
        'segment',
        'email_status',
        'open_count',
        'click_count',
        'last_opened_at',
        'last_clicked_at',
        'last_sent_at',
        'tags',
        'token',
        'source',
        'ip_address',
        'user_agent',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'last_clicked_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(
            CmsNewsletterBatch::class,
            'cms_newsletter_batch_subscriber',
            'subscriber_id',
            'batch_id',
        )->withTimestamps();
    }
}
