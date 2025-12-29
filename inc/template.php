<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <style>
        body {
            font-size: <?= htmlspecialchars($fontsize) ?>;
        }
        footer {
            position: fixed;
            bottom: -10px;
            left: 0; right: 0;
            height: 50px;
            text-align: center;
            font-size: 8pt;
            color: gray;
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
        <?php if ($breakword == 1): ?>
        #items td {
            word-wrap: break-word;
        }
        <?php endif; ?>
    </style>
</head>
<body>

<?php if ($islogo == 1 && file_exists($logo)): 
    $img_type = pathinfo($logo, PATHINFO_EXTENSION);
    $img_data = file_get_contents($logo);
    $base64 = 'data:image/' . $img_type . ';base64,' . base64_encode($img_data);

    // Valeurs par défaut si non fournies
    $max_width = !empty($logo_width) ? intval($logo_width) : 200;
    $max_height = !empty($logo_height) ? intval($logo_height) : 200;

    // Style avec maintien du ratio
    $style = "max-width: {$max_width}px; max-height: {$max_height}px; height: auto; width: auto; object-fit: contain; display: block; margin: 0 auto;";
?>
    <div style="text-align: center;">
        <img src="<?= $base64 ?>" alt="Logo" style="<?= $style ?>" />
    </div>
<?php endif; ?>

<table style="width: 100%; border: none;">
    <tr>
        <td style="height: 8mm; width: 70%;"><?= htmlspecialchars($prot_num) . '-' . date('dmY') ?></td>
        <td style="height: 8mm; width: 30%; text-align: right;"><?= htmlspecialchars($city) . ' ' . date('d.m.Y') ?></td>
    </tr>
</table>

<table style="width: 100%; border: none;">
    <tr>
        <td style="text-align: center; font-size: 15pt; height: 15mm;"><?= htmlspecialchars($title) ?></td>
    </tr>
</table>

<br>

<table>
    <tr>
        <td><?= nl2br(htmlspecialchars($upper_content)) // Sécurisé + nl2br pour les sauts de ligne ?></td>
    </tr>
</table>

<br>

<table id="items" cellspacing="0" cellpadding="0">
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

<?php 
$lp = 1;

if (!empty($number)) {
    foreach ($number as $key) {
        echo '<tr><td>' . $lp++ . '</td>';

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

<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="width:50%; border-bottom: 1px solid black;"><strong><?= __('Administrator') ?>:</strong></td>
        <td style="width:50%; border-bottom: 1px solid black;"><strong><?= __('User') ?>:</strong></td>
    </tr>
    <tr>
        <td style="width:50%; border: 1px solid black; vertical-align: top; height: 20mm;">
            <?= ($author_state == 2) ? htmlspecialchars($author_name) : htmlspecialchars($author) ?>
        </td>
        <td style="width:50%; border: 1px solid black; vertical-align: top; height: 20mm;">
            <?= htmlspecialchars($owner) ?>
        </td>
    </tr>
</table>

<footer><?= nl2br(htmlspecialchars($footer)) // Sécurisé + nl2br pour les sauts de ligne ?></footer>

</body>
</html>