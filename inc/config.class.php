<?php

class PluginProtocolsmanagerConfig extends CommonDBTM {

	public static function getTypeName($nb = 0): string
	{
		return __('Protocols Manager', 'protocolsmanager');
	}

	function showFormProtocolsmanager() {
		global $CFG_GLPI, $DB;
		$plugin_conf = self::checkRights();

		if ($plugin_conf == 'w') {	
			self::displayContent();	
		} else {
			echo "<div align='center'><br><img src='". htmlescape($CFG_GLPI['root_doc']) ."/pics/warning.png'><br>".__("Access denied")."</div>";
		}
	}
	
	
	static function checkRights() {
		global $DB;
		$active_profile = $_SESSION['glpiactiveprofile']['id'] ?? 0;
		if ($active_profile <= 0) { return ''; }
		
		// Updated DB->request syntax
		$req = $DB->request([
			'FROM' => 'glpi_plugin_protocolsmanager_profiles',
			'WHERE' => ['profile_id' => $active_profile]
		]);
					
        if($row = $req->current()) {
            $plugin_conf = $row['plugin_conf'];
        }
        else{
            $plugin_conf = "";
        }

        return $plugin_conf;


	}
	
	static function displayContent() {
		global $CFG_GLPI, $DB;
		
		if (isset($_POST["menu_mode"])) {
			$menu_mode = $_POST["menu_mode"];
		} else if (isset($_SESSION["menu_mode"])) {
			$menu_mode = $_SESSION["menu_mode"];
		} else $menu_mode = "t";
		
		if ($menu_mode == "e") {
			self::displayContentEmail();
		} else {
			self::displayContentConfig();
		}
	}
	
