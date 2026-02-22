<?php
/**
 * Template: Arabic Non-GCC Certificate
 * Version: ar_non_gcc
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
    body {
        margin:0;
        padding:0;
        font-family: <?= $template['font_family'] ?>;
        font-size: <?= $template['font_size'] ?>pt;
    }

    .page {
        position: relative;
        width: 210mm;
        height: 297mm;
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
</style>
</head>
<body>

<div class="page">

    <!-- الخلفية الرسمية -->
    <img src="<?= $_SERVER['DOCUMENT_ROOT'].'/'.$template['background_image'] ?>"
         style="position:absolute; top:0; left:0; width:210mm; height:297mm;">

    <!-- رقم الشهادة -->
    <div class="abs" style="top:42mm; right:28mm;">
        <?= htmlspecialchars($data['certificate_number'] ?? '') ?>
    </div>

    <!-- تاريخ الإصدار -->
    <div class="abs" style="top:50mm; right:28mm;">
        <?= htmlspecialchars($data['issued_at'] ?? '') ?>
    </div>

    <!-- اسم المصدر -->
    <div class="abs" style="top:70mm; right:30mm; width:80mm;">
        <?= htmlspecialchars($data['exporter_name'] ?? '') ?>
    </div>

    <!-- اسم المستورد -->
    <div class="abs" style="top:78mm; right:30mm; width:80mm;">
        <?= htmlspecialchars($data['importer_name'] ?? '') ?>
    </div>

    <!-- بلد المقصد -->
    <div class="abs" style="top:86mm; right:30mm; width:80mm;">
        <?= htmlspecialchars($data['importer_country'] ?? '') ?>
    </div>

    <!-- جدول المنتجات -->
    <div class="abs"
         style="top:<?= $template['table_start_y'] ?>mm;
                right:<?= $template['table_start_x'] ?>mm;
                width:170mm;">

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
            $rowHeight = $template['table_row_height'];
            $maxRows   = $template['table_max_rows'];

            for ($i = 0; $i < $maxRows; $i++):
                $item = $items[$i] ?? null;
            ?>
                <tr style="height:<?= $rowHeight ?>mm;">
                    <td><?= $i+1 ?></td>
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
    <img src="<?= $qrPath ?>"
         class="abs"
         style="left:<?= $template['qr_x'] ?>mm;
                top:<?= $template['qr_y'] ?>mm;
                width:<?= $template['qr_width'] ?>mm;
                height:<?= $template['qr_height'] ?>mm;">

    <!-- التوقيع -->
    <img src="<?= $signaturePath ?>"
         class="abs"
         style="left:<?= $template['signature_x'] ?>mm;
                top:<?= $template['signature_y'] ?>mm;
                width:<?= $template['signature_width'] ?>mm;
                height:<?= $template['signature_height'] ?>mm;">

    <!-- الختم -->
    <img src="<?= $stampPath ?>"
         class="abs"
         style="left:<?= $template['stamp_x'] ?>mm;
                top:<?= $template['stamp_y'] ?>mm;
                width:<?= $template['stamp_width'] ?>mm;
                height:<?= $template['stamp_height'] ?>mm;">

</div>

</body>
</html>
