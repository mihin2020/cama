<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsNewsletterCampaignSend extends Model
{
    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'tracking_token',
        'status',
        'sent_at',
        'opened_at',
        'first_clicked_at',
        'click_count',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'first_clicked_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CmsNewsletterCampaign::class, 'campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(CmsNewsletterSubscriber::class, 'subscriber_id');
    }
}
