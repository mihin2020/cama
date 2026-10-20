<?php

namespace App\Support\Exports;

use RuntimeException;
use ZipArchive;

/**
 * Écrit un vrai classeur Excel (.xlsx) ligne par ligne, sans tout charger en mémoire :
 * chaque onglet est écrit dans un fichier temporaire, puis le tout est zippé.
 * Un onglet par jeu de données, en-tête figé et filtres automatiques.
 */
class XlsxWriter
{
    /** Limite Excel : 1 048 576 lignes par onglet, en-tête compris. */
    private const MAX_ROWS = 1048575;

    private const MAX_CELL_LENGTH = 32767;

    /** @var list<array{name: string, path: string, columns: int, rows: int}> */
    private array $sheets = [];

    /** @var resource|null */
    private $handle = null;

    private array $header = [];

    private array $numeric = [];

    private string $baseName = '';

    private int $part = 1;

    public function __construct(private string $workDir, private string $headerColor = '1F4E3D') {}

    /**
     * @param  list<string>  $header
     * @param  list<bool>  $numeric  colonnes à écrire comme nombres
     */
    public function startSheet(string $name, array $header, array $numeric = []): void
    {
        $this->closeSheet();
        $this->baseName = $name;
        $this->part = 1;
        $this->header = $header;
        $this->numeric = $numeric;
        $this->openSheet($name);
    }

    public function addRow(array $values): void
    {
        if ($this->sheets[array_key_last($this->sheets)]['rows'] >= self::MAX_ROWS) {
            // Onglet plein : la suite part dans « Nom (2) », « Nom (3) »…
            $this->closeSheet();
            $this->part++;
            $this->openSheet("{$this->baseName} ({$this->part})");
        }

        fwrite($this->handle, $this->rowXml(array_values($values)));
        $this->sheets[array_key_last($this->sheets)]['rows']++;
    }

    /** Assemble le classeur et retourne son chemin. */
    public function save(string $path): string
    {
        $this->closeSheet();
        if ($this->sheets === []) {
            throw new RuntimeException('Aucun onglet à écrire.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer le fichier Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFile($sheet['path'], 'xl/worksheets/sheet'.($i + 1).'.xml');
        }
        $zip->close();

        foreach ($this->sheets as $sheet) {
            @unlink($sheet['path']);
        }

        return $path;
    }

    private function openSheet(string $name): void
    {
        $path = $this->workDir.DIRECTORY_SEPARATOR.'sheet'.(count($this->sheets) + 1).'.xml';
        $this->handle = fopen($path, 'w');
        $this->sheets[] = ['name' => $this->uniqueSheetName($name), 'path' => $path, 'columns' => count($this->header), 'rows' => 0];

        $cols = '';
        foreach ($this->header as $i => $label) {
            $width = max(12, min(48, mb_strlen($label) + 4));
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        fwrite($this->handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($cols !== '' ? "<cols>{$cols}</cols>" : '')
            .'<sheetData>');

        $headerCells = '';
        foreach ($this->header as $label) {
            $headerCells .= '<c t="inlineStr" s="1"><is><t>'.$this->escape($label).'</t></is></c>';
        }
        fwrite($this->handle, "<row>{$headerCells}</row>");
    }

    private function closeSheet(): void
    {
        if (! $this->handle) {
            return;
        }

        $sheet = $this->sheets[array_key_last($this->sheets)];
        $filter = $sheet['columns'] > 0
            ? '<autoFilter ref="A1:'.$this->columnLetter($sheet['columns']).($sheet['rows'] + 1).'"/>'
            : '';

        fwrite($this->handle, "</sheetData>{$filter}</worksheet>");
        fclose($this->handle);
        $this->handle = null;
    }

    private function rowXml(array $values): string
    {
        $cells = '';
        foreach ($values as $i => $value) {
            if ($value === null || $value === '') {
                $cells .= '<c/>';
            } elseif (($this->numeric[$i] ?? false) && is_numeric($value)) {
                $cells .= '<c><v>'.$value.'</v></c>';
            } else {
                $cells .= '<c t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
            }
        }

        return "<row>{$cells}</row>";
    }

    private function escape(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }
        // Caractères de contrôle interdits en XML.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        if (mb_strlen($value) > self::MAX_CELL_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_CELL_LENGTH - 1).'…';
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function uniqueSheetName(string $name): string
    {
        $clean = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name), " '") ?: 'Feuille';
        $clean = mb_substr($clean, 0, 31);
        $taken = array_map(fn ($s) => mb_strtolower($s['name']), $this->sheets);

        $candidate = $clean;
        for ($n = 2; in_array(mb_strtolower($candidate), $taken, true); $n++) {
            $suffix = " {$n}";
            $candidate = mb_substr($clean, 0, 31 - mb_strlen($suffix)).$suffix;
        }

        return $candidate;
    }

    private function columnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private function contentTypes(): string
    {
        $overrides = '';
        foreach ($this->sheets as $i => $_) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        $names = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
            if ($sheet['columns'] > 0) {
                $quoted = "'".str_replace("'", "''", $sheet['name'])."'";
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">'
                    .$this->escape($quoted.'!$A$1:$'.$this->columnLetter($sheet['columns']).'$'.($sheet['rows'] + 1))
                    .'</definedName>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<sheets>{$sheets}</sheets>"
            .($names !== '' ? "<definedNames>{$names}</definedNames>" : '')
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $_) {
            $rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function styles(): string
    {
        $color = 'FF'.strtoupper(ltrim($this->headerColor, '#'));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="'.$color.'"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
