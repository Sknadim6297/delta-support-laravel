<?php

namespace App\Support;

use App\Models\Invoice;

class SimplePdf
{
    public static function document(array $lines): string
    {
        return self::render($lines, null);
    }

    public static function invoice(Invoice $invoice): string
    {
        return self::render(self::lines($invoice), self::logoJpeg());
    }

    public static function lines(Invoice $invoice): array
    {
        $lines = [
            'DELTA SUPPORT',
            'Invoice '.$invoice->invoice_number,
            'Status '.ucfirst((string) $invoice->status),
            '',
            'Bill to',
            trim($invoice->member_name.' ('.$invoice->member_id.')'),
            '',
            'Package '.$invoice->package_name.($invoice->package_code ? ' ('.$invoice->package_code.')' : ''),
            'Amount USD '.$invoice->price_usd,
        ];

        foreach (preg_split("/\r\n|\n|\r/", (string) $invoice->package_details) ?: [] as $detail) {
            if (trim($detail) !== '') {
                $lines[] = $detail;
            }
        }

        $lines[] = '';
        $lines[] = 'Purchase '.$invoice->purchase_reference;
        $lines[] = 'Purchased '.optional($invoice->purchased_at)->timezone('Asia/Kolkata')->format('d M Y H:i');
        $lines[] = 'Approved '.optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y H:i');
        $payment = trim(((string) $invoice->currency_label).' '.((string) $invoice->network));
        if ($payment !== '') {
            $lines[] = 'Payment '.$payment;
        }
        $lines[] = 'Transaction '.$invoice->payment_reference;
        $lines[] = '';
        $lines[] = (string) ($invoice->company_name ?: 'Company name not set');
        foreach ([$invoice->company_address, trim(((string) $invoice->company_email).' '.((string) $invoice->company_phone))] as $line) {
            if (trim((string) $line) !== '') {
                $lines[] = (string) $line;
            }
        }

        return $lines;
    }

    private static function render(array $lines, ?string $jpeg): string
    {
        $commands = ["0.035 0.153 0.278 rg", "0 790 595 52 re f", "1 1 1 rg"];
        $text = "BT\n/F1 16 Tf\n".($jpeg ? "90" : "40")." 808 Td\n(DELTA SUPPORT) Tj\nET\n";
        $text .= "0 0 0 rg\nBT\n/F1 11 Tf\n14 TL\n40 760 Td\n";
        foreach ($lines as $line) {
            if ($line === 'DELTA SUPPORT') {
                continue;
            }
            $text .= self::escape($line)." Tj\nT*\n";
        }
        $text .= "ET";
        if ($jpeg) {
            $commands[] = "q";
            $commands[] = "46 0 0 28 36 800 cm";
            $commands[] = "/Im1 Do";
            $commands[] = "Q";
        }
        $stream = implode("\n", $commands)."\n".$text;

        $objects = [
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n",
            "2 0 obj<< /Type /Pages /Count 1 /Kids [3 0 R] >>endobj\n",
        ];
        $resources = $jpeg
            ? "/Resources << /Font << /F1 5 0 R >> /XObject << /Im1 6 0 R >> >>"
            : "/Resources << /Font << /F1 5 0 R >> >>";
        $objects[] = "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R {$resources} >>endobj\n";
        $objects[] = "4 0 obj<< /Length ".strlen($stream)." >>stream\n".$stream."\nendstream\nendobj\n";
        $objects[] = "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n";
        if ($jpeg) {
            $info = @getimagesizefromstring($jpeg);
            $width = (int) ($info[0] ?? 46);
            $height = (int) ($info[1] ?? 28);
            $objects[] = "6 0 obj<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($jpeg)." >>stream\n".$jpeg."\nendstream\nendobj\n";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $start = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $line): string
    {
        $line = preg_replace('/[^\x20-\x7E]/', ' ', $line) ?? '';
        $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);

        return '('.$line.')';
    }

    private static function logoJpeg(): ?string
    {
        $path = public_path('site/img/logo.png');
        if (! is_file($path) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $source = @imagecreatefrompng($path);
        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 9, 39, 71);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }
};
