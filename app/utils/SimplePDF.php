<?php
/**
 * SimplePDF (very small, no external deps)
 * - Generates a single-page PDF with built-in Helvetica font.
 * - Supports basic lines of text.
 *
 * Note: This is intentionally minimal for invoices/receipts.
 */
class SimplePDF {
    private array $lines = [];
    private float $fontSize = 12.0;

    public function setFontSize(float $size): void {
        $this->fontSize = max(6.0, min(24.0, $size));
    }

    public function addLine(string $text): void {
        $this->lines[] = $text;
    }

    private function pdfEscape(string $s): string {
        // Escape \ ( ) for PDF literal strings
        return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $s);
    }

    /**
     * @return string raw PDF bytes
     */
    public function output(): string {
        // Page size: A4 in points (595 x 842)
        $pageW = 595;
        $pageH = 842;

        // Build content stream: simple text lines
        $leading = $this->fontSize + 4;
        $x = 50;
        $yStart = 800;

        $content = "BT\n/F1 {$this->fontSize} Tf\n{$x} {$yStart} Td\n";
        $first = true;
        foreach ($this->lines as $line) {
            $escaped = $this->pdfEscape($line);
            if (!$first) {
                $content .= "0 -" . $leading . " Td\n";
            }
            $content .= "({$escaped}) Tj\n";
            $first = false;
        }
        $content .= "ET\n";

        $objects = [];
        $offsets = [];

        // 1: Catalog
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        // 2: Pages
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        // 3: Page
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        // 4: Font
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        // 5: Contents
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";

        $pdf = "%PDF-1.4\n";
        for ($i = 0; $i < count($objects); $i++) {
            $objNum = $i + 1;
            $offsets[$objNum] = strlen($pdf);
            $pdf .= "{$objNum} 0 obj\n{$objects[$i]}\nendobj\n";
        }

        // xref
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, "0", STR_PAD_LEFT) . " 00000 n \n";
        }

        // trailer
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}

