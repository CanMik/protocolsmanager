<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Clase principal de generación de protocolos.
 *
 * Gestiona la visualización de la pestaña en los ítems GLPI,
 * la generación del PDF (delegada a PluginProtocolsmanagerPdfBuilder,
 * que usa FPDF integrado en GLPI) y el envío de correo electrónico.
 *
 * No depende de ninguna librería externa al propio GLPI.
 *
 * @package glpi\protocolsmanager
 */
class PluginProtocolsmanagerGenerate extends CommonDBTM
{
    // -----------------------------------------------------------------------
    // Integración con el sistema de pestañas de GLPI
    // -----------------------------------------------------------------------

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        // createTabEntry(title, nb, form_itemtype_string_or_null, icon)
        // $form_itemtype must be ?string (not an object).
        // GLPI 11 accepts a 4th $icon param; GLPI 10 does not — use version check.
        if (version_compare(GLPI_VERSION, '11.0.0', '>=')) {
            return self::createTabEntry(
                __('Protocols manager', 'protocolsmanager'),
                0,
                null,
                'ti ti-file-export'
            );
        }
        return self::createTabEntry(
            __('Protocols manager', 'protocolsmanager'),
            0,
            null
        );
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum       = 1,
        $withtemplate = 0
    ): bool {
        global $CFG_GLPI;

        if (self::checkRights() !== 'w') {
            echo "<div align='center'><br>"
                . "<img src='" . htmlescape($CFG_GLPI['root_doc']) . "/pics/warning.png'><br>"
                . __('Access denied')
                . "</div>";
            return false;
        }

        $gen = new self();
        $gen->showContent($item);
        return true;
    }

    // -----------------------------------------------------------------------
    // Control de acceso
    // -----------------------------------------------------------------------

    /**
     * Devuelve el nivel de acceso ('w' | '') del usuario activo para este plugin.
     */
    public static function checkRights(): string
    {
        global $DB;

        if (!isset($_SESSION['glpiactiveprofile']['id'])) {
            return '';
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_protocolsmanager_profiles',
            'WHERE' => ['profile_id' => $_SESSION['glpiactiveprofile']['id']],
        ]);

        if ($row = $iterator->current()) {
            return (string)($row['tab_access'] ?? '');
        }

        return '';
    }

    // -----------------------------------------------------------------------
    // Datos adicionales del usuario
    // -----------------------------------------------------------------------

    /**
     * Obtiene datos extra del usuario (matrícula, título, categoría).
     * Usa Dropdown::getDropdownName de GLPI para evitar queries directas
     * a las tablas de dropdowns.
     *
     * @return array{registration_number:string, title:string, category:string}
     */
    private static function getUserExtraData(?int $user_id): array
    {
        global $DB;

        $defaults = [
            'registration_number' => '_______________',
            'title'               => '_______________',
            'category'            => '_______________',
        ];

        if (empty($user_id)) {
            return $defaults;
        }

        $iterator = $DB->request(['FROM' => 'glpi_users', 'WHERE' => ['id' => $user_id]]);
        $row      = $iterator->current();
        if (!$row) {
            return $defaults;
        }

        $data = $defaults;

        if (!empty($row['registration_number'])) {
            $data['registration_number'] = $row['registration_number'];
        }

        if (!empty($row['usertitles_id'])) {
            $v = Dropdown::getDropdownName('glpi_usertitles', $row['usertitles_id']);
            if (!empty($v) && $v !== '&nbsp;') {
                $data['title'] = $v;
            }
        }

        if (!empty($row['usercategories_id'])) {
            $v = Dropdown::getDropdownName('glpi_usercategories', $row['usercategories_id']);
            if (!empty($v) && $v !== '&nbsp;') {
                $data['category'] = $v;
            }
        }

        return $data;
    }

    // -----------------------------------------------------------------------
    // Pantalla de la pestaña
    // -----------------------------------------------------------------------

    public function showContent(CommonGLPI $item): bool
    {
        global $DB, $CFG_GLPI;

        if (get_class($item) !== 'User') {
            if (!empty($item->id) && !empty($item->fields['users_id'])) {
                $uid  = $item->fields['users_id'];
                $item = new User();
                $item->getFromDB($uid);
            }
        }

        $id   = (int)$item->getField('id');
        $rand = mt_rand();

        // Owner y Author resueltos una sola vez antes de cualquier bucle
        $Owner = new User();
        $Owner->getFromDB($id);
        $owner = $Owner->getFriendlyName();

        $Author = new User();
        $Author->getFromDB(Session::getLoginUserID());
        $author = $Author->getFriendlyName();

        $type_user  = $CFG_GLPI['linkuser_types'] ?? [];
        $field_user = 'users_id';
        $counter    = 0;

        // Normalise: ensure Phone, Monitor, NetworkEquipment etc. are included even
        // when GLPI strips them from linkuser_types in certain configurations.
        $alwaysInclude = ['Computer', 'Monitor', 'Phone', 'Printer', 'Peripheral', 'NetworkEquipment'];
        foreach ($alwaysInclude as $extra) {
            if (!in_array($extra, $type_user, true)) {
                $type_user[] = $extra;
            }
        }

        // Formulario principal
        echo '<br>';
        echo "<form method='post' name='user_field{$rand}' id='user_field{$rand}'"
            . " action='" . htmlescape($CFG_GLPI['root_doc'])
            . "/plugins/protocolsmanager/front/generate.form.php'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        // ── Template selector + Create (GLPI card) ───────────────────
        echo "<div class='card mb-3'>";
        echo "<div class='card-header d-flex align-items-center gap-2'>"
            . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-file-export fs-4'></i></span>"
            . "<h3 class='card-title mb-0'>" . __('Equipment report', 'protocolsmanager') . "</h3>"
            . "</div>";
        echo "<div class='card-body py-2'>";
        echo "<div class='row g-2 align-items-center'>";
        echo "<div class='col-auto d-flex align-items-center gap-2'>"
            . "<label class='form-label mb-0 fw-bold'>"
            . "<i class='ti ti-template me-1'></i>" . __('Template', 'protocolsmanager') . "</label>"
            . "<select required name='list' class='form-select form-select-sm' style='min-width:180px;'>";

        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => 'glpi_plugin_protocolsmanager_config']) as $list) {
            echo '<option value="' . htmlescape($list['id']) . '">' . htmlescape($list['name']) . '</option>';
        }

        echo "</select></div>";
        echo "<div class='col'>"
            . "<input type='text' name='notes' class='form-control form-control-sm' placeholder='" . __('Comment', 'protocolsmanager') . "'>";
        echo "</div>";
        echo "<div class='col-auto'>"
            . "<button type='submit' name='generate' class='btn btn-primary'>"
            . "<i class='ti ti-file-plus me-1'></i>" . __('Create') . "</button>";
        echo "</div></div>";
        echo "</div></div>"; // card-body + card

        // ── Equipment table (GLPI card) ────────────────────────────────
        echo "<div class='card mb-3'>";
        echo "<div class='card-header d-flex align-items-center gap-2'>"
            . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-devices fs-4'></i></span>"
            . "<h3 class='card-title mb-0'>" . __('Equipment', 'protocolsmanager') . "</h3>"
            . "</div>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-vcenter card-table' id='additional_table'>"
            . "<thead><tr>"
            . "<th width='10'><input type='checkbox' class='checkall' style='height:16px;width:16px;'></th>"
            . "<th class='center'>" . __('Type')             . "</th>"
            . "<th class='center'>" . __('Manufacturer')     . "</th>"
            . "<th class='center'>" . __('Model')            . "</th>"
            . "<th class='center'>" . __('Name')             . "</th>"
            . "<th class='center'>" . __('State')            . "</th>"
            . "<th class='center'>" . __('Serial number')    . "</th>"
            . "<th class='center'>" . __('Inventory number') . "</th>"
            . "<th class='center'>" . __('Comments')         . "</th>"
            . "</tr></thead><tbody>";

        foreach ($type_user as $itemtype) {
            $itemObj = getItemForItemtype($itemtype);
            if (!$itemObj || !$itemObj->canView()) {
                continue;
            }

            $itemtable = getTableForItemType($itemtype);
            $criteria  = ['FROM' => $itemtable, 'WHERE' => [$field_user => $id]];

            if ($itemObj->maybeTemplate()) {
                $criteria['WHERE']['is_template'] = 0;
            }
            if ($itemObj->maybeDeleted()) {
                $criteria['WHERE']['is_deleted'] = 0;
            }

            $type_name = $itemObj->getTypeName();

            foreach ($DB->request($criteria) as $data) {
                $cansee   = $itemObj->can($data['id'], READ);
                $linkName = !empty($data['name']) ? $data['name'] : $data['id'];

                if ($cansee) {
                    $linkUrl = $itemObj::getFormURLWithID($data['id']);
                    if ($_SESSION['glpiis_ids_visible'] || empty($data['name'])) {
                        $linkName = sprintf(__('%1$s (%2$s)'), $linkName, $data['id']);
                    }
                    $link = "<a href='" . htmlescape($linkUrl) . "'>" . htmlescape($linkName) . '</a>';
                } else {
                    $link = htmlescape($linkName);
                }

                $man_name = '';
                if (!empty($data['manufacturers_id'])) {
                    $raw = Dropdown::getDropdownName('glpi_manufacturers', $data['manufacturers_id']);
                    $man_name = trim($raw);
                }

                $mod_name = '';
                foreach (['computer', 'phone', 'monitor', 'networkequipment', 'printer', 'peripheral'] as $prefix) {
                    if (!empty($data[$prefix . 'models_id'])) {
                        $mod_name = Dropdown::getDropdownName('glpi_' . $prefix . 'models', $data[$prefix . 'models_id']);
                        break;
                    }
                }

                $sta_name = '';
                if (!empty($data['states_id'])) {
                    $sta_name = Dropdown::getDropdownName('glpi_states', $data['states_id']);
                }

                $serial      = $data['serial']      ?? '';
                $otherserial = $data['otherserial'] ?? '';
                $item_name   = $data['name']         ?? '';
                $ids         = $data['id']            ?? '';

                echo "<tr class='tab_bg_1'>"
                    . "<td width='10'><input type='checkbox' name='number[" . $counter . "]' value='" . htmlescape($counter) . "' class='child' style='height:16px;width:16px;'></td>"
                    . "<td class='center'>" . htmlescape($type_name) . "</td>"
                    . "<td class='center'>" . ($man_name ? htmlescape($man_name) : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($mod_name ? htmlescape($mod_name) : '&mdash;') . "</td>"
                    . "<td class='center'>" . $link . "</td>"
                    . "<td class='center'>" . ($sta_name ? htmlescape($sta_name) : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($serial      ? htmlescape($serial)      : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($otherserial ? htmlescape($otherserial) : '&mdash;') . "</td>"
                    . "<input type='hidden' name='classes[" . $counter . "]' value='" . htmlescape($itemtype) . "'>"
                    . "<input type='hidden' name='ids[" . $counter . "]'     value='" . htmlescape($ids)      . "'>"

                    . "<input type='hidden' name='type_name[" . $counter . "]' value='" . htmlescape($type_name)   . "'>"
                    . "<input type='hidden' name='man_name[" . $counter . "]'  value='" . htmlescape($man_name)    . "'>"
                    . "<input type='hidden' name='mod_name[" . $counter . "]'  value='" . htmlescape($mod_name)    . "'>"
                    . "<input type='hidden' name='serial[" . $counter . "]'    value='" . htmlescape($serial)      . "'>"
                    . "<input type='hidden' name='otherserial[" . $counter . "]' value='" . htmlescape($otherserial) . "'>"
                    . "<input type='hidden' name='item_name[" . $counter . "]' value='" . htmlescape($item_name)   . "'>"
                    . "<input type='hidden' name='user_id'                    value='" . htmlescape($id)          . "'>"
                    . "<input type='hidden' name='states_id[" . $counter . "]' value='" . htmlescape($data['states_id'] ?? 0) . "'>"
                    . "<td class='center'><input type='text' name='comments[" . $counter . "]'></td>"
                    . "</tr>";

                $counter++;
            }
        }

        // Bloque de Assets (GLPI 11 — tabla dinámica)
        if ($DB->tableExists('glpi_assets_assets')) {
            $assetIterator = $DB->request([
                'FROM'  => 'glpi_assets_assets',
                'WHERE' => ['users_id' => $id, 'is_deleted' => 0, 'is_template' => 0],
            ]);

            foreach ($assetIterator as $data) {
                $def_name = '';
                if (!empty($data['assets_assetdefinitions_id'])) {
                    $def_name = Dropdown::getDropdownName('glpi_assets_assetdefinitions', $data['assets_assetdefinitions_id']);
                }

                $man_name = '';
                if (!empty($data['manufacturers_id'])) {
                    $raw = Dropdown::getDropdownName('glpi_manufacturers', $data['manufacturers_id']);
                    $man_name = trim($raw);
                }

                $mod_name = '';
                if (!empty($data['assets_assetmodels_id'])) {
                    $mod_name = Dropdown::getDropdownName('glpi_assets_assetmodels', $data['assets_assetmodels_id']);
                }

                $sta_name = '';
                if (!empty($data['states_id'])) {
                    $sta_name = Dropdown::getDropdownName('glpi_states', $data['states_id']);
                }

                $item_name   = $data['name']        ?? '';
                $serial      = $data['serial']      ?? '';
                $otherserial = $data['otherserial'] ?? '';

                echo "<tr class='tab_bg_1'>"
                    . "<td width='10'><input type='checkbox' name='number[" . $counter . "]' value='" . htmlescape($counter) . "' class='child' style='height:16px;width:16px;'></td>"
                    . "<td class='center'>" . htmlescape($def_name ?: __('Asset', 'protocolsmanager')) . "</td>"
                    . "<td class='center'>" . ($man_name  ? htmlescape($man_name)  : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($mod_name  ? htmlescape($mod_name)  : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($item_name ? htmlescape($item_name) : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($sta_name  ? htmlescape($sta_name)  : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($serial      ? htmlescape($serial)      : '&mdash;') . "</td>"
                    . "<td class='center'>" . ($otherserial ? htmlescape($otherserial) : '&mdash;') . "</td>"
                    . "<input type='hidden' name='classes[" . $counter . "]' value=''>" // glpi_assets_assets: skip documents_items link (dynamic type)
                    . "<input type='hidden' name='ids[" . $counter . "]'     value='" . htmlescape($data['id']) . "'>"

                    . "<input type='hidden' name='type_name[" . $counter . "]' value='" . htmlescape($def_name)   . "'>"
                    . "<input type='hidden' name='man_name[" . $counter . "]'  value='" . htmlescape($man_name)   . "'>"
                    . "<input type='hidden' name='mod_name[" . $counter . "]'  value='" . htmlescape($mod_name)   . "'>"
                    . "<input type='hidden' name='serial[" . $counter . "]'    value='" . htmlescape($serial)     . "'>"
                    . "<input type='hidden' name='otherserial[" . $counter . "]' value='" . htmlescape($otherserial) . "'>"
                    . "<input type='hidden' name='item_name[" . $counter . "]' value='" . htmlescape($item_name)  . "'>"
                    . "<input type='hidden' name='user_id'                    value='" . htmlescape($id)         . "'>"
                    . "<input type='hidden' name='states_id[" . $counter . "]' value='" . htmlescape($data['states_id'] ?? 0) . "'>"
                    . "<td class='center'><input type='text' name='comments[" . $counter . "]'></td>"
                    . "</tr>";

                $counter++;
            }
        }

        echo '</tbody></table></div></div>'; // table-responsive + card
        Html::closeForm();

        // Modal de envío de email
        echo '<div class="modal fade" id="motus" role="dialog">'
            . '<div class="modal-dialog"><div class="modal-content">'
            . '<div class="modal-header">'
            . '<h4 class="modal-title">' . __('Send email', 'protocolsmanager') . '</h4>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . __('Close') . '"></button>'
            . '</div><div class="modal-body">'
            . '<p>' . __('Select recipients from template or enter manually to send email', 'protocolsmanager') . '</p><br>'
            . '<form method="post" action="' . htmlescape($CFG_GLPI['root_doc']) . '/plugins/protocolsmanager/front/generate.form.php">'
            . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()])
            . '<input type="hidden" id="dialogVal" name="doc_id" value="">'
            . '<input type="radio" name="send_type" id="manually" class="send_type" value="1">'
            . '<b> ' . __('Enter recipients manually', 'protocolsmanager') . ' </b><br><br>'
            . '<textarea style="width:90%;height:30px" name="em_list" class="man_recs" placeholder="' . __('Recipients (use ; to separate emails)', 'protocolsmanager') . '"></textarea><br><br>'
            . '<input type="text" style="width:90%" name="email_subject" class="man_recs" placeholder="' . __('Subject', 'protocolsmanager') . '"><br><br>'
            . '<textarea style="width:90%;height:80px" name="email_content" class="man_recs" placeholder="' . __('Content') . '"></textarea><br><br>'
            . '<input type="radio" name="send_type" id="auto" class="send_type" value="2">'
            . '<b> ' . __('Select recipients from template', 'protocolsmanager') . '</b><br><br>'
            . '<select name="e_list" id="auto_recs" disabled="disabled" style="font-size:14px;width:95%">';

        foreach ($DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig']) as $list) {
            echo '<option value="'
                . htmlescape($list['recipients'] . '|' . $list['email_subject'] . '|' . $list['email_content'] . '|' . $list['send_user'])
                . '">'
                . htmlescape($list['tname'] . ' - ' . $list['recipients'])
                . '</option>';
        }

        echo '</select><br><br>'
            . '<button type="submit" name="send" class="btn btn-primary">'
            . '<i class="ti ti-send me-1"></i>' . __('Send') . '</button>'
            . '<input type="hidden" name="user_id" value="' . htmlescape($id)     . '">'
            . Html::closeForm(false)
            . '</div></div></div></div>';

        // ── Add custom row button ──────────────────────────────────────
        echo "<div class='mb-2'>";
        echo "<button class='addNewRow btn btn-outline-secondary' id='addNewRow' type='button'>"
            . "<i class='ti ti-plus me-1'></i>" . __('Add Custom Fields', 'protocolsmanager')
            . "</button>";
        echo "</div>";

        // ── Generated documents table (GLPI card) ────────────────────
        echo "<div class='card mt-3'>";
        echo "<form method='post' name='docs_form' action='"
            . $CFG_GLPI['root_doc'] . "/plugins/protocolsmanager/front/generate.form.php'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<div class='card-header d-flex align-items-center gap-2'>"
            . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-files fs-4'></i></span>"
            . "<h3 class='card-title mb-0 flex-fill'>" . __('Generated documents', 'protocolsmanager') . "</h3>"
            . "<label class='d-flex align-items-center gap-1 me-2 text-muted small' title='" . __('Select all', 'protocolsmanager') . "'>"
            . "<input type='checkbox' class='checkalldoc form-check-input m-0'>"
            . "<span>" . __('Select all', 'protocolsmanager') . "</span></label>"
            . "<button type='submit' name='delete' class='btn btn-danger'"
            . " onclick=\"return confirm('" . addslashes(__('Delete selected documents?', 'protocolsmanager')) . "')\">"
            . "<i class='ti ti-trash me-1'></i>" . __('Delete') . "</button>"
            . "</div>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-vcenter card-table' id='myTable'>";
        echo "<thead><tr>"
            . "<th width='10'></th>"
            . "<th>" . __('Name')       . "</th>"
            . "<th>" . __('Type')       . "</th>"
            . "<th>" . __('Date')       . "</th>"
            . "<th>" . __('File')       . "</th>"
            . "<th>" . __('Creator')    . "</th>"
            . "<th>" . __('Comment')    . "</th>"
            . "<th>" . __('Send email', 'protocolsmanager') . "</th></tr></thead><tbody>";

        $pm_per_page = 20;
        $pm_total    = self::countDocsForUser($id);
        $pm_start    = max(0, (int)($_GET['pm_start'] ?? 0));
        if ($pm_total > 0) {
            $pm_start = min($pm_start, $pm_total - 1);
        }
        self::getAllForUser($id, $pm_start, $pm_per_page, $pm_total);

        echo '</tbody></table></div></div>'; // table-responsive + card
        Html::closeForm();

        // Emit GLPI states as a JSON variable for the addNewRow JS helper.
        // This must run inside showContent() where $DB is already in scope.
        $pmStatesList = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => 'glpi_states', 'ORDER' => 'name ASC']) as $st) {
            $pmStatesList[] = ['id' => (int)$st['id'], 'name' => $st['name']];
        }
        $pmStatesJson = json_encode($pmStatesList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo "<script>\nvar pmStatesOptions = " . $pmStatesJson . ";\n</script>\n";

        return true;
    }

    // -----------------------------------------------------------------------
    // Listado de documentos del usuario
    // -----------------------------------------------------------------------

    /** Returns total document count for a user (used for pagination). */
    public static function countDocsForUser(int $id): int
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => [new \QueryExpression('COUNT(*) AS cnt')],
            'FROM'   => 'glpi_plugin_protocolsmanager_protocols',
            'WHERE'  => ['user_id' => $id],
        ])->current();
        return $row ? (int)$row['cnt'] : 0;
    }

    /**
     * Renders document rows for a user with LIMIT/OFFSET pagination.
     *
     * @param int $id       User ID
     * @param int $start    Row offset (0-based)
     * @param int $perPage  Rows per page
     * @param int $total    Total rows (for pager rendering)
     */
    public static function getAllForUser(int $id, int $start = 0, int $perPage = 20, int $total = 0): void
    {
        global $DB, $CFG_GLPI;

        // Render GLPI-style pager above the rows if there is more than one page.
        if ($total > $perPage) {
            $selfUrl = $CFG_GLPI['root_doc'] . '/front/user.form.php?id=' . $id;
            Html::printPager($start, $total, $selfUrl, 'pm_start', null, $perPage);
        }

        $iterator = $DB->request([
            'FROM'   => 'glpi_plugin_protocolsmanager_protocols',
            'WHERE'  => ['user_id' => $id],
            'ORDER'  => 'gen_date DESC',
            'LIMIT'  => $perPage,
            'START'  => $start,
        ]);

        foreach ($iterator as $exports) {
            $Doc      = new Document();
            $docFound = $Doc->getFromDB($exports['document_id']);

            echo "<tr class='tab_bg_1'>"
                . "<td class='center'><input type='checkbox' name='docnumber[]' value='"
                . htmlescape($exports['document_id'])
                . "' class='docchild' style='height:15px;width:15px; cursor:pointer;'></td>"
                . "<td class='center'>" . ($docFound ? $Doc->getLink() : __('Document not found', 'protocolsmanager')) . "</td>"
                . "<td class='center'>" . htmlescape($exports['document_type']) . "</td>"
                . "<td class='center'>" . htmlescape($exports['gen_date'])      . "</td>"
                . "<td class='center'>" . ($Doc->fields ? $Doc->getDownloadLink() : '') . "</td>"
                . "<td class='center'>" . htmlescape($exports['author'])        . "</td>"
                . "<td class='center'>" . ($Doc->fields ? htmlescape($Doc->getField('comment')) : '') . "</td>"
                . "<td class='center'><button type='button' class='btn btn-success send-email-btn'"
                . " data-docid='" . htmlescape($exports['document_id']) . "'"
                . " data-bs-toggle='modal' data-bs-target='#motus'>"
                . "<i class='ti ti-mail me-1'></i>"
                . __('Send email', 'protocolsmanager') . "</button></td>"
                . "</tr>";
        }

        // Render pager below rows too if multi-page.
        if ($total > $perPage) {
            $selfUrl = $CFG_GLPI['root_doc'] . '/front/user.form.php?id=' . $id;
            Html::printPager($start, $total, $selfUrl, 'pm_start', null, $perPage);
        }
    }

    // -----------------------------------------------------------------------
    // Generación del PDF
    // -----------------------------------------------------------------------

    /**
     * Genera un protocolo PDF a partir de los datos en $_POST.
     *
     * Flujo:
     *  1. Recoge datos del formulario
     *  2. Carga configuración de plantilla de BD
     *  3. Aplica sustituciones de texto ({owner}, {admin}, etc.)
     *  4. Genera PDF via PluginProtocolsmanagerPdfBuilder (FPDF / GLPI)
     *  5. Guarda archivo en GLPI_UPLOAD_DIR y crea Document GLPI
     *  6. Registra protocolo en la tabla del plugin
     *  7. Envía email automático si email_mode = 1
     */
    public static function makeProtocol(): void
    {
        global $DB;

        // 1. Recoger datos del POST
        // $id and $notes first — owner resolution depends on $id.
        $id          = (int)($_POST['user_id'] ?? 0);
        $doc_no      = (int)($_POST['list']    ?? 0);
        $notes       = $_POST['notes']         ?? '';
        $number      = $_POST['number']        ?? [];
        $type_name   = $_POST['type_name']     ?? [];
        $man_name    = $_POST['man_name']       ?? [];
        $mod_name    = $_POST['mod_name']       ?? [];
        $serial      = $_POST['serial']         ?? [];
        $otherserial = $_POST['otherserial']    ?? [];
        $item_name   = $_POST['item_name']      ?? [];
        $comments    = $_POST['comments']       ?? [];
        $states_id   = $_POST['states_id']      ?? []; // for state name resolution
        // Resolve owner and author server-side — never trust POST for display names.
        $ownerUser  = new User();
        $ownerUser->getFromDB($id);
        $owner  = $ownerUser->getFriendlyName();
        $authorUser = new User();
        $authorUser->getFromDB(Session::getLoginUserID());
        $author = $authorUser->getFriendlyName();

        // Guard: require at least one item selected.
        if (empty($number) || !is_array($number)) {
            Session::addMessageAfterRedirect(
                __('Please select at least one item.', 'protocolsmanager'),
                false,
                WARNING
            );
            return;
        }

        // Resolve states_id → state name for each row
        $state_name_map = [];
        foreach ($states_id as $idx => $sid) {
            $sid = (int)$sid;
            if ($sid > 0) {
                $state_name_map[$idx] = Dropdown::getDropdownName('glpi_states', $sid);
            } else {
                $state_name_map[$idx] = '';
            }
        }

        // 2. Datos extra del usuario
        $userExtra           = self::getUserExtraData($id);
        $registration_number = $userExtra['registration_number'];
        $usertitle_name      = $userExtra['title'];
        $usercategory_name   = $userExtra['category'];

        // 3. Cargar configuración de plantilla
        $req = $DB->request([
            'FROM'  => 'glpi_plugin_protocolsmanager_config',
            'WHERE' => ['id' => $doc_no],
        ]);

        if (!($row = $req->current())) {
            Session::addMessageAfterRedirect(
                __('Template not found.', 'protocolsmanager'),
                false,
                ERROR
            );
            return;
        }

        $content         = $row['content']         ?? '';
        $upper_content   = $row['upper_content']   ?? '';
        $footer          = $row['footer']           ?? '';
        $title           = $row['title']            ?? '';
        $title_template  = $row['name']             ?? '';
        $full_img_name   = $row['logo']             ?? '';
        $font            = !empty($row['font'])     ? $row['font']     : 'Helvetica';
        $fontsize        = !empty($row['fontsize']) ? $row['fontsize'] : '9';
        $city            = $row['city']             ?? '';
        $orientation     = $row['orientation']      ?? 'Portrait';
        $email_mode      = (int)($row['email_mode']      ?? 0);
        $email_template  = (int)($row['email_template']  ?? 0);
        $serial_mode     = (int)($row['serial_mode']     ?? 1);
        $author_state    = (int)($row['author_state']    ?? 1);
        $author_name     = $row['author_name']      ?? '';
        $date_format     = (int)($row['date_format']     ?? 0);
        $logo_width      = $row['logo_width']       ?? null;
        $logo_height     = $row['logo_height']      ?? null;

        // 4. Aplicar sustituciones de texto
        // {items_table} se elimina: la tabla se renderiza nativamente en el PDF
        $replacements = [
            '{cur_date}'    => date('d.m.Y'),
            '{owner}'       => $owner,
            '{admin}'       => $author,
            '{reg_num}'     => $registration_number,
            '{title}'       => $usertitle_name,
            '{category}'    => $usercategory_name,
            '{items_table}' => '',
        ];

        $title = str_replace('{owner}', $owner, $title);

        foreach ($replacements as $key => $val) {
            $content       = str_replace($key, $val, $content);
            $upper_content = str_replace($key, $val, $upper_content);
        }

        // 5. Configuración de email
        $email_content = '';
        $email_subject = '';
        $recipients    = '';
        $send_user     = 0;

        if ($email_template > 0) {
            $req2 = $DB->request([
                'FROM'  => 'glpi_plugin_protocolsmanager_emailconfig',
                'WHERE' => ['id' => $email_template],
            ]);
            if ($row2 = $req2->current()) {
                $send_user     = (int)($row2['send_user']     ?? 0);
                $email_subject = $row2['email_subject']       ?? '';
                $email_content = $row2['email_content']       ?? '';
                $recipients    = $row2['recipients']          ?? '';
            }
        }

        foreach (['{owner}' => $owner, '{admin}' => $author, '{cur_date}' => date('d.m.Y')] as $k => $v) {
            $email_content = str_replace($k, $v, $email_content);
            $email_subject = str_replace($k, $v, $email_subject);
        }

        // 6a. Reserve protocol row to obtain a unique, race-free folio number.
        $gen_date    = date('Y-m-d H:i:s');
        $DB->insert('glpi_plugin_protocolsmanager_protocols', [
            'name'          => 'pending',
            'gen_date'      => $gen_date,
            'author'        => $author,
            'user_id'       => $id,
            'document_id'   => 0,
            'document_type' => $title_template,
        ]);
        $protocol_id = $DB->insertId();
        $prot_num    = $protocol_id; // guaranteed unique — no race condition

        // 6b. Generar PDF usando TCPDF integrado en GLPI (via Composer)
        $logo = !empty($full_img_name) ? GLPI_PICTURE_DIR . '/' . $full_img_name : '';

        try {
        $builder = new PluginProtocolsmanagerPdfBuilder();
        $builder->setBaseFont($font, (int)$fontsize);
        $builder->setFooterContent($footer);

        $pdfContent = $builder->generate([
            'orientation'   => $orientation,
            'logo'          => $logo,
            'logo_width'    => $logo_width,
            'logo_height'   => $logo_height,
            'prot_num'      => $prot_num,
            'city'          => $city,
            'title'         => $title,
            'upper_content' => $upper_content,
            'content'       => $content,
            'serial_mode'   => $serial_mode,
            'author_state'  => $author_state,
            'author_name'   => $author_name,
            'author'        => $author,
            'date_format'   => $date_format,
            'owner'         => $owner,
            'number'        => $number,
            'type_name'     => $type_name,
            'man_name'      => $man_name,
            'mod_name'      => $mod_name,
            'serial'        => $serial,
            'otherserial'   => $otherserial,
            'item_name'     => $item_name,
            'comments'      => $comments,
            'state_name'    => $state_name_map,
        ]);

        // 7. Guardar archivo
        // Sanitize: keep only safe chars, append folio+timestamp for uniqueness.
        $safe_title = preg_replace('/[^\w\-]/u', '_', str_replace(' ', '_', $title));
        $safe_title = trim($safe_title, '_');
        $doc_name   = $safe_title . '_' . $prot_num . '-' . date('dmYHis') . '.pdf';
        $written = file_put_contents(GLPI_UPLOAD_DIR . '/' . $doc_name, $pdfContent);
        if ($written === false) {
            throw new \RuntimeException('Failed to write PDF to disk: ' . GLPI_UPLOAD_DIR . '/' . $doc_name);
        }

        $doc_id   = self::createDoc($doc_name, $owner, $notes, $title, $id);

        } catch (\Throwable $e) {
            // PDF generation or disk write failed — remove the reserved row so
            // the folio number is not wasted and the list stays clean.
            $DB->delete('glpi_plugin_protocolsmanager_protocols', ['id' => $protocol_id]);
            Session::addMessageAfterRedirect(
                __('Failed to generate document. Please try again.', 'protocolsmanager'),
                false,
                ERROR
            );
            return;
        }

        if ($email_mode === 1) {
            self::sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id);
        }

        // 8. Actualizar fila reservada del protocolo con datos definitivos.
        $DB->update('glpi_plugin_protocolsmanager_protocols', [
            'name'        => $doc_name,
            'document_id' => $doc_id,
        ], ['id' => $protocol_id]);

        $DB->update('glpi_documents', [
            'users_id' => $id,
            'name'     => $doc_name,
            'comment'  => $notes,
        ], ['id' => $doc_id]);

        // Vincular usuario al documento
        $DB->insert('glpi_documents_items', [
            'documents_id'  => $doc_id,
            'items_id'      => $id,
            'itemtype'      => 'User',
            'users_id'      => $id,
            'date_creation' => $gen_date,
            'date_mod'      => $gen_date,
            'date'          => $gen_date,
        ]);

        // Vincular cada ítem seleccionado
        if (is_array($number)) {
            foreach ($number as $itms) {
                $class = !empty($_POST['classes'][$itms]) ? $_POST['classes'][$itms] : null;
                $it    = !empty($_POST['ids'][$itms])     ? (int)$_POST['ids'][$itms] : null;
                if ($class !== null && $it !== null) {
                    $DB->insert('glpi_documents_items', [
                        'documents_id'  => $doc_id,
                        'items_id'      => $it,
                        'itemtype'      => $class,
                        'users_id'      => $id,
                        'date_creation' => $gen_date,
                        'date_mod'      => $gen_date,
                        'date'          => $gen_date,
                    ]);
                }
            }
        }
    }

    // -----------------------------------------------------------------------
    // Helpers de documento GLPI
    // -----------------------------------------------------------------------

    public static function createDoc(
        string $doc_name,
        string $owner,
        string $notes,
        string $title,
        int    $id
    ): int {
        global $DB;

        $entity  = Session::getActiveEntity();
        $userRow = $DB->request(['FROM' => 'glpi_users', 'WHERE' => ['id' => $id]])->current();
        if ($userRow) {
            $candidate = (int)$userRow['entities_id'];
            if (Session::haveAccessToEntity($candidate)) {
                $entity = $candidate;
            }
        }

        $doc_cat_id = 0;
        $catRow = $DB->request(['FROM' => 'glpi_documentcategories', 'WHERE' => ['name' => $title]])->current();
        if ($catRow) {
            $doc_cat_id = (int)$catRow['id'];
        }

        $doc   = new Document();
        $input = [
            'entities_id'           => $entity,
            'name'                  => date('mdY_Hi'),
            'upload_file'           => $doc_name,
            'documentcategories_id' => $doc_cat_id,
            'mime'                  => 'application/pdf',
            'date_mod'              => date('Y-m-d H:i:s'),
            'users_id'              => Session::getLoginUserID(),
            'comment'               => $notes,
        ];

        $doc->check(-1, CREATE, $input);
        return (int)$doc->add($input);
    }

    // -----------------------------------------------------------------------
    // Eliminación de documentos
    // -----------------------------------------------------------------------

    /**
     * Elimina completamente los documentos seleccionados:
     *  1. Obtiene la ruta del archivo físico ANTES de borrar el registro.
     *  2. Elimina el registro del plugin (glpi_plugin_protocolsmanager_protocols).
     *  3. Elimina todos los vínculos del documento (glpi_documents_items).
     *  4. Borra el archivo físico del filesystem de GLPI.
     *  5. Hard-delete del Document de GLPI mediante delete($input, $force=true).
     *
     * NOTA: CommonDBTM no tiene un método purge() público.
     * El hard-delete (bypass de papelera) en GLPI se realiza pasando true
     * como segundo argumento a delete(): delete(['id' => $id], true).
     */
    public static function deleteDocs(): void
    {
        global $DB;

        if (!isset($_POST['docnumber']) || !is_array($_POST['docnumber'])) {
            return;
        }

        foreach ($_POST['docnumber'] as $raw_key) {
            $doc_id = (int)$raw_key;
            if ($doc_id <= 0) {
                continue;
            }

            // 1. Recuperar ruta del archivo físico antes de borrar nada
            $docRow = $DB->request([
                'SELECT' => ['filepath', 'filename'],
                'FROM'   => 'glpi_documents',
                'WHERE'  => ['id' => $doc_id],
            ])->current();

            // 2. Borrar registro del plugin
            $DB->delete('glpi_plugin_protocolsmanager_protocols', ['document_id' => $doc_id]);

            // 3. Borrar vínculos documento ↔ ítems
            $DB->delete('glpi_documents_items', ['documents_id' => $doc_id]);

            // 4. Borrar archivo físico del disco
            if ($docRow && !empty($docRow['filepath'])) {
                $fullpath = GLPI_VAR_DIR . '/' . $docRow['filepath'];
                if (file_exists($fullpath)) {
                    @unlink($fullpath);
                }
                // Borrar también de GLPI_UPLOAD_DIR si existe copia allí
                if (!empty($docRow['filename'])) {
                    $uploadPath = GLPI_UPLOAD_DIR . '/' . $docRow['filename'];
                    if (file_exists($uploadPath)) {
                        @unlink($uploadPath);
                    }
                }
            }

            // 5. Hard-delete del registro en glpi_documents
            // delete($input, $force=true) salta la papelera y elimina definitivamente.
            // purge() no existe como método público en CommonDBTM de GLPI 10/11.
            $doc = new Document();
            $doc->delete(['id' => $doc_id], true);
        }
    }

    // -----------------------------------------------------------------------
    // Envío de correo (GLPIMailer — nativo GLPI)
    // -----------------------------------------------------------------------

    /** Envío automático al generar (email_mode = 1). */
    public static function sendMail(
        int    $doc_id,
        int    $send_user,
        string $email_subject,
        string $email_content,
        string $recipients,
        int    $id
    ): bool {
        global $CFG_GLPI, $DB;

        $nmail = new GLPIMailer();
        $nmail->SetFrom($CFG_GLPI['admin_email'], $CFG_GLPI['admin_email_name'] ?? '', false);

        $docRow = $DB->request(['FROM' => 'glpi_documents', 'WHERE' => ['id' => $doc_id]])->current();
        if ($docRow && file_exists(GLPI_VAR_DIR . '/' . $docRow['filepath'])) {
            $nmail->addAttachment(GLPI_VAR_DIR . '/' . $docRow['filepath'], $docRow['filename']);
        }

        $addressCount = 0;
        if ($send_user === 1) {
            $emailRow = $DB->request([
                'FROM'  => 'glpi_useremails',
                'WHERE' => ['users_id' => $id, 'is_default' => 1],
            ])->current();
            if ($emailRow && !empty($emailRow['email'])) {
                $nmail->AddAddress($emailRow['email'], '');
                $addressCount++;
            }
        }

        foreach (explode(';', $recipients) as $r) {
            $r = trim($r);
            if (!empty($r)) {
                $nmail->AddAddress($r, '');
                $addressCount++;
            }
        }

        if ($addressCount === 0) {
            Session::addMessageAfterRedirect(__('No recipients specified. Email not sent.', 'protocolsmanager'), false, WARNING);
            return false;
        }

        $nmail->IsHtml(true);
        $nmail->Subject = $email_subject;
        $nmail->Body    = nl2br($email_content);

        if (!$nmail->Send()) {
            Session::addMessageAfterRedirect(__('Failed to send email', 'protocolsmanager'), false, ERROR);
            return false;
        }

        Session::addMessageAfterRedirect(__('Email sent', 'protocolsmanager') . ' to ' . $recipients);
        return true;
    }

    /** Envío manual desde el modal (botón "Send email"). */
    public static function sendOneMail(?int $id = null): bool
    {
        global $CFG_GLPI, $DB;

        if ($id === null) {
            $id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        }

        $nmail = new GLPIMailer();
        $nmail->SetFrom($CFG_GLPI['admin_email'], $CFG_GLPI['admin_email_name'] ?? '', false);

        $doc_id    = (int)($_POST['doc_id'] ?? 0);
        $send_user = 0;

        if (isset($_POST['e_list'])) {
            // Template path: content comes from DB (admin-configured, CSRF-protected).
            $parts = explode('|', $_POST['e_list']);
            if (count($parts) >= 4) {
                $recipients    = $parts[0];
                $email_subject = $parts[1];
                $email_content = $parts[2];
                $send_user     = (int)$parts[3];
            } else {
                $recipients = $email_subject = $email_content = '';
            }
        } else {
            // Manual path: sanitize all three fields from POST.
            $recipients    = $_POST['em_list']      ?? '';
            // Subject must be plain text (no HTML in SMTP headers).
            $email_subject = strip_tags($_POST['email_subject'] ?? __('GLPI Protocols Manager mail', 'protocolsmanager'));
            // Body: allow safe inline HTML; strip scripts/iframes/events.
            $raw_body      = $_POST['email_content'] ?? '';
            $email_content = strip_tags($raw_body, '<b><i><u><br><p><a><ul><ol><li><strong><em><span>');
        }

        // Resolve owner and author server-side.
        $ownerUser2  = new User();
        $ownerUser2->getFromDB($id);
        $owner  = $ownerUser2->getFriendlyName();
        $authUser2 = new User();
        $authUser2->getFromDB(Session::getLoginUserID());
        $author = $authUser2->getFriendlyName();

        foreach (['{owner}' => $owner, '{admin}' => $author, '{cur_date}' => date('d.m.Y')] as $k => $v) {
            $email_content = str_replace($k, $v, $email_content);
            $email_subject = str_replace($k, $v, $email_subject);
        }

        $finalRecipients = [];

        if ($send_user === 1 && $id > 0) {
            $emailRow = $DB->request([
                'FROM'  => 'glpi_useremails',
                'WHERE' => ['users_id' => $id, 'is_default' => 1],
            ])->current();
            if ($emailRow && !empty($emailRow['email'])) {
                $nmail->AddAddress($emailRow['email'], '');
                $finalRecipients[] = $emailRow['email'];
            }
        }

        $invalidEmails = [];
        foreach (explode(';', $recipients) as $r) {
            $r = trim($r);
            if (empty($r)) { continue; }
            if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
                $invalidEmails[] = $r;
                continue;
            }
            $nmail->AddAddress($r, '');
            $finalRecipients[] = $r;
        }
        if (!empty($invalidEmails)) {
            Session::addMessageAfterRedirect(
                __('Invalid email address(es) skipped: ', 'protocolsmanager') . implode(', ', $invalidEmails),
                false, WARNING
            );
        }

        if (empty($finalRecipients)) {
            Session::addMessageAfterRedirect(__('No recipients specified. Email not sent.', 'protocolsmanager'), false, ERROR);
            return false;
        }

        if ($doc_id <= 0) {
            Session::addMessageAfterRedirect(__('No document selected.', 'protocolsmanager'), false, WARNING);
            return false;
        }
        $docRow = $DB->request(['FROM' => 'glpi_documents', 'WHERE' => ['id' => $doc_id]])->current();
        if ($docRow) {
            $fullpath = GLPI_VAR_DIR . '/' . $docRow['filepath'];
            if (file_exists($fullpath)) {
                $nmail->addAttachment($fullpath, $docRow['filename']);
            } else {
                Session::addMessageAfterRedirect(__('Attachment file not found: ', 'protocolsmanager') . $fullpath, false, ERROR);
            }
        }

        $nmail->IsHtml(true);
        $nmail->Subject = $email_subject;
        $nmail->Body    = nl2br($email_content);

        if (!$nmail->Send()) {
            Session::addMessageAfterRedirect(__('Failed to send email', 'protocolsmanager'), false, ERROR);
            return false;
        }

        Session::addMessageAfterRedirect(__('Email sent', 'protocolsmanager') . ' to ' . implode(', ', $finalRecipients));
        return true;
    }
}
?>

