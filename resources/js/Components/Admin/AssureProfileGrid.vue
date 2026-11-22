<script setup>
import { computed } from 'vue';

/**
 * Toutes les informations saisies par l'assuré à l'inscription (profil FIF), regroupées comme sur
 * la fiche : identité, numéros, structure de rattachement, contacts. Une valeur manquante s'affiche « — ».
 */
const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const groups = computed(() => {
    const p = props.profile || {};

    return [
        {
            titre: 'Identité', icone: 'badge', rows: [
                ['Nom', p.nom], ['Prénom(s)', p.prenom], ['Sexe', p.sexe], ['Matricule', p.matricule],
                ['N° informatique', p.numeroInformatique], ['Grade', p.grade], ['Catégorie', p.categorie],
            ],
        },
        {
            titre: 'Numéros', icone: 'pin', rows: [
                ['N° FIF', p.numeroFif], ['N° CIM', p.numeroCim], ['N° Carte CAMA', p.numeroCama], ['N° IUP', p.numeroIup],
            ],
        },
        {
            titre: 'Structure de rattachement', icone: 'account_tree', rows: [
                ['Armée', p.armee], ['Région', p.region], ['Corps', p.corps], ['Service', p.service],
                ['Section', p.section], ['Sous-section', p.sousSection],
            ],
        },
        {
            titre: 'Contacts', icone: 'contact_phone', rows: [
                ['Téléphone', p.telephone], ['E-mail', p.email],
                ['Personne à prévenir', p.personneAPrevenir], ['Tél. personne à prévenir', p.telPersonneAPrevenir],
            ],
        },
    ];
});
</script>

<template>
    <div class="space-y-4">
        <div v-for="group in groups" :key="group.titre">
            <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-primary">{{ group.icone }}</span> {{ group.titre }}
            </h3>
            <div class="grid grid-cols-2 gap-2">
                <div v-for="[label, value] in group.rows" :key="label" class="bg-white rounded-lg p-3 border border-outline-variant">
                    <p class="text-[10px] text-on-surface-variant uppercase">{{ label }}</p>
                    <p class="text-xs font-bold break-words">{{ value || '—' }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
