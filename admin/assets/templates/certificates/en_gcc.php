<?php
/**
 * Template: English GCC Certificate  (en_gcc)
 * Supports both browser print and dompdf PDF generation.
 */
$isPdfMode = defined('CERT_PDF_MODE') && CERT_PDF_MODE;

$L = $labels ?? [];
if (empty($L) && class_exists('CertificatePdfHelper', false)) {
    $L = CertificatePdfHelper::labels('en');
}
$fontFamily = htmlspecialchars($template['font_family'] ?? 'DejaVu Sans');
$fontSize   = htmlspecialchars($template['font_size']   ?? '11');
$lang       = $lang ?? 'en';

$bgSrc = '';
if (!empty($template['background_image_data_uri'])) {
    $bgSrc = $template['background_image_data_uri'];
} elseif (!empty($template['background_image'])) {
    $bgSrc = '/' . htmlspecialchars($template['background_image']);
}

$rowHeight = (float)($template['table_row_height'] ?? 8);
$maxRows   = (int)($template['table_max_rows'] ?? 12);

$officialName     = htmlspecialchars($officialName ?? '');
$officialPosition = htmlspecialchars($officialPosition ?? '');
$certNumber       = htmlspecialchars($data['certificate_number'] ?? '');
$issuedAt         = htmlspecialchars($data['issued_at'] ?? '');
$exporterName     = htmlspecialchars($data['exporter_name'] ?? '');
$importerName     = htmlspecialchars($data['importer_name'] ?? '');
$importerCountry  = htmlspecialchars($data['importer_country'] ?? '');

