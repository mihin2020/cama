<?php

namespace App\Console\Commands;

use App\Services\CmsArticleService;
use Illuminate\Console\Command;

class PublishScheduledCmsArticles extends Command
{
    protected $signature = 'cms:publish-scheduled-articles';

    protected $description = 'Publie les articles CMS dont la date de programmation est atteinte';

    public function handle(CmsArticleService $articles): int
    {
        $count = $articles->publishDueArticles();

        if ($count > 0) {
            $this->info("{$count} article(s) publié(s) automatiquement.");
        }

        return self::SUCCESS;
    }
}