	static function displayContentConfig() {
		global $CFG_GLPI, $DB;
		
		if (isset($_POST["edit_id"])) {
			
			$edit_id = $_POST['edit_id'];
			$mode = $edit_id;
			
			// Updated DB->request syntax
			$req = $DB->request([
				'FROM' => 'glpi_plugin_protocolsmanager_config',
				'WHERE' => ['id' => $edit_id ]
			]);
				
			if ($row = $req->current()) {
				$template_uppercontent = $row["upper_content"];
				$template_content = $row["content"];
				$template_footer = $row["footer"];
				$template_name = $row["name"];
				$title = $row["title"];
				$font = $row["font"];
				$fontsize = $row["fontsize"];
				$city = $row["city"];
				$logo = $row["logo"];
				$serial_mode = $row["serial_mode"];
				$orientation = $row["orientation"];
				$breakword = $row["breakword"];
				$email_mode = $row["email_mode"];
				$email_template = $row["email_template"];
				$author_name  = $row["author_name"];
				$author_state = $row["author_state"];
				$date_format  = (int)($row["date_format"] ?? 0);
	
				// Récupérer width/height du logo si présents
				$logo_width = isset($row["logo_width"]) ? $row["logo_width"] : '';
				$logo_height = isset($row["logo_height"]) ? $row["logo_height"] : '';
			}
			
		} else {
			$template_uppercontent = '';
			$template_content = '';
			$template_footer = '';
			$template_name = '';
			$title = '';
			$font = '';
			$fontsize = '9';
			$city = '';
			$mode = 0; //if mode 0 then you creating new template instead of edit
			$serial_mode = 1;
			$orientation = "p";
			$breakword = 1;
			$email_mode = 2;
			$email_template = 1;
			$author_name = '';
			$author_state = 1;
			$date_format  = 0;
			$logo_width   = '';
			$logo_height  = '';
		}
		
		// TCPDF native fonts bundled in GLPI vendor/tecnickcom/tcpdf/fonts/
		$fonts = array(
					'Helvetica'   => 'Helvetica',
					'Times'       => 'Times',
					'Courier'     => 'Courier',
					'DejaVu Sans' => 'DejaVu Sans (Unicode)',
				);
						
		$fontsizes = array('7' => '7',
							'8' => '8',
							'9' => '9',
							'10' => '10',
							'11' => '11',
							'12' => '12');
						
		$orientations = array(
							'Portrait'  => __('Portrait', 'protocolsmanager'),
							'Landscape' => __('Landscape', 'protocolsmanager')
						);
		
		if (!isset($font)) {
			$font='Helvetica';
		}
		
		// ── Tab navigation (GLPI nav-tabs) ──────────────────────────────
		echo "<ul class='nav nav-tabs mb-3'>";
		// Tab 1 — Templates (active)
		echo "<li class='nav-item'>";
		echo "<form action='config.form.php' method='post' class='m-0'>";
		echo "<input type='hidden' name='menu_mode' value='t'>";
		echo "<button type='submit' name='template_settings' class='nav-link active'>"
		    . "<i class='ti ti-template me-1'></i>"
		    . htmlescape(__('Templates settings', 'protocolsmanager'))
		    . "</button>";
		Html::closeForm();
		echo "</li>";
		// Tab 2 — Email
		echo "<li class='nav-item'>";
		echo "<form action='config.form.php' method='post' class='m-0'>";
		echo "<input type='hidden' name='menu_mode' value='e'>";
		echo "<button type='submit' name='email_settings' class='nav-link'>"
		    . "<i class='ti ti-mail me-1'></i>"
		    . htmlescape(__('Email settings', 'protocolsmanager'))
		    . "</button>";
		Html::closeForm();
		echo "</li>";
		echo "</ul>";

		echo "<div class='center'>";
		echo "<form name='form' action='config.form.php' method='post' enctype='multipart/form-data'>";
		echo "<input type='hidden' name='MAX_FILE_SIZE' value=1948000>";
		echo "<input type='hidden' name='mode' value='" . htmlescape($mode) . "'>";

		// ── Section header: Template settings ───────────────────────────
		echo "<div class='card mb-3'>";
		echo "<div class='card-header d-flex align-items-center gap-2'>"
		    . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-template fs-4'></i></span>"
		    . "<h3 class='card-title mb-0'>"
		    . (__($mode ? 'Edit' : 'Create') . ' ' . __('template', 'protocolsmanager'))
		    . "</h3>"
		    . "<a href='https://github.com/CanMik/protocolsmanager/wiki/Using-the-plugin' target='_blank' "
		    . "class='ms-auto btn btn-info d-inline-flex align-items-center gap-1'>"
		    . "<i class='ti ti-help-circle me-1'></i>" . __('Help', 'protocolsmanager') . "</a>"
		    . "</div>";
		echo "<div class='card-body'>";
		echo "<table class='tab_cadre_fixe'>";
		// Added htmlescape for XSS protection
		echo "<tr><td>".__('Template name', 'protocolsmanager')."*</td><td colspan='2'><input type='text' name='template_name' style='width:80%;' value='" . htmlescape($template_name) . "'></td></tr>";
		echo "<tr><td>".__('Document title', 'protocolsmanager')."*</td><td colspan='2'><input type='text' name='title' style='width:80%;' value='" . htmlescape($title) . "'></td></tr>";
		echo "<tr><td></td><td colspan='2'><small class='text-muted'>".__('You can use {owner} here.', 'protocolsmanager')."</small></td></tr>";

		echo "<tr><td>" . __('Font', 'protocolsmanager') . "</td><td colspan='2'><select name='font' style='width:150px'>";
			foreach($fonts as $code => $fontname) {
				echo "<option value='".$code."' ";
				if ($code == $font) {
					echo " selected";
				}
				echo ">".$fontname."</option>";
			}
		echo "</select></td></tr>";
		
		echo "<tr><td>" . __('Font size', 'protocolsmanager') . "</td><td colspan='2'><select name='fontsize' style='width:150px'";
			foreach($fontsizes as $fsize => $fsizes) {
				echo "<option value='".$fsize."' ";
				if ($fsize == $fontsize) {
					echo " selected";
				}
				echo ">".$fsizes."</option>";
			}
			
		echo "<tr><td>" . __('Word breaking', 'protocolsmanager') . "</td><td><label><input type='radio' name='breakword' value=1 ";
		if ($breakword == 1)
			echo "checked='checked'";
		echo "> " . __('On', 'protocolsmanager') . "</label></td>";
		echo "<td><label><input type='radio' name='breakword' value=0 ";
		if ($breakword == 0)
			echo "checked='checked'";
		echo "> " . __('Off', 'protocolsmanager') . "</label></td></tr>";
		
		// Added htmlescape for XSS protection
		echo "<tr><td colspan='3' class='p-0 pt-2'>";
		echo "<div class='subheader d-flex align-items-center gap-2 mb-1'>"
		    . "<i class='ti ti-file-description text-blue'></i>"
		    . "<span>" . __('Document content', 'protocolsmanager') . "</span>"
		    . "</div>";
		echo "<hr class='mt-0 mb-1'></td></tr>";
		echo "<tr><td>".__('City')."</td><td colspan='2'><input type='text' name='city' style='width:80%;' value='" . htmlescape($city) . "'></td></tr>";
		echo "<tr><td>".__('Upper Content', 'protocolsmanager')."</td><td colspan='2' class='middle'><textarea style='width:80%; height:100px;' cols='50' rows'8' name='template_uppercontent'>" . htmlescape($template_uppercontent) . "</textarea></td></tr>";
		echo "<tr><td></td><td colspan='2'><small class='text-muted'>".__('You can use {owner} or {admin} here.', 'protocolsmanager')."</small></td></tr>";
		echo "<tr><td>".__('Content')."</td><td colspan='2' class='middle'><textarea style='width:80%; height:100px;' cols='50' rows'8' name='template_content'>" . htmlescape($template_content) . "</textarea></td></tr>";
		echo "<tr><td></td><td colspan='2'><small class='text-muted'>".__('You can use {owner} or {admin} here.', 'protocolsmanager')."</small></td></tr>";
		echo "<tr><td>".__('Footer', 'protocolsmanager')."</td><td class='middle' colspan='2'><textarea style='width:80%; height:100px;' cols='45' rows'4' name='footer_text'>" . htmlescape($template_footer) . "</textarea></td></tr>";	
		echo "<tr><td>".__('Orientation')."</td><td colspan='2'><select name='orientation' style='width:150px'>";
			foreach($orientations as $vals => $valname) {
				echo "<option value='".$vals."' ";
				if ($vals == $orientation) {
					echo " selected";
				}
				echo ">".$valname."</option>";
			}	
		echo "</select></td></tr>";

		echo "<tr><td>" . __('Date format', 'protocolsmanager') . "</td>";
		echo "<td><label><input type='radio' name='date_format' value='0' ";
		if ($date_format == 0) echo "checked='checked'";
		echo "> " . __('Numeric (DD.MM.YYYY)', 'protocolsmanager') . "</label></td>";
		echo "<td><label><input type='radio' name='date_format' value='1' ";
		if ($date_format == 1) echo "checked='checked'";
		echo "> " . __('Written date — e.g. 16 de marzo de 2026', 'protocolsmanager') . "</label></td></tr>";

		echo "<tr><td>".__('Serial number')."</td><td><label><input type='radio' name='serial_mode' value='1' ";
		if ($serial_mode == 1)
			echo "checked='checked'";
		echo ">, " . __('Serial and inventory number in separate columns', 'protocolsmanager') . "</label></td>";
		echo "<td><label><input type='radio' name='serial_mode' value='2' ";
		if ($serial_mode == 2)
			echo "checked='checked'";
		echo ">, " . __('Serial or inventory number (if serial is empty)', 'protocolsmanager') . "</label></td></tr>";
		echo "<tr><td colspan='3' class='p-0 pt-2'>";
		echo "<div class='subheader d-flex align-items-center gap-2 mb-1'>"
		    . "<i class='ti ti-photo text-blue'></i>"
		    . "<span>" . __('Logo & appearance', 'protocolsmanager') . "</span>"
		    . "</div>";
		echo "<hr class='mt-0 mb-1'></td></tr>";
		echo "<tr><td>".__('Logo')."</td><td colspan='2'><input type='file' id='pm_logo_input' name='logo' accept='image/png, image/jpeg'>"; // id for JS auto-fill
		if (isset($logo) && $logo != '') {
			$full_img_name = GLPI_PICTURE_DIR . '/' . $logo;
			if (file_exists($full_img_name)) {
				$img_type = pathinfo($full_img_name, PATHINFO_EXTENSION);
				$img_data = file_get_contents($full_img_name);
				$base64 = 'data:image/'.$img_type.';base64,'.base64_encode($img_data);
				$img_delete = true;
	
				// Construire la balise img avec les dimensions si définies
				$width_attr = $logo_width != '' ? "width='".intval($logo_width)."'": "style='height:50px; width:auto;'";
				$height_attr = $logo_height != '' ? "height='".intval($logo_height)."'" : "";
	
				// Prioriser l'attribut width et height s'ils existent, sinon style par défaut
				if ($logo_width != '' || $logo_height != '') {
					// Si au moins un est défini, on utilise width et height
					$img_tag = "<img src='".$base64."' ";
					if ($logo_width != '') {
						$img_tag .= "width='".intval($logo_width)."' ";
					}
					if ($logo_height != '') {
						$img_tag .= "height='".intval($logo_height)."' ";
					}
					$img_tag .= "/>";
				} else {
					$img_tag = "<img src='".$base64."' style='height:50px; width:auto;'/>";
				}
	
				echo "&nbsp;&nbsp;".$img_tag;
				// Added htmlescape for XSS protection
				echo "&nbsp;&nbsp;<input type='checkbox' name='img_delete' value='" . htmlescape($img_delete) . "'>&nbsp ".__('Delete')." ".__('File');
			}
		}
		echo "</td></tr>";
	
		// Nouveau champ largeur logo
		// Added htmlescape for XSS protection
		echo "<tr><td>".__('Logo width (px)', 'protocolsmanager')."</td><td colspan='2'><input type='number' id='pm_logo_width' min='0' name='logo_width' value='" . htmlescape($logo_width) . "' style='width:80px;'></td></tr>";
	
		// Nouveau champ hauteur logo
		// Added htmlescape for XSS protection
		echo "<tr><td>".__('Logo height (px)', 'protocolsmanager')."</td><td colspan='2'><input type='number' id='pm_logo_height' min='0' name='logo_height' value='" . htmlescape($logo_height) . "' style='width:80px;'></td></tr>";
	
		// ... suite du formulaire ...
		echo "<tr><td colspan='3' class='p-0 pt-2'>";
		echo "<div class='subheader d-flex align-items-center gap-2 mb-1'>"
		    . "<i class='ti ti-mail text-blue'></i>"
		    . "<span>" . __('Email settings', 'protocolsmanager') . "</span>"
		    . "</div>";
		echo "<hr class='mt-0 mb-1'></td></tr>";
		echo "<tr><td>".__('Enable email autosending', 'protocolsmanager')."</td><td><label><input type='radio' name='email_mode' value='1'";
		if ($email_mode == 1)
			echo "checked='checked'";
		echo "> " . __('On', 'protocolsmanager') . "</label></td>";
		echo "<td><label><input type='radio' name='email_mode' value='2'";
		if ($email_mode == 2)
			echo "checked='checked'";
		echo "> " . __('Off', 'protocolsmanager') . "</label></td></tr>";
		echo "<tr><td>".__('Email template', 'protocolsmanager')."</td><td colspan='2'><select name='email_template' required style='width:150px'>";
			
			// Updated DB->request syntax
			foreach ($DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig']) as $list) {
				$selected = ((int)$list['id'] === (int)$email_template) ? ' selected' : '';
				echo '<option value="' . htmlescape($list['id']) . '"' . $selected . '>'
					. htmlescape($list['tname']) . '</option>';
			}
		echo "</select></td></tr>";
		/**
		 *
		 */
		echo "<tr><td>".__('Select who should generate the pdf', 'protocolsmanager')."</td>";
		
		if($author_state == 2)
		{
			echo "<td><label><input type='radio' name='author_state' value='1'> ".__('The user who generates the document', 'protocolsmanager')."</label></td>";
			// Added htmlescape for XSS protection
			echo "<td><input type='radio' name='author_state' value='2' checked='checked'> <input type='text' name='author_name' value='" . htmlescape($author_name) . "'/></td>";

		}
		else {
			echo "<td><label><input type='radio' name='author_state' value='1' checked='checked'> ".__('The user who generates the document', 'protocolsmanager')."</label></td>";
			// Added htmlescape for XSS protection
			echo "<td><input type='radio' name='author_state' value='2'> <input type='text' name='author_name' value='" . htmlescape($author_name) . "'/></td>";
		}

		echo "</tr>";
		/**
		*
		*/
	
		echo "</table>";
		echo "</div>"; // card-body
		echo "<div class='card-footer d-flex justify-content-end gap-2'>";
		// Cancel
		echo "<form name='cancelform' action='config.form.php' method='post' class='m-0'>";
		echo "<button type='submit' name='cancel' class='btn btn-ghost-secondary'>"
		    . "<i class='ti ti-x me-1'></i>" . __('Cancel')
		    . "</button>";
		Html::closeForm();
		// Save
		echo "<button type='submit' name='save' class='btn btn-primary'>"
		    . "<i class='ti ti-device-floppy me-1'></i>" . __('Save')
		    . "</button>";
		Html::closeForm();
		echo "</div>"; // card-footer
		echo "</div>"; // card
		echo "</div>"; // center
		echo "<br>";
		// ── Auto-fill logo dimensions when a file is selected ────────
		echo "<script>\n"
		    . "(function () {\n"
		    . "  var inp = document.getElementById('pm_logo_input');\n"
		    . "  if (!inp) return;\n"
		    . "  inp.addEventListener('change', function () {\n"
		    . "    var file = this.files[0];\n"
		    . "    if (!file) return;\n"
		    . "    var reader = new FileReader();\n"
		    . "    reader.onload = function (e) {\n"
		    . "      var img = new Image();\n"
		    . "      img.onload = function () {\n"
		    . "        var w = document.getElementById('pm_logo_width');\n"
		    . "        var h = document.getElementById('pm_logo_height');\n"
		    . "        // Enforce 10:1 aspect ratio (width:height) as per plugin spec.\n"
		    . "        var ratio10to1h = Math.round(img.naturalWidth / 10);\n"
		    . "        if (w) w.value = img.naturalWidth;\n"
		    . "        if (h) h.value = ratio10to1h > 0 ? ratio10to1h : 1;\n"
		    . "      };\n"
		    . "      img.src = e.target.result;\n"
		    . "    };\n"
		    . "    reader.readAsDataURL(file);\n"
		    . "  });\n"
		    . "})();\n"
		    . "</script>\n";
		self::showConfigs();
	}
	
	
	static function DisplayContentEmail() {
		global $DB, $CFG_GLPI;
		
		// ── Tab navigation (GLPI nav-tabs) ──────────────────────────────
		echo "<ul class='nav nav-tabs mb-3'>";
		// Tab 1 — Templates
		echo "<li class='nav-item'>";
		echo "<form action='config.form.php' method='post' class='m-0'>";
		echo "<input type='hidden' name='menu_mode' value='t'>";
		echo "<button type='submit' name='template_settings' class='nav-link'>"
		    . "<i class='ti ti-template me-1'></i>"
		    . htmlescape(__('Templates settings', 'protocolsmanager'))
		    . "</button>";
		Html::closeForm();
		echo "</li>";
		// Tab 2 — Email (active)
		echo "<li class='nav-item'>";
		echo "<form action='config.form.php' method='post' class='m-0'>";
		echo "<input type='hidden' name='menu_mode' value='e'>";
		echo "<button type='submit' name='email_settings' class='nav-link active'>"
		    . "<i class='ti ti-mail me-1'></i>"
		    . htmlescape(__('Email settings', 'protocolsmanager'))
		    . "</button>";
		Html::closeForm();
		echo "</li>";
		echo "</ul>";

		if (isset($_POST["email_edit_id"])) {
			
			$email_edit_id = $_POST['email_edit_id'];

			// Updated DB->request syntax
			$req = $DB->request([
				'FROM' => 'glpi_plugin_protocolsmanager_emailconfig',
				'WHERE' => ['id' => $email_edit_id ]
			]);			
				
			if ($row = $req->current()) {
				$tname = $row["tname"];
				$send_user = $row["send_user"];
				$email_subject = $row["email_subject"];
				$email_content = $row["email_content"];
				$recipients = $row["recipients"];
			}
		} else {
			$tname = '';
			$send_user = 2;
			$email_subject = '';
			$email_content = '';
			$recipients = '';
			$email_edit_id = 0;
		}
		
		//email template edit
		echo "<div class='center'>";
		echo "<form name ='email_template_edit' action='config.form.php' method='post' enctype='multipart/form-data'>";
		
		echo "<div class='card mb-3'>";
		echo "<div class='card-header d-flex align-items-center gap-2'>"
		    . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-mail fs-4'></i></span>"
		    . "<h3 class='card-title mb-0'>"
		    . (__($email_edit_id ? 'Edit' : 'Create') . ' ' . __('email template', 'protocolsmanager'))
		    . "</h3>"
		    . "<a href='https://github.com/CanMik/protocolsmanager/wiki/Email-sending-configuration' target='_blank' "
		    . "class='ms-auto btn btn-info d-inline-flex align-items-center gap-1'>"
		    . "<i class='ti ti-help-circle me-1'></i>" . __('Help', 'protocolsmanager') . "</a>"
		    . "</div>";
		echo "<div class='card-body'>";
		echo "<table class='tab_cadre_fixe'>";
		// Added htmlescape for XSS protection
		echo "<tr><td>".__('Template name', 'protocolsmanager')."*</td><td colspan='2' class='middle'><input type='text' class='eboxes' name='tname' style='width:80%;' value='" . htmlescape($tname) . "'></td></tr>";
		echo "<tr><td>".__('Send to user', 'protocolsmanager')."</td><td><label><input type='radio' name='send_user' value='1' class='eboxes' ";
		if ($send_user == 1)
			echo "checked='checked'";
		echo "> " . __('Send to user', 'protocolsmanager') . "</label></td>";
		echo "<td><label><input type='radio' name='send_user' value='2' class='eboxes' ";
		if ($send_user == 2)
			echo "checked='checked'";
		echo "> " . __('Don\'t send to user', 'protocolsmanager') . "</label></td></tr>";
		// Added htmlescape for XSS protection
		echo "<tr><td>".__('Email content', 'protocolsmanager')."*</td><td colspan='2' class='middle'><textarea style='width:80%; height:100px;' class='eboxes' cols='50' rows'8' name='email_content'>" . htmlescape($email_content) . "</textarea></td></tr>";
		echo "<tr><td>".__('Email subject', 'protocolsmanager')."*</td><td colspan='2' class='middle'><input type='text' class='eboxes' name='email_subject' style='width:80%;' value='" . htmlescape($email_subject) . "'></td></tr>";
		echo "<tr><td>".__('Add emails - use ; to separate', 'protocolsmanager')."*</td><td colspan='2' class='middle'><textarea style='width:80%; height:100px;' class='eboxes' cols='50' rows '8' name='recipients'>" . htmlescape($recipients) . "</textarea></td></tr>";
		echo "</table>";
		// Added htmlescape for XSS protection
		echo "<input type='hidden' name='email_edit_id' value='" . htmlescape($email_edit_id) . "'>";
		echo "</table>";
		echo "</div>"; // card-body
		echo "<div class='card-footer d-flex justify-content-end gap-2'>";
		echo "<form name='cancelform' action='config.form.php' method='post' class='m-0'>";
		echo "<button type='submit' name='cancel_email' class='btn btn-ghost-secondary'>"
		    . "<i class='ti ti-x me-1'></i>" . __('Cancel')
		    . "</button>";
		Html::closeForm();
		echo "<button type='submit' id='email_submit' name='save_email' class='btn btn-primary'>"
		    . "<i class='ti ti-device-floppy me-1'></i>" . __('Save')
		    . "</button>";
		Html::closeForm();
		echo "</div>"; // card-footer
		echo "</div>"; // card
		echo "</div>"; // center
		echo "<br>";
		self::showEmailConfigs();
	}
	
