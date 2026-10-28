<?php

class PluginProtocolsmanagerConfig extends CommonDBTM {
	
	
	function showFormProtocolsmanager() {
		global $CFG_GLPI, $DB;
		$plugin_conf = self::checkRights();

		if ($plugin_conf == 'w') {	
			self::displayContent();	
		} else {
			echo "<div align='center'><br><img src='".$CFG_GLPI['root_doc']."/pics/warning.png'><br>".__("Access denied")."</div>";
		}
	}
	
	
	static function checkRights() {
		global $DB;
		$active_profile = $_SESSION['glpiactiveprofile']['id'];
		
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
		$section = (string)($_GET['section'] ?? $_POST['section'] ?? 'inventory');
		$sections = ['inventory', 'commission', 'disposal', 'branding', 'email'];
		if (!in_array($section, $sections, true)) {
			$section = 'inventory';
		}
		self::displaySettingsNavigation($section);
		switch ($section) {
			case 'commission':
			case 'disposal':
				self::displayPvTemplateSettings($section);
				break;
			case 'branding':
				self::displayBrandingSettings();
				break;
			case 'email':
				self::displayContentEmail();
				break;
			default:
				self::displayContentConfig();
		}
		echo '</div>';
	}

	private static function displaySettingsNavigation(string $active): void
	{
		$sections = [
			'inventory' => __('Modèles d’inventaire', 'protocolsmanager'),
			'commission' => __('PV de mise en service', 'protocolsmanager'),
			'disposal' => __('PV de mise au rebut', 'protocolsmanager'),
			'branding' => __('Identité visuelle', 'protocolsmanager'),
			'email' => __('E-mails', 'protocolsmanager'),
		];
		echo "<div class='container-fluid px-0 protocolsmanager-settings'>";
		echo "<div class='mb-3'><h1 class='h2 mb-1'>" . htmlescape(__('Configuration de Protocols Manager', 'protocolsmanager')) . "</h1>";
		echo "<p class='text-muted mb-0'>" . htmlescape(__('Gérez les modèles d’inventaire, les procès-verbaux et leur présentation.', 'protocolsmanager')) . '</p></div>';
		echo "<nav class='nav nav-tabs mb-4' aria-label='" . htmlescape(__('Sections de configuration', 'protocolsmanager')) . "'>";
		foreach ($sections as $key => $label) {
			$class = $key === $active ? 'nav-link active' : 'nav-link';
			$current = $key === $active ? " aria-current='page'" : '';
			echo "<a class='" . $class . "' href='config.form.php?section=" . rawurlencode($key) . "'" . $current . '>' . htmlescape($label) . '</a>';
		}
		echo '</nav>';
	}

