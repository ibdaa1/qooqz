<?php
/**
 * Template: English GCC Certificate
 * Version: en_gcc
 */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
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

    <!-- Official Background -->
    <img src="<?= $_SERVER['DOCUMENT_ROOT'].'/'.$template['background_image'] ?>"
         style="position:absolute; top:0; left:0; width:210mm; height:297mm;">

    <!-- Certificate Number -->
    <div class="abs" style="top:42mm; left:30mm;">
        <?= htmlspecialchars($data['certificate_number'] ?? '') ?>
    </div>

    <!-- Issue Date -->
    <div class="abs" style="top:50mm; left:30mm;">
        <?= htmlspecialchars($data['issued_at'] ?? '') ?>
    </div>

    <!-- Exporter Name -->
    <div class="abs" style="top:70mm; left:30mm; width:90mm;">
        <?= htmlspecialchars($data['exporter_name'] ?? '') ?>
    </div>

    <!-- Importer Name -->
    <div class="abs" style="top:78mm; left:30mm; width:90mm;">
        <?= htmlspecialchars($data['importer_name'] ?? '') ?>
    </div>

    <!-- Destination Country -->
    <div class="abs" style="top:86mm; left:30mm; width:90mm;">
        <?= htmlspecialchars($data['importer_country'] ?? '') ?>
    </div>

    <!-- Items Table -->
    <div class="abs"
         style="top:<?= $template['table_start_y'] ?>mm;
                left:<?= $template['table_start_x'] ?>mm;
                width:170mm;">

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Net Weight</th>
                    <th>Origin</th>
                    <th>Production</th>
                    <th>Expiry</th>
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
    </div>

    <!-- QR Code -->
    <img src="<?= $qrPath ?>"
         class="abs"
         style="left:<?= $template['qr_x'] ?>mm;
                top:<?= $template['qr_y'] ?>mm;
                width:<?= $template['qr_width'] ?>mm;
                height:<?= $template['qr_height'] ?>mm;">

    <!-- Authorized Signature -->
    <img src="<?= $signaturePath ?>"
         class="abs"
         style="left:<?= $template['signature_x'] ?>mm;
                top:<?= $template['signature_y'] ?>mm;
                width:<?= $template['signature_width'] ?>mm;
                height:<?= $template['signature_height'] ?>mm;">

    <!-- Official Stamp -->
    <img src="<?= $stampPath ?>"
         class="abs"
         style="left:<?= $template['stamp_x'] ?>mm;
                top:<?= $template['stamp_y'] ?>mm;
                width:<?= $template['stamp_width'] ?>mm;
                height:<?= $template['stamp_height'] ?>mm;">

</div>

</body>
</html>