	static function saveConfigs() {
		global $DB, $CFG_GLPI;
		
		if (empty($_POST["template_name"]) || empty($_POST["email_template"]) || empty($_POST["title"])) {
			Session::addMessageAfterRedirect(__('Fill mandatory fields', 'protocolsmanager'), false, WARNING);
		} else {

			// No addslashes() needed here. GLPI 11 DB layer handles sanitization.
			$template_name = $_POST['template_name'];
			$title = $_POST['title'];
			$font = $_POST["font"];
			$fontsize = $_POST["fontsize"];
			$serial_mode   = $_POST["serial_mode"];
			$orientation   = $_POST["orientation"];
			$date_format   = isset($_POST["date_format"]) ? (int)$_POST["date_format"] : 0;

			if(isset($_POST["template_uppercontent"])) {
				$template_uppercontent = $_POST['template_uppercontent'];
			}
			
			if(isset($_POST["template_content"])) {
				$template_content = $_POST['template_content'];
			}

			if(isset($_POST["footer_text"])) {
				$template_footer = $_POST['footer_text'];
			}

			if(isset($_POST["city"])) {
				$city = $_POST["city"];
			}

			if(isset($_POST["mode"])) {
				$mode = $_POST["mode"];
			}

			if(isset($_POST["breakword"])) {
				$breakword = $_POST["breakword"];
			}

			if(isset($_POST["email_mode"])) {
				$email_mode = $_POST["email_mode"];
			}

			if(isset($_POST["email_template"])) {
				$email_template = $_POST["email_template"];
			}
			
			// Read existing logo path before uploading — to delete it if replaced.
			$existing_logo = null;
			if (!empty($mode)) {
				$existing_row = $DB->request(['SELECT' => ['logo'], 'FROM' => 'glpi_plugin_protocolsmanager_config', 'WHERE' => ['id' => $mode]])->current();
				$existing_logo = $existing_row['logo'] ?? null;
			}
			$full_img_name = self::uploadImage();
			// If a new logo was uploaded and an old one existed, delete the old file.
			if ($full_img_name && $existing_logo && $existing_logo !== $full_img_name) {
				$old_path = GLPI_PICTURE_DIR . '/' . $existing_logo;
				if (file_exists($old_path)) { @unlink($old_path); }
			}

			if (isset($_POST['img_delete'])) {
				
				$DB->update('glpi_plugin_protocolsmanager_config', [
						'logo' => $full_img_name
					], [
						'id' => $mode
					]
				);
			}

			if (isset($_POST["author_name"])) {
				$author_name = $_POST["author_name"];

			}
			if (isset($_POST["author_state"])) {
				$author_state = $_POST["author_state"];

			}


			if (isset($_POST["logo_width"])) {
				$logo_width = intval($_POST["logo_width"]);
			}
			if (isset($_POST["logo_height"])) {
				$logo_height = intval($_POST["logo_height"]);
			}


			// TODO : concaténé quand les champs sont vides
			
			//if new template
			if ($mode == 0) {
				
				$DB->insert('glpi_plugin_protocolsmanager_config', [
					'name' => $template_name,
					'title' => $title,
					'upper_content' => $template_uppercontent,
					'content' => $template_content,
					'footer' => $template_footer,
					'logo' => $full_img_name,
					'logo_width'    => $logo_width,
					'logo_height'   => $logo_height,
					'font' => $font,
					'fontsize' => $fontsize,
					'city' => $city,
					'serial_mode'  => $serial_mode,
					'orientation'  => $orientation,
					'breakword'    => $breakword,
					'email_mode'   => $email_mode,
					'email_template' => $email_template,
					'author_name'  => $author_name,
					'author_state' => $author_state,
					'date_format'  => $date_format,
					]
				);
			}
			
			// Edit existing template — single authoritative UPDATE.
			if ($mode != 0) {
				// Sanitize email_template id.
				$email_template = preg_replace('/[^A-Za-z0-9\-]/', '', $email_template);

				$updateData = [
					'name'           => $template_name,
					'title'          => $title,
					'content'        => $template_content        ?? '',
					'upper_content'  => $template_uppercontent   ?? '',
					'footer'         => $template_footer          ?? '',
					'logo_width'     => $logo_width,
					'logo_height'    => $logo_height,
					'font'           => $font,
					'fontsize'       => $fontsize,
					'city'           => $city                     ?? '',
					'serial_mode'    => $serial_mode,
					'orientation'    => $orientation,
					'breakword'      => $breakword,
					'email_mode'     => $email_mode               ?? 2,
					'email_template' => $email_template,
					'author_name'    => $author_name,
					'author_state'   => $author_state,
					'date_format'    => $date_format,
				];
				// Only overwrite logo when a new file was uploaded.
				if (isset($full_img_name)) {
					$updateData['logo'] = $full_img_name;
				}
				$DB->update('glpi_plugin_protocolsmanager_config', $updateData, ['id' => $mode]);
			}
			
			Session::addMessageAfterRedirect(__('Configuration saved.', 'protocolsmanager'), false, INFO);
		}		
	
	}
	