$lbl = function(string $key, string $fallback = '') use ($L): string {
    return htmlspecialchars($L[$key] ?? $fallback);
};
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }
body {
    margin: 0; padding: 0;
    font-family: "<?= $fontFamily ?>", "DejaVu Sans", sans-serif;
    font-size: <?= $fontSize ?>pt;
    direction: ltr;
}
.page {
    width: 210mm;
    min-height: 297mm;
    position: relative;
    background: #fff;
    padding: 10mm 12mm;
}
.bg-img { position: absolute; top: 0; left: 0; width: 210mm; height: 297mm; z-index: 0; }
.content { position: relative; z-index: 1; }
.cert-header { text-align: center; margin-bottom: 6mm; border-bottom: 2px solid #333; padding-bottom: 3mm; }
.cert-header h1 { font-size: 16pt; margin: 0; }
.cert-header h2 { font-size: 12pt; margin: 2mm 0 0; color: #555; }
.info-row { margin: 2mm 0; }
.info-row span { display: inline-block; min-width: 40mm; font-weight: bold; }
.products-title { font-weight: bold; margin: 4mm 0 2mm; border-bottom: 1px solid #666; }
table.products { width: 100%; border-collapse: collapse; font-size: <?= (float)$fontSize - 1 ?>pt; }
table.products th { background: #f0f0f0; border: 1px solid #333; padding: 2mm; text-align: center; }
table.products td { border: 1px solid #999; padding: 1.5mm 2mm; text-align: center; vertical-align: middle; }
.footer-section { margin-top: 8mm; width: 100%; }
.footer-label { font-size: <?= (float)$fontSize - 1 ?>pt; color: #555; margin-bottom: 1mm; }
.footer-value { font-size: <?= $fontSize ?>pt; font-weight: bold; }
.official-img { max-width: 100%; max-height: 20mm; }
.qr-img { width: 35mm; height: 35mm; }
.no-print { display: <?= $isPdfMode ? 'none' : 'block' ?>; }
@media print { .no-print { display: none !important; } }
</style>
</head>
<body>
<?php if (!$isPdfMode): ?>
<div class="no-print" style="padding:6px;background:#f0f0f0;text-align:center;">
    <button onclick="window.print()" style="padding:8px 20px;font-size:14px;cursor:pointer;">
        &#128438; <?= $lbl('print_pdf', 'Print / Export PDF') ?>
    </button>
</div>
<?php endif; ?>

<div class="page">
    <?php if ($bgSrc !== ''): ?><img class="bg-img" src="<?= $bgSrc ?>"><?php endif; ?>
    <div class="content">
        <div class="cert-header">
            <h1><?= $lbl('certificate_of_origin', 'Certificate of Origin') ?></h1>
            <h2><?= $lbl('gcc', 'GCC Countries') ?></h2>
        </div>
        <div class="info-row"><span><?= $lbl('certificate_number', 'Certificate No.') ?>:</span> <?= $certNumber ?></div>
        <div class="info-row"><span><?= $lbl('issue_date', 'Date of Issue') ?>:</span> <?= $issuedAt ?></div>
        <div class="info-row"><span><?= $lbl('exporter', 'Exporter') ?>:</span> <?= $exporterName ?></div>
        <div class="info-row"><span><?= $lbl('importer', 'Importer') ?>:</span> <?= $importerName ?></div>
        <div class="info-row"><span><?= $lbl('destination_country', 'Country of Destination') ?>:</span> <?= $importerCountry ?></div>

        <div class="products-title"><?= $lbl('products_table', 'Description of Goods') ?></div>
        <table class="products">
            <thead>
                <tr>
                    <th><?= $lbl('col_no', '#') ?></th>
                    <th><?= $lbl('col_product', 'Product') ?></th>
                    <th><?= $lbl('col_quantity', 'Quantity') ?></th>
                    <th><?= $lbl('col_net_weight', 'Net Weight') ?></th>
                    <th><?= $lbl('col_origin', 'Country of Origin') ?></th>
                    <th><?= $lbl('col_production_date', 'Production Date') ?></th>
                    <th><?= $lbl('col_expiry_date', 'Expiry Date') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php for ($i = 0; $i < $maxRows; $i++): $item = $items[$i] ?? null; ?>
                <tr style="height:<?= $rowHeight ?>mm;">
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['quantity'] ?? '') ?></td>
                    <td>
                        <?= htmlspecialchars($item['net_weight'] ?? '') ?>
                        <?= htmlspecialchars($item['weight_unit_code'] ?? '') ?>
                    </td>
                    <td><?= htmlspecialchars($item['country_of_origin'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['production_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['expiry_date'] ?? '') ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>

        <div class="footer-section">
            <table style="width:100%;border:none;margin-top:6mm;">
                <tr>
                    <td style="width:40mm;text-align:center;vertical-align:bottom;border:none;">
                        <?php if (!empty($qrPath)): ?>
                        <div class="footer-label"><?= $lbl('scan_to_verify', 'Scan to verify') ?></div>
                        <img class="qr-img" src="<?= htmlspecialchars($qrPath) ?>">
                        <?php endif; ?>
                    </td>
                    <td style="width:45mm;text-align:center;vertical-align:bottom;border:none;">
                        <?php if (!empty($stampPath)): ?>
                        <div class="footer-label"><?= $lbl('official_stamp', 'Official Stamp') ?></div>
                        <img class="official-img" src="<?= htmlspecialchars($stampPath) ?>">
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;vertical-align:bottom;border:none;">
                        <?php if ($officialName !== ''): ?>
                        <div class="footer-label"><?= $lbl('official_name', 'Name') ?></div>
                        <div class="footer-value"><?= $officialName ?></div>
                        <?php if ($officialPosition !== ''): ?>
                        <div class="footer-label" style="margin-top:1mm;"><?= $lbl('official_position', 'Position') ?></div>
                        <div class="footer-value"><?= $officialPosition ?></div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($signaturePath)): ?>
                        <div class="footer-label" style="margin-top:2mm;"><?= $lbl('authorized_signature', 'Authorized Signature') ?></div>
                        <img class="official-img" src="<?= htmlspecialchars($signaturePath) ?>">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <?php if (!empty($data['verification_code'])): ?>
        <div style="margin-top:3mm;font-size:8pt;color:#888;text-align:center;">
            <?= $lbl('verification_code', 'Verification Code') ?>: <?= htmlspecialchars($data['verification_code']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
