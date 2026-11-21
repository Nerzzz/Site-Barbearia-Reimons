<?php
function gerarPdf(string $titulo, array $linhas, ?string $logo = null): string {
    $txt = fn($s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'],
        iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$s));

    $temLogo = false;
    if ($logo && is_file($logo) && ($info = getimagesize($logo)) && $info[2] === IMAGETYPE_JPEG) {
        $temLogo = true;
    }

    $c = '';
    $y = 790;

    // logo (largura de 120pt, mantendo a proporção)
    if ($temLogo) {
        $w = 120;
        $h = $w * $info[1] / $info[0];
        $c .= sprintf("q %.2F 0 0 %.2F 50 %.2F cm /Im1 Do Q\n", $w, $h, $y - $h);
        $y -= $h + 40;
    }

    // título
    $c .= sprintf("BT /F2 20 Tf 50 %d Td (%s) Tj ET\n", $y, $txt($titulo));
    $y -= 40;

    // linhas "Rótulo: valor"
    foreach ($linhas as $rotulo => $valor) {
        $c .= sprintf("BT /F2 12 Tf 50 %d Td (%s) Tj /F1 12 Tf 110 0 Td (%s) Tj ET\n",
            $y, $txt($rotulo . ':'), $txt($valor));
        $y -= 24;
    }

    $obj = [];
    $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $obj[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $xobj = $temLogo ? "/XObject << /Im1 7 0 R >>" : "";
    $obj[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
            . "/Resources << /Font << /F1 4 0 R /F2 5 0 R >> $xobj >> /Contents 6 0 R >>";
    $obj[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $obj[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
    $obj[6] = "<< /Length " . strlen($c) . " >>\nstream\n$c\nendstream";

    if ($temLogo) {
        $dados = file_get_contents($logo);
        $obj[7] = "<< /Type /XObject /Subtype /Image /Width {$info[0]} /Height {$info[1]} "
                . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode "
                . "/Length " . strlen($dados) . " >>\nstream\n$dados\nendstream";
    }

    $pdf = "%PDF-1.4\n";
    $offs = [];
    foreach ($obj as $n => $conteudo) {
        $offs[$n] = strlen($pdf);
        $pdf .= "$n 0 obj\n$conteudo\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($offs as $o) {
        $pdf .= sprintf("%010d 00000 n \n", $o);
    }
    $pdf .= "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

    return $pdf;
}