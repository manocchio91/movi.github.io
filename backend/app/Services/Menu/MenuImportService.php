<?php

namespace App\Services\Menu;

use App\Models\Activity;
use App\Models\Menu;
use App\Models\MenuSection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class MenuImportService
{
    private const REQUIRED_CONTENT_TYPES = '[Content_Types].xml';

    public function import(Activity $activity, UploadedFile $file, ?string $name = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true || $zip->numFiles > config('menu.max_archive_entries')) {
            throw ValidationException::withMessages(['file' => ['Archivio non valido o troppo complesso.']]);
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $total += (int) ($stat['size'] ?? 0);
            $entry = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($total > config('menu.max_uncompressed_bytes') || str_starts_with($entry, '/') || preg_match('#(^|/)\.\.(/|$)#', $entry)) {
                $zip->close();
                throw ValidationException::withMessages(['file' => ['Archivio non sicuro.']]);
            }
        }
        if ($zip->locateName(self::REQUIRED_CONTENT_TYPES) === false) {
            $zip->close();
            throw ValidationException::withMessages(['file' => ['Struttura Office non valida.']]);
        }
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = $extension === 'xlsx' ? $this->xlsx($zip) : $this->docx($zip);
        $zip->close();
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['Nessuna riga menu riconoscibile.']]);
        }

        return DB::transaction(function () use ($activity, $rows, $name): array {
            $menu = Menu::query()->create(['ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'name' => $name ?: 'Menu importato', 'status' => 'draft', 'active' => true]);
            $sections = [];
            $warnings = [];
            $imported = 0;
            foreach ($rows as $index => $row) {
                $sectionName = trim((string) ($row['section'] ?? 'Altro')) ?: 'Altro';
                $itemName = trim((string) ($row['name'] ?? ''));
                if ($itemName === '') {
                    $warnings[] = 'Riga '.($index + 1).' senza nome, ignorata.';

                    continue;
                }
                $section = $sections[$sectionName] ??= $menu->sections()->create(['ulid' => (string) Str::ulid(), 'name' => $sectionName, 'sort_order' => count($sections)]);
                assert($section instanceof MenuSection);
                $price = $this->price($row['price'] ?? null);
                if ($price === null) {
                    $warnings[] = 'Prezzo mancante o ambiguo per '.$itemName.'.';
                }
                $allergens = array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) ($row['allergens'] ?? '')) ?: [])));
                $section->items()->create(['ulid' => (string) Str::ulid(), 'name' => $itemName, 'description' => trim((string) ($row['description'] ?? '')) ?: null, 'price_cents' => $price,
                    'available' => ! in_array(mb_strtolower(trim((string) ($row['available'] ?? ''))), ['no', 'false', '0', 'non disponibile'], true), 'sort_order' => $imported, 'allergens' => $allergens ?: null, 'allergen_information_verified' => false]);
                $imported++;
            }
            if ($imported === 0) {
                throw ValidationException::withMessages(['file' => ['Nessun piatto riconosciuto.']]);
            }

            return ['menu' => $menu->load('sections.items'), 'report' => ['rows_imported' => $imported, 'warnings' => $warnings, 'unrecognized_rows' => count($rows) - $imported, 'requires_review' => true]];
        });
    }

    private function xlsx(ZipArchive $zip): array
    {
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            return [];
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            foreach ($this->xml($sharedXml)->si as $si) {
                $shared[] = trim((string) $si->t ?: implode('', array_map('strval', iterator_to_array($si->r->t))));
            }
        }
        $matrix = [];
        foreach ($this->xml($sheet)->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                $values[] = (string) $cell['t'] === 's' ? ($shared[(int) $value] ?? '') : $value;
            } $matrix[] = $values;
        }
        if (count($matrix) < 2) {
            return [];
        } $headers = array_map(fn ($v) => $this->header($v), array_shift($matrix));

        return array_map(fn ($values) => array_filter(array_combine($headers, array_pad($values, count($headers), '')) ?: []), $matrix);
    }

    private function docx(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            return [];
        } $document = $this->xml($xml);
        $document->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $lines = [];
        foreach ($document->xpath('//w:p') ?: [] as $paragraph) {
            $paragraph->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $line = trim(implode('', array_map('strval', $paragraph->xpath('.//w:t') ?: [])));
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        $section = 'Altro';
        $rows = [];
        foreach ($lines as $line) {
            if (mb_strtoupper($line) === $line && ! preg_match('/\d/', $line)) {
                $section = mb_convert_case($line, MB_CASE_TITLE, 'UTF-8');

                continue;
            } if (preg_match('/^(.+?)(?:\.{2,}|\s+)(\d+(?:[,.]\d{1,2})?)\s*€?$/u', $line, $match)) {
                $rows[] = ['section' => $section, 'name' => trim($match[1]), 'price' => $match[2]];
            } else {
                $rows[] = ['section' => $section, 'name' => $line, 'price' => null];
            }
        }

        return $rows;
    }

    private function header(string $header): string
    {
        $value = Str::slug($header);

        return match ($value) {
            'categoria','sezione' => 'section','piatto','nome','prodotto' => 'name','descrizione' => 'description','prezzo','price' => 'price','allergeni' => 'allergens','disponibile','disponibilita' => 'available',default => 'ignored_'.$value
        };
    }

    private function price(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        } $normalized = preg_replace('/[^0-9,.]/', '', (string) $value);
        if (! is_string($normalized) || ! is_numeric(str_replace(',', '.', $normalized))) {
            return null;
        }

        return (int) round((float) str_replace(',', '.', $normalized) * 100);
    }

    private function xml(string $contents): SimpleXMLElement
    {
        if (strlen($contents) > config('menu.max_xml_bytes') || preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            throw ValidationException::withMessages(['file' => ['XML Office non sicuro o troppo grande.']]);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
            if (! $xml instanceof SimpleXMLElement) {
                throw ValidationException::withMessages(['file' => ['XML Office non valido.']]);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