	static function saveEmailConfigs() {
		global $DB, $CFG_GLPI;
		
		if (empty($_POST["email_subject"]) || empty($_POST["email_content"]) || empty($_POST["recipients"]) || empty($_POST["tname"])) {
			Session::addMessageAfterRedirect(__('Fill mandatory fields', 'protocolsmanager'), false, WARNING);
		} else {
			
			// No addslashes() needed here
			$tname = $_POST["tname"];
			$send_user = $_POST["send_user"];
			$email_subject = $_POST["email_subject"];
			$email_content = $_POST["email_content"];
			$recipients = $_POST["recipients"];
			$email_edit_id = $_POST["email_edit_id"];
			
			if($email_edit_id == 0) {
				
				$DB->insert('glpi_plugin_protocolsmanager_emailconfig', [
					'tname' => $tname,
					'send_user' => $send_user,
					'email_subject' => $email_subject,
					'email_content' => $email_content,
					'recipients' => $recipients
					]
				);
			}
				
			if($email_edit_id != 0) {
				
				$DB->update('glpi_plugin_protocolsmanager_emailconfig', [
					'tname' => $tname,
					'send_user' => $send_user,
					'email_subject' => $email_subject,
					'email_content' => $email_content,
					'recipients' => $recipients
					], [
					'id' => $email_edit_id
					]
				);
				
			}
			
			Session::addMessageAfterRedirect(__('Configuration saved.', 'protocolsmanager'), false, INFO);
		}

	}
	

