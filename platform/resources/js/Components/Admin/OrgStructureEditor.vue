<script setup>
import OrgTreeNode from '@/Components/Admin/OrgTreeNode.vue';
import { computed } from 'vue';

const regions = defineModel({ type: Array, required: true });

const CHILD_KEYS = ['corps', 'services', 'sections', 'sous_sections'];

function newId() {
    return `n-${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}`;
}

function getNode(path) {
    let arr = regions.value;
    let node = null;
    let parentArr = arr;

    for (let d = 0; d < path.length; d++) {
        parentArr = arr;
        node = arr[path[d]];
        if (!node) {
            break;
        }
        if (d < path.length - 1) {
            arr = node[CHILD_KEYS[d]] || [];
        }
    }

    return { node, parentArr, index: path[path.length - 1] };
}

function updateLibelle({ path, libelle }) {
    const { node } = getNode(path);
    if (node) {
        node.libelle = libelle;
    }
}

function addChild(path) {
    const { node } = getNode(path);
    if (!node) {
        return;
    }

    const childKey = CHILD_KEYS[path.length - 1];
    node[childKey] = node[childKey] || [];

    const grandChildKey = path.length < 4 ? CHILD_KEYS[path.length] : null;
    const child = { id: newId(), libelle: '' };
    if (grandChildKey) {
        child[grandChildKey] = [];
    }
    node[childKey].push(child);
}

function removeNode(path) {
    const { parentArr, index } = getNode(path);
    parentArr.splice(index, 1);
}

function addRegion() {
    regions.value.push({ id: newId(), libelle: '', corps: [] });
}

function updateRegionLibelle(index, libelle) {
    if (regions.value[index]) {
        regions.value[index].libelle = libelle;
    }
}

function removeRegion(index) {
    regions.value.splice(index, 1);
}

const stats = computed(() => {
    let corps = 0;
    let services = 0;
    let sections = 0;
    let sousSections = 0;

    (regions.value || []).forEach((r) => {
        (r.corps || []).forEach((c) => {
            corps++;
            (c.services || []).forEach((s) => {
                services++;
                (s.sections || []).forEach((sec) => {
                    sections++;
                    sousSections += (sec.sous_sections || []).length;
                });
            });
        });
    });

    return {
        regions: (regions.value || []).length,
        corps,
        services,
        sections,
        sousSections,
    };
});

</script>

<template>
    <div>
        <p v-if="stats.regions" class="text-[11px] text-on-surface-variant mb-3">
            {{ stats.regions }} région{{ stats.regions > 1 ? 's' : '' }} · {{ stats.corps }} corps · {{ stats.services }} services · {{ stats.sections }} sections · {{ stats.sousSections }} sous-sections
        </p>
        <p v-else class="text-[11px] text-on-surface-variant mb-3">Aucune région configurée pour le moment.</p>

        <div v-if="!regions.length" class="org-empty-state">
            <span class="material-symbols-outlined text-[40px] text-on-surface-variant mb-2">account_tree</span>
            <p class="text-sm font-bold text-on-surface mb-1">Aucune région configurée</p>
            <p class="text-xs text-on-surface-variant mb-4 max-w-sm mx-auto">
                Commencez par ajouter une <strong>région militaire</strong>, puis déployez les corps, services, sections et sous-sections.
            </p>
            <button type="button" class="px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-bold inline-flex items-center gap-1" @click="addRegion">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Créer la première région
            </button>
        </div>

        <div v-else class="space-y-4">
            <div v-for="(region, ri) in regions" :key="region.id || ri" class="org-region-block">
                <div class="flex flex-wrap items-center gap-2 p-4 bg-primary/5 border-b border-outline-variant">
                    <span class="org-level-pill org-level-pill--1">
                        <span class="material-symbols-outlined">map</span>
                        Niv. 1 · Région
                    </span>
                    <input
                        class="flex-1 min-w-[180px] px-3 py-2 text-sm font-semibold border border-outline-variant rounded-lg bg-white"
                        :value="region.libelle"
                        placeholder="Ex. 1re Région Militaire — Centre (Ouagadougou)"
                        aria-label="Région"
                        @input="updateRegionLibelle(ri, $event.target.value)"
                    />
                    <span class="text-[10px] text-on-surface-variant whitespace-nowrap">{{ (region.corps || []).length }} corps</span>
                    <button
                        type="button"
                        class="text-error shrink-0 px-2 py-1 rounded-lg text-[11px] font-bold hover:bg-error/10 flex items-center gap-1"
                        title="Supprimer cette région"
                        @click="removeRegion(ri)"
                    >
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                        <span class="hidden sm:inline">Supprimer</span>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <p v-if="!(region.corps || []).length" class="text-[11px] text-on-surface-variant italic py-2 pl-1">
                        Aucun corps dans cette région. Ajoutez-en un pour que l'assuré puisse continuer sa sélection.
                    </p>
                    <OrgTreeNode
                        v-for="(corps, ci) in region.corps || []"
                        :key="corps.id || ci"
                        :node="corps"
                        :depth="2"
                        :path="[ri, ci]"
                        @update-libelle="updateLibelle"
                        @add-child="addChild"
                        @remove="removeNode"
                    />
                    <button type="button" class="org-add-btn" @click="addChild([ri])">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        Ajouter un corps
                    </button>
                </div>
            </div>
        </div>

        <button
            v-if="regions.length"
            type="button"
            class="mt-4 shrink-0 px-4 py-2 rounded-lg bg-surface-container-high text-on-surface text-xs font-bold flex items-center gap-1 justify-center"
            @click="addRegion"
        >
            <span class="material-symbols-outlined text-[18px]">add</span>
            Nouvelle région
        </button>
    </div>
</template>

<style scoped>
.org-empty-state {
    text-align: center;
    padding: 32px 16px;
    border: 1px dashed #e5bdbb;
    border-radius: 0.75rem;
    background: #faf8f7;
}
.org-region-block {
    background: #fff;
    border: 1px solid #e5bdbb;
    border-radius: 0.75rem;
    overflow: hidden;
}
.org-level-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 9999px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}
.org-level-pill .material-symbols-outlined { font-size: 14px; }
.org-level-pill--1 { background: rgba(158, 0, 31, 0.1); color: #9e001f; }
.org-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border-radius: 0.5rem;
    border: 1px dashed #e5bdbb;
    color: #9e001f;
    font-size: 11px;
    font-weight: 700;
    background: transparent;
    cursor: pointer;
}
.org-add-btn:hover { background: rgba(158, 0, 31, 0.06); border-color: #9e001f; }
</style>
