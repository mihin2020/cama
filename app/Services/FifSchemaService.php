<?php

namespace App\Services;

use App\Models\FifField;
use App\Models\FifSection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Formulaire FIF configurable par l'administration.
 *
 * Une section regroupe des champs ; chaque cible (assuré, conjoint, enfant) a ses sections. Un champ
 * ou une section masqué n'est ni proposé dans le formulaire ni imprimé sur la FIF, mais les valeurs
 * déjà saisies restent en base. La suppression est « douce » pour la même raison.
 *
 * Les valeurs saisies sont enregistrées sous la forme {code_du_champ: valeur} : le code est attribué à
 * la création et ne change plus, même si le libellé est modifié.
 */
class FifSchemaService
{
    private const CACHE_KEY = 'fif.schema.v1.';

    public const MAX_OPTIONS = 30;

    /** @return list<string> */
    public static function cibles(): array
    {
        return array_keys(FifSection::CIBLES);
    }

    // ------------------------------------------------------------------ lecture

    /**
     * Formulaire tel que l'assuré le voit et tel qu'il est imprimé : sections et champs visibles.
     *
     * @return list<array<string, mixed>>
     */
    public function schema(string $cible): array
    {
        return Cache::rememberForever(self::CACHE_KEY.$cible, function () use ($cible) {
            return collect($this->load($cible))
                ->where('visible', true)
                ->map(fn (array $section) => [...$section, 'fields' => array_values(array_filter($section['fields'], fn (array $f) => $f['visible']))])
                ->filter(fn (array $section) => $section['fields'] !== [])
                ->values()
                ->all();
        });
    }

    /**
     * Formulaire complet pour l'écran d'administration (masqués compris).
     *
     * @return list<array<string, mixed>>
     */
    public function adminSchema(string $cible): array
    {
        return $this->load($cible);
    }

