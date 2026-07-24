<?php

namespace App\Support;

use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * Construit un bouton « Exporter CSV » prêt à poser dans getHeaderActions().
     *
     * @param  string  $baseName  Base du nom de fichier (ex. « annonces »).
     * @param  array<string,callable>  $columns  [libellé de colonne => fn($record) => valeur]
     * @param  \Closure():iterable  $recordsResolver  Renvoie les enregistrements à exporter
     *         (typiquement $this->getFilteredTableQuery()->get(), pour respecter les filtres).
     */
    public static function action(string $baseName, array $columns, \Closure $recordsResolver): Action
    {
        return Action::make('export_csv')
            ->label('Exporter CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn (): StreamedResponse => self::streamDownload(
                $baseName . '-' . now()->format('Y-m-d_His') . '.csv',
                $columns,
                $recordsResolver()
            ));
    }

    /**
     * Génère et télécharge un CSV (séparateur « ; » + BOM UTF-8 pour Excel).
     *
     * @param  array<string,callable>  $columns
     */
    public static function streamDownload(string $filename, array $columns, iterable $records): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $records) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 : accents corrects à l'ouverture dans Excel.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_keys($columns), ';', '"', '');

            foreach ($records as $record) {
                $row = [];

                foreach ($columns as $resolver) {
                    $value = $resolver($record);

                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('d/m/Y H:i');
                    } elseif (is_bool($value)) {
                        $value = $value ? 'Oui' : 'Non';
                    } elseif (is_array($value)) {
                        $value = implode(', ', $value);
                    }

                    $row[] = $value === null ? '' : (string) $value;
                }

                fputcsv($out, $row, ';', '"', '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
