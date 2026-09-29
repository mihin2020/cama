<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsNewsletterCampaign extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'preheader',
        'blocks_json',
        'html_body',
        'segment',
        'batch_ids',
        'subscriber_ids',
        'status',
        'sent_count',
        'failed_count',
        'open_count',
        'click_count',
        'unsubscribe_count',
        'template_id',
        'created_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'blocks_json' => 'array',
            'batch_ids' => 'array',
            'subscriber_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CmsNewsletterTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CmsNewsletterCampaignSend::class, 'campaign_id');
    }

    public function openRate(): float
    {
        if ($this->sent_count <= 0) {
            return 0;
        }

        return round(($this->open_count / $this->sent_count) * 100, 1);
    }

    public function clickRate(): float
    {
        if ($this->sent_count <= 0) {
            return 0;
        }

        return round(($this->click_count / $this->sent_count) * 100, 1);
    }
}
