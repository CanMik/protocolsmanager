<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

require_once dirname(__DIR__) . '/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PluginProtocolsmanagerGenerate extends CommonDBTM {

    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
        return self::createTabEntry('Protocols manager');
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
        global $CFG_GLPI;
        
        $tab_access = self::checkRights();
    
        if ($tab_access == 'w') {
            $PluginProtocolsmanagerGenerate = new self();
            $PluginProtocolsmanagerGenerate->showContent($item);
        } else {
            echo "<div align='center'><br><img src='". htmlescape($CFG_GLPI['root_doc']) ."/pics/warning.png'><br>".__("Access denied")."</div>";
        }
    }
    
    /**
     * Check if logged user has rights to plugin
     */
    static function checkRights() {
        global $DB;
        
        if (!isset($_SESSION['glpiactiveprofile']['id'])) {
            return "";
        }

        $active_profile = $_SESSION['glpiactiveprofile']['id'];
        
        // Use iterator for GLPI 10+ standard
        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_protocolsmanager_profiles',
            'WHERE' => ['profile_id' => $active_profile]
        ]);
        
        if ($row = $iterator->current()) {
            return $row['tab_access'];
        }

        return "";
    }

    /**
     * Helper para obtener datos extra del usuario (Registration, Title, Category)
     * Evita duplicar código en showContent y makeProtocol
     */
    private static function getUserExtraData($user_id) {
        global $DB;

        $data = [
            'registration_number' => "_______________",
            'title'               => "_______________",
            'category'            => "_______________"
        ];

        if (empty($user_id)) {
            return $data;
        }

        // Obtener datos del usuario
        $iterator = $DB->request('glpi_users', ['id' => $user_id]);
        $user_row = $iterator->current();

        if ($user_row) {
            // Registration Number
            if (!empty($user_row['registration_number'])) {
                $data['registration_number'] = $user_row['registration_number'];
            }

            // Title - Use Dropdown::getDropdownName to optimize/simplify
            if (!empty($user_row['usertitles_id'])) {
                $title = Dropdown::getDropdownName('glpi_usertitles', $user_row['usertitles_id']);
                if (!empty($title) && $title != '&nbsp;') {
                    $data['title'] = $title;
                }
            }

            // Category - Use Dropdown::getDropdownName
            if (!empty($user_row['usercategories_id'])) {
                $category = Dropdown::getDropdownName('glpi_usercategories', $user_row['usercategories_id']);
                if (!empty($category) && $category != '&nbsp;') {
                    $data['category'] = $category;
                }
            }
        }

        return $data;
    }
    
    /**
     * Show plugin content
     */
    function showContent($item) {
        global $DB, $CFG_GLPI;

        $itemid = null;
        $tstid  = null;

        // Verificar si es un objeto User o un item con users_id
        if (get_class($item) !== "User") {
            if (!empty($item->id) && !empty($item->fields["users_id"])) {
                $itemid = $item->id;
                $tstid  = $item->fields["users_id"];
                $item = new User();
                $item->getFromDB($tstid);
            }
        }

        $id = $item->getField('id'); // User ID
        
        // Obtener datos extra optimizados
        $userData = self::getUserExtraData($id);
        
        // Variables de configuración
        $type_user  = $CFG_GLPI['linkuser_types'];
        $field_user = 'users_id';
        $rand       = mt_rand();
        $counter    = 0;

        echo "<br>";
        echo "<form method='post' name='user_field". htmlescape($rand) ."' id='user_field". htmlescape($rand) ."' action=\"" . htmlescape($CFG_GLPI["root_doc"]) . "/plugins/protocolsmanager/front/generate.form.php\">";
        
        // Tabla de selección de plantilla
        echo "<table class='tab_cadre_fixe'><tr><td style ='width:25%'></td>";
        echo "<td class='center' style ='width:25%'>";
        echo "<select required name='list' style='font-size:14px; width:95%'>";
            $doc_types = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM' => 'glpi_plugin_protocolsmanager_config'
            ]);
            foreach ($doc_types as $list) {
                // Assuming htmlescape is available globally or via plugin
                echo '<option value="'.htmlescape($list["id"]).'">'.htmlescape($list["name"]).'</option>';
            }
        echo "</select>";
        echo "</td>";
        echo "<td style='width:10%'><input type='submit' name='generate' class='submit' value='".__('Create')."'></td>";
        echo "<td style='width:30%'></td></tr>";
        echo "<tr><td></td><td colspan='2'><input type='text' name='notes' placeholder='".__('Note')."' style='width:89%; font-size:14px; padding: 2px'></td><td></td></tr>";
        echo "</table>";

        // Tabla principal de items
        echo "<div class='spaced'><table class='tab_cadre_fixehov' id='additional_table'>";
        $header = "<th width='10'><input type='checkbox' class='checkall' style='height:16px; width: 16px;'></th>";
        $header .= "<th class='center'>".__('Type')."</th>";
        $header .= "<th class='center'>".__('Manufacturer')."</th>";
        $header .= "<th class='center'>".__('Model')."</th>";
        $header .= "<th class='center'>".__('Name')."</th>";
        $header .= "<th class='center'>".__('State')."</th>";
        $header .= "<th class='center'>".__('Serial number')."</th>";
        $header .= "<th class='center'>".__('Inventory number')."</th>";
        $header .= "<th class='center'>".__('Comments')."</th></tr>";
        echo $header;
        
        // Iterar sobre tipos de items vinculados al usuario
        foreach ($type_user as $itemtype) {
            if (!($itemObj = getItemForItemtype($itemtype))) {
                continue;
            }
            
            if ($itemObj->canView()) {
                $itemtable = getTableForItemType($itemtype);
                
                $criteria = [
                    'FROM'  => $itemtable,
                    'WHERE' => [$field_user => $id]
                ];

                if ($itemObj->maybeTemplate()) {
                    $criteria['WHERE']['is_template'] = 0;
                }
                
                if ($itemObj->maybeDeleted()) {
                    $criteria['WHERE']['is_deleted'] = 0;
                }

                $item_iterator = $DB->request($criteria);
                $type_name = $itemObj->getTypeName();

                foreach ($item_iterator as $data) {
                    $cansee = $itemObj->can($data["id"], READ);
                    $linkName = empty($data["name"]) ? $data["id"] : $data["name"];
                    
                    if ($cansee) {
                        $link_item = $itemObj::getFormURLWithID($data['id']);
                        if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                            $linkName = sprintf(__('%1$s (%2$s)'), $linkName, $data["id"]);
                        }
                        $link = "<a href='" . htmlescape($link_item) . "'>" . htmlescape($linkName) . "</a>";
                    } else {
                        $link = htmlescape($linkName);
                    }
        
                    echo "<tr class='tab_bg_1'>";
                    // Checkbox
                    echo "<td width='10'><input type='checkbox' name='number[]' value='" . htmlescape($counter) . "' class='child' style='height:16px; width: 16px;'></td>";
                    
                    // Type
                    echo "<td class='center'>" . htmlescape($type_name) . "</td>";
                    
                    // Manufacturer (Optimized)
                    $man_name = '';
                    if (!empty($data["manufacturers_id"])) {
                        $man_name = Dropdown::getDropdownName('glpi_manufacturers', $data['manufacturers_id']);
                        $man_name = explode(' ', trim($man_name))[0];
                    }
                    echo "<td class='center'>" . ($man_name ? htmlescape($man_name) : '&nbsp;') . "</td>";

                    // Model (Optimized)
                    $mod_name = '';
                    $modeltypes = ["computer", "phone", "monitor", "networkequipment", "printer", "peripheral"];
                    foreach ($modeltypes as $prefix) {
                        if (!empty($data[$prefix.'models_id'])) {
                            $mod_name = Dropdown::getDropdownName('glpi_'.$prefix.'models', $data[$prefix.'models_id']);
                            break; 
                        }
                    }
                    echo "<td class='center'>" . ($mod_name ? htmlescape($mod_name) : '&nbsp;') . "</td>";
                    
                    // Link/Name
                    echo "<td class='center'>" . $link . "</td>"; 
                    
                    // State (Optimized)
                    $sta_name = '';
                    if (!empty($data["states_id"])) {
                        $sta_name = Dropdown::getDropdownName('glpi_states', $data['states_id']);
                        $sta_name = explode(' ', trim($sta_name))[0];
                    }
                    echo "<td class='center'>" . ($sta_name ? htmlescape($sta_name) : '&nbsp;') . "</td>";
                    
                    // Serial
                    $serial = !empty($data["serial"]) ? $data["serial"] : '';
                    echo "<td class='center'>" . ($serial ? htmlescape($serial) : '&nbsp;') . "</td>";
                    
                    // Inventory Number (otherserial)
                    $otherserial = !empty($data["otherserial"]) ? $data["otherserial"] : '';
                    echo "<td class='center'>" . ($otherserial ? htmlescape($otherserial) : '&nbsp;') . "</td>";
                    
                    // Hidden fields for Form Processing
                    $item_name = !empty($data["name"]) ? $data["name"] : '';
                    $ids = !empty($data["id"]) ? $data["id"] : '';
                    
                    $Owner = new User();
                    $Owner->getFromDB($id);
                    $owner = $Owner->getFriendlyName();

                    $Author = new User();
                    $Author->getFromDB(Session::getLoginUserID());
                    $author = $Author->getFriendlyName();

                    echo "<input type='hidden' name='classes[]' value='" . htmlescape($itemtype) . "'>";
                    echo "<input type='hidden' name='ids[]' value='" . htmlescape($ids) . "'>";    
                    echo "<input type='hidden' name='owner' value ='" . htmlescape($owner) . "'>";
                    echo "<input type='hidden' name='author' value ='" . htmlescape($author) . "'>";
                    echo "<input type='hidden' name='type_name[]' value='" . htmlescape($type_name) . "'>";
                    echo "<input type='hidden' name='man_name[]' value='" . htmlescape($man_name) . "'>";
                    echo "<input type='hidden' name='mod_name[]' value='" . htmlescape($mod_name) . "'>";
                    echo "<input type='hidden' name='serial[]' value='" . htmlescape($serial) . "'>";
                    echo "<input type='hidden' name='otherserial[]' value='" . htmlescape($otherserial) . "'>";
                    echo "<input type='hidden' name='item_name[]' value='" . htmlescape($item_name) . "'>";
                    echo "<input type='hidden' name='user_id' value='" . htmlescape($id) . "'>";
                    
                    echo "<td class='center'><input type='text' name='comments[]'></td>";
                    echo "</tr>";
                    
                    $counter++;
                }
            }
        }

        // --- BLOQUE ASSETS ---
        $criteria_assets = [
            'FROM'  => 'glpi_assets_assets',
            'WHERE' => ['users_id' => $id, 'is_deleted' => 0, 'is_template' => 0]
        ];

        // Verificar si la tabla existe antes de consultarla para evitar errores
        if ($DB->tableExists('glpi_assets_assets')) {
            $item_iterator_assets = $DB->request($criteria_assets);
            $tablet_type_name = 'Tablet';

            foreach ($item_iterator_assets as $data) {
                echo "<tr class='tab_bg_1'>";
                echo "<td width='10'><input type='checkbox' name='number[]' value='" . htmlescape($counter) . "' class='child' style='height:16px; width:16px;'></td>";

                // Definición
                $definition_name = '';
                if (!empty($data['assets_assetdefinitions_id'])) {
                    $definition_name = Dropdown::getDropdownName('glpi_assets_assetdefinitions', $data['assets_assetdefinitions_id']);
                }
                echo "<td class='center'>" . htmlescape($definition_name ?: $tablet_type_name) . "</td>";

                // Manufacturer
                $man_name = '';
                if (!empty($data['manufacturers_id'])) {
                    $man_name = Dropdown::getDropdownName('glpi_manufacturers', $data['manufacturers_id']);
                    $man_name = explode(' ', trim($man_name))[0];
                }
                echo "<td class='center'>" . htmlescape($man_name) . "</td>";

                // Model
                $mod_name = '';
                if (!empty($data['assets_assetmodels_id'])) {
                    $mod_name = Dropdown::getDropdownName('glpi_assets_assetmodels', $data['assets_assetmodels_id']);
                }
                echo "<td class='center'>" . htmlescape($mod_name) . "</td>";

                // Name
                $item_name = isset($data['name']) ? $data['name'] : '';
                echo "<td class='center'>" . htmlescape($item_name) . "</td>";

                // State
                $sta_name = '';
                if (!empty($data['states_id'])) {
                    $sta_name = Dropdown::getDropdownName('glpi_states', $data['states_id']);
                    $sta_name = explode(' ', trim($sta_name))[0];
                }
                echo "<td class='center'>" . htmlescape($sta_name) . "</td>";

                $serial = isset($data['serial']) ? $data['serial'] : '';
                echo "<td class='center'>" . htmlescape($serial) . "</td>";

                $otherserial = isset($data['otherserial']) ? $data['otherserial'] : '';
                echo "<td class='center'>" . htmlescape($otherserial) . "</td>";

                echo "<td class='center'><input type='text' name='comments[]'></td>";

                // Hidden fields
                echo "<input type='hidden' name='classes[]' value='Tablet'>";
                echo "<input type='hidden' name='ids[]' value='" . htmlescape($data['id']) . "'>";
                echo "<input type='hidden' name='owner' value='" . htmlescape($owner) . "'>";
                echo "<input type='hidden' name='author' value='" . htmlescape($author) . "'>";
                echo "<input type='hidden' name='type_name[]' value='" . htmlescape($definition_name) . "'>";
                echo "<input type='hidden' name='man_name[]' value='" . htmlescape($man_name) . "'>";
                echo "<input type='hidden' name='mod_name[]' value='" . htmlescape($mod_name) . "'>";
                echo "<input type='hidden' name='serial[]' value='" . htmlescape($serial) . "'>";
                echo "<input type='hidden' name='otherserial[]' value='" . htmlescape($otherserial) . "'>";
                echo "<input type='hidden' name='item_name[]' value='" . htmlescape($item_name) . "'>";
                echo "<input type='hidden' name='user_id' value='" . htmlescape($id) . "'>";

                echo "</tr>";
                $counter++;
            }
        }
        // --- FIN BLOQUE ASSETS ---
        
        echo "</table>";
        Html::closeForm();
        echo "</div>";
        
        // --- MODAL EMAIL ---
        $conca  = '<div class="modal fade" id="motus" role="dialog">';
        $conca .= '<div class="modal-dialog">';
        $conca .= '<div class="modal-content">';
        $conca .= '<div class="modal-header">';
        $conca .= '<h4 class="modal-title">'.__("Send").' email</h4>';
        $conca .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>';
        $conca .= '</div><div class="modal-body" title="'.__("Send").' email"><p>Select recipients from template or enter manually to send email</p><br><br>';
        $conca .= '<form method="post" action="'. htmlescape($CFG_GLPI["root_doc"]) .'/plugins/protocolsmanager/front/generate.form.php">';

        $conca .= Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        $conca .= '<input type="hidden" id="dialogVal" name="doc_id" value="">';
        $conca .= '<input type="radio" name="send_type" id="manually" class="send_type" value="1"><b> Enter recipients manually </b><br><br>';
        $conca .= '<textarea style="width:90%; height:30px" name="em_list" class="man_recs" placeholder="Recipients (use ; to separate emails)"></textarea><br><br>';
        $conca .= '<input type="text" style="width:90%" name="email_subject" class="man_recs" placeholder="Subject"><br><br>';
        $conca .= '<textarea style="width:90%; height:80px" name="email_content" class="man_recs" placeholder="Content"></textarea><br><br>';
        $conca .= '<input type="radio" name="send_type" id="auto" class="send_type" value="2"><b> Select recipients from template</b><br><br>';

        $conca .= '<select name="e_list" id="auto_recs" disabled="disabled" style="font-size:14px; width:95%">';
        
        $email_configs = $DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig']);
        foreach ($email_configs as $list) {
            $conca .= '<option value="';
            $conca .= htmlescape($list["recipients"]."|".$list["email_subject"]."|".$list["email_content"]."|".$list["send_user"]);
            $conca .= '">';
            $conca .= htmlescape($list["tname"]." - ".$list["recipients"]);
            $conca .= '</option>';
        }
        $conca .= '</select><br><br><input type="submit" name="send" class="submit" value='.__("Send").'>';

        if(!empty($author)) {
            $conca .= '<input type="hidden" name="author" value="' . htmlescape($author) . '">';
        }

        if(!empty($owner)) {
            $conca .= '<input type="hidden" name="owner" value="' . htmlescape($owner) . '">';
        }

        $conca .= '<input type="hidden" name="user_id" value="' . htmlescape($id) . '">';
        $conca .=  Html::closeForm(false);

        $conca .= '</div>'; // modal-body
        $conca .= '</div>'; // modal-content
        $conca .= '</div>'; // modal-dialog
        $conca .= '</div>'; // modal
        echo $conca;

        // Custom Fields Button
        echo "<div class='spaced'><button class='addNewRow' id='addNewRow' style='background-color:#8ec547; color:#fff; cursor:pointer; font:bold 12px Arial, Helvetica; border:0; padding:5px;'>Add Custom Fields</button></div>";

        // Table for existing documents
        echo "<div class='spaced'>";
        echo "<form method='post' name='docs_form' action='" . htmlescape($CFG_GLPI["root_doc"]) . "/plugins/protocolsmanager/front/generate.form.php'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo "<table class='tab_cadre_fixe'><td style='width:5%'><img src='" . htmlescape($CFG_GLPI["root_doc"]) . "/plugins/protocolsmanager/img/arrow-left-top.png'></td><td style='width:5%'>";
        echo "<input type='submit' name='delete' class='submit' value=".__('Delete').">";
        echo "</td><td style='width:90%'></table>";
        
        echo "<table class='tab_cadre_fixehov' id='myTable'>";
        echo "<th width='10'><input type='checkbox' class='checkalldoc' style='height:16px; width: 16px;'></th>";
        $header2 = "<th>".__('Name')."</th>";
        $header2 .= "<th>".__('Type')."</th>";
        $header2 .= "<th>".__('Date')."</th>";
        $header2 .= "<th>".__('File')."</th>";
        $header2 .= "<th>".__('Creator')."</th>";
        $header2 .= "<th>".__('Comment')."</th>";
        $header2 .= "<th>".__('Send email')."</th></tr>";
        echo $header2;

        self::getAllForUser($id);
        echo "</table>";
        Html::closeForm();
        echo "</div>";

        return true;
    }
    
    static function getAllForUser($id) {
        global $DB;
    
        $iterator = $DB->request(['FROM' => 'glpi_plugin_protocolsmanager_protocols', 'WHERE' => ['user_id' => $id]]);

        foreach ($iterator as $exports) {
            echo "<tr class='tab_bg_1'>";
            echo "<td class='center'>";
            echo "<input type='checkbox' name='docnumber[]' value='" . htmlescape($exports['document_id']) . "' class='docchild' style='height:16px; width:16px;'>";
            echo "</td>";
    
            echo "<td class='center'>";
            $Doc = new Document();
            if ($Doc->getFromDB($exports['document_id'])) {
                 echo $Doc->getLink();
            } else {
                 echo __('Document not found');
            }
            echo "</td>";
    
            echo "<td class='center'>" . htmlescape($exports['document_type']) . "</td>";
            echo "<td class='center'>" . htmlescape($exports['gen_date']) . "</td>";
            
            echo "<td class='center'>";
            if ($Doc->fields) {
                echo $Doc->getDownloadLink();
            }
            echo "</td>";
    
            echo "<td class='center'>" . htmlescape($exports['author']) . "</td>";
            echo "<td class='center'>" . ($Doc->fields ? htmlescape($Doc->getField("comment")) : '') . "</td>";
    
            echo "<td class='center'>";
            echo "<button type='button' class='btn btn-sm btn-success send-email-btn' 
                    data-docid='" . htmlescape($exports['document_id']) . "' 
                    data-bs-toggle='modal' 
                    data-bs-target='#motus'>".__('Send')."</button>";
            echo "</td>";
            echo "</tr>";
        }
    }
    
    /**
     * Generate PDF and save to DB
     */
    static function makeProtocol() 
    {
            global $DB;

            // Sanitize and Retrieve POST data
            $number     = isset($_POST['number']) ? $_POST['number'] : [];
            $type_name  = isset($_POST['type_name']) ? $_POST['type_name'] : [];
            $man_name   = isset($_POST['man_name']) ? $_POST['man_name'] : [];
            $mod_name   = isset($_POST['mod_name']) ? $_POST['mod_name'] : [];
            $serial     = isset($_POST['serial']) ? $_POST['serial'] : [];
            $otherserial= isset($_POST['otherserial']) ? $_POST['otherserial'] : [];
            $item_name  = isset($_POST['item_name']) ? $_POST['item_name'] : [];
            $comments   = isset($_POST['comments']) ? $_POST['comments'] : [];
            
            $owner  = isset($_POST['owner']) ? $_POST['owner'] : '';
            $author = isset($_POST['author']) ? $_POST['author'] : '';              
            $doc_no = isset($_POST['list']) ? $_POST['list'] : 0;
            $id     = isset($_POST['user_id']) ? $_POST['user_id'] : 0;
            $notes  = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            // Obtener datos del usuario (usando helper)
            $userExtra = self::getUserExtraData($id);
            $registration_number = $userExtra['registration_number'];
            $usertitle_name      = $userExtra['title'];
            $usercategory_name   = $userExtra['category'];
            $prot_num = self::getDocNumber();

            // Obtener configuración del documento
            $req = $DB->request([
                'FROM' => 'glpi_plugin_protocolsmanager_config',
                'WHERE' => ['id' => $doc_no ]
            ]);
            
            $font = 'dejavusans';
            $fontsize = '9';
            $city = '';
            $email_content = '';
            $email_subject = '';
            $recipients = '';
            $full_img_name = '';
            $orientation = 'portrait';
            $email_mode = 0;
            $send_user = 0;

            if ($row = $req->current()) {
                $content = $row["content"];
                $upper_content = $row["upper_content"];
                $footer = $row["footer"];
                $title = $row["title"];
                $title_template = $row["name"];
                $full_img_name = $row["logo"];
                $font = !empty($row["font"]) ? $row["font"] : $font;
                $fontsize = !empty($row["fontsize"]) ? $row["fontsize"] : $fontsize;
                $orientation = $row["orientation"];
                $city = $row["city"];
                $email_mode = $row["email_mode"];
                $email_template = $row["email_template"];
                $serial_mode = $row["serial_mode"];
                $author_state = $row["author_state"];
                
                // Reemplazos comunes
                $replacements = [
                    "{cur_date}" => date("d.m.Y"),
                    "{owner}"    => $owner,
                    "{admin}"    => $author,
                    "{reg_num}"  => $registration_number,
                    "{title}"    => $usertitle_name,
                    "{category}" => $usercategory_name
                ];

                $title = str_replace("{owner}", $owner, $title);
                
                foreach ($replacements as $key => $val) {
                    $content = str_replace($key, $val, $content);
                    $upper_content = str_replace($key, $val, $upper_content);
                }

                $logo_width = isset($row["logo_width"]) ? $row["logo_width"] : null;
                $logo_height = isset($row["logo_height"]) ? $row["logo_height"] : null;
            } else {
                return; // Error: config not found
            }

            // Configuración de Email
            if (!empty($email_template)) {
                $req2 = $DB->request([
                    'FROM' => 'glpi_plugin_protocolsmanager_emailconfig',
                    'WHERE' => ['id' => $email_template ]
                ]);
                
                if ($row2 = $req2->current()) {
                    $send_user = $row2["send_user"];
                    $email_subject = $row2["email_subject"];
                    $email_content = $row2["email_content"];
                    $recipients = $row2["recipients"];
                }
            }
            
            // Reemplazos en Email
            $email_replacements = [
                "{owner}"    => $owner,
                "{admin}"    => $author,
                "{cur_date}" => date("d.m.Y")
            ];
            foreach ($email_replacements as $key => $val) {
                $email_content = str_replace($key, $val, $email_content);
                $email_subject = str_replace($key, $val, $email_subject);
            }

            // Configuración de imagen
            if (empty($full_img_name)) {
                $backtop = "20mm";
                $islogo = 0;
                $logo = '';
            } else {
                $logo = GLPI_PICTURE_DIR . '/' . $full_img_name;
                $backtop = "40mm";
                $islogo = 1;
            }
            
            // --- GENERAR TABLA HTML DE ITEMS ---
            $table_html = "<table style='width:100%; border-collapse:collapse;' border='1'>";
            $table_html .= "<tr><th>".__('Type')."</th><th>".__('Manufacturer')."</th><th>".__('Model')."</th><th>".__('Name')."</th><th>".__('Serial number')."</th><th>".__('Inventory number')."</th><th>".__('Comments')."</th></tr>";
            
            if (is_array($number)) {
                foreach ($number as $idx) {
                    // Verificación de índice para evitar Undefined Index
                    $t = isset($type_name[$idx]) ? htmlescape($type_name[$idx]) : '';
                    $m = isset($man_name[$idx]) ? htmlescape($man_name[$idx]) : '';
                    $mo = isset($mod_name[$idx]) ? htmlescape($mod_name[$idx]) : '';
                    $n = isset($item_name[$idx]) ? htmlescape($item_name[$idx]) : '';
                    $s = isset($serial[$idx]) ? htmlescape($serial[$idx]) : '';
                    $os = isset($otherserial[$idx]) ? htmlescape($otherserial[$idx]) : '';
                    $cmt = isset($comments[$idx]) ? htmlescape($comments[$idx]) : '';
                    
                    $table_html .= "<tr><td>{$t}</td><td>{$m}</td><td>{$mo}</td><td>{$n}</td><td>{$s}</td><td>{$os}</td><td>{$cmt}</td></tr>";
                }
            }
            $table_html .= "</table>";
            
            $content = str_replace("{items_table}", $table_html, $content);
            $upper_content = str_replace("{items_table}", $table_html, $upper_content);
            
            // Generar PDF
            ob_start();
            include dirname(__FILE__).'/template.php';
            $file_content = ob_get_clean(); // get_clean hace get_contents + end_clean

            $options = new Options();
            $options->set('defaultFont', $font);
            // Habilitar carga de imágenes remotas si es necesario, aunque aquí son locales
            $options->set('isRemoteEnabled', true); 

            $html2pdf = new Dompdf($options);
            $html2pdf->loadHtml($file_content);
            $html2pdf->setPaper('A4', $orientation);
            $html2pdf->render();

            $doc_name = str_replace(' ', '_', $title)."-".date('dmY').'.pdf';
            $output = $html2pdf->output();

            file_put_contents(GLPI_UPLOAD_DIR .'/'.$doc_name, $output);
            
            $doc_id = self::createDoc($doc_name, $owner, $notes, $title, $id); 
            
            if ($email_mode == 1) {
                self::sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id);
            }
            
            $gen_date = date('Y-m-d H:i:s');

            $DB->insert('glpi_plugin_protocolsmanager_protocols', [
                'name' => $doc_name,
                'gen_date' => $gen_date,
                'author' => $author,
                'user_id' => $id,
                'document_id' => $doc_id,
                'document_type' => $title_template
            ]);

            $DB->update(
                'glpi_documents',
                [
                    'users_id' => $id,
                    'name' => $doc_name,
                    'comment' => $owner,
                ],
                [
                    'id' => $doc_id
                ]
            );

            // Link items to document
            $DB->insert('glpi_documents_items', [
                'documents_id' => $doc_id,
                'items_id' => $id,
                'itemtype' => 'User',
                'users_id' => $id,
                'date_creation' => $gen_date,
                'date_mod' => $gen_date,
                'date' => $gen_date,
            ]);

            if (isset($_POST["number"]) && is_array($_POST["number"])) {
                foreach ($_POST["number"] as $itms) {
                    if (isset($_POST["classes"][$itms]) && isset($_POST["ids"][$itms])) {
                        $class = $_POST["classes"][$itms];
                        $it    = $_POST["ids"][$itms];

                        $DB->insert('glpi_documents_items',[
                            'documents_id' => $doc_id,
                            'items_id' => $it,
                            'itemtype' => $class,
                            'users_id' => $id,
                            'date_creation' => $gen_date,
                            'date_mod' => $gen_date,
                            'date' => $gen_date,
                        ]);
                    }
                }
            }
    }

    static function getDocNumber() {
        global $DB;
        
        $req = $DB->request([
            'SELECT' => [new \QueryExpression('MAX(id) AS max')],
            'FROM' => 'glpi_plugin_protocolsmanager_protocols'
        ]);

        if ($row = $req->current()) {
            $nextnum = $row["max"];
            return $nextnum ? $nextnum + 1 : 1;
        }
        return 1; 
    }
    
    static function createDoc($doc_name, $owner, $notes, $title, $id) {
        global $DB;
        
        $entity = Session::getActiveEntity(); // Valor por defecto

        $req1 = $DB->request('glpi_users', ['id' => $id]);
        if ($row1 = $req1->current()) {
            $entity = $row1["entities_id"];
        }

        if (!Session::haveAccessToEntity($entity)) {
            $entity = Session::getActiveEntity();
        }
        
        // Obtener ID de categoría de documento por nombre
        $doc_cat_id = 0;
        $req2 = $DB->request('glpi_documentcategories', ['name' => $title]);
        if ($row2 = $req2->current()) {
            $doc_cat_id = $row2["id"];
        }
        
        $input = [];
        $doc = new Document();
        $input["entities_id"] = $entity;
        $input["name"] = date('mdY_Hi');
        $input["upload_file"] = $doc_name;
        $input["documentcategories_id"] = $doc_cat_id;
        $input["mime"] = "application/pdf";
        $input["date_mod"] = date("Y-m-d H:i:s");
        $input["users_id"] = Session::getLoginUserID();
        $input["comment"] = $owner."\r".$notes;
        
        $doc->check(-1, CREATE, $input);
        return $doc->add($input);
    }
    
    static function deleteDocs() {
        global $DB;
        
        if (isset($_POST['docnumber']) && is_array($_POST['docnumber'])) {
            foreach ($_POST['docnumber'] as $del_key) {
                $DB->delete(
                    'glpi_plugin_protocolsmanager_protocols', [
                        'document_id' => $del_key
                    ]
                );
                
                $doc = new Document();
                $doc->delete(['id' => $del_key]); 
            }
        }
    }

    static function sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id) {
        global $CFG_GLPI, $DB;
        
        $nmail = new GLPIMailer();
        $sender_name = $CFG_GLPI["admin_email_name"] ?? '';
        $nmail->SetFrom($CFG_GLPI["admin_email"], $sender_name, false);
        
        $req = $DB->request('glpi_documents', ['id' => $doc_id]);
        
        if ($row = $req->current()) {
            $fullpath = GLPI_VAR_DIR . '/' . $row["filepath"];
            $filename = $row["filename"];
            $nmail->addAttachment($fullpath, $filename);
        } else {
            // Si no hay documento, quizás loggear error o retornar
        }
        
        $owner_email = null;
        if ($send_user == 1) {
            $req2 = $DB->request([
                'FROM' => 'glpi_useremails',
                'WHERE' => ['users_id' => $id, 'is_default' => 1]
            ]);
            if ($row2 = $req2->current()) {
                $owner_email = $row2["email"];
                $nmail->AddAddress($owner_email, '');
            }
        }
        
        $recipients_array = explode(';', $recipients);
        foreach($recipients_array as $recipient) {
            if (!empty($recipient)) {
                $nmail->AddAddress(trim($recipient), '');
            }
        }
        
        $nmail->Subject = $email_subject;
        $nmail->Body = $email_content;
        
        if (!$nmail->Send()) {
            Session::addMessageAfterRedirect(__('Failed to send email'), false, ERROR);
            return false;
        } else {
            $msg = __('Email sent')." to ".implode(", ", $recipients_array);
            if (!empty($owner_email)) {
                $msg .= " ".$owner_email;
            }
            Session::addMessageAfterRedirect($msg);
            return true;
        }
    }

    static function sendOneMail($id=null) {
        global $CFG_GLPI, $DB;
    
        if (is_null($id) && isset($_POST['user_id'])) {
            $id = $_POST['user_id'];
        }
        
        $nmail = new GLPIMailer();
        $sender_name = $CFG_GLPI["admin_email_name"] ?? '';
        $nmail->SetFrom($CFG_GLPI["admin_email"], $sender_name, false);
        
        $doc_id = isset($_POST["doc_id"]) ? $_POST["doc_id"] : 0;
        $recipients = isset($_POST["em_list"]) ? $_POST["em_list"] : '';
        $email_subject = isset($_POST["email_subject"]) ? $_POST["email_subject"] : "GLPI Protocols Manager mail";
        $email_content = isset($_POST['email_content']) ? $_POST['email_content'] : ' ';
        $send_user = 0;

        if (isset($_POST['e_list'])) {
            $result = explode('|', $_POST['e_list']);
            // Asegurar que hay suficientes elementos
            if (count($result) >= 4) {
                $recipients   = $result[0];
                $email_subject = $result[1];
                $email_content = $result[2];
                $send_user     = $result[3];
            }
        }
        
        $owner  = isset($_POST["owner"]) ? $_POST["owner"] : '';
        $author = isset($_POST["author"]) ? $_POST["author"] : '';
        
        $replace = [
            "{owner}" => $owner,
            "{admin}" => $author,
            "{cur_date}" => date("d.m.Y")
        ];
        
        foreach ($replace as $k => $v) {
            $email_content = str_replace($k, $v, $email_content);
            $email_subject = str_replace($k, $v, $email_subject);
        }
        
        $final_recipients = [];

        // Agregar dueño
        if ($send_user == 1 && !empty($id)) {
            $req2 = $DB->request([
                'FROM' => 'glpi_useremails',
                'WHERE' => ['users_id' => $id, 'is_default' => 1]
            ]);
            if ($row2 = $req2->current()) {
                $owner_email = $row2["email"];
                if (!empty($owner_email)) {
                    $nmail->AddAddress($owner_email, '');
                    $final_recipients[] = $owner_email;
                }
            }
        }
        
        // Agregar otros
        $recipients_array = explode(';', $recipients);
        foreach($recipients_array as $recipient) {
            $recipient = trim($recipient);
            if (!empty($recipient)) {
                $nmail->AddAddress($recipient, '');
                $final_recipients[] = $recipient;
            }
        }

        if (empty($final_recipients)) {
            Session::addMessageAfterRedirect(__('No recipients specified. Email not sent.'), false, ERROR);
            return false;
        }
        
        if (!empty($doc_id)) {
            $req = $DB->request('glpi_documents', ['id' => $doc_id]);
            if ($row = $req->current()) {
                $fullpath = GLPI_VAR_DIR . '/' . $row["filepath"];
                $filename = $row["filename"];
                if (file_exists($fullpath)) {
                    $nmail->addAttachment($fullpath, $filename);
                } else {
                    Session::addMessageAfterRedirect(__('Attachment file not found: ') . $fullpath, false, ERROR);
                }
            }
        }
        
        $nmail->IsHtml(true);
        $nmail->Subject = $email_subject;
        $nmail->Body    = nl2br(stripcslashes($email_content));
        
        if (!$nmail->Send()) {
            Session::addMessageAfterRedirect(__('Failed to send email'), false, ERROR);
            return false;
        } else {
            Session::addMessageAfterRedirect(__('Email sent')." to ".implode(", ", $final_recipients));
            return true;
        }
    }       
}
?>