    /** @return list<array<string, mixed>> */
    private function load(string $cible): array
    {
        return FifSection::query()
            ->where('cible', $cible)
            ->with('fields')
            ->orderBy('ordre')
            ->orderBy('id')
            ->get()
            ->map(fn (FifSection $section) => [
                'id' => $section->id,
                'uuid' => $section->uuid,
                'code' => $section->code,
                'titre' => $section->titre,
                'description' => $section->description,
                'ordre' => $section->ordre,
                'visible' => $section->visible,
                'fields' => $section->fields->map(fn (FifField $field) => $this->fieldArray($field))->all(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function fieldArray(FifField $field): array
    {
        return [
            'id' => $field->id,
            'uuid' => $field->uuid,
            'code' => $field->code,
            'libelle' => $field->libelle,
            'type' => $field->type,
            'options' => $field->options ?? [],
            'aide' => $field->aide,
            'obligatoire' => $field->obligatoire,
            'visible' => $field->visible,
            'imprimer_pdf' => $field->imprimer_pdf,
            'ordre' => $field->ordre,
        ];
    }

    /** @return list<array<string, mixed>> champs visibles à plat */
    public function fields(string $cible): array
    {
        return collect($this->schema($cible))->flatMap(fn (array $section) => $section['fields'])->values()->all();
    }

    // ------------------------------------------------------------------ saisie

    /**
     * Règles de validation des champs visibles d'une cible, à fusionner dans une validation :
     * la clé est « {préfixe}.{code} » (ex. « conjoints.*.fif.permis »).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $cible, string $prefix = 'fif', bool $enforceRequired = true): array
    {
        $rules = [];

        foreach ($this->fields($cible) as $field) {
            // Brouillon : le type est contrôlé, mais rien n'est exigé (le membre peut être complété plus tard).
            $rules["{$prefix}.{$field['code']}"] = $this->fieldRules($enforceRequired ? $field : [...$field, 'obligatoire' => false]);
        }

        return $rules;
    }

    /**
     * Libellés des champs obligatoires non renseignés (soumission d'un membre).
     *
     * @param  array<string, mixed>|null  $values
     * @param  list<string>  $filesPresent  codes des champs fichier satisfaits (envoyé maintenant ou déjà enregistré)
     * @return list<string>
     */
    public function missingRequired(string $cible, ?array $values, array $filesPresent = []): array
    {
        return collect($this->fields($cible))
            ->filter(fn (array $field) => $field['obligatoire'])
            ->filter(function (array $field) use ($values, $filesPresent) {
                if ($field['type'] === 'fichier') {
                    return ! in_array($field['code'], $filesPresent, true) && empty($values[$field['code']]);
                }

                $value = $values[$field['code']] ?? null;

                return $field['type'] === 'checkbox' ? ! $value : ($value === null || (is_string($value) && trim($value) === ''));
            })
            ->pluck('libelle')
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $field */
    private function fieldRules(array $field): array
    {
        $required = $field['obligatoire'];

        return match ($field['type']) {
            'number' => [$required ? 'required' : 'nullable', 'numeric'],
            'date' => [$required ? 'required' : 'nullable', 'date'],
            'email' => [$required ? 'required' : 'nullable', 'email', 'max:190'],
            'tel' => [$required ? 'required' : 'nullable', 'string', 'max:50', 'regex:/^[0-9 +().\-]{6,50}$/'],
            'textarea' => [$required ? 'required' : 'nullable', 'string', 'max:2000'],
            'select', 'radio' => [$required ? 'required' : 'nullable', Rule::in($field['options'])],
            // Case à cocher obligatoire : elle doit être cochée (ex. « Je certifie l'exactitude »).
            'checkbox' => $required ? ['accepted'] : ['nullable', 'boolean'],
            // Fichier chargé par l'assuré : PDF uniquement (contenu réel vérifié). Le caractère
            // obligatoire est contrôlé à part (un fichier déjà envoyé reste valable).
            'fichier' => ['nullable', new \App\Rules\PdfDocument],
            default => [$required ? 'required' : 'nullable', 'string', 'max:255'],
        };
    }

    /**
     * Valeurs à enregistrer : uniquement celles des champs visibles, dans leur type.
     *
     * @param  array<string, mixed>|null  $input
     * @return array<string, mixed>
     */
    public function clean(string $cible, ?array $input): array
    {
        $values = [];

        foreach ($this->fields($cible) as $field) {
            // Les fichiers sont rangés à part (voir DossierPieceStorageService::storeFifFile).
            if ($field['type'] === 'fichier') {
                continue;
            }

            $value = Arr::get($input ?? [], $field['code']);

            $values[$field['code']] = match (true) {
                $field['type'] === 'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                $value === null || (is_string($value) && trim($value) === '') => null,
                $field['type'] === 'number' => is_numeric($value) ? $value + 0 : null,
                default => is_string($value) ? trim($value) : $value,
            };
        }

        return $values;
    }

    /**
     * Valeurs déjà enregistrées, limitées aux champs visibles (pour préremplir un formulaire).
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public function valuesFor(string $cible, ?array $stored): array
    {
        return collect($this->fields($cible))
            ->mapWithKeys(fn (array $field) => [$field['code'] => $field['type'] === 'fichier'
                ? $this->fileSummary($stored[$field['code']] ?? null)
                : ($stored[$field['code']] ?? ($field['type'] === 'checkbox' ? false : null))])
            ->all();
    }

    /**
     * Fichier déjà chargé, sans son chemin interne : de quoi afficher « Fichier actuel : nom ».
     *
     * @return array{filename: ?string}|null
     */
    public function fileSummary(mixed $stored): ?array
    {
        return is_array($stored) && ! empty($stored['path'])
            ? ['filename' => $stored['original_name'] ?? $stored['filename'] ?? null]
            : null;
    }

    // ------------------------------------------------------------------ affichage et impression

    /**
     * Sections et lignes « libellé : valeur » d'une cible, pour l'écran d'administration et le PDF.
     *
     * @param  array<string, mixed>|null  $stored
     * @return list<array{titre: string, rows: list<array{code: string, label: string, value: string, imprimer: bool}>}>
     */
    public function display(string $cible, ?array $stored): array
    {
        return collect($this->schema($cible))
            ->map(fn (array $section) => [
                'titre' => $section['titre'],
                'rows' => collect($section['fields'])->map(fn (array $field) => [
                    'code' => $field['code'],
                    'label' => $field['libelle'],
                    'type' => $field['type'],
                    'value' => $this->formatValue($field, $stored[$field['code']] ?? null),
                    'imprimer' => $field['imprimer_pdf'],
                    // Fichier chargé : l'écran propose « Visualiser » et « Télécharger » (jamais le chemin interne).
                    'fichier' => $field['type'] === 'fichier' && ! empty($stored[$field['code']]['path'] ?? null),
                ])->all(),
            ])
            ->all();
    }

    /** @param  array<string, mixed>  $field */
    private function formatValue(array $field, mixed $value): string
    {
        if ($field['type'] === 'checkbox') {
            return $value ? 'Oui' : 'Non';
        }

        if ($field['type'] === 'fichier') {
            return is_array($value) ? (string) ($value['original_name'] ?? $value['filename'] ?? '') : '';
        }

        if ($value === null || $value === '') {
            return '';
        }

        if ($field['type'] === 'date') {
            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    // ------------------------------------------------------------------ administration : sections

    /** @param  array<string, mixed>  $data */
    public function createSection(string $cible, array $data): FifSection
    {
        $this->assertCible($cible);

        $section = FifSection::query()->create([
            'cible' => $cible,
            'code' => $this->uniqueCode(FifSection::withTrashed()->where('cible', $cible), $data['titre']),
            'titre' => trim($data['titre']),
            'description' => $this->nullable($data['description'] ?? null),
            'ordre' => (int) FifSection::withTrashed()->where('cible', $cible)->max('ordre') + 1,
            'visible' => (bool) ($data['visible'] ?? true),
        ]);

        $this->forget();

        return $section;
    }

    /** @param  array<string, mixed>  $data */
    public function updateSection(FifSection $section, array $data): FifSection
    {
        $section->update([
            'titre' => trim($data['titre']),
            'description' => $this->nullable($data['description'] ?? null),
            'visible' => (bool) ($data['visible'] ?? $section->visible),
        ]);
        $this->forget();

        return $section;
    }

    public function deleteSection(FifSection $section): void
    {
        DB::transaction(function () use ($section) {
            $section->fields()->get()->each->delete();
            $section->delete();
        });
        $this->forget();
    }

    public function moveSection(FifSection $section, string $direction): void
    {
        $siblings = FifSection::query()->where('cible', $section->cible)->orderBy('ordre')->orderBy('id')->get();
        $this->swap($siblings->all(), $section->id, $direction);
        $this->forget();
    }

    // ------------------------------------------------------------------ administration : champs

    /** @param  array<string, mixed>  $data */
    public function createField(FifSection $section, array $data): FifField
    {
        $field = FifField::query()->create([
            'section_id' => $section->id,
            'code' => $this->uniqueCode(FifField::withTrashed()->where('section_id', $section->id), $data['libelle']),
            ...$this->fieldAttributes($data),
            'ordre' => (int) FifField::withTrashed()->where('section_id', $section->id)->max('ordre') + 1,
        ]);
        $this->forget();

        return $field;
    }

    /** @param  array<string, mixed>  $data */
    public function updateField(FifField $field, array $data): FifField
    {
        // Le code ne change jamais : les valeurs déjà saisies restent rattachées au champ.
        $field->update($this->fieldAttributes($data, $field));
        $this->forget();

        return $field;
    }

    public function deleteField(FifField $field): void
    {
        $field->delete();
        $this->forget();
    }

    public function moveField(FifField $field, string $direction): void
    {
        $siblings = FifField::query()->where('section_id', $field->section_id)->orderBy('ordre')->orderBy('id')->get();
        $this->swap($siblings->all(), $field->id, $direction);
        $this->forget();
    }

    // ------------------------------------------------------------------ internes

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fieldAttributes(array $data, ?FifField $current = null): array
    {
        $type = (string) ($data['type'] ?? $current?->type ?? 'text');

        if (! array_key_exists($type, FifField::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Type de champ inconnu.']);
        }

        $options = null;
        if (in_array($type, FifField::CHOICE_TYPES, true)) {
            $options = collect($data['options'] ?? [])
                ->map(fn ($option) => trim((string) $option))
                ->filter()
                ->unique()
                ->values()
                ->take(self::MAX_OPTIONS)
                ->all();

            if (count($options) < 2) {
                throw ValidationException::withMessages(['options' => 'Indiquez au moins deux choix.']);
            }
        }

        return [
            'libelle' => trim((string) $data['libelle']),
            'type' => $type,
            'options' => $options,
            'aide' => $this->nullable($data['aide'] ?? null),
            'obligatoire' => (bool) ($data['obligatoire'] ?? false),
            'visible' => (bool) ($data['visible'] ?? true),
            'imprimer_pdf' => (bool) ($data['imprimer_pdf'] ?? true),
        ];
    }

    /** Code unique et stable (jamais réutilisé, même après suppression). */
    private function uniqueCode($query, string $label): string
    {
        $base = Str::limit(Str::slug($label, '_') ?: 'champ', 60, '');
        $code = $base;
        $i = 2;

        while ((clone $query)->where('code', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        return $code;
    }

    /**
     * Échange la position d'un élément avec son voisin, puis renumérote proprement.
     *
     * @param  list<\Illuminate\Database\Eloquent\Model>  $siblings
     */
    private function swap(array $siblings, int $id, string $direction): void
    {
        $ids = array_map(fn ($item) => $item->id, $siblings);
        $index = array_search($id, $ids, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        DB::transaction(function () use ($siblings, $ids) {
            $byId = collect($siblings)->keyBy('id');
            foreach ($ids as $position => $itemId) {
                $byId[$itemId]->update(['ordre' => $position + 1]);
            }
        });
    }

    private function assertCible(string $cible): void
    {
        if (! in_array($cible, self::cibles(), true)) {
            throw ValidationException::withMessages(['cible' => 'Cible inconnue.']);
        }
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function forget(): void
    {
        foreach (self::cibles() as $cible) {
            Cache::forget(self::CACHE_KEY.$cible);
        }
    }
}