	static function showConfigs() {
		global $DB, $CFG_GLPI;
		$configs = [];
		
		echo "<div class='card mt-3' id='show_configs'>";
		echo "<div class='card-header d-flex align-items-center gap-2'>"
		    . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-list fs-4'></i></span>"
		    . "<h3 class='card-title mb-0'>" . __('Templates') . "</h3>"
		    . "</div>";
		echo "<div class='table-responsive'>";
		echo "<table class='table table-vcenter card-table'>";
		echo "<thead><tr>"
		    . "<th>" . __('Name') . "</th>"
		    . "<th class='w-1 text-end'>" . __('Action') . "</th>"
		    . "</tr></thead><tbody>";
		
		// Updated DB->request syntax
		foreach ($DB->request(['FROM' => 'glpi_plugin_protocolsmanager_config']) as $config_data => $configs) {
				
				echo "<tr><td>" . htmlescape($configs['name']) . "</td>";
				$conf_id = $configs['id'];
				echo "<td class='text-end' style='white-space:nowrap;'>";
				// Edit
				echo "<form method='post' action='config.form.php' class='d-inline me-1'>";
				echo "<input type='hidden' value='" . htmlescape($conf_id) . "' name='edit_id'>";
				echo "<input type='hidden' name='menu_mode' value='t'>";
				echo "<button type='submit' name='edit' class='btn btn-primary'>"
				    . "<i class='ti ti-edit me-1'></i>" . __('Edit')
				    . "</button>";
				Html::closeForm();
				// Delete
				echo "<form method='post' action='config.form.php' class='d-inline'>";
				echo "<input type='hidden' value='" . htmlescape($conf_id) . "' name='conf_id'>";
				echo "<button type='submit' name='delete' class='btn btn-danger'"
				    . " onclick=\"return confirm('" . addslashes(__('Delete this template?', 'protocolsmanager')) . "')\">"
				    . "<i class='ti ti-trash me-1'></i>" . __('Delete')
				    . "</button>";
				Html::closeForm();
				echo "</td></tr>";				
			}
		echo "</tbody></table></div></div>"; // table-responsive + card
	}
	
