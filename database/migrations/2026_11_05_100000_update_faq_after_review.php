<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * FAQ du site : intègre la relecture du 05/11/2026 (réponses corrigées + 2 nouvelles questions).
     *
     * Une réponse n'est remplacée que si elle est encore celle d'origine : une réponse déjà
     * modifiée par un administrateur depuis le CMS n'est jamais écrasée.
     */
    public function up(): void
    {
        foreach ($this->corrections() as [$question, $oldAnswer, $newAnswer]) {
            $faq = DB::table('cms_faq')->where('question', $question)->first();

            if (! $faq) {
                continue;
            }

            if ($faq->answer !== $oldAnswer) {
                Log::info('FAQ relecture : réponse déjà modifiée dans le CMS, laissée telle quelle', ['question' => $question]);

                continue;
            }

            DB::table('cms_faq')->where('id', $faq->id)->update(['answer' => $newAnswer, 'updated_at' => now()]);
        }

        foreach ($this->newQuestions() as [$category, $question, $answer]) {
            $categoryId = DB::table('cms_faq_categories')->where('name', $category)->value('id');

            if (! $categoryId || DB::table('cms_faq')->where('question', $question)->exists()) {
                continue;
            }

            DB::table('cms_faq')->insert([
                'question' => $question,
                'answer' => $answer,
                'category_id' => $categoryId,
                'sort_order' => (int) DB::table('cms_faq')->where('category_id', $categoryId)->max('sort_order') + 1,
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->rank();
    }

    /**
     * Classe les questions de la plus à la moins pertinente pour un assuré (ordre d'affichage
     * sur la page Services). Appliqué seulement si l'ordre d'origine n'a pas été retouché dans le CMS.
     */
    private function rank(): void
    {
        $original = [
            'Comment obtenir un remboursement pour une consultation ?' => 1,
            "Qu'est-ce qu'une demande d'entente préalable ?" => 1,
            'Mes ayants-droit bénéficient-ils des mêmes tarifs ?' => 1,
            'Comment se fait le remboursement ?' => 2,
            "Qui peut s'inscrire sur la plateforme ?" => 1,
            'Quels justificatifs pour un enfant ?' => 2,
        ];

        foreach ($original as $question => $sortOrder) {
            $current = DB::table('cms_faq')->where('question', $question)->value('sort_order');

            if ($current !== null && (int) $current !== $sortOrder) {
                Log::info('FAQ relecture : ordre déjà retouché dans le CMS, classement non appliqué');

                return;
            }
        }

        foreach ($this->ranking() as $position => $question) {
            DB::table('cms_faq')->where('question', $question)->update(['sort_order' => $position + 1]);
        }
    }

    /** @return list<string> de la plus à la moins pertinente */
    private function ranking(): array
    {
        return [
            "Qui peut s'inscrire sur la plateforme ?",
            'Mes ayants-droit bénéficient-ils des mêmes tarifs ?',
            'Quels justificatifs pour un enfant ?',
            'Comment obtenir un remboursement pour une consultation ?',
            'Comment se fait le remboursement ?',
            'Comment reconnaître un prestataire conventionné avec la CAMA ?',
            'Sur quelles bases tarifaires les remboursements sont-ils faits ?',
            "Qu'est-ce qu'une demande d'entente préalable ?",
        ];
    }

    public function down(): void
    {
        foreach ($this->corrections() as [$question, $oldAnswer, $newAnswer]) {
            DB::table('cms_faq')->where('question', $question)->where('answer', $newAnswer)->update(['answer' => $oldAnswer]);
        }

        foreach ($this->newQuestions() as [, $question, $answer]) {
            DB::table('cms_faq')->where('question', $question)->where('answer', $answer)->delete();
        }
    }

    /** @return list<array{string, string, string}> [question, réponse d'origine, réponse relue] */
    private function corrections(): array
    {
        return [
            [
                'Quels justificatifs pour un enfant ?',
                'Acte de naissance, copie CNIB du parent assuré, et certificat de scolarité le cas échéant.',
                "Acte de naissance, copie CNIB du parent assuré, et certificat de scolarité à partir de 21 ans. Pour les enfants adoptés ou sous tutelle, il faut ajouter le certificat d'adoption ou de tutelle légal aux autres pièces à fournir.",
            ],
            [
                "Qu'est-ce qu'une demande d'entente préalable ?",
                "C'est une demande formelle à remplir par votre médecin avant certains actes coûteux (actes chirurgicaux, IRM, prothèses), afin que la CAMA valide la prise en charge avant l'intervention.",
                "C'est une demande formelle à remplir par l'assuré avant certains actes coûteux (actes chirurgicaux, IRM, prothèses, médicaments spécifiques), afin que la CAMA valide la prise en charge avant l'intervention. Elle est adressée au directeur général de la CAMA et doit être accompagnée de la prescription faite par votre médecin, et éventuellement de la facture proforma pour les actes chirurgicaux et les prothèses, des résultats de certains examens préalables pour ce qui concerne les IRM et les médicaments spécifiques.",
            ],
            [
                'Comment se fait le remboursement ?',
                'Les remboursements sont effectués par virement bancaire après réception et traitement complet de votre dossier par les services de la CAMA.',
                'Les remboursements sont effectués par virement bancaire, chèque ou mobile money après réception et traitement complet de votre dossier par les services de la CAMA. Le délai réglementaire de remboursement est de 30 jours.',
            ],
            [
                "Qui peut s'inscrire sur la plateforme ?",
                'Tout militaire en fonction et ayant un numéro CAMA valide peut créer un compte assuré.',
                "Tout militaire en activité ainsi que tout membre de sa famille (conjoints et enfants) remplissant les conditions d'éligibilité et ayant un numéro CAMA valide peut créer un compte assuré. Les retraités et le personnel civil des armées ne sont pas concernés.",
            ],
        ];
    }

    /** @return list<array{string, string, string}> [catégorie, question, réponse] */
    private function newQuestions(): array
    {
        return [
            [
                'Remboursements',
                'Sur quelles bases tarifaires les remboursements sont-ils faits ?',
                "Les remboursements sont faits suivant les tarifs conventionnés avec les partenaires de la CAMA si vos soins proviennent d'un partenaire, ou suivant le référentiel des actes éligibles au RAMU pour les soins réalisés chez des prestataires non conventionnés.",
            ],
            [
                'Prestations',
                'Comment reconnaître un prestataire conventionné avec la CAMA ?',
                "À travers l'affiche « CAMA accepté ici », collée au comptoir ou au niveau des caisses chez les prestataires partenaires. La liste de tous les partenaires de la CAMA est disponible sur l'application mobile CAMA, téléchargeable sur Play Store uniquement.",
            ],
        ];
    }
};
