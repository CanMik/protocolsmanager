<?php
$theme_color = isset($theme_color) && is_string($theme_color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $theme_color)
    ? $theme_color
    : '#8A6428';
$header_text_color = isset($header_text_color) && is_string($header_text_color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $header_text_color)
    ? $header_text_color
    : '#FFFFFF';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <style>
        body {
            font-size: <?= htmlspecialchars($fontsize) ?>;
        }
        #document-header {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        #document-header td {
            vertical-align: bottom;
            padding: 0 3px 2mm;
        }
        #document-number, #document-date {
            border-bottom: 1px solid <?= $theme_color ?>;
            height: 8mm;
        }
        #document-number { width: 34%; text-align: left; }
        #document-logo { width: 32%; text-align: center; }
        #document-date { width: 34%; text-align: right; }
        #document-logo img {
            max-width: 100%;
            max-height: 42mm;
            height: auto;
            width: auto;
        }
        #document-title {
            width: 100%;
            margin-top: 5mm;
            text-align: center;
            font-size: 15pt;
        }
        #title-prefix { color: #111; font-weight: bold; }
        #title-owner {
            margin-top: 1mm;
            color: <?= $theme_color ?>;
            font-size: 14pt;
            font-weight: bold;
        }
        #title-owner-rule {
            width: 25mm;
            margin: 2mm auto 0;
            border-top: 1px solid <?= $theme_color ?>;
        }
        #items {
            border: 0.5px solid black;
            width: 100%;
            table-layout: auto;
            border-collapse: collapse;
        }
        #items th, #items td {
            border: 0.5px solid black;
            padding: 2px;
        }
        #items th {
            background-color: <?= $theme_color ?>;
            color: <?= $header_text_color ?>;
            font-weight: bold;
        }
        #signatures {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        #signatures td { vertical-align: top; }
        #signatures .signature-block {
            width: 46%;
            padding: 0;
            border: 1px solid <?= $theme_color ?>;
        }
        #signatures .signature-gap { width: 8%; padding: 0; }
        .signature-heading {
            padding: 4px;
            background-color: <?= $theme_color ?>;
            color: <?= $header_text_color ?>;
            font-weight: bold;
        }
        .signature-body {
            height: 20mm;
            padding: 4px;
        }
        <?php if ($breakword == 1): ?>
        #items td {
            word-wrap: break-word;
        }
        <?php endif; ?>
    </style>
</head>
<body>

<?php
$logo_html = '';
if ($islogo == 1 && file_exists($logo)):
    $img_type = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
    // Use the standard media type for JPEG files.
    if ($img_type === 'jpg') {
        $img_type = 'jpeg';
    }
    $img_data = file_get_contents($logo);
    $base64 = 'data:image/' . $img_type . ';base64,' . base64_encode($img_data);

    // Valeurs par défaut si non fournies
    $max_width = !empty($logo_width) ? intval($logo_width) : 160;
    $max_height = !empty($logo_height) ? intval($logo_height) : 160;

    // Style avec maintien du ratio
    $style = "max-width: {$max_width}px; max-height: {$max_height}px; height: auto; width: auto; object-fit: contain; display: block; margin: 0 auto;";
    $logo_html = '<img src="' . $base64 . '" alt="Logo" style="' . $style . '" />';
?>
<?php endif; ?>

<table id="document-header">
    <tr>
        <td id="document-number"><?= htmlspecialchars($prot_num) . '-' . date('dmY') ?></td>
        <td id="document-logo"><?= $logo_html ?></td>
        <td id="document-date"><?= htmlspecialchars($city) . ' ' . date('d.m.Y') ?></td>
    </tr>
</table>

<div id="document-title">
    <div id="title-prefix"><?= htmlspecialchars($title_prefix) ?></div>
    <?php if ($title_owner !== ''): ?>
        <div id="title-owner"><?= htmlspecialchars($title_owner) ?></div>
        <div id="title-owner-rule"></div>
    <?php endif; ?>
</div>

<br>

<table>
    <tr>
        <td><?= nl2br(htmlspecialchars($upper_content)) // Sécurisé + nl2br pour les sauts de ligne ?></td>
    </tr>
</table>

<br>

<table id="items" cellspacing="0" cellpadding="0">
    <thead>
    <tr>
        <th></th>
        <th><?= __('Type') ?></th>
        <th><?= __('Manufacturer') ?></th>
        <th><?= __('Model') ?></th>
        <th><?= __('Name') ?></th>
        <?php if ($serial_mode == 1): ?>
            <th><?= __('Serial number') ?></th>
            <th><?= __('Inventory number') ?></th>
        <?php else: ?>
            <th><?= __('Serial number') ?></th>
        <?php endif; ?>
        <?php if (!empty(array_filter($comments))): ?>
            <th><?= __('Comments') ?></th>
        <?php endif; ?>
    </tr>
    </thead>
    <tbody>

<?php 
$lp = 1;

if (!empty($number)) {
    foreach ($number as $key) {
        $row_background = ($lp % 2 === 0) ? '#e8e8e8' : '#fff';
        echo '<tr style="background-color: ' . $row_background . '"><td>' . $lp++ . '</td>';

        // Type
        echo '<td>' . htmlspecialchars($type_name[$key] ?? '') . '</td>';

        // Manufacturer and Model
        if (!empty($man_name[$key])) {
            echo '<td>' . htmlspecialchars($man_name[$key]) . '</td>';
            echo '<td>' . htmlspecialchars($mod_name[$key] ?? '') . '</td>';
        } else {
            echo '<td></td>';
            echo '<td>' . htmlspecialchars($mod_name[$key] ?? '') . '</td>';
        }

        // Name
        echo '<td>' . htmlspecialchars($item_name[$key] ?? '') . '</td>';

        // Serial / Inventory
        if ($serial_mode == 1) {
            echo '<td>' . htmlspecialchars($serial[$key] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($otherserial[$key] ?? '') . '</td>';
        } else {
            // serial_mode == 2
            $serial_value = $serial[$key] ?? '';
            if (empty($serial_value)) {
                $serial_value = $otherserial[$key] ?? '';
            }
            echo '<td>' . htmlspecialchars($serial_value) . '</td>';
        }

        // Comments if any
        if (!empty(array_filter($comments))) {
            echo '<td>' . htmlspecialchars($comments[$key] ?? '') . '</td>';
        }

        echo '</tr>';

    }
}
?>
    </tbody>
</table>

<br>

<table>
    <tr><td style="height: 10mm;"></td></tr>
</table>

<table>
    <tr><td><?= nl2br(htmlspecialchars($content)) // Sécurisé + nl2br pour les sauts de ligne ?></td></tr>
</table>

<table>
    <tr><td style="height: 20mm;"></td></tr>
</table>

<table id="signatures">
    <tr>
        <td class="signature-block">
            <div class="signature-heading"><?= __('Administrator') ?>:</div>
            <div class="signature-body">
                <?= ($author_state == 2) ? htmlspecialchars($author_name) : htmlspecialchars($author) ?>
            </div>
        </td>
        <td class="signature-gap"></td>
        <td class="signature-block">
            <div class="signature-heading"><?= __('User') ?>:</div>
            <div class="signature-body"><?= htmlspecialchars($owner) ?></div>
        </td>
    </tr>
</table>

</body>
</html>
