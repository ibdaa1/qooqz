<?php
/**
 * Template: Arabic GCC Certificate
 * Version: ar_gcc
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

    .field {
        position: absolute;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11pt;
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

    <!-- الخلفية -->
    <img src="<?= $_SERVER['DOCUMENT_ROOT'].'/'.$template['background_image'] ?>"
         style="position:absolute; top:0; left:0; width:210mm; height:297mm;">

    <!-- رقم الشهادة -->
    <div class="field"
         style="top:40mm; right:30mm;">
        <?= htmlspecialchars($data['certificate_number']) ?>
    </div>

    <!-- تاريخ الإصدار -->
    <div class="field"
         style="top:48mm; right:30mm;">
        <?= htmlspecialchars($data['issued_at']) ?>
    </div>

    <!-- جدول المنتجات -->
    <div class="field"
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

            for($i=0; $i<$maxRows; $i++):
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
         style="position:absolute;
                left:<?= $template['qr_x'] ?>mm;
                top:<?= $template['qr_y'] ?>mm;
                width:<?= $template['qr_width'] ?>mm;
                height:<?= $template['qr_height'] ?>mm;">

    <!-- التوقيع -->
    <img src="<?= $signaturePath ?>"
         style="position:absolute;
                left:<?= $template['signature_x'] ?>mm;
                top:<?= $template['signature_y'] ?>mm;
                width:<?= $template['signature_width'] ?>mm;
                height:<?= $template['signature_height'] ?>mm;">

    <!-- الختم -->
    <img src="<?= $stampPath ?>"
         style="position:absolute;
                left:<?= $template['stamp_x'] ?>mm;
                top:<?= $template['stamp_y'] ?>mm;
                width:<?= $template['stamp_width'] ?>mm;
                height:<?= $template['stamp_height'] ?>mm;">

</div>

</body>
</html>
