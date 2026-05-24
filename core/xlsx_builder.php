<?php
/**
 * XLSXBuilder — Full-featured Excel builder dengan styling, border, warna, print area
 * Menggunakan ZipArchive + manual XML (tidak butuh library eksternal)
 */
class XLSXBuilder {

    private array $sheets   = [];
    private array $strTable = [];
    private array $strIndex = [];

    // ── Style IDs ──────────────────────────────────────────
    // 0  = normal
    // 1  = bold
    // 2  = header (bold, bg biru tua, font putih, border, center)
    // 3  = header sub (bold, bg biru muda, border, center)
    // 4  = judul (bold, font besar, merge hint)
    // 5  = angka (right-align, border)
    // 6  = normal border
    // 7  = bold border
    // 8  = zebra (bg abu muda, border)
    // 9  = nilai tinggi (bg hijau muda, bold, border)
    // 10 = nilai rendah (bg merah muda, bold, border)
    // 11 = sub-judul (italic, abu)
    // 12 = center border

    public function addSheet(string $name, array $data, array $colWidths = []): void {
        $this->sheets[] = [
            'name'      => mb_substr(preg_replace('/[\\/\\\\?*\[\]:]/', '-', $name), 0, 31),
            'data'      => $data,
            'colWidths' => $colWidths,
        ];
    }

    public function download(string $filename): void {
        $xlsx = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: max-age=0');
        echo $xlsx;
        exit;
    }