	private static function displayPvTemplateSettings(string $type): void
	{
		$defaults = PluginProtocolsmanagerPvtemplate::defaults($type);
		$stored_template = PluginProtocolsmanagerPvtemplate::load($type);
		$template = array_replace_recursive($defaults, $stored_template);
		foreach (['states', 'cities', 'methods', 'checks', 'validators'] as $list_key) {
			if (is_array($stored_template['form'] ?? null) && array_key_exists($list_key, $stored_template['form']) && is_array($stored_template['form'][$list_key])) {
				$template['form'][$list_key] = $stored_template['form'][$list_key];
			}
		}
		if (array_key_exists('custom_chapters', $stored_template) && is_array($stored_template['custom_chapters'])) {
			$template['custom_chapters'] = $stored_template['custom_chapters'];
		}
		if ($type === 'disposal') {
			$chapters_by_key = [];
			foreach ($template['chapters'] as $chapter) {
				$key = (string)($chapter['key'] ?? '');
				if ($key !== '' && !isset($chapters_by_key[$key])) {
					$chapters_by_key[$key] = $chapter;
				}
			}
			$normalized_chapters = [];
			foreach ($defaults['chapters'] as $default_chapter) {
				$key = (string)$default_chapter['key'];
				$chapter = $chapters_by_key[$key] ?? $default_chapter;
				if ($key === 'model_photo') {
					$chapter['title'] = 'Annexes';
					$content = trim((string)($chapter['content'] ?? ''));
					if (!str_contains($content, '{photo}')) {
						$chapter['content'] = $content === '' ? '{photo}' : $content . "\n{photo}";
					}
				} elseif ($key === 'signature') {
					$chapter['title'] = 'Signatures';
				}
				$normalized_chapters[] = $chapter;
			}
			$template['chapters'] = $normalized_chapters;
		} else {
			foreach ($template['chapters'] as &$chapter) {
				if (($chapter['key'] ?? '') === 'model_photo') {
					$chapter['title'] = 'Annexes';
					$content = trim((string)($chapter['content'] ?? ''));
					if (!str_contains($content, '{photo}')) {
						$chapter['content'] = $content === '' ? '{photo}' : $content . "\n{photo}";
					}
				}
			}
			unset($chapter);
		}
		if (in_array($type, ['commission', 'disposal'], true)) {
			$template['custom_chapters'] = array_values(array_filter(
				$template['custom_chapters'],
			static function ($custom_chapter) use ($type): bool {
					$title = trim((string)($custom_chapter['title'] ?? ''));
					if ($type === 'disposal') {
						return !in_array(strtolower($title), ['annexe', 'annexes', 'photo', 'photos', 'signature', 'signatures'], true)
							&& !preg_match('/^annexe\s*1\b/i', $title);
					}
					return !preg_match('/^annexe\s*1\b/i', $title);
				}
			));
		}
		$title = $type === 'commission' ? __('Mise en service', 'protocolsmanager') : __('Mise au rebut', 'protocolsmanager');
		$help = __('Ces réglages déterminent les champs proposés à la création et les chapitres du PDF généré.', 'protocolsmanager');
		echo "<div class='mb-3'><h2 class='h3 mb-1'>" . htmlescape($title) . "</h2><p class='text-muted'>" . htmlescape($help) . '</p></div>';
		echo "<form action='config.form.php?section=" . htmlescape($type) . "' method='post' class='protocolsmanager-settings-form'>";
		if (method_exists(Session::class, 'getNewCSRFToken')) {
			echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		}
		echo Html::hidden('section', ['value' => $type]);
		echo "<section class='card mb-3'><div class='card-header'><h3 class='h5 mb-0'>" . htmlescape(__('Présentation du document', 'protocolsmanager')) . '</h3></div><div class="card-body">';
		echo "<label class='form-label' for='pv-title'>" . htmlescape(__('Titre du document', 'protocolsmanager')) . "</label><input class='form-control' id='pv-title' name='template[title]' maxlength='255' required value='" . htmlescape($template['title'] ?? '') . "'>";
		echo '</div></section>';

		echo "<section class='card mb-3'><div class='card-header'><h3 class='h5 mb-0'>" . htmlescape(__('Formulaire de création', 'protocolsmanager')) . '</h3></div><div class="card-body">';
		$form_fields = $template['form'] ?? [];
		$simple_fields = $type === 'commission'
			? ['effective_date', 'planned_use', 'city', 'observations']
			: ['disposal_date', 'reason', 'method'];
		foreach ($simple_fields as $key) {
			$field = $form_fields[$key] ?? ['label' => $key, 'enabled' => true];
			$id = 'pv-field-' . $key;
			echo "<div class='row align-items-center g-2 py-2 border-bottom'><div class='col-md-3'><label class='form-label mb-md-0' for='" . htmlescape($id) . "'>" . htmlescape(self::pvFieldTitle($key)) . '</label></div>';
			echo "<div class='col-md-7'><input class='form-control' id='" . htmlescape($id) . "' name='template[form][" . htmlescape($key) . "][label]' maxlength='160' value='" . htmlescape($field['label'] ?? '') . "'></div>";
			echo "<div class='col-md-2'><input type='hidden' name='template[form][" . htmlescape($key) . "][enabled]' value='0'><label class='form-check'><input class='form-check-input' type='checkbox' name='template[form][" . htmlescape($key) . "][enabled]' value='1'" . (!empty($field['enabled']) ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape(__('Afficher', 'protocolsmanager')) . '</span></label></div></div>';
		}

		if ($type === 'commission') {
			self::displayCheckboxListEditor($type, 'states', $form_fields['states'] ?? []);
			self::displayChoiceListEditor('validators', $form_fields['validators'] ?? [], __('Validateurs du PV', 'protocolsmanager'), __('Ajouter un validateur', 'protocolsmanager'), __('Rôle du validateur', 'protocolsmanager'));
			self::displayChoiceListEditor('cities', $form_fields['cities'] ?? [], __('Lieux proposés', 'protocolsmanager'), __('Ajouter un lieu', 'protocolsmanager'), __('Lieu', 'protocolsmanager'));
		} else {
			self::displayChoiceListEditor('methods', $form_fields['methods'] ?? [], __('Modes proposés', 'protocolsmanager'), __('Ajouter un mode', 'protocolsmanager'), __('Libellé du mode', 'protocolsmanager'));
			self::displayChoiceListEditor('validators', $form_fields['validators'] ?? [], __('Signataires du PV', 'protocolsmanager'), __('Ajouter un signataire', 'protocolsmanager'), __('Rôle du signataire', 'protocolsmanager'));
			echo '<p class="form-text">' . htmlescape(__('Le choix libellé « Autre » demande une précision dans le formulaire de génération.', 'protocolsmanager')) . '</p>';
			self::displayCheckboxListEditor($type, 'checks', $form_fields['checks'] ?? []);
		}
		echo '</div></section>';

		echo "<section class='card mb-3'><div class='card-header'><h3 class='h5 mb-0'>" . htmlescape(__('Chapitres du PDF', 'protocolsmanager')) . '</h3></div><div class="card-body">';
		echo '<p class="form-text">' . htmlescape(__('Activez ou masquez chaque chapitre, puis modifiez son titre et le texte introductif. Les tableaux et valeurs dynamiques restent calculés à partir du formulaire et du matériel.', 'protocolsmanager')) . '</p>';
		echo '<p class="form-text">' . htmlescape(__('Dans le chapitre Annexes, le jeton {photo} insère la photo du modèle ; il est retiré si aucune photo n’est disponible.', 'protocolsmanager')) . '</p>';
		$chapter_entries = [];
		foreach (($template['chapters'] ?? []) as $index => $chapter) {
			$chapter_entries[] = ['index' => $index, 'chapter' => $chapter];
		}
		$photo_position = null;
		$signature_key = $type === 'commission' ? 'validation' : 'signature';
		$signature_position = null;
		foreach ($chapter_entries as $position => $entry) {
			if (($entry['chapter']['key'] ?? '') === 'model_photo') {
				$photo_position = $position;
			}
			if (($entry['chapter']['key'] ?? '') === $signature_key) {
				$signature_position = $position;
			}
		}
		if ($photo_position !== null && $signature_position !== null && $photo_position > $signature_position) {
			$photo_entry = array_splice($chapter_entries, $photo_position, 1);
			array_splice($chapter_entries, $signature_position, 0, $photo_entry);
		}
		foreach ($chapter_entries as $position => $chapter_entry) {
			$index = $chapter_entry['index'];
			$chapter = $chapter_entry['chapter'];
			$path = 'template[chapters][' . (int)$index . ']';
			echo "<fieldset class='border rounded p-3 mb-3'><legend class='float-none w-auto px-2 fs-6'>" . htmlescape(__('Chapitre', 'protocolsmanager') . ' ' . ((int)$position + 1)) . '</legend>';
			echo '<input type="hidden" name="' . htmlescape($path . '[key]') . '" value="' . htmlescape($chapter['key'] ?? '') . '">';
			echo '<label class="form-label" for="chapter-title-' . (int)$index . '">' . htmlescape(__('Titre', 'protocolsmanager')) . '</label><input class="form-control mb-2" id="chapter-title-' . (int)$index . '" name="' . htmlescape($path . '[title]') . '" maxlength="180" value="' . htmlescape($chapter['title'] ?? '') . '">';
			echo '<label class="form-label" for="chapter-content-' . (int)$index . '">' . htmlescape(__('Texte et instructions', 'protocolsmanager')) . '</label><textarea class="form-control mb-2" id="chapter-content-' . (int)$index . '" name="' . htmlescape($path . '[content]') . '" rows="3" maxlength="5000">' . htmlescape($chapter['content'] ?? '') . '</textarea>';
			echo '<input type="hidden" name="' . htmlescape($path . '[enabled]') . '" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="' . htmlescape($path . '[enabled]') . '" value="1"' . (!empty($chapter['enabled']) ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape(__('Inclure dans le PDF', 'protocolsmanager')) . '</span></label></fieldset>';
		}
		self::displayCustomChapterEditor($type, $template['custom_chapters'] ?? []);
		echo '</div></section>';
		echo "<div class='d-flex justify-content-end mb-4'><button class='btn btn-primary' type='submit' name='save_pv_template' value='1'><i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Enregistrer le modèle', 'protocolsmanager')) . '</button></div></form>';
	}

	private static function displayCustomChapterEditor(string $type, array $chapters): void
	{
		$list_id = 'pv-custom-chapters-' . $type;
		$base = 'template[custom_chapters]';
		echo "<div class='border-top pt-3 mt-4'><div class='d-flex align-items-center justify-content-between flex-wrap gap-2'><div><h4 class='h6 mb-1'>" . htmlescape(__('Chapitres personnalisés', 'protocolsmanager')) . "</h4><p class='form-text mb-0'>" . htmlescape(__('Ajoutez des chapitres libres après les sections standard du PDF.', 'protocolsmanager')) . '</p></div>';
		echo '<button class="btn btn-sm btn-outline-primary" type="submit" formnovalidate name="add_pv_chapter" value="1"><i class="ti ti-plus me-1"></i>' . htmlescape(__('Ajouter un chapitre', 'protocolsmanager')) . '</button></div>';
		echo '<div class="table-responsive mt-3"><table class="table align-middle"><thead><tr><th>' . htmlescape(__('Titre du chapitre', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Contenu', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Inclure', 'protocolsmanager')) . '</th><th><span class="visually-hidden">' . htmlescape(__('Ordre et actions', 'protocolsmanager')) . '</span></th></tr></thead><tbody id="' . htmlescape($list_id) . '">';
		$count = count($chapters);
		$position = 0;
		foreach ($chapters as $index => $chapter) {
			self::displayCustomChapterRow($base . '[' . (int)$index . ']', (int)$index, (string)($chapter['key'] ?? ''), (string)($chapter['title'] ?? ''), (string)($chapter['content'] ?? ''), !empty($chapter['enabled']), $position === 0, $position === $count - 1);
			$position++;
		}
		echo '</tbody></table></div></div>';
	}

	private static function displayCustomChapterRow(string $path, int $index, string $key, string $title, string $content, bool $enabled, bool $canMoveUp, bool $canMoveDown): void
	{
		$path = htmlescape($path);
		echo '<tr data-pv-config-row><td><input type="hidden" name="' . $path . '[key]" value="' . htmlescape($key) . '"><input class="form-control" maxlength="180" required name="' . $path . '[title]" value="' . htmlescape($title) . '" aria-label="' . htmlescape(__('Titre du chapitre', 'protocolsmanager')) . '"></td>';
		echo '<td><textarea class="form-control" rows="2" maxlength="5000" name="' . $path . '[content]" aria-label="' . htmlescape(__('Contenu du chapitre', 'protocolsmanager')) . '">' . htmlescape($content) . '</textarea></td>';
		echo '<td><input type="hidden" name="' . $path . '[enabled]" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="' . $path . '[enabled]" value="1"' . ($enabled ? ' checked' : '') . '> <span class="visually-hidden">' . htmlescape(__('Inclure dans le PDF', 'protocolsmanager')) . '</span></label></td>';
		echo '<td><div class="d-flex gap-1">';
		echo '<button class="btn btn-sm btn-outline-secondary" type="submit" formnovalidate name="move_pv_chapter" value="up:' . $index . '" aria-label="' . htmlescape(__('Monter ce chapitre', 'protocolsmanager')) . '"' . ($canMoveUp ? '' : ' disabled') . '><i class="ti ti-arrow-up" aria-hidden="true"></i></button>';
		echo '<button class="btn btn-sm btn-outline-secondary" type="submit" formnovalidate name="move_pv_chapter" value="down:' . $index . '" aria-label="' . htmlescape(__('Descendre ce chapitre', 'protocolsmanager')) . '"' . ($canMoveDown ? '' : ' disabled') . '><i class="ti ti-arrow-down" aria-hidden="true"></i></button>';
		echo '<button class="btn btn-sm btn-outline-danger" type="submit" formnovalidate name="remove_pv_row" value="' . $path . '" aria-label="' . htmlescape(__('Supprimer ce chapitre', 'protocolsmanager')) . '"><i class="ti ti-trash" aria-hidden="true"></i></button></div></td></tr>';
	}

	private static function displayCheckboxListEditor(string $type, string $list_key, array $entries): void
	{
		$title = $type === 'commission' ? __('Cases de mise en service', 'protocolsmanager') : __('Cases de contrôle de mise au rebut', 'protocolsmanager');
		$add_id = 'add-pv-check-' . $type;
		$list_id = 'pv-check-list-' . $type;
		$base = 'template[form][' . $list_key . ']';
		$next_index = $entries === [] ? 0 : max(array_map('intval', array_keys($entries))) + 1;
		echo "<div class='mt-4'><div class='d-flex align-items-center justify-content-between flex-wrap gap-2'><h4 class='h6 mb-0'>" . htmlescape($title) . '</h4>';
		echo '<button class="btn btn-sm btn-outline-primary" type="submit" formnovalidate id="' . htmlescape($add_id) . '" name="add_pv_list_entry" value="checkbox:' . htmlescape($list_key) . '"><i class="ti ti-plus me-1"></i>' . htmlescape(__('Ajouter une case', 'protocolsmanager')) . '</button></div>';
		echo '<div class="table-responsive mt-2"><table class="table align-middle"><thead><tr><th>' . htmlescape(__('Libellé de la case', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Afficher', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Cochée par défaut', 'protocolsmanager')) . '</th><th><span class="visually-hidden">' . htmlescape(__('Action', 'protocolsmanager')) . '</span></th></tr></thead><tbody id="' . htmlescape($list_id) . '" data-next-index="' . $next_index . '">';
		foreach ($entries as $index => $entry) {
			$path = $base . '[' . (int)$index . ']';
			self::displayCheckboxRow($path, (string)($entry['key'] ?? ''), (string)($entry['label'] ?? ''), !empty($entry['enabled']), !empty($entry['checked']));
		}
		echo '</tbody></table></div>';
		echo '<template id="template-' . htmlescape($list_id) . '">';
		self::displayCheckboxRow($base . '[__INDEX__]', 'custom___INDEX__', '', true, false);
		echo '</template></div>';
	}

	private static function displayChoiceListEditor(string $list_key, array $entries, string $title, string $add_label, string $entry_label): void
	{
		$list_id = 'pv-choice-list-' . $list_key;
		$base = 'template[form][' . $list_key . ']';
		$next_index = $entries === [] ? 0 : max(array_map('intval', array_keys($entries))) + 1;
		echo "<div class='mt-4'><div class='d-flex align-items-center justify-content-between flex-wrap gap-2'><h4 class='h6 mb-0'>" . htmlescape($title) . '</h4>';
		echo '<button class="btn btn-sm btn-outline-primary" type="submit" formnovalidate name="add_pv_list_entry" value="choice:' . htmlescape($list_key) . '"><i class="ti ti-plus me-1"></i>' . htmlescape($add_label) . '</button></div>';
		echo '<div class="table-responsive mt-2"><table class="table align-middle"><thead><tr><th>' . htmlescape(__('Libellé', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Actif', 'protocolsmanager')) . '</th><th><span class="visually-hidden">' . htmlescape(__('Action', 'protocolsmanager')) . '</span></th></tr></thead><tbody id="' . htmlescape($list_id) . '" data-next-index="' . $next_index . '">';
		foreach ($entries as $index => $entry) {
			self::displayChoiceRow($base . '[' . (int)$index . ']', (string)($entry['key'] ?? ''), (string)($entry['label'] ?? ''), !empty($entry['enabled']), $entry_label);
		}
		echo '</tbody></table></div><template id="template-' . htmlescape($list_id) . '">';
		self::displayChoiceRow($base . '[__INDEX__]', 'custom-' . $list_key . '-__INDEX__', '', true, $entry_label);
		echo '</template></div>';
	}

	private static function displayChoiceRow(string $path, string $key, string $label, bool $enabled, string $entry_label): void
	{
		$path = htmlescape($path);
		echo '<tr data-pv-config-row><td><input type="hidden" name="' . $path . '[key]" value="' . htmlescape($key) . '"><input class="form-control" maxlength="160" required name="' . $path . '[label]" value="' . htmlescape($label) . '" aria-label="' . htmlescape($entry_label) . '"></td>';
		echo '<td><input type="hidden" name="' . $path . '[enabled]" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="' . $path . '[enabled]" value="1"' . ($enabled ? ' checked' : '') . '> <span class="visually-hidden">' . htmlescape(__('Actif', 'protocolsmanager')) . '</span></label></td>';
		echo '<td><button class="btn btn-sm btn-outline-danger" type="submit" formnovalidate name="remove_pv_row" value="' . $path . '" aria-label="' . htmlescape(__('Supprimer cette entrée', 'protocolsmanager')) . '"><i class="ti ti-trash" aria-hidden="true"></i></button></td></tr>';
	}

	private static function displayCheckboxRow(string $path, string $key, string $label, bool $enabled, bool $checked): void
	{
		$path = htmlescape($path);
		echo '<tr data-pv-config-row><td><input type="hidden" name="' . $path . '[key]" value="' . htmlescape($key) . '"><input class="form-control" maxlength="160" name="' . $path . '[label]" value="' . htmlescape($label) . '" aria-label="' . htmlescape(__('Libellé de la case', 'protocolsmanager')) . '"></td>';
		echo '<td><input type="hidden" name="' . $path . '[enabled]" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="' . $path . '[enabled]" value="1"' . ($enabled ? ' checked' : '') . '> <span class="visually-hidden">' . htmlescape(__('Afficher', 'protocolsmanager')) . '</span></label></td>';
		echo '<td><input type="hidden" name="' . $path . '[checked]" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="' . $path . '[checked]" value="1"' . ($checked ? ' checked' : '') . '> <span class="visually-hidden">' . htmlescape(__('Cochée par défaut', 'protocolsmanager')) . '</span></label></td>';
		echo '<td><button class="btn btn-sm btn-outline-danger" type="submit" formnovalidate name="remove_pv_row" value="' . $path . '" aria-label="' . htmlescape(__('Supprimer cette case', 'protocolsmanager')) . '"><i class="ti ti-trash" aria-hidden="true"></i></button></td></tr>';
	}

	private static function pvFieldTitle(string $key): string
	{
		$labels = [
			'effective_date' => __('Date effective de mise en service', 'protocolsmanager'),
			'planned_use' => __('Description de l’utilisation prévue', 'protocolsmanager'),
			'city' => __('Lieu d’établissement du document', 'protocolsmanager'),
			'observations' => __('Observations éventuelles', 'protocolsmanager'),
			'disposal_date' => __('Date de mise au rebut', 'protocolsmanager'),
			'reason' => __('Motif de la mise au rebut', 'protocolsmanager'),
			'method' => __('Mode de mise au rebut', 'protocolsmanager'),
		];
		return $labels[$key] ?? $key;
	}

	private static function displayBrandingSettings(): void
	{
		$branding = PluginProtocolsmanagerPvtemplate::load('branding');
		$color = $branding['accent_color'] ?? '#8A6428';
		if (!is_string($color) || !preg_match('/^#[0-9a-f]{6}$/i', $color)) {
			$color = '#8A6428';
		}
		echo "<div class='mb-3'><h2 class='h3 mb-1'>" . htmlescape(__('Identité visuelle globale', 'protocolsmanager')) . "</h2><p class='text-muted'>" . htmlescape(__('Le logo et la couleur d’accentuation seront partagés par les trois familles de documents.', 'protocolsmanager')) . '</p></div>';
		echo "<form action='config.form.php?section=branding' method='post' enctype='multipart/form-data' class='protocolsmanager-settings-form'>";
		if (method_exists(Session::class, 'getNewCSRFToken')) {
			echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		}
		echo Html::hidden('section', ['value' => 'branding']);
		echo "<section class='card mb-3'><div class='card-header'><h3 class='h5 mb-0'>" . htmlescape(__('Logo des PDF', 'protocolsmanager')) . '</h3></div><div class="card-body">';
		if (!empty($branding['logo'])) {
			$path = GLPI_PICTURE_DIR . '/' . basename((string)$branding['logo']);
			if (is_file($path) && is_readable($path) && (int)filesize($path) <= 2097152) {
				$mime = mime_content_type($path) ?: '';
				if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
					echo '<div class="mb-3"><img class="img-thumbnail protocolsmanager-logo-preview" alt="' . htmlescape(__('Logo actuel', 'protocolsmanager')) . '" src="data:' . htmlescape($mime) . ';base64,' . base64_encode((string)file_get_contents($path)) . '"></div>';
				}
			}
		}
		echo '<label class="form-label" for="global-pdf-logo">' . htmlescape(__('Choisir un logo PNG ou JPEG (2 Mo maximum)', 'protocolsmanager')) . '</label><input class="form-control" type="file" id="global-pdf-logo" name="global_logo" accept="image/png,image/jpeg">';
		if (!empty($branding['logo'])) {
			echo '<input type="hidden" name="remove_logo" value="0"><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_logo" value="1"> <span class="form-check-label">' . htmlescape(__('Supprimer le logo global', 'protocolsmanager')) . '</span></label>';
		}
		echo '</div></section>';
		echo "<section class='card mb-3'><div class='card-header'><h3 class='h5 mb-0'>" . htmlescape(__('Couleur d’accentuation', 'protocolsmanager')) . '</h3></div><div class="card-body"><label class="form-label" for="global-accent">' . htmlescape(__('Couleur appliquée aux titres et repères des PDF', 'protocolsmanager')) . '</label><div class="d-flex align-items-center gap-3"><input class="form-control form-control-color" type="color" id="global-accent" name="accent_color" value="' . htmlescape($color) . '"><code>' . htmlescape($color) . '</code></div></div></section>';
		echo "<div class='d-flex justify-content-end mb-4'><button class='btn btn-primary' type='submit' name='save_branding' value='1'><i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Enregistrer l’identité visuelle', 'protocolsmanager')) . '</button></div></form>';
	}

	public static function savePvTemplate(): bool
	{
		$type = (string)($_POST['section'] ?? '');
		if (!in_array($type, ['commission', 'disposal'], true)) {
			return false;
		}
		$defaults = PluginProtocolsmanagerPvtemplate::defaults($type);
		$posted = is_array($_POST['template'] ?? null) ? $_POST['template'] : [];
		$template = PluginProtocolsmanagerPvtemplate::load($type);
		$template = array_replace_recursive($defaults, $template);
		$template['title'] = self::settingText($posted['title'] ?? '', 255) ?: $defaults['title'];
		$posted_form = is_array($posted['form'] ?? null) ? $posted['form'] : [];
		foreach (array_keys($defaults['form']) as $key) {
			if (!is_array($defaults['form'][$key])) {
				continue;
			}
			if ($key === 'states' || $key === 'checks') {
				$template['form'][$key] = self::sanitizeCheckboxEntries($posted_form[$key] ?? []);
				continue;
			}
			if ($key === 'methods' || $key === 'cities' || $key === 'validators') {
				$template['form'][$key] = self::sanitizeChoiceEntries($posted_form[$key] ?? []);
				continue;
			}
			if (in_array($key, ['states', 'cities', 'methods'], true)) {
				$source = is_array($posted_form[$key] ?? null) ? $posted_form[$key] : [];
				foreach ($defaults['form'][$key] as $index => $default_entry) {
					$entry = is_array($source[$index] ?? null) ? $source[$index] : [];
					$template['form'][$key][$index] = $default_entry;
					$template['form'][$key][$index]['label'] = self::settingText($entry['label'] ?? $default_entry['label'], 160);
					$template['form'][$key][$index]['enabled'] = ($entry['enabled'] ?? '0') === '1';
					if (array_key_exists('checked', $default_entry)) {
						$template['form'][$key][$index]['checked'] = ($entry['checked'] ?? '0') === '1';
					}
				}
			} else {
				$entry = is_array($posted_form[$key] ?? null) ? $posted_form[$key] : [];
				$template['form'][$key]['label'] = self::settingText($entry['label'] ?? $template['form'][$key]['label'], 160);
				$template['form'][$key]['enabled'] = ($entry['enabled'] ?? '0') === '1';
			}
		}
		$posted_chapters = is_array($posted['chapters'] ?? null) ? $posted['chapters'] : [];
		foreach ($defaults['chapters'] as $index => $chapter) {
			$entry = is_array($posted_chapters[$index] ?? null) ? $posted_chapters[$index] : [];
			$template['chapters'][$index] = $chapter;
			$template['chapters'][$index]['title'] = self::settingText($entry['title'] ?? $chapter['title'], 180) ?: $chapter['title'];
			$template['chapters'][$index]['content'] = self::settingText($entry['content'] ?? $chapter['content'], 5000);
			$template['chapters'][$index]['enabled'] = ($entry['enabled'] ?? '0') === '1';
		}
		if ($type === 'disposal' && !empty($template['form']['method']['enabled'])) {
			$enabled_methods = array_filter($template['form']['methods'] ?? [], static fn($entry) => is_array($entry) && !empty($entry['enabled']));
			if ($enabled_methods === []) {
				Session::addMessageAfterRedirect(__('Activez au moins un mode de mise au rebut ou masquez le champ correspondant.', 'protocolsmanager'), false, ERROR);
				return false;
			}
		}
		$template['custom_chapters'] = self::sanitizeCustomChapters($posted['custom_chapters'] ?? []);
		$saved = PluginProtocolsmanagerPvtemplate::save($type, $template);
		if (!$saved) {
			Session::addMessageAfterRedirect(__('Le modèle de procès-verbal n’a pas pu être enregistré.', 'protocolsmanager'), false, ERROR);
		}
		return $saved;
	}

	public static function removePvTemplateRow(): bool
	{
		$path = (string)($_POST['remove_pv_row'] ?? '');
		if (preg_match('/^template\\[form\\]\\[(states|checks|methods|cities|validators)\\]\\[(\\d+)\\]$/', $path, $matches)) {
			$list_key = $matches[1];
			$index = (int)$matches[2];
			if (isset($_POST['template']['form'][$list_key]) && is_array($_POST['template']['form'][$list_key])) {
				unset($_POST['template']['form'][$list_key][$index]);
			}
		} elseif (preg_match('/^template\\[custom_chapters\\]\\[(\\d+)\\]$/', $path, $matches)) {
			$index = (int)$matches[1];
			if (isset($_POST['template']['custom_chapters']) && is_array($_POST['template']['custom_chapters'])) {
				unset($_POST['template']['custom_chapters'][$index]);
			}
		} else {
			Session::addMessageAfterRedirect(__('La ligne à supprimer est invalide.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		return self::savePvTemplate();
	}

	public static function addPvCustomChapter(): bool
	{
		$chapters = is_array($_POST['template']['custom_chapters'] ?? null) ? $_POST['template']['custom_chapters'] : [];
		$existing_keys = [];
		foreach ($chapters as $chapter) {
			if (is_array($chapter)) {
				$existing_keys[(string)($chapter['key'] ?? '')] = true;
			}
		}
		$index = count($chapters);
		$key = 'custom-chapter-' . $index;
		while (isset($existing_keys[$key])) {
			$index++;
			$key = 'custom-chapter-' . $index;
		}
		$chapters[] = [
			'key' => $key,
			'title' => __('Nouveau chapitre', 'protocolsmanager'),
			'content' => '',
			'enabled' => '1',
		];
		$_POST['template']['custom_chapters'] = $chapters;
		return self::savePvTemplate();
	}

	public static function addPvListEntry(): bool
	{
		$action = (string)($_POST['add_pv_list_entry'] ?? '');
		if (!preg_match('/^(checkbox|choice):(states|checks|methods|cities|validators)$/', $action, $matches)) {
			Session::addMessageAfterRedirect(__('L’entrée à ajouter est invalide.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$entry_type = $matches[1];
		$list_key = $matches[2];
		$type = (string)($_POST['section'] ?? '');
		$allowed_lists = [
			'commission' => ['states' => 'checkbox', 'validators' => 'choice', 'cities' => 'choice'],
			'disposal' => ['checks' => 'checkbox', 'methods' => 'choice', 'validators' => 'choice'],
		];
		if (($allowed_lists[$type][$list_key] ?? null) !== $entry_type) {
			Session::addMessageAfterRedirect(__('L’entrée à ajouter est invalide.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$entries = is_array($_POST['template']['form'][$list_key] ?? null) ? $_POST['template']['form'][$list_key] : [];
		$existing_keys = [];
		foreach ($entries as $entry) {
			if (is_array($entry)) {
				$existing_keys[(string)($entry['key'] ?? '')] = true;
			}
		}
		$index = count($entries);
		$key = 'custom-' . $list_key . '-' . $index;
		while (isset($existing_keys[$key])) {
			$index++;
			$key = 'custom-' . $list_key . '-' . $index;
		}
		if ($entry_type === 'checkbox') {
			$label = __('Nouvelle case', 'protocolsmanager');
			$entry = ['key' => $key, 'label' => $label, 'enabled' => '1', 'checked' => '0'];
		} else {
			$labels = [
				'validators' => __('Nouveau rôle', 'protocolsmanager'),
				'cities' => __('Nouveau lieu', 'protocolsmanager'),
				'methods' => __('Nouveau mode', 'protocolsmanager'),
			];
			$entry = ['key' => $key, 'label' => $labels[$list_key], 'enabled' => '1'];
		}
		$entries[] = $entry;
		$_POST['template']['form'][$list_key] = $entries;
		return self::savePvTemplate();
	}

	public static function movePvCustomChapter(): bool
	{
		$action = (string)($_POST['move_pv_chapter'] ?? '');
		if (!preg_match('/^(up|down):(\\d+)$/', $action, $matches)) {
			Session::addMessageAfterRedirect(__('Le déplacement du chapitre est invalide.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$chapters = is_array($_POST['template']['custom_chapters'] ?? null) ? $_POST['template']['custom_chapters'] : [];
		$keys = array_keys($chapters);
		$position = array_search((int)$matches[2], $keys, true);
		$target_position = $position === false ? false : $position + ($matches[1] === 'up' ? -1 : 1);
		if ($position === false || !isset($keys[$target_position])) {
			return false;
		}
		$ordered = array_values($chapters);
		[$ordered[$position], $ordered[$target_position]] = [$ordered[$target_position], $ordered[$position]];
		$_POST['template']['custom_chapters'] = $ordered;
		return self::savePvTemplate();
	}

	private static function sanitizeCustomChapters($source): array
	{
		if (!is_array($source)) {
			return [];
		}
		$chapters = [];
		$keys = [];
		foreach ($source as $chapter) {
			if (!is_array($chapter)) {
				continue;
			}
			$key = (string)($chapter['key'] ?? '');
			$title = self::settingText($chapter['title'] ?? '', 180);
			if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key) || isset($keys[$key]) || $title === '') {
				continue;
			}
			$keys[$key] = true;
			$chapters[] = [
				'key' => $key,
				'title' => $title,
				'content' => self::settingText($chapter['content'] ?? '', 5000),
				'enabled' => ($chapter['enabled'] ?? '0') === '1',
			];
		}
		return $chapters;
	}

	private static function sanitizeChoiceEntries($source): array
	{
		if (!is_array($source)) {
			return [];
		}
		$entries = [];
		$keys = [];
		foreach ($source as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$key = (string)($entry['key'] ?? '');
			$label = self::settingText($entry['label'] ?? '', 160);
			if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key) || isset($keys[$key]) || $label === '') {
				continue;
			}
			$keys[$key] = true;
			$entries[] = ['key' => $key, 'label' => $label, 'enabled' => ($entry['enabled'] ?? '0') === '1'];
		}
		return $entries;
	}

	private static function sanitizeCheckboxEntries($source): array
	{
		if (!is_array($source)) {
			return [];
		}
		$entries = [];
		$keys = [];
		foreach ($source as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$key = (string)($entry['key'] ?? '');
			if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key) || isset($keys[$key])) {
				continue;
			}
			$label = self::settingText($entry['label'] ?? '', 160);
			if ($label === '') {
				continue;
			}
			$keys[$key] = true;
			$entries[] = [
				'key' => $key,
				'label' => $label,
				'enabled' => ($entry['enabled'] ?? '0') === '1',
				'checked' => ($entry['checked'] ?? '0') === '1',
			];
		}
		return $entries;
	}

	public static function saveBranding(): bool
	{
		$branding = PluginProtocolsmanagerPvtemplate::load('branding');
		$previous_logo = is_string($branding['logo'] ?? null) ? basename($branding['logo']) : '';
		$new_logo_path = '';
		$color = $_POST['accent_color'] ?? '';
		if (!is_string($color) || !preg_match('/^#[0-9a-f]{6}$/i', $color)) {
			Session::addMessageAfterRedirect(__('La couleur indiquée est invalide.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$branding['accent_color'] = $color;
		if (($_POST['remove_logo'] ?? '0') === '1') {
			$branding['logo'] = '';
		}
		$file = $_FILES['global_logo'] ?? null;
		if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
			if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 2097152) {
				Session::addMessageAfterRedirect(__('Le logo dépasse 2 Mo ou le téléversement a échoué.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$image = @getimagesize($file['tmp_name']);
			$mime = is_array($image) ? ($image['mime'] ?? '') : '';
			$width = is_array($image) ? (int)($image[0] ?? 0) : 0;
			$height = is_array($image) ? (int)($image[1] ?? 0) : 0;
			$extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
			if (!isset($extensions[$mime]) || $width <= 0 || $height <= 0 || $width > 5000 || $height > 5000 || ($width * $height) > 25000000) {
				Session::addMessageAfterRedirect(__('Le logo doit être une image PNG ou JPEG valide de 5 000 × 5 000 pixels maximum.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$name = 'protocolsmanager-global-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
			$destination = GLPI_PICTURE_DIR . '/' . $name;
			if (!move_uploaded_file($file['tmp_name'], $destination)) {
				Session::addMessageAfterRedirect(__('Le logo n’a pas pu être enregistré.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$new_logo_path = $destination;
			$branding['logo'] = $name;
		}
		$saved = PluginProtocolsmanagerPvtemplate::save('branding', $branding);
		if (!$saved && $new_logo_path !== '' && is_file($new_logo_path)) {
			@unlink($new_logo_path);
		}
		if (!$saved) {
			Session::addMessageAfterRedirect(__('L’identité visuelle n’a pas pu être enregistrée.', 'protocolsmanager'), false, ERROR);
		}
		if ($saved && $previous_logo !== '' && $previous_logo !== ($branding['logo'] ?? '') && str_starts_with($previous_logo, 'protocolsmanager-global-')) {
			$previous_path = GLPI_PICTURE_DIR . '/' . $previous_logo;
			if (is_file($previous_path) && is_writable($previous_path)) {
				@unlink($previous_path);
			}
		}
		return $saved;
	}

	private static function settingText($value, int $limit): string
	{
		if (!is_string($value)) {
			return '';
		}
		return mb_substr(trim(strip_tags($value)), 0, $limit);
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
				$author_name = $row["author_name"];
				$author_state = $row["author_state"];
	
				// Récupérer width/height du logo si présents
				$logo_width = isset($row["logo_width"]) ? $row["logo_width"] : '';
				$logo_height = isset($row["logo_height"]) ? $row["logo_height"] : '';
				$theme_color = isset($row["theme_color"]) ? $row["theme_color"] : '#8A6428';
				$header_text_color = isset($row["header_text_color"]) ? $row["header_text_color"] : '#FFFFFF';
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
			$logo_width = '';
			$logo_height = '';
			$theme_color = '#8A6428';
			$header_text_color = '#FFFFFF';
		}
		if (!is_string($theme_color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $theme_color)) {
			$theme_color = '#8A6428';
		}
		if (!is_string($header_text_color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $header_text_color)) {
			$header_text_color = '#FFFFFF';
		}
		
		$fonts = array('Courier' => 'Courier',
						'Helvetica' => 'Helvetica', 
						'Times' => 'Times',
						'Istok' => 'Istok',
						'UbuntuMono' => 'UbuntuMono',
						'Roboto' => 'Roboto',
						'Liberation-Sans' => 'Liberation-Sans',
						'DroidSerif' => 'DroidSerif',
						'DejaVu Sans' => 'DejaVu Sans');
						
		$fontsizes = array('7' => '7',
							'8' => '8',
							'9' => '9',
							'10' => '10',
							'11' => '11',
							'12' => '12');
						
		$orientations = array('Portrait' => 'Portrait',
							'Landscape' => 'Landscape');
		
		if (!isset($font)) {
			$font='freesans';
		}
		
		echo "<form name='form' action='config.form.php' method='post' enctype='multipart/form-data' class='protocolsmanager-settings-form'>";
		if (method_exists(Session::class, 'getNewCSRFToken')) {
			echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		}
		echo Html::hidden('section', ['value' => 'inventory']);
		echo Html::hidden('MAX_FILE_SIZE', ['value' => 1948000]);
		echo Html::hidden('mode', ['value' => $mode]);
		echo "<div class='card mb-4'><div class='card-header d-flex align-items-center justify-content-between flex-wrap gap-2'><h2 class='h4 mb-0'>" . htmlescape($mode ? __('Modifier un modèle d’inventaire', 'protocolsmanager') : __('Créer un modèle d’inventaire', 'protocolsmanager')) . "</h2><a href='https://github.com/mateusznitka/protocolsmanager/wiki/Using-the-plugin' target='_blank' rel='noopener noreferrer'>" . htmlescape(__('Aide sur les modèles', 'protocolsmanager')) . " <i class='ti ti-external-link' aria-hidden='true'></i></a></div><div class='card-body'>";
		echo "<div class='alert alert-info'>" . htmlescape(__('Le logo et la couleur d’accentuation sont partagés par tous les modèles.', 'protocolsmanager')) . " <a class='alert-link' href='config.form.php?section=branding'>" . htmlescape(__('Configurer l’identité visuelle', 'protocolsmanager')) . '</a></div>';
		echo "<div class='row g-3'>";
		echo "<div class='col-md-6'><label class='form-label' for='inventory-template-name'>" . htmlescape(__('Template name')) . " *</label><input class='form-control' id='inventory-template-name' name='template_name' required maxlength='255' value='" . htmlescape($template_name) . "'></div>";
		echo "<div class='col-md-6'><label class='form-label' for='inventory-document-title'>" . htmlescape(__('Document title', 'protocolsmanager')) . " *</label><input class='form-control' id='inventory-document-title' name='title' required maxlength='255' value='" . htmlescape($title) . "'><div class='form-text'>" . htmlescape(__('You can use {owner} here.', 'protocolsmanager')) . '</div></div>';
		echo "<div class='col-md-4'><label class='form-label' for='inventory-font'>" . htmlescape(__('Font')) . "</label><select class='form-select' id='inventory-font' name='font'>";
		foreach ($fonts as $code => $fontname) {
			echo '<option value="' . htmlescape($code) . '"' . ($code == $font ? ' selected' : '') . '>' . htmlescape($fontname) . '</option>';
		}
		echo "</select></div><div class='col-md-4'><label class='form-label' for='inventory-font-size'>" . htmlescape(__('Font size')) . "</label><select class='form-select' id='inventory-font-size' name='fontsize'>";
		foreach ($fontsizes as $fsize => $fsizes) {
			echo '<option value="' . htmlescape($fsize) . '"' . ($fsize == $fontsize ? ' selected' : '') . '>' . htmlescape($fsizes) . '</option>';
		}
		echo "</select></div><div class='col-md-4'><label class='form-label' for='inventory-orientation'>" . htmlescape(__('Orientation')) . "</label><select class='form-select' id='inventory-orientation' name='orientation'>";
		foreach ($orientations as $value => $label) {
			echo '<option value="' . htmlescape($value) . '"' . ($value == $orientation ? ' selected' : '') . '>' . htmlescape($label) . '</option>';
		}
		echo "</select></div><div class='col-md-6'><label class='form-label' for='inventory-city'>" . htmlescape(__('City')) . "</label><input class='form-control' id='inventory-city' name='city' value='" . htmlescape($city) . "'></div>";
		echo "<div class='col-md-6'><label class='form-label' for='inventory-email-template'>" . htmlescape(__('Email template')) . "</label><select class='form-select' id='inventory-email-template' name='email_template' required>";
		foreach ($DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig']) as $email_template_row) {
			$id = (string)$email_template_row['id'];
			echo '<option value="' . htmlescape($id) . '"' . ($id == $email_template ? ' selected' : '') . '>' . htmlescape($email_template_row['tname']) . '</option>';
		}
		echo "</select></div></div>";
		echo "<hr><h3 class='h5'>" . htmlescape(__('Contenu et affichage', 'protocolsmanager')) . '</h3>';
		echo "<div class='mb-3'><label class='form-label' for='inventory-upper-content'>" . htmlescape(__('Upper Content')) . "</label><textarea class='form-control' id='inventory-upper-content' name='template_uppercontent' rows='4'>" . htmlescape($template_uppercontent) . "</textarea><div class='form-text'>" . htmlescape(__('You can use {owner} or {admin} here.', 'protocolsmanager')) . '</div></div>';
		echo "<div class='mb-3'><label class='form-label' for='inventory-content'>" . htmlescape(__('Content')) . "</label><textarea class='form-control' id='inventory-content' name='template_content' rows='8'>" . htmlescape($template_content) . "</textarea><div class='form-text'>" . htmlescape(__('You can use {owner} or {admin} here.', 'protocolsmanager')) . '</div></div>';
		echo "<div class='mb-3'><label class='form-label' for='inventory-footer'>" . htmlescape(__('Footer')) . "</label><textarea class='form-control' id='inventory-footer' name='footer_text' rows='3'>" . htmlescape($template_footer) . '</textarea></div>';
		echo "<div class='row g-3'><div class='col-lg-6'><fieldset><legend class='fs-6'>" . htmlescape(__('Word breaking')) . '</legend>';
		foreach ([1 => __('On'), 0 => __('Off')] as $value => $label) {
			echo '<label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="breakword" value="' . $value . '"' . ((int)$breakword === $value ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape($label) . '</span></label>';
		}
		echo "</fieldset></div><div class='col-lg-6'><fieldset><legend class='fs-6'>" . htmlescape(__('Serial number')) . '</legend>';
		foreach ([1 => __('Serial and inventory number in separate columns', 'protocolsmanager'), 2 => __('Serial or inventory number if serial does not exist', 'protocolsmanager')] as $value => $label) {
			echo '<label class="form-check"><input class="form-check-input" type="radio" name="serial_mode" value="' . $value . '"' . ((int)$serial_mode === $value ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape($label) . '</span></label>';
		}
		echo "</fieldset></div><div class='col-lg-6'><fieldset><legend class='fs-6'>" . htmlescape(__('Enable email autosending')) . '</legend>';
		foreach ([1 => __('On'), 2 => __('Off')] as $value => $label) {
			echo '<label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="email_mode" value="' . $value . '"' . ((int)$email_mode === $value ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape($label) . '</span></label>';
		}
		echo "</fieldset></div><div class='col-lg-6'><fieldset><legend class='fs-6'>" . htmlescape(__('Select who should generate the pdf', 'protocolsmanager')) . '</legend>';
		echo '<label class="form-check"><input class="form-check-input" type="radio" name="author_state" value="1"' . ((int)$author_state !== 2 ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape(__('The user who generates the document', 'protocolsmanager')) . '</span></label>';
		echo '<div class="d-flex gap-2 align-items-center"><label class="form-check"><input class="form-check-input" type="radio" name="author_state" value="2"' . ((int)$author_state === 2 ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape(__('Other author', 'protocolsmanager')) . '</span></label><input class="form-control" name="author_name" aria-label="' . htmlescape(__('Author name', 'protocolsmanager')) . '" value="' . htmlescape($author_name) . '"></div></fieldset></div></div>';
		echo "<div class='d-flex justify-content-end gap-2 mt-4'><a class='btn btn-secondary' href='config.form.php?section=inventory'>" . htmlescape(__('Cancel')) . "</a><button type='submit' name='save' value='1' class='btn btn-primary'><i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Save')) . '</button></div>';
		echo '</div></div></form>';
		echo "<br>";
		self::showConfigs();
	}
	
	
	static function DisplayContentEmail() {
		global $DB, $CFG_GLPI;
		
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
		
		echo "<form name='email_template_edit' action='config.form.php' method='post' class='protocolsmanager-settings-form'>";
		if (method_exists(Session::class, 'getNewCSRFToken')) {
			echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		}
		echo Html::hidden('section', ['value' => 'email']) . Html::hidden('email_edit_id', ['value' => $email_edit_id]);
		echo "<section class='card mb-4'><div class='card-header d-flex align-items-center justify-content-between flex-wrap gap-2'><h2 class='h4 mb-0'>" . htmlescape($email_edit_id ? __('Modifier un modèle d’e-mail', 'protocolsmanager') : __('Créer un modèle d’e-mail', 'protocolsmanager')) . "</h2><a href='https://github.com/mateusznitka/protocolsmanager/wiki/Email-sending-configuration' target='_blank' rel='noopener noreferrer'>" . htmlescape(__('Aide sur l’envoi d’e-mails', 'protocolsmanager')) . " <i class='ti ti-external-link' aria-hidden='true'></i></a></div><div class='card-body'>";
		echo "<div class='mb-3'><label class='form-label' for='email-template-name'>" . htmlescape(__('Template name')) . " *</label><input class='form-control' id='email-template-name' name='tname' required maxlength='255' value='" . htmlescape($tname) . "'></div>";
		echo "<fieldset class='mb-3'><legend class='fs-6'>" . htmlescape(__('Send to user')) . '</legend>';
		foreach ([1 => __('Send to user'), 2 => __('Do not send to user')] as $value => $label) {
			echo '<label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="send_user" value="' . $value . '"' . ((int)$send_user === $value ? ' checked' : '') . '> <span class="form-check-label">' . htmlescape($label) . '</span></label>';
		}
		echo '</fieldset>';
		echo "<div class='mb-3'><label class='form-label' for='email-subject'>" . htmlescape(__('Email subject')) . " *</label><input class='form-control' id='email-subject' name='email_subject' required maxlength='255' value='" . htmlescape($email_subject) . "'></div>";
		echo "<div class='mb-3'><label class='form-label' for='email-content'>" . htmlescape(__('Email content')) . " *</label><textarea class='form-control' id='email-content' name='email_content' rows='8' required>" . htmlescape($email_content) . '</textarea></div>';
		echo "<div class='mb-3'><label class='form-label' for='email-recipients'>" . htmlescape(__('Add emails - use ; to separate')) . " *</label><textarea class='form-control' id='email-recipients' name='recipients' rows='3' required>" . htmlescape($recipients) . '</textarea></div>';
		echo "<div class='d-flex justify-content-end gap-2'><a class='btn btn-secondary' href='config.form.php?section=email'>" . htmlescape(__('Cancel')) . "</a><button class='btn btn-primary' type='submit' name='save_email' value='1'><i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Save')) . '</button></div></div></section></form>';
		echo "<br>";
		self::showEmailConfigs();
	}
	
	static function saveConfigs() {
		global $DB, $CFG_GLPI;
		
		if (empty($_POST["template_name"]) || empty($_POST["email_template"]) || empty($_POST["title"])) {
			Session::AddMessageAfterRedirect('Fill mandatory fields', 'WARNING', true);
		} else {

			// No addslashes() needed here. GLPI 11 DB layer handles sanitization.
			$template_name = $_POST['template_name'];
			$title = $_POST['title'];
			$font = $_POST["font"];
			$fontsize = $_POST["fontsize"];
			$serial_mode = $_POST["serial_mode"];
			$orientation = $_POST["orientation"];
			$posted_theme_color = $_POST['theme_color'] ?? '';
			$theme_color = is_string($posted_theme_color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $posted_theme_color)
				? $posted_theme_color
				: '#8A6428';
			$posted_header_text_color = $_POST['header_text_color'] ?? '';
			$header_text_color = is_string($posted_header_text_color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $posted_header_text_color)
				? $posted_header_text_color
				: '#FFFFFF';

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
				$mode = (int)$_POST["mode"];
			}
			if (!empty($mode) && (!isset($_POST['theme_color']) || !isset($_POST['header_text_color']) || !isset($_POST['logo_width']) || !isset($_POST['logo_height']))) {
				$legacy_columns = [];
				foreach (['theme_color', 'header_text_color', 'logo_width', 'logo_height'] as $field) {
					if ($DB->fieldExists('glpi_plugin_protocolsmanager_config', $field)) {
						$legacy_columns[] = $field;
					}
				}
				$legacy_values = [];
				if ($legacy_columns !== []) {
					$legacy_result = $DB->request([
						'SELECT' => $legacy_columns,
						'FROM' => 'glpi_plugin_protocolsmanager_config',
						'WHERE' => ['id' => (int)$mode],
						'LIMIT' => 1,
					]);
					$legacy_values = $legacy_result->current() ?: [];
				}
				if ($legacy_values) {
					if (!isset($_POST['theme_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($legacy_values['theme_color'] ?? ''))) {
						$theme_color = $legacy_values['theme_color'];
					}
					if (!isset($_POST['header_text_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($legacy_values['header_text_color'] ?? ''))) {
						$header_text_color = $legacy_values['header_text_color'];
					}
					if (!isset($_POST['logo_width'])) {
						$logo_width = $legacy_values['logo_width'] ?? null;
					}
					if (!isset($_POST['logo_height'])) {
						$logo_height = $legacy_values['logo_height'] ?? null;
					}
				}
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
			
			$full_img_name = self::uploadImage();

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
				$logo_width = max(0, (int)$_POST["logo_width"]);
			} elseif (!isset($logo_width)) {
				$logo_width = null;
			}
			if (isset($_POST["logo_height"])) {
				$logo_height = max(0, (int)$_POST["logo_height"]);
			} elseif (!isset($logo_height)) {
				$logo_height = null;
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
					'serial_mode' => $serial_mode,
					'orientation' => $orientation,
					'breakword' => $breakword,
					'email_mode' => $email_mode,
					'email_template' => $email_template,
					'author_name' => $author_name,
					'author_state' => $author_state,
					'theme_color' => $theme_color,
					'header_text_color' => $header_text_color
					]
				);
			}
			
			//if edit template
			if ($mode != 0) {


				// quand c'est séléctionner et qu'on refais sauvegardé
				// la valeur est égale à "X\"" au lieu de "X"

				// idéalement checker la value de $email_template
				// This sanitization is fine to keep
				$email_template = preg_replace('/[^A-Za-z0-9\-]/','',$email_template);


				//if logo is uploaded
				if (isset($full_img_name)) {
					
					$DB->update('glpi_plugin_protocolsmanager_config', [
							'name' => $template_name,
							'title' => $title,
							'content' => $template_content,
							'upper_content' => $template_uppercontent,
							'footer' => $template_footer,
							'logo' => $full_img_name,
							'logo_width'    => $logo_width,
							'logo_height'   => $logo_height,
							'font' => $font,
							'fontsize' => $fontsize,
							'city' => $city,
							'serial_mode' => $serial_mode,
							'orientation' => $orientation,
							'breakword' => $breakword,
							'email_mode' => $email_mode,
							'email_template' => $email_template,
							'author_name' => $author_name,
							'author_state' => $author_state,
							'theme_color' => $theme_color,
							'header_text_color' => $header_text_color
						], [
							'id' => $mode
						]
					);
				} else {
					if(!$city)
					{
						$city = '';
					}
					if(!$template_uppercontent)
					{
						$template_uppercontent = '';
					}
					if(!$template_content)
					{
						$template_content = '';
					}
					if(!$template_footer)
					{
						$template_footer = '';
					}
					if(!isset($email_mode))
					{
						$DB->update('glpi_plugin_protocolsmanager_config', [
							'name' => $template_name,
							'title' => $title,
							'content' => $template_content,
							'upper_content' => $template_uppercontent,
							'footer' => $template_footer,
							'logo_width'    => $logo_width,
							'logo_height'   => $logo_height,
							'font' => $font,
							'fontsize' => $fontsize,
							'city' => $city,
							'serial_mode' => $serial_mode,
							'orientation' => $orientation,
							'breakword' => $breakword,
							'email_template' => $email_template,
							'author_name' => $author_name,
							'author_state' => $author_state,
							'theme_color' => $theme_color,
							'header_text_color' => $header_text_color
						], [
							'id' => $mode
						]
					);
					}
					else {
						$DB->update('glpi_plugin_protocolsmanager_config', [
							'name' => $template_name,
							'title' => $title,
							'content' => $template_content,
							'upper_content' => $template_uppercontent,
							'footer' => $template_footer,
							'logo_width'    => $logo_width,
							'logo_height'   => $logo_height,
							'font' => $font,
							'fontsize' => $fontsize,
							'city' => $city,
							'serial_mode' => $serial_mode,
							'orientation' => $orientation,
							'breakword' => $breakword,
							'email_mode' => $email_mode,
							'email_template' => $email_template,
							'author_name' => $author_name,
							'author_state' => $author_state,
							'theme_color' => $theme_color,
							'header_text_color' => $header_text_color
						], [
							'id' => $mode
						]
					);
					}
				}
			}
			
			Session::AddMessageAfterRedirect('Config saved');
		}		
	
	}
	
	static function saveEmailConfigs() {
		global $DB, $CFG_GLPI;
		
		if (empty($_POST["email_subject"]) || empty($_POST["email_content"]) || empty($_POST["recipients"]) || empty($_POST["tname"])) {
			Session::AddMessageAfterRedirect('Fill mandatory fields', 'WARNING', true);
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
			
			Session::AddMessageAfterRedirect('Config saved');
		}

	}
	

	static function showConfigs() {
		global $DB, $CFG_GLPI;
		$rows = $DB->request(['FROM' => 'glpi_plugin_protocolsmanager_config', 'ORDER' => ['name ASC']]);
		echo "<section class='card mb-4' id='show_configs'><div class='card-header'><h2 class='h4 mb-0'>" . htmlescape(__('Modèles d’inventaire', 'protocolsmanager')) . '</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>' . htmlescape(__('Name')) . '</th><th class="text-end">' . htmlescape(__('Action')) . '</th></tr></thead><tbody>';
		$has_rows = false;
		foreach ($rows as $row) {
			$has_rows = true;
			$id = (int)$row['id'];
			echo '<tr><td><strong>' . htmlescape($row['name']) . '</strong></td><td><div class="d-flex justify-content-end gap-2">';
			echo "<form method='post' action='config.form.php'>";
			if (method_exists(Session::class, 'getNewCSRFToken')) {
				echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
			}
			echo Html::hidden('section', ['value' => 'inventory']) . Html::hidden('edit_id', ['value' => $id]);
			echo "<button class='btn btn-sm btn-outline-primary' type='submit' name='edit' value='1'><i class='ti ti-pencil me-1'></i>" . htmlescape(__('Edit')) . '</button></form>';
			echo "<form method='post' action='config.form.php'>";
			if (method_exists(Session::class, 'getNewCSRFToken')) {
				echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
			}
			echo Html::hidden('section', ['value' => 'inventory']) . Html::hidden('conf_id', ['value' => $id]);
			echo "<button class='btn btn-sm btn-outline-danger' type='submit' name='delete' value='1'><i class='ti ti-trash me-1'></i>" . htmlescape(__('Delete')) . '</button></form></div></td></tr>';
		}
		if (!$has_rows) {
			echo '<tr><td colspan="2" class="text-center text-muted py-4">' . htmlescape(__('Aucun modèle d’inventaire. Créez le premier avec le formulaire ci-dessus.', 'protocolsmanager')) . '</td></tr>';
		}
		echo '</tbody></table></div></div></section>';
	}
	
	static function showEmailConfigs() {
		global $DB, $CFG_GLPI;
		$rows = $DB->request(['FROM' => 'glpi_plugin_protocolsmanager_emailconfig', 'ORDER' => ['tname ASC']]);
		echo "<section class='card mb-4' id='show_emailconfigs'><div class='card-header'><h2 class='h4 mb-0'>" . htmlescape(__('Modèles d’e-mail', 'protocolsmanager')) . '</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>' . htmlescape(__('Name')) . '</th><th>' . htmlescape(__('Recipients')) . '</th><th class="text-end">' . htmlescape(__('Action')) . '</th></tr></thead><tbody>';
		$has_rows = false;
		foreach ($rows as $row) {
			$has_rows = true;
			$id = (int)$row['id'];
			echo '<tr><td><strong>' . htmlescape($row['tname']) . '</strong></td><td>' . htmlescape($row['recipients']) . '</td><td><div class="d-flex justify-content-end gap-2">';
			echo "<form method='post' action='config.form.php'>";
			if (method_exists(Session::class, 'getNewCSRFToken')) {
				echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
			}
			echo Html::hidden('section', ['value' => 'email']) . Html::hidden('email_edit_id', ['value' => $id]);
			echo "<button class='btn btn-sm btn-outline-primary' type='submit' name='email_edit' value='1'><i class='ti ti-pencil me-1'></i>" . htmlescape(__('Edit')) . '</button></form>';
			echo "<form method='post' action='config.form.php'>";
			if (method_exists(Session::class, 'getNewCSRFToken')) {
				echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
			}
			echo Html::hidden('section', ['value' => 'email']) . Html::hidden('email_conf_id', ['value' => $id]);
			echo "<button class='btn btn-sm btn-outline-danger' type='submit' name='delete_email' value='1'><i class='ti ti-trash me-1'></i>" . htmlescape(__('Delete')) . '</button></form></div></td></tr>';
		}
		if (!$has_rows) {
			echo '<tr><td colspan="3" class="text-center text-muted py-4">' . htmlescape(__('Aucun modèle d’e-mail configuré.', 'protocolsmanager')) . '</td></tr>';
		}
		echo '</tbody></table></div></div></section>';
	}
	
	static function uploadImage() {
		global $DB, $CFG_GLPI;
		if (!isset($_FILES['logo']) || !is_array($_FILES['logo']) || empty($_FILES['logo']['name'])) {
			return null;
		}
		if($_FILES['logo']['name']) {
			
			if($_FILES['logo']['error'] != UPLOAD_ERR_FORM_SIZE) {
			
				if (!$_FILES['logo']['error']) {
					
					if ($_FILES['logo']['type'] == 'image/jpeg' || $_FILES['logo']['type'] == 'image/png' || $_FILES['logo']['type'] == 'image/jpg') {
						
						$img_name = "logo".time();
						$ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
						$full_img_name = $img_name.'.'.$ext;
						$img_path = GLPI_PICTURE_DIR . '/' . $full_img_name;
						
						move_uploaded_file($_FILES['logo']['tmp_name'], $img_path);
						
						return $full_img_name;
						
					} else {
						Session::addMessageAfterRedirect('Wrong file type. Only .jpg and .png files accepted', 'WARNING', true);
					}
				} else {
					Session::addMessageAfterRedirect(__('Unknown error'), 'WARNING', true);
				}
			} else {
				Session::addMessageAfterRedirect('File size too large', 'WARNING', true);
			}
		}

	}
	
	static function deleteConfigs() {
		global $DB;
		
		$conf_id = $_POST['conf_id'];
		
		$DB->delete(
			'glpi_plugin_protocolsmanager_config', [
				'id' => $conf_id
			]
		);
		
	}
	
	
	static function deleteEmailConfigs() {
		global $DB;
		
		$email_conf_id = $_POST['email_conf_id'];
		
		$DB->delete(
			'glpi_plugin_protocolsmanager_emailconfig', [
				'id' => $email_conf_id
			]
		);	
		
	}

}

?>
