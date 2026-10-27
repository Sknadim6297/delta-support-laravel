<?php

namespace App\Support;

class SimplePdf
{
    public static function document(array $lines): string
    {
        $escaped = array_map(function (string $line): string {
            $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);

            return '(' . $line . ") Tj\nT*";
        }, $lines);

        $stream = "BT\n/F1 11 Tf\n14 TL\n40 800 Td\n" . implode("\n", $escaped) . "\nET";
        $objects = [
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n",
            "2 0 obj<< /Type /Pages /Count 1 /Kids [3 0 R] >>endobj\n",
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj\n",
            "4 0 obj<< /Length " . strlen($stream) . " >>stream\n" . $stream . "\nendstream\nendobj\n",
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $start = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF";

        return $pdf;
    }
}
