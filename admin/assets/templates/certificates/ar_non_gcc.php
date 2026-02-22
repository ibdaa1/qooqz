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
    <title>شهادة صحية - دول خارج مجلس التعاون</title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #333; }
        .cert-container { border: 5px solid #444; padding: 30px; max-width: 800px; margin: auto; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .header h1 { margin: 10px 0; font-size: 24px; }
        .section-title { font-weight: bold; background: #eee; padding: 5px 10px; margin: 20px 0 10px; border-right: 5px solid #444; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .info-item { display: flex; border-bottom: 1px dotted #ccc; padding: 5px 0; }
        .label { font-weight: bold; width: 140px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th, .items-table td { border: 1px solid #444; padding: 8px; text-align: center; }
        .items-table th { background: #eee; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 200px; }
        .signature-line { border-top: 1px solid #444; margin-top: 40px; }
        
        @media print {
            body { padding: 0; }
            .cert-container { border: none; max-width: 100%; }
            @page { size: A4; margin: 1cm; }
        }
    </style>
</head>
<body>
    <div class="cert-container">
        <div class="header">
            <h1>شهادة صحية للمنتجات الغذائية</h1>
            <h3>دول خارج مجلس التعاون الخليجي (Non-GCC)</h3>
        </div>

        <div class="section-title">بيانات الشهادة</div>
        <div class="info-grid">
            <div class="info-item"><span class="label">رقم الشهادة:</span> <span><?= htmlspecialchars($data['certificate_number'] ?? '---') ?></span></div>
            <div class="info-item"><span class="label">تاريخ الإصدار:</span> <span><?= htmlspecialchars($data['issued_at'] ?? '---') ?></span></div>
        </div>

        <div class="section-title">بيانات المصدر والمستورد</div>
        <div class="info-grid">
            <div class="info-item"><span class="label">اسم المصدر:</span> <span><?= htmlspecialchars($data['exporter_name'] ?? '---') ?></span></div>
            <div class="info-item"><span class="label">اسم المستورد:</span> <span><?= htmlspecialchars($data['importer_name'] ?? '---') ?></span></div>
            <div class="info-item"><span class="label">بلد المقصد:</span> <span><?= htmlspecialchars($data['importer_country'] ?? '---') ?></span></div>
        </div>

        <div class="section-title">تفاصيل الشحنة</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المنتج</th>
                    <th>الكمية</th>
                    <th>الوزن الصافي</th>
                    <th>بلد المنشأ</th>
                    <th>تاريخ الإنتاج</th>
                    <th>تاريخ الانتهاء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): foreach($items as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($item['name'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($item['quantity'] ?? '0') ?></td>
                    <td><?= htmlspecialchars($item['net_weight'] ?? '0') ?> <?= htmlspecialchars($item['weight_unit_code'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['country_of_origin'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($item['production_date'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($item['expiry_date'] ?? '---') ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7">لا توجد بيانات متاحة</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="signature-box">
                <p>توقيع المفتش المختص</p>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <p>الختم الرسمي</p>
                <div class="signature-line"></div>
            </div>
        </div>
    </div>
</body>
</html>