	static function showEmailConfigs() {
		global $DB, $CFG_GLPI;
		$emailconfigs = [];
		
		echo "<div class='card mt-3' id='show_emailconfigs'>";
		echo "<div class='card-header d-flex align-items-center gap-2'>"
		    . "<span class='badge bg-blue-lt p-2 me-1'><i class='ti ti-mail fs-4'></i></span>"
		    . "<h3 class='card-title mb-0'>" . __('Email templates', 'protocolsmanager') . "</h3>"
		    . "</div>";
		echo "<div class='table-responsive'>";
		echo "<table class='table table-vcenter card-table'>";
		echo "<thead><tr>"
		    . "<th>" . __('Name') . "</th>"
		    . "<th>" . __('Recipients') . "</th>"
		    . "<th class='w-1 text-end'>" . __('Action') . "</th>"
		    . "</tr></thead><tbody>";
		
		// Updated DB->request syntax
		foreach ($DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig']) as $configs_data => $emailconfigs) {
				
				$email_conf_id = $emailconfigs['id'];
				echo "<tr><td>" . htmlescape($emailconfigs['tname']) . "</td>";
				echo "<td>" . htmlescape($emailconfigs['recipients']) . "</td>";
				echo "<td class='text-end' style='white-space:nowrap;'>";
				echo "<form method='post' action='config.form.php' class='d-inline me-1'>";
				echo "<input type='hidden' value='" . htmlescape($email_conf_id) . "' name='email_edit_id'>";
				echo "<input type='hidden' name='menu_mode' value='e'>";
				echo "<button type='submit' name='email_edit' class='btn btn-primary'>"
				    . "<i class='ti ti-edit me-1'></i>" . __('Edit')
				    . "</button>";
				Html::closeForm();
				echo "<form method='post' action='config.form.php' class='d-inline'>";
				echo "<input type='hidden' value='" . htmlescape($email_conf_id) . "' name='email_conf_id'>";
				echo "<button type='submit' name='delete_email' class='btn btn-danger'"
				    . " onclick=\"return confirm('" . addslashes(__('Delete this email template?', 'protocolsmanager')) . "')\">"
				    . "<i class='ti ti-trash me-1'></i>" . __('Delete')
				    . "</button>";
				Html::closeForm();
				echo "</td></tr>";				
			}
		echo "</tbody></table></div></div>"; // table-responsive + card
	}
	
