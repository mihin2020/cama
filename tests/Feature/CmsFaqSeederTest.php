<?php

namespace Tests\Feature;

use App\Models\CmsFaq;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FAQ issue de la relecture du 05/11/2026 : installation neuve complète, et jamais d'écrasement d'une réponse du CMS. */
class CmsFaqSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_gets_all_eight_reviewed_questions_in_order(): void
    {
        $this->seed(CmsSeeder::class);

        $this->assertSame([
            "Qui peut s'inscrire sur la plateforme ?",
            'Mes ayants-droit bénéficient-ils des mêmes tarifs ?',
            'Quels justificatifs pour un enfant ?',
            'Comment obtenir un remboursement pour une consultation ?',
            'Comment se fait le remboursement ?',
            'Comment reconnaître un prestataire conventionné avec la CAMA ?',
            'Sur quelles bases tarifaires les remboursements sont-ils faits ?',
            "Qu'est-ce qu'une demande d'entente préalable ?",
        ], CmsFaq::query()->orderBy('sort_order')->pluck('question')->all());
        $this->assertSame(8, CmsFaq::query()->where('published', true)->count());
        $this->assertStringContainsString('à partir de 21 ans', CmsFaq::query()->where('question', 'Quels justificatifs pour un enfant ?')->value('answer'));
    }

    public function test_reseeding_never_overwrites_an_answer_edited_in_the_cms(): void
    {
        $this->seed(CmsSeeder::class);
        CmsFaq::query()->where('question', 'Comment se fait le remboursement ?')->update(['answer' => 'Réponse modifiée dans le CMS.']);

        $this->seed(CmsSeeder::class);

        $this->assertSame(8, CmsFaq::query()->count());
        $this->assertSame('Réponse modifiée dans le CMS.', CmsFaq::query()->where('question', 'Comment se fait le remboursement ?')->value('answer'));
    }

    public function test_reseeding_never_brings_back_a_question_deleted_or_reworded_in_the_cms(): void
    {
        $this->seed(CmsSeeder::class);
        CmsFaq::query()->where('question', 'Comment se fait le remboursement ?')->delete();
        CmsFaq::query()->where('question', 'Quels justificatifs pour un enfant ?')->update(['question' => 'Quelles pièces pour un enfant ?']);

        $this->seed(CmsSeeder::class);

        $this->assertSame(7, CmsFaq::query()->count());
        $this->assertFalse(CmsFaq::query()->where('question', 'Comment se fait le remboursement ?')->exists());
        $this->assertFalse(CmsFaq::query()->where('question', 'Quels justificatifs pour un enfant ?')->exists());
    }

    public function test_an_existing_faq_written_in_the_cms_is_left_alone(): void
    {
        CmsFaq::query()->create([
            'question' => 'Question rédigée dans le CMS', 'answer' => 'R',
            'category_id' => \App\Models\CmsFaqCategory::query()->firstOrCreate(['name' => 'Enrôlement'], ['sort_order' => 1])->id,
            'sort_order' => 1, 'published' => true,
        ]);

        $this->seed(CmsSeeder::class);

        $this->assertFalse(CmsFaq::query()->where('question', 'Qui peut s\'inscrire sur la plateforme ?')->exists());
    }
}