<script>
    $(function(){
        $(".man_recs").prop('disabled', true);
        $('.send_type').click(function(){
            if($(this).prop('id') == "manually"){
                $(".man_recs").prop('disabled', false);
                $("#auto_recs").prop('disabled', true);
            }else{
                $(".man_recs").prop('disabled', true);
                $("#auto_recs").prop('disabled', false);
            }
        });

        // Dialog handling
        $("#myTable").on('click','.openDialog',function(){
            var currentRow = $(this).closest("tr");
            var docid = currentRow.find(".docid").html(); 
            $('#dialogVal').val(docid);
            $("#motus").modal('show');
        });

        // Checkbox all
        $('.checkall').on('click', function() {
            $('.child').prop('checked', this.checked)
        });
        $('.child').prop('checked', true); // Default checked

        $('.checkalldoc').on('click', function() {
            $('.docchild').prop('checked', this.checked)
        });

        // Add New Row Logic
        var counter = $('.child').length;
        
        $("#addNewRow").on("click", function () {
            var newRow = $("<tr class='tab_bg_1'>");
            var cols = "";
            cols += '<td><input type="button" class="ibtnDel" value="&#10006" style="background-color:red; font-size:9px;"></td>';
            cols += '<td class="center"><input type="text" style="width:80% " name="type_name[]"></td>';
            cols += '<td class="center"><input type="text" style="width:90% "name="man_name[]"></td>';
            cols += '<td class="center"><input type="text" style="width:90% "name="mod_name[]"></td>';
            cols += '<td class="center"><input type="text" style="width:90% "name="item_name[]"></td>';
            cols += '<td class="center"><input type="text" style="width:90% "name="serial[]"></td>';
            cols += '<td class="center"><input type="text" style="width:90% "name="otherserial[]"></td>';
            cols += '<td class="center"><input type="text" style="width:90% "name="comments[]"><input type="hidden" name="number[]" value="' + counter + '"></td>';
            
            newRow.append(cols);
            $("#additional_table").append(newRow);
            counter++;
        });
        
        $("#additional_table").on("click", ".ibtnDel", function (event) {
            $(this).closest("tr").remove();
        });
    });

    // Pass docID to modal
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.send-email-btn');
        if (!btn) return;
    
        var docId = btn.getAttribute('data-docid') || '';
        var input = document.getElementById('dialogVal');
        if (input) input.value = docId;
    }); 
</script>