	static function uploadImage() {
		global $DB, $CFG_GLPI;
		
		if($_FILES['logo']['name']) {
			
			if($_FILES['logo']['error'] != UPLOAD_ERR_FORM_SIZE) {
			
				if (!$_FILES['logo']['error']) {
					
					// Validate real MIME from file content — never trust $_FILES['type'].
					$finfo    = new \finfo(FILEINFO_MIME_TYPE);
					$realMime = $finfo->file($_FILES['logo']['tmp_name']);
					if ($realMime === 'image/jpeg' || $realMime === 'image/png') {
						$ext           = ($realMime === 'image/png') ? 'png' : 'jpg';
						$full_img_name = 'logo' . uniqid('', true) . '.' . $ext;
						$img_path      = GLPI_PICTURE_DIR . '/' . $full_img_name;
						move_uploaded_file($_FILES['logo']['tmp_name'], $img_path);
						return $full_img_name;
					} else {
						Session::addMessageAfterRedirect(__('Wrong file type. Only JPG and PNG files are accepted.', 'protocolsmanager'), false, WARNING);
					}
				} else {
					Session::addMessageAfterRedirect(__('Unknown error'), false, WARNING);
				}
			} else {
				Session::addMessageAfterRedirect(__('File size too large.', 'protocolsmanager'), false, WARNING);
			}
		}

	}
	
