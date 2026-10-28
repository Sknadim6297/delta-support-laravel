<?php

namespace App\Services\Reports;

use App\Models\AdminActivityLog;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExport
{
    public function download(string $report, string $format, string $scope, array $filters, array $headings, iterable $rows, int $userId): StreamedResponse
    {
        $stamp = CarbonImmutable::now(config('deltasupport.report_timezone'))->format('Ymd-His');
        $filename = $report.'-report-'.$stamp.'.'.$format;
        $meta = [
            'title' => ucfirst($report).' report',
            'generated' => CarbonImmutable::now(config('deltasupport.report_timezone'))->format('d M Y, H:i T'),
            'scope' => $scope,
            'filters' => $filters,
        ];

        return response()->streamDownload(function () use ($report, $format, $scope, $filters, $headings, $rows, $userId, $meta): void {
            $count = match ($format) {
                'xlsx' => $this->xlsx($meta, $headings, $rows),
                'pdf' => $this->pdf($meta, $headings, $rows),
                default => $this->csv($meta, $headings, $rows),
            };

            AdminActivityLog::query()->create([
                'user_id' => $userId,
                'action' => 'report_export',
                'subject' => $report,
                'format' => $format,
                'scope' => $scope,
                'filters' => $filters,
                'row_count' => $count,
            ]);
        }, $filename, [
            'Content-Type' => match ($format) {
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pdf' => 'application/pdf',
                default => 'text/csv; charset=UTF-8',
            },
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, string>  $headings
     */
    private function csv(array $meta, array $headings, iterable $rows): int
    {
        $handle = fopen('php://output', 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [$meta['title']]);
        fputcsv($handle, ['Generated', $meta['generated']]);
        fputcsv($handle, ['Scope', $meta['scope']]);
        foreach ($meta['filters'] as $label => $value) {
            fputcsv($handle, [$label, $this->cell((string) $value)]);
        }
        fputcsv($handle, ['Amounts keep four decimal places. Currencies are listed in their own column and are not converted.']);
        fputcsv($handle, $headings);
        $count = 0;
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => $this->cell((string) $value), array_values($row)));
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, string>  $headings
     */
    private function xlsx(array $meta, array $headings, iterable $rows): int
    {
        $path = storage_path('app/report-exports');
        File::ensureDirectoryExists($path);
        $sheetPath = $path.'/'.uniqid('sheet-', true).'.xml';
        $sheet = fopen($sheetPath, 'wb');
        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
        $line = 1;
        $this->xlsxRow($sheet, $line++, [$meta['title']]);
        $this->xlsxRow($sheet, $line++, ['Generated', $meta['generated']]);
        $this->xlsxRow($sheet, $line++, ['Scope', $meta['scope']]);
        foreach ($meta['filters'] as $label => $value) {
            $this->xlsxRow($sheet, $line++, [$label, (string) $value]);
        }
        $this->xlsxRow($sheet, $line++, ['Amounts keep four decimal places. Currencies are listed in their own column and are not converted.']);
        $this->xlsxRow($sheet, $line++, $headings);
        $count = 0;
        foreach ($rows as $row) {
            $this->xlsxRow($sheet, $line++, array_values($row));
            $count++;
        }
        fwrite($sheet, '</sheetData></worksheet>');
        fclose($sheet);

        $file = $path.'/'.uniqid('report-', true).'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        readfile($file);
        File::delete($file);
        File::delete($sheetPath);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, string>  $headings
     */
    private function pdf(array $meta, array $headings, iterable $rows): int
    {
        $body = [];
        $count = 0;
        foreach ($rows as $row) {
            $body[] = array_values($row);
            $count++;
        }
        $html = view('admin.reports.pdf', [
            'meta' => $meta,
            'headings' => $headings,
            'rows' => $body,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot(storage_path('app'));
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->getCanvas()->page_text(700, 560, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.2, 0.27, 0.35]);
        echo $dompdf->output();

        return $count;
    }

    /**
     * @param  resource  $sheet
     * @param  array<int, mixed>  $values
     */
    private function xlsxRow($sheet, int $line, array $values): void
    {
        fwrite($sheet, '<row r="'.$line.'">');
        foreach (array_values($values) as $index => $value) {
            $column = $this->column($index);
            $text = htmlspecialchars($this->cell((string) $value), ENT_XML1);
            fwrite($sheet, '<c r="'.$column.$line.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>');
        }
        fwrite($sheet, '</row>');
    }

    private function column(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function cell(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