    private function build(): string {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Reset shared strings
        $this->strTable = [];
        $this->strIndex = [];

        // Build sheets XML
        $sheetXmls = [];
        foreach ($this->sheets as $idx => $sheet) {
            $sheetXmls[$idx] = $this->buildSheet($sheet['data'], $sheet['colWidths']);
        }

        // [Content_Types].xml
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $ct .= '<Default Extension="xml" ContentType="application/xml"/>';
        $ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        // _rels/.rels
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>');

        // xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $wb .= '<bookViews><workbookView activeTab="0"/></bookViews><sheets>';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $wb .= '<sheet name="'.htmlspecialchars($this->sheets[$i-1]['name']).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
        }
        $wb .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // xl/_rels/workbook.xml.rels
        $wr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $wr .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $wr .= '<Relationship Id="rIdSt" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wr .= '<Relationship Id="rIdSS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $wr .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wr);

        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        // xl/sharedStrings.xml
        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStrings());

        // xl/worksheets/sheetN.xml
        foreach ($sheetXmls as $idx => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.($idx+1).'.xml', $xml);
        }

        $zip->close();
        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }

    private function buildSheet(array $data, array $colWidths): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $xml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        // Print settings
        $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';

        // Lebar kolom
        if (!empty($colWidths)) {
            $xml .= '<cols>';
            foreach ($colWidths as $ci => $w) {
                $xml .= '<col min="'.($ci+1).'" max="'.($ci+1).'" width="'.$w.'" customWidth="1" bestFit="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($data as $ri => $row) {
            if (empty($row)) { $xml .= '<row r="'.($ri+1).'"/>'; continue; }
            $xml .= '<row r="'.($ri+1).'">';
            foreach ($row as $ci => $cell) {
                $xml .= $this->buildCell($ri+1, $ci, $cell);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        // Print area & page setup
        $maxRow = count($data);
        $maxCol = 0;
        foreach ($data as $row) { if (count($row) > $maxCol) $maxCol = count($row); }
        $lastCol = $maxCol > 0 ? $this->colLetter($maxCol-1) : 'A';

        $xml .= '<printOptions headings="0" gridLines="0"/>';
        $xml .= '<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0" scale="100"/>';
        $xml .= '<headerFooter>';
        $xml .= '<oddHeader>&amp;C&amp;8&amp;P dari &amp;N</oddHeader>';
        $xml .= '<oddFooter>&amp;L&amp;8Dicetak: &amp;D &amp;T&amp;R&amp;8Halaman &amp;P</oddFooter>';
        $xml .= '</headerFooter>';
        $xml .= '</worksheet>';
        return $xml;
    }

    private function buildCell(int $row, int $col, $cell): string {
        $ref   = $this->colLetter($col) . $row;
        $val   = is_array($cell) ? ($cell['value'] ?? '') : $cell;
        $style = is_array($cell) ? ($cell['style'] ?? 0) : 0;

        if ($val === '' || $val === null) {
            return '<c r="'.$ref.'" s="'.$style.'"/>';
        }
        if (is_numeric($val) && !is_string($val)) {
            return '<c r="'.$ref.'" s="'.$style.'"><v>'.$val.'</v></c>';
        }
        // Shared string
        $str = (string)$val;
        if (!isset($this->strIndex[$str])) {
            $this->strIndex[$str] = count($this->strTable);
            $this->strTable[] = $str;
        }
        $idx = $this->strIndex[$str];
        return '<c r="'.$ref.'" t="s" s="'.$style.'"><v>'.$idx.'</v></c>';
    }

    private function buildSharedStrings(): string {
        $cnt = count($this->strTable);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$cnt.'" uniqueCount="'.$cnt.'">';
        foreach ($this->strTable as $s) {
            $xml .= '<si><t xml:space="preserve">'.htmlspecialchars($s).'</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function buildStyles(): string {
        // numFmts
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Fonts: 0=normal, 1=bold, 2=bold+white, 3=bold+12, 4=italic+grey, 5=bold+navy
        $xml .= '<fonts count="6">';
        $xml .= '<font><sz val="10"/><name val="Calibri"/><color rgb="FF1E293B"/></font>'; // 0 normal
        $xml .= '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF1E293B"/></font>'; // 1 bold
        $xml .= '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'; // 2 bold white
        $xml .= '<font><b/><sz val="12"/><name val="Calibri"/><color rgb="FF1E3A8A"/></font>'; // 3 judul biru
        $xml .= '<font><sz val="9"/><name val="Calibri"/><color rgb="FF64748B"/><i/></font>'; // 4 italic abu
        $xml .= '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF166534"/></font>'; // 5 bold hijau
        $xml .= '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF991B1B"/></font>'; // 6 bold merah
        $xml .= '</fonts>';

        // Fills
        $xml .= '<fills count="10">';
        $xml .= '<fill><patternFill patternType="none"/></fill>';         // 0
        $xml .= '<fill><patternFill patternType="gray125"/></fill>';      // 1
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>'; // 2 biru tua
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>'; // 3 biru muda
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'; // 4 abu
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>'; // 5 hijau
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>'; // 6 merah
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF7ED"/></patternFill></fill>'; // 7 kuning
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>'; // 8 kuning muda
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFE0F2FE"/></patternFill></fill>'; // 9 biru super muda
        $xml .= '</fills>';

        // Borders
        $thin = '<color rgb="FFB0BEC5"/>';
        $bdr  = '<left style="thin">'.$thin.'</left><right style="thin">'.$thin.'</right><top style="thin">'.$thin.'</top><bottom style="thin">'.$thin.'</bottom><diagonal/>';
        $xml .= '<borders count="3">';
        $xml .= '<border><left/><right/><top/><bottom/><diagonal/></border>';  // 0 none
        $xml .= '<border>'.$bdr.'</border>';  // 1 thin all
        $xml .= '<border><left style="medium"><color rgb="FF1E3A8A"/></left><right style="thin">'.$thin.'</right><top style="medium"><color rgb="FF1E3A8A"/></top><bottom style="medium"><color rgb="FF1E3A8A"/></bottom><diagonal/></border>'; // 2 thick blue
        $xml .= '</borders>';

        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';

        // cellXfs — style index maps to export_excel.php 'style' values
        $al = fn($h,$v='center')=>'<alignment horizontal="'.$h.'" vertical="'.$v.'" wrapText="1"/>';
        $xml .= '<cellXfs count="13">';
        // 0: normal
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0">'.$al('left','center').'</xf>';
        // 1: bold
        $xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0">'.$al('left','center').'</xf>';
        // 2: header (bold white, biru tua, border tebal, center)
        $xml .= '<xf numFmtId="0" fontId="2" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1">'.$al('center').'</xf>';
        // 3: sub-header (bold, biru muda, border, center)
        $xml .= '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">'.$al('center').'</xf>';
        // 4: judul besar (bold biru 12px)
        $xml .= '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1">'.$al('left','center').'</xf>';
        // 5: angka (right, border)
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1">'.$al('right').'</xf>';
        // 6: normal border
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1">'.$al('left','center').'</xf>';
        // 7: bold border
        $xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1">'.$al('left','center').'</xf>';
        // 8: zebra abu (border)
        $xml .= '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1">'.$al('left','center').'</xf>';
        // 9: nilai bagus (bg hijau, bold)
        $xml .= '<xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">'.$al('center').'</xf>';
        // 10: nilai kurang (bg merah, bold)
        $xml .= '<xf numFmtId="0" fontId="6" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">'.$al('center').'</xf>';
        // 11: sub-judul italic abu
        $xml .= '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1">'.$al('left','center').'</xf>';
        // 12: center border normal
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1">'.$al('center').'</xf>';
        $xml .= '</cellXfs>';
        $xml .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        $xml .= '</styleSheet>';
        return $xml;
    }

    private function colLetter(int $col): string {
        $letter = '';
        $col++;
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intdiv($col, 26);
        }
        return $letter;
    }
}
