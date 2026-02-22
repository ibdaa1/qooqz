<?php
/**
 * Template: Arabic Non-GCC Certificate
 * Version: ar_non_gcc
 */
$fontFamily = htmlspecialchars($template['font_family'] ?? 'Arial');
$fontSize   = htmlspecialchars($template['font_size'] ?? '12');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
    body {
        margin:0;
        padding:0;
        font-family: <?= $fontFamily ?>;
        font-size: <?= $fontSize ?>pt;
    }

    .page {
        position: relative;
        width: 210mm;
        height: 297mm;
        overflow: hidden;
    }

    .abs {
        position: absolute;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 10pt;
    }

    th, td {
        border: 1px solid #000;
        padding: 4px;
        text-align: center;
    }

    .no-print { display: block; }

    @media print {
        .no-print { display: none !important; }
        body { margin: 0; }
    }
</style>
</head>
<body>

<!-- Print / PDF button (hidden when printing) -->
<div class="no-print" style="padding:8px; background:#f0f0f0; text-align:center;">
    <button onclick="window.print()" style="padding:8px 24px; font-size:14px; cursor:pointer;">
        &#128438; طباعة / تصدير PDF
    </button>
</div>

<div class="page">

    <!-- الخلفية الرسمية -->
    <?php if (!empty($template['background_image'])): ?>
    <img src="/<?= htmlspecialchars($template['background_image']) ?>"
         style="position:absolute; top:0; left:0; width:210mm; height:297mm; z-index:0;">
    <?php endif; ?>

    <!-- رقم الشهادة -->
    <div class="abs" style="top:42mm; right:28mm; z-index:1;">
        <?= htmlspecialchars($data['certificate_number'] ?? '') ?>
    </div>

    <!-- تاريخ الإصدار -->
    <div class="abs" style="top:50mm; right:28mm; z-index:1;">
        <?= htmlspecialchars($data['issued_at'] ?? '') ?>
    </div>

    <!-- اسم المصدر -->
    <div class="abs" style="top:70mm; right:30mm; width:80mm; z-index:1;">
        <?= htmlspecialchars($data['exporter_name'] ?? '') ?>
    </div>

    <!-- اسم المستورد -->
    <div class="abs" style="top:78mm; right:30mm; width:80mm; z-index:1;">
        <?= htmlspecialchars($data['importer_name'] ?? '') ?>
    </div>

    <!-- بلد المقصد -->
    <div class="abs" style="top:86mm; right:30mm; width:80mm; z-index:1;">
        <?= htmlspecialchars($data['importer_country'] ?? '') ?>
    </div>

    <!-- جدول المنتجات -->
    <div class="abs"
         style="top:<?= htmlspecialchars((string)($template['table_start_y'] ?? '50')) ?>mm;
                right:<?= htmlspecialchars((string)($template['table_start_x'] ?? '10')) ?>mm;
                width:170mm; z-index:1;">

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المنتج</th>
                    <th>الكمية</th>
                    <th>الوزن</th>
                    <th>بلد المنشأ</th>
                    <th>الإنتاج</th>
                    <th>الانتهاء</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rowHeight = $template['table_row_height'] ?? '12';
            $maxRows   = (int)($template['table_max_rows'] ?? 12);

            for ($i = 0; $i < $maxRows; $i++):
                $item = $items[$i] ?? null;
            ?>
                <tr style="height:<?= htmlspecialchars((string)$rowHeight) ?>mm;">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['quantity'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['net_weight'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['country_of_origin'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['production_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['expiry_date'] ?? '') ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- QR -->
    <?php if (!empty($qrPath)): ?>
    <img src="<?= htmlspecialchars($qrPath) ?>"
         class="abs"
         style="left:<?= htmlspecialchars((string)($template['qr_x'] ?? '180')) ?>mm;
                top:<?= htmlspecialchars((string)($template['qr_y'] ?? '250')) ?>mm;
                width:<?= htmlspecialchars((string)($template['qr_width'] ?? '50')) ?>mm;
                height:<?= htmlspecialchars((string)($template['qr_height'] ?? '50')) ?>mm;
                z-index:1;">
    <?php endif; ?>

    <!-- التوقيع -->
    <?php if (!empty($signaturePath)): ?>
    <img src="<?= htmlspecialchars($signaturePath) ?>"
         class="abs"
         style="left:<?= htmlspecialchars((string)($template['signature_x'] ?? '100')) ?>mm;
                top:<?= htmlspecialchars((string)($template['signature_y'] ?? '250')) ?>mm;
                width:<?= htmlspecialchars((string)($template['signature_width'] ?? '50')) ?>mm;
                height:<?= htmlspecialchars((string)($template['signature_height'] ?? '50')) ?>mm;
                z-index:1;">
    <?php endif; ?>

    <!-- الختم -->
    <?php if (!empty($stampPath)): ?>
    <img src="<?= htmlspecialchars($stampPath) ?>"
         class="abs"
         style="left:<?= htmlspecialchars((string)($template['stamp_x'] ?? '150')) ?>mm;
                top:<?= htmlspecialchars((string)($template['stamp_y'] ?? '250')) ?>mm;
                width:<?= htmlspecialchars((string)($template['stamp_width'] ?? '50')) ?>mm;
                height:<?= htmlspecialchars((string)($template['stamp_height'] ?? '50')) ?>mm;
                z-index:1;">
    <?php endif; ?>

</div>

</body>
</html>