<script>
$(function () {

    // Checkboxes
    $('.checkall').on('click', function () {
        $('.child').prop('checked', this.checked);
    });
    $('.child').prop('checked', true);

    $('.checkalldoc').on('click', function () {
        $('.docchild').prop('checked', this.checked);
    });

    // Modal email: habilitar/deshabilitar campos
    $(".man_recs").prop('disabled', true);

    $('.send_type').on('click', function () {
        if ($(this).prop('id') === 'manually') {
            $(".man_recs").prop('disabled', false);
            $("#auto_recs").prop('disabled', true);
        } else {
            $(".man_recs").prop('disabled', true);
            $("#auto_recs").prop('disabled', false);
        }
    });

    // Pasar doc_id al modal
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.send-email-btn');
        if (!btn) return;
        var input = document.getElementById('dialogVal');
        if (input) input.value = btn.getAttribute('data-docid') || '';
    });

    // Añadir filas personalizadas
    var counter = $('.child').length;
    var __delete_label = '<?php echo addslashes(__('Delete row', 'protocolsmanager')); ?>';

    // Build a State <select> cell pre-loaded with GLPI states
    function buildStateCell(idx) {
        var opts = '<option value="">—</option>';
        if (typeof pmStatesOptions !== 'undefined') {
            pmStatesOptions.forEach(function(s) {
                opts += '<option value="' + s.id + '">' + $('<div/>').text(s.name).html() + '</option>';
            });
        }
        return '<td class="center"><select name="states_id[' + idx + ']" style="width:95%;max-width:120px;font-size:11px;">' + opts + '</select></td>';
    }


    $('#addNewRow').on('click', function () {
        var idx = counter;
        var row = $('<tr class="tab_bg_1">');
        row.append(
            // col 1 — checkbox slot (delete button for manual rows)
            '<td width="10"><button type="button" class="ibtnDel btn btn-icon btn-ghost-danger btn-sm" title="' + __delete_label + '"><i class="ti ti-x"></i></button></td>'
            // col 2 — Type
            + '<td class="center"><input type="text" style="width:95%" name="type_name[' + idx + ']"></td>'
            // col 3 — Manufacturer
            + '<td class="center"><input type="text" style="width:95%" name="man_name[' + idx + ']"></td>'
            // col 4 — Model
            + '<td class="center"><input type="text" style="width:95%" name="mod_name[' + idx + ']"></td>'
            // col 5 — Name
            + '<td class="center"><input type="text" style="width:95%" name="item_name[' + idx + ']"></td>'
            // col 6 — State: GLPI states dropdown
            + buildStateCell(idx)
            // col 7 — Serial number
            + '<td class="center"><input type="text" style="width:95%" name="serial[' + idx + ']"></td>'
            // col 8 — Inventory number
            + '<td class="center"><input type="text" style="width:95%" name="otherserial[' + idx + ']"></td>'
            // col 9 — Comments + hidden index fields
            + '<td class="center"><input type="text" style="width:95%" name="comments[' + idx + ']">'
            + '<input type="hidden" name="number[' + idx + ']" value="' + idx + '">'
            // Empty classes/ids so makeProtocol skips the documents_items link for manual rows
            + '<input type="hidden" name="classes[' + idx + ']" value="">'
            + '<input type="hidden" name="ids[' + idx + ']" value="0">'
            + '</td>'
        );
        $('#additional_table').append(row);
        counter++;
    });

    $('#additional_table').on('click', '.ibtnDel', function () {
        $(this).closest('tr').remove();
    });

});
</script>