	static function deleteConfigs() {
		global $DB;
		
		$conf_id = (int)($_POST['conf_id'] ?? 0);
		if ($conf_id <= 0) { return; }
		$row = $DB->request(['FROM' => 'glpi_plugin_protocolsmanager_config', 'WHERE' => ['id' => $conf_id]])->current();
		if (!$row) {
			Session::addMessageAfterRedirect(__('Template not found.', 'protocolsmanager'), false, ERROR);
			return;
		}
		// Delete logo file from disk before removing the DB row.
		if (!empty($row['logo'])) {
			$logoPath = GLPI_PICTURE_DIR . '/' . $row['logo'];
			if (file_exists($logoPath)) {
				@unlink($logoPath);
			}
		}
		$DB->delete('glpi_plugin_protocolsmanager_config', ['id' => $conf_id]);
	}
	
	
	static function deleteEmailConfigs() {
		global $DB;
		
		$email_conf_id = (int)($_POST['email_conf_id'] ?? 0);
		if ($email_conf_id <= 0) { return; }
		if (!$DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig', 'WHERE' => ['id' => $email_conf_id]])->current()) {
			Session::addMessageAfterRedirect(__('Email template not found.', 'protocolsmanager'), false, ERROR);
			return;
		}
		$DB->delete('glpi_plugin_protocolsmanager_emailconfig', ['id' => $email_conf_id]);
	}

}

?>