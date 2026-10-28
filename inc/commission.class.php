<?php
if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

require_once dirname(__DIR__) . '/dompdf/vendor/autoload.php';
require_once __DIR__ . '/pdf.class.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** Procès-verbaux de mise en service, distincts des inventaires utilisateurs. */
class PluginProtocolsmanagerCommission extends CommonDBTM
{
	private const LICENSE_ITEMTYPE = 'SoftwareLicense';
	private const ITEM_TYPES = [
		'Computer', 'Monitor', 'Printer', 'Peripheral', 'Phone', 'NetworkEquipment'
	];

	public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
	{
		if (version_compare(GLPI_VERSION, '12.0.0', '>=')) {
			return self::createTabEntry(__('Procès-Verbaux', 'protocolsmanager'), icon: 'ti ti-file-check');
		}
		return self::createTabEntry(__('Procès-Verbaux', 'protocolsmanager'));
	}

	public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
	{
		if (!self::hasAccess()) {
			echo "<div class='alert alert-warning'>" . htmlescape(__('Accès refusé')) . '</div>';
			return true;
		}
		self::renderCommissionForm($item);
		return true;
	}

	private static function hasAccess(): bool
	{
		global $DB;
		$profile_id = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
		if ($profile_id <= 0 || !$DB->tableExists('glpi_plugin_protocolsmanager_profiles')) {
			return false;
		}
		$result = $DB->request([
			'FROM' => 'glpi_plugin_protocolsmanager_profiles',
			'WHERE' => ['profile_id' => $profile_id],
		]);
		$row = $result->current();
		return $row && ($row['tab_access'] ?? '') === 'w';
	}

	private static function renderCommissionForm(CommonGLPI $item): void
	{
		global $CFG_GLPI, $DB;
		$itemtype = get_class($item);
		$item_id = (int)$item->getID();
		if (!self::isSupportedSourceItemType($itemtype) || $item_id <= 0 || !$item->can($item_id, READ)) {
			echo "<div class='alert alert-warning'>" . htmlescape(__('Accès refusé')) . '</div>';
			return;
		}
		echo "<div class='container-fluid px-0'>";
		echo "<div class='d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3'><h2 class='mb-0'>" . htmlescape(__('Procès-Verbaux', 'protocolsmanager')) . '</h2>';
		if (!Session::haveRight('document', CREATE)) {
			echo "<span class='badge bg-secondary'>" . htmlescape(__('Consultation seule', 'protocolsmanager')) . '</span>';
		}
		echo '</div>';
		echo "<details class='card mb-3' name='protocolsmanager-pv-type'><summary class='card-header d-flex justify-content-between align-items-center' style='cursor:pointer;list-style:none'><span>" . htmlescape(__('PV de mise en service', 'protocolsmanager')) . "</span><i class='ti ti-chevron-down'></i></summary><div class=\"card-body\">";
		self::renderCommissionForItem($itemtype, $item_id, $CFG_GLPI['root_doc'], $DB);
		echo '</div></details>';
		echo "<details class='card mb-3' name='protocolsmanager-pv-type'><summary class='card-header d-flex justify-content-between align-items-center' style='cursor:pointer;list-style:none'><span>" . htmlescape(__('PV de mise au rebut', 'protocolsmanager')) . "</span><i class='ti ti-chevron-down'></i></summary><div class=\"card-body\">";
		self::renderDisposalForItem($itemtype, $item_id, $CFG_GLPI['root_doc'], $DB);
		echo '</div></details>';
		self::showGeneratedDocuments($itemtype, $item_id);
		echo '</div>';
	}

	private static function renderCommissionForItem(string $itemtype, int $item_id, string $root_doc, $DB): void
	{
		$seed = self::loadAsset($itemtype, $item_id);
		if (!Session::haveRight('document', CREATE)) {
			echo "<div class='alert alert-info'>" . htmlescape(__('Vous pouvez consulter les PV associés, mais votre profil ne permet pas d’en créer.', 'protocolsmanager')) . '</div>';
		} elseif (!$seed) {
			echo "<div class='alert alert-info'>" . htmlescape(__('Aucune donnée de gestion exploitable n’a été trouvée. Le PV sera rattaché à cet élément, sans numéro immo.', 'protocolsmanager')) . '</div>';
			self::renderGenerationForm([], '', $root_doc, $DB, $itemtype, $item_id);
		} elseif ($seed['immo_number'] === '') {
			self::renderGenerationForm([], '', $root_doc, $DB, $itemtype, $item_id);
		} elseif ($itemtype === self::LICENSE_ITEMTYPE) {
			// Licenses keep their own PV even when they have an asset number; hardware grouping is unchanged.
			self::renderGenerationForm([$seed], $seed['immo_number'], $root_doc, $DB);
		} else {
			$group = self::findAssetGroup($seed['immo_number']);
			if ($group === null) {
				echo "<div class='alert alert-warning'>" . htmlescape(__('Au moins un matériel portant ce numéro immo n’est pas accessible. Le PV groupé ne peut pas être généré.', 'protocolsmanager')) . '</div>';
			} elseif ($group === []) {
				echo "<div class='alert alert-warning'>" . htmlescape(__('Aucun matériel n’a été trouvé pour ce numéro immo.', 'protocolsmanager')) . '</div>';
			} else {
				self::renderGenerationForm($group, $seed['immo_number'], $root_doc, $DB);
			}
		}
	}

	private static function renderDisposalForItem(string $itemtype, int $item_id, string $root_doc, $DB): void
	{
		$seed = self::loadAsset($itemtype, $item_id);
		if (!$seed) {
			echo "<div class='alert alert-info'>" . htmlescape(__('Aucune donnée de gestion exploitable n’a été trouvée. Le PV sera rattaché à cet élément, sans numéro immo.', 'protocolsmanager')) . '</div>';
			self::renderDisposalForm([], '', $root_doc, $DB, $itemtype, $item_id);
			return;
		}
		if ($seed['immo_number'] === '') {
			self::renderDisposalForm([$seed], '', $root_doc, $DB, $itemtype, $item_id);
			return;
		}
		if ($itemtype === self::LICENSE_ITEMTYPE) {
			self::renderDisposalForm([$seed], $seed['immo_number'], $root_doc, $DB);
			return;
		}
		$group = self::findAssetGroup($seed['immo_number']);
		if ($group === null) {
			echo "<div class='alert alert-warning'>" . htmlescape(__('Au moins un matériel portant ce numéro immo n’est pas accessible. Le PV groupé ne peut pas être généré.', 'protocolsmanager')) . '</div>';
		} elseif ($group === []) {
			echo "<div class='alert alert-warning'>" . htmlescape(__('Aucun matériel n’a été trouvé pour ce numéro immo.', 'protocolsmanager')) . '</div>';
		} else {
			self::renderDisposalForm($group, $seed['immo_number'], $root_doc, $DB);
		}
	}

	private static function renderGenerationForm(array $group, string $immo_number, string $root_doc, $DB, string $source_itemtype = '', int $source_item_id = 0): void
	{
		$pv_template = PluginProtocolsmanagerPvtemplate::load('commission');
		$form = $pv_template['form'] ?? [];
		echo "<form method='post' action='" . htmlescape($root_doc . '/plugins/protocolsmanager/front/commission.form.php') . "' class='commission-form'>";
		if (method_exists(Session::class, 'getNewCSRFToken')) {
			echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		}
		echo Html::hidden('immo_number', ['value' => $immo_number]);
		if ($immo_number === '' && $source_itemtype !== '' && $source_item_id > 0) {
			echo Html::hidden('source_itemtype', ['value' => $source_itemtype]);
			echo Html::hidden('source_item_id', ['value' => $source_item_id]);
		}
		if ($group !== []) {
			$is_license_group = count(array_filter($group, static fn($asset) => $asset['itemtype'] === self::LICENSE_ITEMTYPE)) === count($group);
			$group_title = $is_license_group ? __('Licence concernée', 'protocolsmanager') : __('Matériels concernés', 'protocolsmanager');
			$item_title = $is_license_group ? __('Licence', 'protocolsmanager') : __('Matériel', 'protocolsmanager');
			$include_label = $is_license_group ? __('Inclure cette licence', 'protocolsmanager') : __('Inclure ce matériel', 'protocolsmanager');
			echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape($group_title) . '</strong>';
			echo "<span class='badge bg-secondary ms-2'>" . htmlescape(__('Numéro immo', 'protocolsmanager')) . ': ' . htmlescape($immo_number) . "</span></div><div class='card-body'>";
			echo "<div class='form-check mb-2'><input class='form-check-input' type='checkbox' id='commission-select-all' data-pv-select-all checked><label class='form-check-label' for='commission-select-all'>" . htmlescape(__('Tout sélectionner / tout décocher', 'protocolsmanager')) . '</label></div>';
			echo "<div class='table-responsive'><table class='table table-sm table-hover align-middle mb-3'><thead><tr><th>" . htmlescape(__('Inclure', 'protocolsmanager')) . '</th><th>' . htmlescape($item_title) . '</th><th>' . htmlescape(__('Type', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Modèle', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Numéro d’inventaire', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Statut', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Personne affectée', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Prix d’achat', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Numéro de série', 'protocolsmanager')) . '</th></tr></thead><tbody>';
			foreach ($group as $asset) {
				$key = $asset['itemtype'] . ':' . $asset['id'];
				echo '<tr><td><input class="form-check-input protocolsmanager-pv-item" type="checkbox" name="items[]" value="' . htmlescape($key) . '" checked aria-label="' . htmlescape($include_label) . '"></td>';
				echo '<td><strong>' . htmlescape($asset['name'] !== '' ? $asset['name'] : '#' . $asset['id']) . '</strong></td>';
				echo '<td>' . ($asset['type'] !== '' ? htmlescape($asset['type']) : '—') . '</td><td>' . ($asset['model'] !== '' ? htmlescape($asset['model']) : '—') . '</td>';
				echo '<td>' . ($asset['inventory_number'] !== '' ? htmlescape($asset['inventory_number']) : '—') . '</td><td>' . ($asset['status'] !== '' ? htmlescape($asset['status']) : '—') . '</td><td>' . ($asset['assigned_to'] !== '' ? htmlescape($asset['assigned_to']) : '—') . '</td>';
				$purchase_price = $asset['value'] === null || $asset['value'] === '' ? '' : number_format((float)$asset['value'], 2, ',', ' ') . ' €';
				echo '<td>' . ($purchase_price !== '' ? htmlescape($purchase_price) : '—') . '</td><td>' . ($asset['serial'] !== '' ? htmlescape($asset['serial']) : '—') . '</td></tr>';
			}
			echo '</tbody></table></div>';
		} else {
			$notice = $source_itemtype === self::LICENSE_ITEMTYPE
				? __('Ce PV sera rattaché à la licence courante, sans numéro immo.', 'protocolsmanager')
				: __('Ce PV sera rattaché au matériel courant, sans numéro immo.', 'protocolsmanager');
			echo "<div class='alert alert-secondary'>" . htmlescape($notice) . '</div>';
		}
		$branding = PluginProtocolsmanagerPvtemplate::load('branding');
		echo "<div class='row g-3 align-items-end'>";
		if (!empty($form['effective_date']['enabled'])) {
			$fallback_date = self::commonPurchaseDate($group);
			echo '<div class="col-md-8"><div class="d-flex align-items-baseline justify-content-between flex-wrap gap-1"><label class="form-label mb-1" for="effective-date">' . htmlescape($form['effective_date']['label'] ?? __('Date effective de mise en service', 'protocolsmanager')) . '</label>';
			if ($fallback_date !== '') {
				echo '<span class="form-text mt-0">' . htmlescape(__('Si non remplie, la date de facture sera utilisée, à savoir :', 'protocolsmanager') . ' ' . self::displayDate($fallback_date)) . '</span>';
			}
			echo '</div><input class="form-control" type="date" id="effective-date" name="effective_date" value="' . htmlescape($fallback_date) . '"></div>';
		}
		echo '<div class="col-md-4"><div class="form-text">' . htmlescape(__('Identité visuelle globale appliquée au PDF.', 'protocolsmanager')) . '</div>';
		if (!empty($branding['accent_color'])) {
			echo '<span class="badge" style="background-color:' . htmlescape($branding['accent_color']) . '">' . htmlescape(__('Couleur PDF', 'protocolsmanager')) . '</span>';
		}
		echo '</div></div>';
		if ($group !== []) {
			echo '</div></section>';
		}

		echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape($pv_template['title'] ?? __('Mise en service', 'protocolsmanager')) . '</strong></div><div class="card-body">';
		echo "<p class='mb-3'>" . htmlescape(__('Je soussigné(e) certifie que l’immobilisation décrite ci-dessous a été :', 'protocolsmanager')) . '</p><div class="d-flex flex-wrap gap-3 mb-4">';
		foreach (($form['states'] ?? []) as $state) {
			if (empty($state['enabled'])) {
				continue;
			}
			$key = (string)($state['key'] ?? '');
			$checked = !empty($state['checked']) ? ' checked' : '';
			echo '<label class="form-check form-check-inline mb-0"><input class="form-check-input" type="checkbox" name="states[]" value="' . htmlescape($key) . '"' . $checked . '><span class="form-check-label">' . htmlescape($state['label'] ?? '') . '</span></label>';
		}
		echo '</div><div class="row g-3">';
		if (!empty($form['planned_use']['enabled'])) {
			echo '<div class="col-12"><label class="form-label" for="planned-use">' . htmlescape($form['planned_use']['label'] ?? __('Description de l’utilisation prévue', 'protocolsmanager')) . '</label><textarea class="form-control" id="planned-use" name="planned_use" rows="3"></textarea></div>';
		}
		echo '</div>';
		if (!empty($form['city']['enabled'])) {
			echo "<fieldset class='mt-3'><legend class='fs-6'>" . htmlescape($form['city']['label'] ?? __('Lieu d’établissement du document', 'protocolsmanager')) . '</legend><div class="d-flex gap-4">';
			$default_city_selected = false;
			foreach (($form['cities'] ?? []) as $city) {
				if (empty($city['enabled'])) {
					continue;
				}
				$value = (string)($city['key'] ?? '');
				$checked = !$default_city_selected ? ' checked' : '';
				$default_city_selected = true;
				echo '<label class="form-check"><input class="form-check-input" type="radio" name="city" value="' . htmlescape($value) . '"' . $checked . '> <span class="form-check-label">' . htmlescape($city['label'] ?? $value) . '</span></label>';
			}
			echo '</div></fieldset>';
		}
		echo '</div></section>';

		if (!empty($form['observations']['enabled'])) {
			echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape($form['observations']['label'] ?? __('Observations éventuelles', 'protocolsmanager')) . '</strong></div><div class="card-body"><textarea class="form-control" id="commission-observations" name="observations" rows="4" aria-label="' . htmlescape($form['observations']['label'] ?? '') . '"></textarea></div></section>';
		}
		echo "<div class='d-flex justify-content-end mb-4'><button class='btn btn-primary' type='submit' name='generate_commission' value='1'><i class='ti ti-file-type-pdf me-1'></i>" . htmlescape(__('Générer le PV PDF', 'protocolsmanager')) . '</button></div>';
		echo '</form>';
	}

	private static function renderDisposalForm(array $group, string $immo_number, string $root_doc, $DB, string $source_itemtype = '', int $source_item_id = 0): void
	{
		$pv_template = PluginProtocolsmanagerPvtemplate::load('disposal');
		$form = $pv_template['form'] ?? [];
		if (!Session::haveRight('document', CREATE)) {
			echo "<div class='alert alert-info'>" . htmlescape(__('Votre profil ne permet pas de créer des documents GLPI.', 'protocolsmanager')) . '</div>';
			return;
		}
		echo "<form method='post' action='" . htmlescape($root_doc . '/plugins/protocolsmanager/front/commission.form.php') . "' class='commission-form'>";
		if (method_exists(Session::class, 'getNewCSRFToken')) {
			echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		}
		echo Html::hidden('immo_number', ['value' => $immo_number]);
		if ($immo_number === '' && $source_itemtype !== '' && $source_item_id > 0) {
			echo Html::hidden('source_itemtype', ['value' => $source_itemtype]);
			echo Html::hidden('source_item_id', ['value' => $source_item_id]);
		}
		echo "<div class='mb-3'><h3 class='h4 mb-1'>" . htmlescape($pv_template['title'] ?? __('Procès-verbal de mise au rebut', 'protocolsmanager')) . "</h3><p class='text-muted mb-0'>" . htmlescape(__('Renseignez les informations du procès-verbal et choisissez les contrôles à faire figurer dans le document.', 'protocolsmanager')) . '</p></div>';
		if ($immo_number !== '' && $group !== []) {
			$is_license_group = count(array_filter($group, static fn($asset) => $asset['itemtype'] === self::LICENSE_ITEMTYPE)) === count($group);
			$group_title = $is_license_group ? __('Licence concernée', 'protocolsmanager') : __('Matériels concernés', 'protocolsmanager');
			$item_title = $is_license_group ? __('Licence', 'protocolsmanager') : __('Matériel', 'protocolsmanager');
			echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape($group_title) . '</strong>';
			echo "<span class='badge bg-secondary ms-2'>" . htmlescape(__('Numéro immo', 'protocolsmanager')) . ': ' . htmlescape($immo_number) . "</span></div><div class='card-body'>";
			echo "<div class='form-check mb-2'><input class='form-check-input' type='checkbox' id='disposal-select-all' data-pv-select-all checked><label class='form-check-label' for='disposal-select-all'>" . htmlescape(__('Tout sélectionner / tout décocher', 'protocolsmanager')) . '</label></div>';
			echo "<div class='table-responsive'><table class='table table-sm table-hover align-middle mb-0'><thead><tr><th>" . htmlescape(__('Inclure', 'protocolsmanager')) . '</th><th>' . htmlescape($item_title) . '</th><th>' . htmlescape(__('Type', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Modèle', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Numéro d’inventaire', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Statut', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Personne affectée', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Prix d’achat', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Numéro de série', 'protocolsmanager')) . '</th></tr></thead><tbody>';
			foreach ($group as $asset) {
				$key = $asset['itemtype'] . ':' . $asset['id'];
				$include_label = $is_license_group ? __('Inclure cette licence', 'protocolsmanager') : __('Inclure ce matériel', 'protocolsmanager');
				echo '<tr><td><input class="form-check-input protocolsmanager-pv-item" type="checkbox" name="items[]" value="' . htmlescape($key) . '" checked aria-label="' . htmlescape($include_label) . '"></td>';
				echo '<td><strong>' . htmlescape($asset['name'] !== '' ? $asset['name'] : '#' . $asset['id']) . '</strong></td>';
				echo '<td>' . ($asset['type'] !== '' ? htmlescape($asset['type']) : '—') . '</td><td>' . ($asset['model'] !== '' ? htmlescape($asset['model']) : '—') . '</td>';
				echo '<td>' . ($asset['inventory_number'] !== '' ? htmlescape($asset['inventory_number']) : '—') . '</td><td>' . ($asset['status'] !== '' ? htmlescape($asset['status']) : '—') . '</td><td>' . ($asset['assigned_to'] !== '' ? htmlescape($asset['assigned_to']) : '—') . '</td>';
				$purchase_price = $asset['value'] === null || $asset['value'] === '' ? '' : number_format((float)$asset['value'], 2, ',', ' ') . ' €';
				echo '<td>' . ($purchase_price !== '' ? htmlescape($purchase_price) : '—') . '</td><td>' . ($asset['serial'] !== '' ? htmlescape($asset['serial']) : '—') . '</td></tr>';
			}
			echo '</tbody></table></div></div></section>';
		} else {
			$notice = $source_itemtype === self::LICENSE_ITEMTYPE
				? __('Ce PV sera rattaché à la licence courante. Le numéro immo restera vide.', 'protocolsmanager')
				: __('Ce PV sera rattaché au matériel courant. Le numéro immo restera vide.', 'protocolsmanager');
			echo "<div class='alert alert-secondary'>" . htmlescape($notice) . '</div>';
		}
		echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape(__('Informations générales', 'protocolsmanager')) . '</strong></div><div class="card-body"><div class="row g-3">';
		if (!empty($form['disposal_date']['enabled'])) {
			echo '<div class="col-md-6"><label class="form-label" for="disposal-date">' . htmlescape($form['disposal_date']['label'] ?? __('Date de mise au rebut', 'protocolsmanager')) . '</label><input class="form-control" type="date" id="disposal-date" name="disposal_date" value="' . htmlescape(date('Y-m-d')) . '" required></div>';
		}
		echo '<div class="col-md-6"><label class="form-label">' . htmlescape(__('Service concerné', 'protocolsmanager')) . '</label><input class="form-control" type="text" value="Informatique" readonly></div>';
		$user = new User();
		$user_id = (int)Session::getLoginUserID();
		$user->getFromDB($user_id);
		$requester = trim((string)($user->fields['firstname'] ?? '') . ' ' . (string)($user->fields['realname'] ?? ''));
		$title_id = (int)($user->fields['usertitles_id'] ?? 0);
		$title = '';
		if ($title_id > 0) {
			$title_result = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_usertitles', 'WHERE' => ['id' => $title_id]]);
			$title_row = $title_result->current();
			$title = (string)($title_row['name'] ?? '');
		}
		echo '<div class="col-md-6"><label class="form-label">' . htmlescape(__('Nom du demandeur', 'protocolsmanager')) . '</label><input class="form-control" type="text" value="' . htmlescape($requester) . '" readonly></div>';
		echo '<div class="col-md-6"><label class="form-label">' . htmlescape(__('Fonction', 'protocolsmanager')) . '</label><input class="form-control" type="text" value="' . htmlescape($title) . '" readonly></div>';
		echo '</div></div></section>';

		if (!empty($form['reason']['enabled'])) {
			echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape($form['reason']['label'] ?? __('Motif de la mise au rebut', 'protocolsmanager')) . '</strong></div><div class="card-body"><textarea class="form-control" name="disposal_reason" rows="4" aria-label="' . htmlescape($form['reason']['label'] ?? '') . '" required></textarea></div></section>';
		}
		if (!empty($form['method']['enabled'])) {
			echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape($form['method']['label'] ?? __('Mode de mise au rebut', 'protocolsmanager')) . '</strong></div><div class="card-body">';
		foreach (self::disposalMethodOptions() as $value => $label) {
			echo '<div class="form-check mb-1"><input class="form-check-input" type="radio" name="disposal_method" id="disposal-method-' . htmlescape($value) . '" value="' . htmlescape($value) . '" required><label class="form-check-label" for="disposal-method-' . htmlescape($value) . '">' . htmlescape($label) . '</label></div>';
		}
		echo '<label class="form-label mt-2" for="disposal-method-other">' . htmlescape(__('Préciser si autre', 'protocolsmanager')) . '</label><textarea class="form-control" id="disposal-method-other" name="disposal_method_other" rows="2"></textarea></div></section>';
		}
		$active_checks = array_values(array_filter(($form['checks'] ?? []), static fn($entry) => is_array($entry) && !empty($entry['enabled']) && !empty($entry['key']) && !empty($entry['label'])));
		if ($active_checks !== []) {
			echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape(__('Liste de contrôle', 'protocolsmanager')) . '</strong></div><div class="card-body"><div class="row g-2">';
			foreach ($active_checks as $check) {
				$key = (string)$check['key'];
				$checked = !empty($check['checked']) ? ' checked' : '';
				echo '<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="' . htmlescape($key) . '"' . $checked . '> <span class="form-check-label">' . htmlescape($check['label']) . '</span></label></div>';
			}
			echo '</div></div></section>';
		}
		echo "<p class='form-text mb-3'>" . htmlescape(__('Le logo et la couleur d’accentuation globaux seront appliqués à ce PDF.', 'protocolsmanager')) . '</p>';
		echo "<div class='d-flex justify-content-end mb-4'><button class='btn btn-primary' type='submit' name='generate_disposal' value='1'><i class='ti ti-file-type-pdf me-1'></i>" . htmlescape(__('Générer le PV de mise au rebut', 'protocolsmanager')) . '</button></div></form>';
	}

	private static function showGeneratedDocuments(string $itemtype, int $item_id): void
	{
		global $DB;
		$links = $DB->request([
			'FROM' => 'glpi_documents_items',
			'WHERE' => ['itemtype' => $itemtype, 'items_id' => $item_id],
			'ORDER' => ['documents_id DESC'],
		]);
		$documents = [];
		foreach ($links as $link) {
			$document_id = (int)$link['documents_id'];
			if (isset($documents[$document_id])) {
				continue;
			}
			$document = new Document();
			$comment = '';
			$found = $document->getFromDB($document_id);
			if ($found) {
				$comment = (string)($document->fields['comment'] ?? '');
			}
			$is_plugin_pv = str_starts_with($comment, __('Procès-verbal de mise en service', 'protocolsmanager') . ' — ')
				|| str_starts_with($comment, __('Procès-verbal de mise au rebut', 'protocolsmanager') . ' — ');
			if ($found && $document->can($document_id, READ) && $is_plugin_pv) {
				$documents[$document_id] = $document;
			}
		}
		echo "<section class='card mb-3'><div class='card-header'><strong>" . htmlescape(__('Documents générés', 'protocolsmanager')) . '</strong></div><div class="card-body p-0">';
		echo "<div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr><th>" . htmlescape(__('Document', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Date', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Fichier', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Créé par', 'protocolsmanager')) . '</th><th>' . htmlescape(__('Commentaire', 'protocolsmanager')) . '</th></tr></thead><tbody>';
		if ($documents === []) {
			echo "<tr><td colspan='5' class='text-center text-muted py-3'>" . htmlescape(__('Aucun PV généré pour cet élément.', 'protocolsmanager')) . '</td></tr>';
		} else {
			foreach ($documents as $document) {
				$creator = new User();
				$creator_link = '';
				$creator_id = (int)($document->fields['users_id'] ?? 0);
				if ($creator_id > 0 && $creator->getFromDB($creator_id)) {
					$creator_link = $creator->getLink();
				}
				echo '<tr><td>' . $document->getLink() . '</td>';
				echo '<td>' . htmlescape($document->fields['date_creation'] ?? '') . '</td>';
				echo '<td>' . $document->getDownloadLink() . '</td><td>' . $creator_link . '</td>';
				echo '<td>' . htmlescape($document->fields['comment'] ?? '') . '</td></tr>';
			}
		}
		echo '</tbody></table></div></div></section>';
	}

	private static function loadAsset(string $itemtype, int $id): ?array
	{
		global $DB;
		if (!self::isSupportedSourceItemType($itemtype)) {
			return null;
		}
		$item = getItemForItemtype($itemtype);
		if (!$item || !$item->getFromDB($id) || !$item->can($id, READ)) {
			return null;
		}
		$table = getTableForItemType($itemtype);
		$asset_result = $DB->request(['FROM' => $table, 'WHERE' => ['id' => $id]]);
		$fields = $asset_result->current();
		if (!$fields) {
			return null;
		}
		$info_result = $DB->request(['FROM' => 'glpi_infocoms', 'WHERE' => ['itemtype' => $itemtype, 'items_id' => $id]]);
		$info = $info_result->current() ?: [];
		$location = self::locationName((int)($fields['locations_id'] ?? 0));
		$supplier = self::supplierName((int)($info['suppliers_id'] ?? 0));
		$manufacturer = self::dropdownName('glpi_manufacturers', (int)($fields['manufacturers_id'] ?? 0));
		$model = self::modelName($itemtype, $fields);
		$status = self::dropdownName('glpi_states', (int)($fields['states_id'] ?? 0));
		$assigned_to = self::userName((int)($fields['users_id'] ?? 0));
		return [
			'id' => $id,
			'itemtype' => $itemtype,
			'type' => $item->getTypeName(),
			'name' => (string)($fields['name'] ?? ''),
			'status' => $status,
			'assigned_to' => $assigned_to,
			'immo_number' => trim((string)($info['immo_number'] ?? '')),
			'inventory_number' => (string)($fields['otherserial'] ?? ''),
			'serial' => (string)($fields['serial'] ?? ''),
			'manufacturer' => $manufacturer,
			'model' => $model,
			'supplier' => $supplier,
			'buy_date' => (string)($info['buy_date'] ?? ''),
			'service_date' => (string)($info['buy_date'] ?? ''),
			'bill' => (string)($info['bill'] ?? ''),
			'value' => $info['value'] ?? null,
			'amortization_type' => (int)($info['sink_type'] ?? 0),
			'amortization_coefficient' => (float)($info['sink_coeff'] ?? 0),
			'amortization_duration' => (int)($info['sink_time'] ?? 0),
			'use_date' => (string)($info['use_date'] ?? ''),
			'location' => $location,
			'model_photo' => self::modelPictureDataUri($itemtype, $fields),
		];
	}

	private static function calculateNetBookValue(array $asset, string $as_of_date): ?float
	{
		global $CFG_GLPI;
		$type = (int)($asset['amortization_type'] ?? 0);
		$value = (float)($asset['value'] ?? 0);
		$duration = (int)($asset['amortization_duration'] ?? 0);
		$coefficient = (float)($asset['amortization_coefficient'] ?? 0);
		$buy_date = (string)($asset['buy_date'] ?? '');
		$use_date = (string)($asset['use_date'] ?? '');
		$fiscal_date = (string)($CFG_GLPI['date_tax'] ?? '');
		$target_date = DateTimeImmutable::createFromFormat('!Y-m-d', $as_of_date);

		if (!class_exists('Infocom') || !method_exists('Infocom', 'Amort')
			|| $type === 0 || $value <= 0 || $duration <= 0
			|| $fiscal_date === '' || ($buy_date === '' && $use_date === '')
			|| !$target_date || $target_date->format('Y-m-d') !== $as_of_date) {
			return null;
		}

		try {
			$schedule = Infocom::Amort($type, $value, $duration, $coefficient, $buy_date, $use_date, $fiscal_date, 'all');
		} catch (Throwable $exception) {
			error_log('ProtocolsManager net book value calculation failed: ' . $exception->getMessage());
			return null;
		}

		if (!is_array($schedule)
			|| !is_array($schedule['annee'] ?? null)
			|| !is_array($schedule['vcnetdeb'] ?? null)
			|| !is_array($schedule['vcnetfin'] ?? null)
			|| $schedule['annee'] === []) {
			return null;
		}

		$target_year = (int)$target_date->format('Y');
		$schedule_index = null;
		$first_year = null;
		foreach ($schedule['annee'] as $index => $year) {
			$year = (int)$year;
			$first_year = $first_year === null ? $year : min($first_year, $year);
			if ($year === $target_year) {
				$schedule_index = $index;
				break;
			}
		}
		if ($schedule_index === null) {
			return $target_year < $first_year ? $value : 0.0;
		}

		$fiscal_timestamp = strtotime($fiscal_date);
		if ($fiscal_timestamp === false) {
			return null;
		}
		$fiscal_month_day = date('m-d', $fiscal_timestamp);
		$target_month_day = $target_date->format('m-d');
		// GLPI's linear amortization returns the ending value for the requested year.
		// Its declining-balance calculation switches between start/end at the fiscal date.
		$balance_key = $type === 2 || $target_month_day > $fiscal_month_day ? 'vcnetfin' : 'vcnetdeb';
		$net_value = $schedule[$balance_key][$schedule_index] ?? null;

		return is_numeric($net_value) ? (float)$net_value : null;
	}

	private static function modelName(string $itemtype, array $fields): string
	{
		$model_fields = [
			'Computer' => ['computermodels_id', 'glpi_computermodels'],
			'Monitor' => ['monitormodels_id', 'glpi_monitormodels'],
			'Printer' => ['printermodels_id', 'glpi_printermodels'],
			'Peripheral' => ['peripheralmodels_id', 'glpi_peripheralmodels'],
			'Phone' => ['phonemodels_id', 'glpi_phonemodels'],
			'NetworkEquipment' => ['networkequipmentmodels_id', 'glpi_networkequipmentmodels'],
		];
		if (!isset($model_fields[$itemtype])) {
			return '';
		}
		[$field, $table] = $model_fields[$itemtype];
		return self::dropdownName($table, (int)($fields[$field] ?? 0));
	}

	private static function modelPictureDataUri(string $itemtype, array $fields): string
	{
		global $DB;
		$model_fields = [
			'Computer' => ['computermodels_id', 'glpi_computermodels'],
			'Monitor' => ['monitormodels_id', 'glpi_monitormodels'],
			'Printer' => ['printermodels_id', 'glpi_printermodels'],
			'Peripheral' => ['peripheralmodels_id', 'glpi_peripheralmodels'],
			'Phone' => ['phonemodels_id', 'glpi_phonemodels'],
			'NetworkEquipment' => ['networkequipmentmodels_id', 'glpi_networkequipmentmodels'],
		];
		if (!isset($model_fields[$itemtype])) {
			return '';
		}
		[$model_field, $model_table] = $model_fields[$itemtype];
		$model_id = (int)($fields[$model_field] ?? 0);
		if ($model_id <= 0) {
			return '';
		}
		$picture_fields = [];
		foreach (['picture_front', 'pictures', 'picture'] as $field) {
			if ($DB->fieldExists($model_table, $field)) {
				$picture_fields[] = $field;
			}
		}
		if ($picture_fields === []) {
			return '';
		}
		$result = $DB->request([
			'SELECT' => $picture_fields,
			'FROM' => $model_table,
			'WHERE' => ['id' => $model_id],
		]);
		$model = $result->current();
		$candidates = [];
		foreach (['picture_front', 'picture'] as $field) {
			if (!empty($model[$field])) {
				$candidates[] = (string)$model[$field];
			}
		}
		if (!empty($model['pictures'])) {
			$gallery = importArrayFromDB((string)$model['pictures']);
			if (is_array($gallery)) {
				foreach ($gallery as $picture) {
					if (is_string($picture) && $picture !== '') {
						$candidates[] = $picture;
					}
				}
			}
		}
		$picture_directory = realpath(GLPI_PICTURE_DIR);
		if ($picture_directory === false) {
			return '';
		}
		foreach ($candidates as $candidate) {
			$relative_path = str_replace('\\', '/', trim($candidate));
			if ($relative_path === '' || str_starts_with($relative_path, '/') || preg_match('#(^|/)\.\.?(/|$)#', $relative_path)) {
				continue;
			}
			$path = realpath($picture_directory . '/' . $relative_path);
			if ($path === false || !str_starts_with($path, $picture_directory . DIRECTORY_SEPARATOR)
				|| !is_file($path) || !is_readable($path) || (int)filesize($path) > 5242880) {
				continue;
			}
			$image_info = @getimagesize($path);
			$mime = is_array($image_info) ? (string)($image_info['mime'] ?? '') : '';
			if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
				continue;
			}
			return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($path));
		}
		return '';
	}

	private static function dropdownName(string $table, int $id): string
	{
		global $DB;
		if ($id <= 0) {
			return '';
		}
		$result = $DB->request(['SELECT' => ['name'], 'FROM' => $table, 'WHERE' => ['id' => $id]]);
		$row = $result->current();
		return (string)($row['name'] ?? '');
	}

	private static function userName(int $id): string
	{
		if ($id <= 0) {
			return '';
		}
		$user = new User();
		return $user->getFromDB($id) ? $user->getFriendlyName() : '';
	}

	private static function isSupportedSourceItemType(string $itemtype): bool
	{
		return in_array($itemtype, self::ITEM_TYPES, true) || $itemtype === self::LICENSE_ITEMTYPE;
	}

	private static function findAssetGroup(string $immo_number): ?array
	{
		global $DB;
		$group = [];
		foreach (self::ITEM_TYPES as $itemtype) {
			$rows = $DB->request([
				'FROM' => 'glpi_infocoms',
				'WHERE' => ['itemtype' => $itemtype, 'immo_number' => $immo_number],
			]);
			foreach ($rows as $info) {
				$item = getItemForItemtype($itemtype);
				$item_id = (int)$info['items_id'];
				if (!$item || !$item->getFromDB($item_id)) {
					continue;
				}
				if (!$item->can($item_id, READ)) {
					return null;
				}
				$asset = self::loadAsset($itemtype, $item_id);
				if ($asset && $asset['immo_number'] === $immo_number) {
					$group[] = $asset;
				}
			}
		}
		return $group;
	}

	private static function commonPurchaseDate(array $assets): string
	{
		$dates = array_values(array_unique(array_filter(
			array_map(static fn($asset) => trim((string)($asset['buy_date'] ?? '')), $assets),
			static fn($date) => $date !== ''
		)));
		if (count($dates) !== 1) {
			return '';
		}
		return $dates[0];
	}

	private static function displayDate(string $date): string
	{
		if ($date === '') {
			return '';
		}
		$timestamp = strtotime($date);
		return $timestamp === false ? '' : date('d/m/Y', $timestamp);
	}

	private static function locationName(int $location_id): string
	{
		if ($location_id <= 0) {
			return '';
		}
		$location = new Location();
		if (!$location->getFromDB($location_id)) {
			return '';
		}
		$name = (string)($location->fields['name'] ?? '');
		$parent_id = (int)($location->fields['locations_id'] ?? 0);
		$seen = [$location_id => true];
		while ($parent_id > 0 && !isset($seen[$parent_id])) {
			$seen[$parent_id] = true;
			$parent = new Location();
			if (!$parent->getFromDB($parent_id)) {
				break;
			}
			$name = (string)($parent->fields['name'] ?? $name);
			$parent_id = (int)($parent->fields['locations_id'] ?? 0);
		}
		return $name;
	}

	private static function supplierName(int $supplier_id): string
	{
		if ($supplier_id <= 0) {
			return '';
		}
		$supplier = new Supplier();
		return $supplier->getFromDB($supplier_id) ? (string)($supplier->fields['name'] ?? '') : '';
	}

	public static function generateFromRequest(): bool
	{
		global $DB;
		if (!self::hasAccess()) {
			Session::addMessageAfterRedirect(__('Accès refusé'), false, ERROR);
			return false;
		}
		if (!Session::haveRight('document', CREATE)) {
			Session::addMessageAfterRedirect(__('Vous n’avez pas le droit de créer des documents GLPI.'), false, ERROR);
			return false;
		}
		$tokens = $_POST['items'] ?? [];
		$immo = trim((string)($_POST['immo_number'] ?? ''));
		if (!is_array($tokens) || ($immo !== '' && $tokens === []) || ($immo === '' && $tokens !== [])) {
			Session::addMessageAfterRedirect(__('Sélectionnez des matériels pour un numéro immo renseigné, ou laissez le numéro immo vide pour créer un PV autonome.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$assets = [];
		foreach ($tokens as $token) {
			if (!is_string($token) || !preg_match('/^([A-Za-z][A-Za-z0-9]*)\:(\d+)$/', $token, $matches)) {
				continue;
			}
			$asset = self::loadAsset($matches[1], (int)$matches[2]);
			if (!$asset || $asset['immo_number'] === '' || $asset['immo_number'] !== $immo) {
				Session::addMessageAfterRedirect(__('Les matériels sélectionnés doivent être lisibles et porter le même numéro immo.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$assets[$asset['itemtype'] . ':' . $asset['id']] = $asset;
		}
		if ($assets === [] && $immo !== '') {
			Session::addMessageAfterRedirect(__('Aucun matériel valide n’a été sélectionné.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		if ($immo === '') {
			$source_itemtype = (string)($_POST['source_itemtype'] ?? '');
			$source_item_id = (int)($_POST['source_item_id'] ?? 0);
			$source_asset = self::isSupportedSourceItemType($source_itemtype) && $source_item_id > 0
				? self::loadAsset($source_itemtype, $source_item_id)
				: null;
			if (!$source_asset || $source_asset['immo_number'] !== '') {
				Session::addMessageAfterRedirect(__('L’élément courant n’a pas pu être validé pour rattacher le PV.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$assets[$source_itemtype . ':' . $source_item_id] = $source_asset;
		}
		$submitted_date = (string)($_POST['effective_date'] ?? '');
		$effective_date = self::commonPurchaseDate(array_values($assets));
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $submitted_date, $date_parts)
			&& checkdate((int)$date_parts[2], (int)$date_parts[3], (int)$date_parts[1])) {
			$effective_date = $submitted_date;
		}
		$template = PluginProtocolsmanagerPvtemplate::load('commission');
		$active_states = [];
		foreach (($template['form']['states'] ?? []) as $state) {
			if (!empty($state['enabled']) && isset($state['key'])) {
				$active_states[] = (string)$state['key'];
			}
		}
		$submitted_states = $_POST['states'] ?? [];
		$requested_states = is_array($submitted_states) ? array_values(array_filter($submitted_states, 'is_string')) : [];
		$states = array_values(array_intersect($active_states, $requested_states));
		$active_cities = [];
		foreach (($template['form']['cities'] ?? []) as $city_option) {
			if (!empty($city_option['enabled']) && isset($city_option['key'])) {
				$active_cities[] = (string)$city_option['key'];
			}
		}
		$submitted_city = is_string($_POST['city'] ?? null) ? $_POST['city'] : '';
		$city = in_array($submitted_city, $active_cities, true) ? $submitted_city : ($active_cities[0] ?? '');
		$planned_use = self::cleanText($_POST['planned_use'] ?? '', 5000);
		$observations = self::cleanText($_POST['observations'] ?? '', 10000);
		if ($observations === '') {
			$observations = 'RAS';
		}
		return self::createPdf(array_values($assets), $immo, $effective_date, $states, $city, $planned_use, $observations);
	}

	public static function generateDisposalFromRequest(): bool
	{
		if (!self::hasAccess()) {
			Session::addMessageAfterRedirect(__('Accès refusé'), false, ERROR);
			return false;
		}
		if (!Session::haveRight('document', CREATE)) {
			Session::addMessageAfterRedirect(__('Vous n’avez pas le droit de créer des documents GLPI.'), false, ERROR);
			return false;
		}
		$tokens = $_POST['items'] ?? [];
		$immo = trim((string)($_POST['immo_number'] ?? ''));
		if (!is_array($tokens) || ($immo !== '' && $tokens === []) || ($immo === '' && $tokens !== [])) {
			Session::addMessageAfterRedirect(__('La sélection des matériels est invalide.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$assets = [];
		foreach ($tokens as $token) {
			if (!is_string($token) || !preg_match('/^([A-Za-z][A-Za-z0-9]*)\:(\d+)$/', $token, $matches)) {
				continue;
			}
			$asset = self::loadAsset($matches[1], (int)$matches[2]);
			if (!$asset || $asset['immo_number'] === '' || $asset['immo_number'] !== $immo) {
				Session::addMessageAfterRedirect(__('Les éléments sélectionnés doivent être lisibles et porter le même numéro immo.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$assets[$asset['itemtype'] . ':' . $asset['id']] = $asset;
		}
		if ($assets === [] && $immo !== '') {
			Session::addMessageAfterRedirect(__('Aucun élément valide n’a été sélectionné.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		if ($immo === '') {
			$source_itemtype = (string)($_POST['source_itemtype'] ?? '');
			$source_item_id = (int)($_POST['source_item_id'] ?? 0);
			$source_asset = self::isSupportedSourceItemType($source_itemtype) && $source_item_id > 0
				? self::loadAsset($source_itemtype, $source_item_id)
				: null;
			if (!$source_asset || $source_asset['immo_number'] !== '') {
				Session::addMessageAfterRedirect(__('L’élément courant n’a pas pu être validé pour rattacher le PV.', 'protocolsmanager'), false, ERROR);
				return false;
			}
			$assets[$source_itemtype . ':' . $source_item_id] = $source_asset;
		}
		$submitted_date = (string)($_POST['disposal_date'] ?? '');
		$disposal_date = date('Y-m-d');
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $submitted_date, $date_parts)
			&& checkdate((int)$date_parts[2], (int)$date_parts[3], (int)$date_parts[1])) {
			$disposal_date = $submitted_date;
		}
		$template = PluginProtocolsmanagerPvtemplate::load('disposal');
		$reason = self::cleanText($_POST['disposal_reason'] ?? '', 10000);
		$method_key = is_string($_POST['disposal_method'] ?? null) ? $_POST['disposal_method'] : '';
		$method_options = self::disposalMethodOptions();
		$method_other = self::cleanText($_POST['disposal_method_other'] ?? '', 10000);
		$reason_required = !empty($template['form']['reason']['enabled']);
		$method_required = !empty($template['form']['method']['enabled']);
		$is_other_method = isset($method_options[$method_key])
			&& ($method_key === 'autre' || strtolower($method_options[$method_key]) === 'autre');
		if (($reason_required && $reason === '') || ($method_required && !isset($method_options[$method_key])) || ($method_required && $is_other_method && $method_other === '')) {
			Session::addMessageAfterRedirect(__('Le motif et le mode de mise au rebut sont obligatoires. Précisez le mode si « Autre » est sélectionné.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$method = isset($method_options[$method_key]) ? $method_options[$method_key] . ($is_other_method ? ' : ' . $method_other : '') : '';
		$active_checks = [];
		foreach (($template['form']['checks'] ?? []) as $entry) {
			if (is_array($entry) && !empty($entry['enabled']) && isset($entry['key'])) {
				$active_checks[(string)$entry['key']] = (string)($entry['label'] ?? '');
			}
		}
		$submitted_checks = $_POST['checks'] ?? [];
		$checks = is_array($submitted_checks) ? array_values(array_intersect(array_keys($active_checks), array_filter($submitted_checks, 'is_string'))) : [];
		return self::createDisposalPdf(array_values($assets), $immo, $disposal_date, $reason, $method, $active_checks, $checks);
	}

	private static function disposalMethodOptions(): array
	{
		$template = PluginProtocolsmanagerPvtemplate::load('disposal');
		$options = [];
		foreach (($template['form']['methods'] ?? []) as $entry) {
			if (!empty($entry['enabled']) && !empty($entry['key']) && !empty($entry['label'])) {
				$options[(string)$entry['key']] = (string)$entry['label'];
			}
		}
		return $options;
	}

	private static function cleanText($value, int $limit): string
	{
		if (!is_string($value)) {
			return '';
		}
		$value = strip_tags($value);
		return mb_substr(trim($value), 0, $limit);
	}

	private static function createDisposalPdf(array $assets, string $immo, string $disposal_date, string $reason, string $method, array $check_labels, array $checks): bool
	{
		global $DB;
		$config = PluginProtocolsmanagerPvtemplate::load('branding');
		$accent = $config['accent_color'] ?? '#8A6428';
		$header_text_color = '#FFFFFF';
		if (!is_string($accent) || !preg_match('/^#[0-9a-f]{6}$/i', $accent)) {
			$accent = '#8A6428';
		}
		if (!is_string($header_text_color) || !preg_match('/^#[0-9a-f]{6}$/i', $header_text_color)) {
			$header_text_color = '#FFFFFF';
		}
		$logo_path = !empty($config['logo']) ? GLPI_PICTURE_DIR . '/' . basename($config['logo']) : '';
		$logo_html = '';
		$logo_width = 120;
		$logo_height = 120;
		if ($logo_path !== '' && is_file($logo_path) && is_readable($logo_path)) {
			$mime = function_exists('mime_content_type') ? mime_content_type($logo_path) : 'image/png';
			if (!is_string($mime) || !in_array($mime, ['image/png', 'image/jpeg'], true)) {
				$mime = 'image/png';
			}
			$data = base64_encode((string)file_get_contents($logo_path));
			$logo_html = '<img class="logo" style="max-width:' . $logo_width . 'px;max-height:' . $logo_height . 'px" src="data:' . htmlescape($mime) . ';base64,' . $data . '" alt="">';
		}
		$user = new User();
		$user_id = (int)Session::getLoginUserID();
		$user->getFromDB($user_id);
		$requester = trim((string)($user->fields['firstname'] ?? '') . ' ' . (string)($user->fields['realname'] ?? ''));
		$title_id = (int)($user->fields['usertitles_id'] ?? 0);
		$title = '';
		if ($title_id > 0) {
			$title_result = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_usertitles', 'WHERE' => ['id' => $title_id]]);
			$title_row = $title_result->current();
			$title = (string)($title_row['name'] ?? '');
		}
		$total = 0.0;
		$all_amounts_present = $assets !== [];
		foreach ($assets as $asset) {
			if ($asset['value'] === null || $asset['value'] === '') {
				$all_amounts_present = false;
			} else {
				$total += (float)$asset['value'];
			}
		}
		$requester_with_title = $requester;
		if ($title !== '') {
			$requester_with_title .= ' - ' . $title;
		}
		$pv_template = PluginProtocolsmanagerPvtemplate::load('disposal');
		$disposal_form = is_array($pv_template['form'] ?? null) ? $pv_template['form'] : [];
		if (!array_key_exists('validators', $disposal_form)) {
			$disposal_form['validators'] = PluginProtocolsmanagerPvtemplate::defaults('disposal')['form']['validators'];
		}
		$configured_validators = $disposal_form['validators'];
		$validator_roles = array_values(array_filter(
			is_array($configured_validators) ? $configured_validators : [],
			static fn($validator) => is_array($validator) && !empty($validator['enabled']) && trim((string)($validator['label'] ?? '')) !== ''
		));
		$form_labels = $pv_template['form'] ?? [];
		$chapters = [];
		foreach (($pv_template['chapters'] ?? []) as $chapter) {
			if (isset($chapter['key'])) {
				$chapters[$chapter['key']] = $chapter;
			}
		}
		$chapter_title = static fn($key, $fallback) => (string)($chapters[$key]['title'] ?? $fallback);
		$chapter_enabled = static fn($key) => !isset($chapters[$key]) || !empty($chapters[$key]['enabled']);
		$chapter_intro_source = static fn($key) => trim((string)($chapters[$key]['content'] ?? ''));
		$reason_intro_has_token = str_contains($chapter_intro_source('reason'), '{reason}');
		$method_intro_has_token = str_contains($chapter_intro_source('method'), '{method}');
		$chapter_intro = static fn($key) => strtr($chapter_intro_source($key), ['{reason}' => $reason, '{method}' => $method]);
		$escape = static fn($value) => htmlescape((string)$value);
		ob_start();
?>
<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 20mm 16mm 24mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
h1 { color: <?= $escape($accent) ?>; text-align:center; font-size:16pt; margin: 0 0 4mm; }
h2 { color:<?= $escape($header_text_color) ?>; background:<?= $escape($accent) ?>; padding:5px 7px; font-size:10.5pt; margin:5mm 0 2mm; }
.logo { display:block; max-width:<?= min(120, $logo_width) ?>px; max-height:<?= min(120, $logo_height) ?>px; margin:0 auto; }
.pdf-header { table-layout:fixed; width:100%; margin:0 0 5mm; }
.pdf-header td { border:0; padding:0 3px 2mm; vertical-align:middle; }
.pdf-header .immo-cell { width:34%; text-align:left; }
.pdf-header .logo-cell { width:32%; text-align:center; }
.pdf-header .date-cell { width:34%; text-align:right; }
.immo-label, .date-label { color:#111; font-size:9pt; font-weight:normal; }
.immo-value, .date-value { display:inline-block; box-sizing:border-box; min-width:45mm; height:9mm; color:<?= $escape($accent) ?>; font-size:13pt; line-height:7mm; font-weight:bold; border-bottom:2px solid <?= $escape($accent) ?>; padding:0 5mm 1mm; vertical-align:bottom; }
.date-value.is-empty { min-height:9mm; font-size:9pt; font-weight:normal; }
table { width:100%; border-collapse:collapse; margin:2mm 0 3mm; }
th,td { border:1px solid #999; padding:4px 5px; vertical-align:top; }
th { background:#f1f1f1; text-align:left; }
.field { font-weight:bold; white-space:nowrap; }
.general-table { table-layout:fixed; }
.general-table .service-label { width:20%; }
.general-table .service-value { width:17%; }
.general-table .requester-label { width:16%; }
.asset { page-break-inside:avoid; margin-bottom:3mm; }
.asset-table { table-layout:fixed; }
.free { white-space:pre-wrap; min-height:9mm; }
.model-photo-item { page-break-inside:avoid; margin:3mm 0 6mm; text-align:center; }
.model-photo-item img { max-width:150mm; max-height:105mm; }
.signature { table-layout:fixed; }
.signature .role-col { width:20%; }
.signature .name-col { width:27%; }
.signature .sign-col { width:33%; }
.signature .date-col { width:20%; }
.signature td { height:18mm; }
</style></head><body>
<table class="pdf-header"><tr><td class="immo-cell"><span class="immo-label"><?= $escape(__('Numéro immo', 'protocolsmanager')) ?> :</span><br><span class="immo-value"><?= $escape($immo) ?></span></td><td class="logo-cell"><?= $logo_html ?></td><td class="date-cell"><span class="date-label"><?= $escape($form_labels['disposal_date']['label'] ?? __('Date de mise au rebut', 'protocolsmanager')) ?> :</span><br><span class="date-value<?= $disposal_date === '' ? ' is-empty' : '' ?>"><?= $escape(self::displayDate($disposal_date)) ?></span></td></tr></table>
<h1><?= $escape($pv_template['title'] ?? __('Procès-verbal de mise au rebut', 'protocolsmanager')) ?></h1>
<?php if ($chapter_enabled('general')): ?><h2>1. <?= $escape($chapter_title('general', __('Informations générales', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('general') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('general'))) ?></p><?php endif; ?>
<table class="general-table"><tbody>
<tr><td class="field service-label"><?= $escape(__('Service concerné', 'protocolsmanager')) ?></td><td class="service-value">Informatique</td><td class="field requester-label"><?= $escape(__('Demandeur', 'protocolsmanager')) ?></td><td><?= $escape($requester_with_title) ?></td></tr>
</tbody></table>
<?php endif; ?>
<?php if ($chapter_enabled('asset')): ?><h2>2. <?= $escape($chapter_title('asset', __('Détails de l’immobilisation', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('asset') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('asset'))) ?></p><?php endif; ?>
<?php foreach ($assets as $asset): ?><div class="asset"><table class="asset-table"><colgroup><col class="label" style="width:30%"><col class="value" style="width:20%"><col class="label" style="width:24%"><col class="value" style="width:26%"></colgroup><tbody>
<?php
$make_model = implode(' — ', array_filter([$asset['manufacturer'], $asset['model']], static fn($value) => $value !== ''));
if ($make_model !== '' && $asset['type'] !== '') {
	$make_model .= ' (' . $asset['type'] . ')';
} elseif ($make_model === '') {
	$make_model = $asset['type'];
}
$amount = $asset['value'] === null || $asset['value'] === '' ? '' : number_format((float)$asset['value'], 2, ',', ' ') . ' €';
$calculated_net_book_value = self::calculateNetBookValue($asset, $disposal_date);
$net_book_value = $calculated_net_book_value === null
	? ''
	: number_format($calculated_net_book_value, 2, ',', ' ') . ' €';
?>
<tr><td class="field" style="width:30%"><?= $escape(__('Désignation', 'protocolsmanager')) ?></td><td colspan="3"><?= $escape($asset['name']) ?></td></tr>
<tr><td class="field" style="width:30%"><?= $escape(__('Numéro d’inventaire', 'protocolsmanager')) ?></td><td><?= $escape($asset['inventory_number']) ?></td><td class="field"><?= $escape(__('Numéro de série', 'protocolsmanager')) ?></td><td><?= $escape($asset['serial']) ?></td></tr>
<tr><td class="field" style="width:30%"><?= $escape(__('Date de mise en service', 'protocolsmanager')) ?></td><td><?= $escape(self::displayDate($asset['service_date'])) ?></td><td class="field"><?= $escape(__('Montant', 'protocolsmanager')) ?></td><td><?= $escape($amount) ?></td></tr>
<tr><td class="field" style="width:30%"><?= $escape(__('Valeur nette comptable', 'protocolsmanager')) ?></td><td colspan="3"><?= $escape($net_book_value) ?></td></tr>
<tr><td class="field" style="width:30%"><?= $escape(__('Marque / Modèle', 'protocolsmanager')) ?></td><td colspan="3"><?= $escape($make_model) ?></td></tr>
</tbody></table></div><?php endforeach; ?>
<?php if (count($assets) > 1 && $all_amounts_present): ?><p><strong><?= $escape(__('Montant total', 'protocolsmanager')) ?> : <?= $escape(number_format($total, 2, ',', ' ')) ?> €</strong></p><?php endif; ?><?php endif; ?>
<?php if ($chapter_enabled('reason')): ?><h2>3. <?= $escape($chapter_title('reason', __('Motif de la mise au rebut', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('reason') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('reason'))) ?></p><?php endif; ?><?php if (!$reason_intro_has_token): ?><div class="free"><?= $escape($reason) ?></div><?php endif; ?><?php endif; ?>
<?php if ($chapter_enabled('method')): ?><h2>4. <?= $escape($chapter_title('method', __('Mode de mise au rebut', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('method') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('method'))) ?></p><?php endif; ?><?php if (!$method_intro_has_token): ?><div class="free"><?= $escape($method) ?></div><?php endif; ?><?php endif; ?>
<?php if ($chapter_enabled('checks') && $check_labels !== []): ?><h2>5. <?= $escape($chapter_title('checks', __('Liste de contrôle', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('checks') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('checks'))) ?></p><?php endif; ?><ul><?php foreach ($check_labels as $key => $label): ?><li><?= in_array($key, $checks, true) ? '☒' : '☐' ?> <?= $escape($label) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php
$disposal_custom_annexes = [];
$disposal_photo_markup = '';
foreach ($assets as $asset) {
	if ($asset['model_photo'] !== '') {
		$disposal_photo_markup .= '<div class="model-photo-item"><strong>' . $escape($asset['name']) . '</strong><br><img src="' . $escape($asset['model_photo']) . '" alt="' . $escape($asset['name']) . '"></div>';
	}
}
$render_disposal_content = static fn(string $content): string => str_replace('{photo}', $disposal_photo_markup, nl2br($escape($content)));
foreach (($pv_template['custom_chapters'] ?? []) as $custom_chapter) {
	$title = trim((string)($custom_chapter['title'] ?? ''));
	if (empty($custom_chapter['enabled']) || $title === '' || in_array(strtolower($title), ['photo', 'photos', 'signature', 'signatures'], true)) {
		continue;
	}
	$disposal_custom_annexes[] = $custom_chapter;
}
$has_disposal_annexes = $chapter_enabled('model_photo') || $disposal_custom_annexes !== [];
?>
<?php if ($has_disposal_annexes): ?><h2>6. <?= $escape(__('Annexes', 'protocolsmanager')) ?></h2>
<?php if ($chapter_enabled('model_photo')): ?><?php $photo_intro_source = $chapter_intro_source('model_photo'); if ($photo_intro_source === '') { $photo_intro_source = '{photo}'; } $rendered_photo_intro = $render_disposal_content($photo_intro_source); ?><?php if (trim($rendered_photo_intro) !== ''): ?><p class="free"><?= $rendered_photo_intro ?></p><?php endif; ?>
<?php if (!str_contains($photo_intro_source, '{photo}')): ?><?php foreach ($assets as $asset): ?><div class="model-photo-item"><strong><?= $escape($asset['name']) ?></strong><br><?php if ($asset['model_photo'] !== ''): ?><img src="<?= $escape($asset['model_photo']) ?>" alt="<?= $escape($asset['name']) ?>"><?php else: ?><p><?= $escape(__('Aucune photo principale enregistrée pour ce modèle.', 'protocolsmanager')) ?></p><?php endif; ?></div><?php endforeach; ?><?php endif; ?>
<?php endif; ?>
<?php foreach ($disposal_custom_annexes as $custom_chapter): ?><?php if (!preg_match('/^annexes?(?:\s+\d+)?\b/i', trim((string)$custom_chapter['title']))): ?><h3><?= $escape($custom_chapter['title']) ?></h3><?php endif; ?><?php $rendered_custom_content = $render_disposal_content((string)($custom_chapter['content'] ?? '')); ?><?php if (trim($rendered_custom_content) !== ''): ?><div class="free"><?= $rendered_custom_content ?></div><?php endif; ?><?php endforeach; ?>
<?php endif; ?>
<?php if ($chapter_enabled('signature')): ?><h2>7. <?= $escape(__('Signatures', 'protocolsmanager')) ?></h2>
<table class="signature"><colgroup><col class="role-col"><col class="name-col"><col class="sign-col"><col class="date-col"></colgroup><tr><th><?= $escape(__('Fonction', 'protocolsmanager')) ?></th><th><?= $escape(__('Nom', 'protocolsmanager')) ?></th><th><?= $escape(__('Signature', 'protocolsmanager')) ?></th><th><?= $escape(__('Date', 'protocolsmanager')) ?></th></tr>
<?php foreach ($validator_roles as $validator): ?><tr><td><?= $escape($validator['label']) ?></td><td><?= ($validator['key'] ?? '') === 'applicants' ? $escape($requester) : '' ?></td><td></td><td></td></tr><?php endforeach; ?></table>
<?php endif; ?>
</body></html>
<?php
		$html = ob_get_clean();
		try {
			$options = new Options();
			$options->set('defaultFont', 'DejaVu Sans');
			$pdf = new Dompdf($options);
			$pdf->loadHtml($html, 'UTF-8');
			$pdf->setPaper('A4', 'portrait');
			$pdf->render();
			PluginProtocolsmanagerPdf::addFooter($pdf, $logo_path, __('Procès-verbal de mise au rebut', 'protocolsmanager'), $immo);
			$pdf_content = $pdf->output();
		} catch (\Throwable $exception) {
			error_log('ProtocolsManager disposal PDF rendering failed: ' . $exception->getMessage());
			Session::addMessageAfterRedirect(__('Le PDF n’a pas pu être généré. Consultez les journaux GLPI pour le détail.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$safe_immo = preg_replace('/[^A-Za-z0-9_-]/', '_', $immo);
		$filename = 'PV_mise_au_rebut_' . ($safe_immo !== '' ? $safe_immo : 'sans_numero_immo') . '_' . date('Ymd_His') . '.pdf';
		$path = GLPI_UPLOAD_DIR . '/' . $filename;
		if (file_put_contents($path, $pdf_content) === false) {
			Session::addMessageAfterRedirect(__('Le PDF n’a pas pu être enregistré.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$document = new Document();
		try {
			$document_id = $document->add([
				'name' => $filename,
				'upload_file' => $filename,
				'documentcategories_id' => 0,
				'mime' => 'application/pdf',
				'users_id' => $user_id,
				'entities_id' => Session::getActiveEntity(),
				'comment' => __('Procès-verbal de mise au rebut', 'protocolsmanager') . ' — ' . $immo,
			]);
		} catch (\Throwable $exception) {
			error_log('ProtocolsManager disposal document registration failed: ' . $exception->getMessage());
			@unlink($path);
			Session::addMessageAfterRedirect(__('Le PDF a été créé, mais GLPI n’a pas pu enregistrer le document. Consultez les journaux GLPI pour le détail.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		if (!$document_id) {
			@unlink($path);
			Session::addMessageAfterRedirect(__('Le document PDF n’a pas pu être enregistré dans GLPI.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		foreach ($assets as $asset) {
			$link = new Document_Item();
			$link->add(['documents_id' => $document_id, 'itemtype' => $asset['itemtype'], 'items_id' => $asset['id']]);
		}
		Session::addMessageAfterRedirect(__('Le procès-verbal de mise au rebut a été généré.', 'protocolsmanager'), true, INFO);
		return true;
	}

	private static function createPdf(array $assets, string $immo, string $effective_date, array $states, string $city, string $planned_use, string $observations): bool
	{
		global $DB;
		$config = PluginProtocolsmanagerPvtemplate::load('branding');
		$accent = $config['accent_color'] ?? '#8A6428';
		$header_text_color = '#FFFFFF';
		if (!is_string($accent) || !preg_match('/^#[0-9a-f]{6}$/i', $accent)) {
			$accent = '#8A6428';
		}
		if (!is_string($header_text_color) || !preg_match('/^#[0-9a-f]{6}$/i', $header_text_color)) {
			$header_text_color = '#FFFFFF';
		}
		$logo_path = !empty($config['logo']) ? GLPI_PICTURE_DIR . '/' . basename($config['logo']) : '';
		$logo_html = '';
		$logo_width = 120;
		$logo_height = 120;
		if ($logo_path !== '' && is_file($logo_path) && is_readable($logo_path)) {
			$mime = function_exists('mime_content_type') ? mime_content_type($logo_path) : 'image/png';
			if (!is_string($mime) || !in_array($mime, ['image/png', 'image/jpeg'], true)) {
				$mime = 'image/png';
			}
			$data = base64_encode((string)file_get_contents($logo_path));
			$logo_html = '<img class="logo" style="max-width:' . $logo_width . 'px;max-height:' . $logo_height . 'px" src="data:' . htmlescape($mime) . ';base64,' . $data . '" alt="">';
		}
		$user = new User();
		$user_id = (int)Session::getLoginUserID();
		$user->getFromDB($user_id);
		$signer = trim((string)($user->fields['firstname'] ?? '') . ' ' . (string)($user->fields['realname'] ?? ''));
		$purchase_total = 0.0;
		$all_amounts_present = true;
		foreach ($assets as $asset) {
			if ($asset['value'] === null || $asset['value'] === '') {
				$all_amounts_present = false;
			} else {
				$purchase_total += (float)$asset['value'];
			}
		}
		$pv_template = PluginProtocolsmanagerPvtemplate::load('commission');
		// Existing installations may predate the configurable validator list.
		// Use the original roles only when that setting is absent; an explicit empty list is intentional.
		$commission_form = is_array($pv_template['form'] ?? null) ? $pv_template['form'] : [];
		if (!array_key_exists('validators', $commission_form)) {
			$commission_form['validators'] = PluginProtocolsmanagerPvtemplate::defaults('commission')['form']['validators'];
		}
		$pv_template['form'] = $commission_form;
		$form_labels = $pv_template['form'] ?? [];
		$city_label = $city;
		foreach (($form_labels['cities'] ?? []) as $city_option) {
			if (!empty($city_option['enabled']) && (string)($city_option['key'] ?? '') === $city) {
				$city_label = (string)($city_option['label'] ?? $city);
				break;
			}
		}
		$state_labels = [];
		foreach (($pv_template['form']['states'] ?? []) as $state) {
			if (!empty($state['enabled']) && isset($state['key'])) {
				$state_labels[(string)$state['key']] = (string)($state['label'] ?? '');
			}
		}
		$configured_validators = $pv_template['form']['validators'] ?? [];
		$validator_roles = array_values(array_filter(
			is_array($configured_validators) ? $configured_validators : [],
			static fn($validator) => is_array($validator) && !empty($validator['enabled']) && trim((string)($validator['label'] ?? '')) !== ''
		));
		$chapters = [];
		foreach (($pv_template['chapters'] ?? []) as $chapter) {
			if (isset($chapter['key'])) {
				$chapters[$chapter['key']] = $chapter;
			}
		}
		$chapter_title = static fn($key, $fallback) => (string)($chapters[$key]['title'] ?? $fallback);
		$chapter_enabled = static fn($key) => !isset($chapters[$key]) || !empty($chapters[$key]['enabled']);
		$chapter_intro_source = static fn($key) => trim((string)($chapters[$key]['content'] ?? ''));
		$observations_intro_has_token = str_contains($chapter_intro_source('observations'), '{observations}');
		$chapter_intro = static fn($key) => strtr($chapter_intro_source($key), ['{observations}' => $observations]);
		$escape = static fn($value) => htmlescape((string)$value);
		$model_photo_markup = '';
		foreach ($assets as $asset) {
			if ($asset['model_photo'] !== '') {
				$model_photo_markup .= '<div class="model-photo-item"><strong>' . $escape($asset['name']) . '</strong><br><img src="' . $escape($asset['model_photo']) . '" alt="' . $escape($asset['name']) . '"></div>';
			}
		}
		$render_commission_content = static fn(string $content): string => str_replace('{photo}', $model_photo_markup, nl2br($escape($content)));
		ob_start();
?>
<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 20mm 16mm 24mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
h1 { color: <?= $escape($accent) ?>; text-align:center; font-size:17pt; margin: 0 0 5mm; }
h2 { color:<?= $escape($header_text_color) ?>; background:<?= $escape($accent) ?>; padding:6px 8px; font-size:11pt; margin:7mm 0 3mm; }
.logo { display:block; margin:0 auto 5mm; }
.pdf-header { table-layout:fixed; width:100%; margin:0 0 5mm; }
.pdf-header td { border:0; padding:0 3px 2mm; vertical-align:middle; }
.pdf-header .immo-cell { width:34%; text-align:left; }
.pdf-header .logo-cell { width:32%; text-align:center; }
.pdf-header .date-cell { width:34%; text-align:right; }
.logo { display:block; max-width:120px; max-height:120px; margin:0 auto; }
.immo-label, .date-label { color:#111; font-size:9pt; font-weight:normal; }
.immo-value, .date-value { display:inline-block; box-sizing:border-box; min-width:45mm; height:9mm; color:<?= $escape($accent) ?>; font-size:13pt; line-height:7mm; font-weight:bold; border-bottom:2px solid <?= $escape($accent) ?>; padding:0 5mm 1mm; vertical-align:bottom; }
.date-value.is-empty { min-height:9mm; font-size:9pt; font-weight:normal; }
.effective-date { color:<?= $escape($accent) ?>; text-decoration:underline; }
table { width:100%; border-collapse:collapse; margin:2mm 0 4mm; }
th,td { border:1px solid #999; padding:5px; vertical-align:top; }
th { background:#f1f1f1; text-align:left; }
.asset { page-break-inside:avoid; margin-bottom:4mm; }
.field { width:28%; font-weight:bold; }
.asset-table { table-layout:fixed; }
.asset-table col.label { width:22%; }
.asset-table col.value { width:28%; }
.signature { table-layout:fixed; }
.signature .role-col { width:20%; }
.signature .name-col { width:27%; }
.signature .sign-col { width:33%; }
.signature .date-col { width:20%; }
.signature td { height:18mm; }
.free { white-space:pre-wrap; min-height:9mm; }
.model-photo-item { page-break-inside:avoid; margin:3mm 0 6mm; text-align:center; }
.model-photo-item img { max-width:150mm; max-height:105mm; }
.chapter-four { page-break-before:always; break-before:page; }
</style></head><body>
<table class="pdf-header"><tr>
<td class="immo-cell"><span class="immo-label"><?= $escape(__('Numéro immo', 'protocolsmanager')) ?> :</span><br><span class="immo-value"><?= $escape($immo) ?></span></td>
<td class="logo-cell"><?= $logo_html ?></td>
<td class="date-cell"><span class="date-label"><?= $escape($form_labels['effective_date']['label'] ?? __('Date effective de mise en service', 'protocolsmanager')) ?> :</span><br><span class="date-value<?= $effective_date === '' ? ' is-empty' : '' ?>"><?= $escape(self::displayDate($effective_date)) ?></span></td>
</tr></table>
<h1><?= $escape($pv_template['title'] ?? __('Procès-verbal de mise en service d’une immobilisation', 'protocolsmanager')) ?></h1>
<?php if ($chapter_enabled('identification')): ?><h2>1. <?= $escape($chapter_title('identification', __('Identification de l’immobilisation', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('identification') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('identification'))) ?></p><?php endif; ?>
<?php foreach ($assets as $asset): ?>
<div class="asset"><table class="asset-table"><colgroup><col class="label"><col class="value"><col class="label"><col class="value"></colgroup>
<?php
$rows = [
	__('Désignation', 'protocolsmanager') => trim($asset['name'] . ($asset['type'] !== '' ? ' (' . $asset['type'] . ')' : '')),
	__('Fournisseur', 'protocolsmanager') => $asset['supplier'],
	__('Date de facture', 'protocolsmanager') => self::displayDate($asset['buy_date']),
	__('Numéro de facture', 'protocolsmanager') => $asset['bill'],
	__('Valeur d’acquisition HT', 'protocolsmanager') => $asset['value'] === null || $asset['value'] === '' ? '' : number_format((float)$asset['value'], 2, ',', ' ') . ' €',
	__('Durée d’amortissement', 'protocolsmanager') => $asset['amortization_duration'] > 0
		? sprintf(_n('%d year', '%d years', $asset['amortization_duration']), $asset['amortization_duration'])
		: '',
];
?>
<tr>
<td class="field"><?= $escape(__('Numéro d’inventaire', 'protocolsmanager')) ?></td><td><?= $escape($asset['inventory_number']) ?></td>
<td class="field"><?= $escape(__('Numéro de série', 'protocolsmanager')) ?></td><td><?= $escape($asset['serial']) ?></td>
</tr>
<?php foreach ($rows as $label => $value): ?>
<tr><td class="field"><?= $escape($label) ?></td><td colspan="3"><?= $escape($value) ?></td></tr>
<?php endforeach; ?>
<tr><td class="field"><?= $escape(__('Site d’affectation', 'protocolsmanager')) ?></td><td><?= $escape($asset['location']) ?></td><td class="field"><?= $escape(__('Atelier', 'protocolsmanager')) ?></td><td><?= $escape(__('Informatique', 'protocolsmanager')) ?></td></tr>
</table></div>
<?php endforeach; ?>
<?php if (count($assets) > 1 && $all_amounts_present): ?><p><strong><?= $escape(__('Montant total d’acquisition HT', 'protocolsmanager')) ?> : <?= $escape(number_format($purchase_total, 2, ',', ' ')) ?> €</strong></p><?php endif; ?><?php endif; ?>
<?php if ($chapter_enabled('commissioning')): ?><h2>2. <?= $escape($chapter_title('commissioning', __('Mise en service', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('commissioning') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('commissioning'))) ?></p><?php endif; ?>
<p><?= $escape(__('Je soussigné(e)', 'protocolsmanager')) ?> <strong><?= $escape($signer) ?></strong>, <?= $escape(__('certifie que l’immobilisation décrite ci-dessous a été :', 'protocolsmanager')) ?></p>
<p><?php foreach ($state_labels as $key => $label): ?><?= in_array($key, $states, true) ? '☒' : '☐' ?> <?= $escape($label) ?>&nbsp;&nbsp;<?php endforeach; ?></p>
<p><strong><?= $escape($form_labels['effective_date']['label'] ?? __('Date effective de mise en service', 'protocolsmanager')) ?> :</strong> <span class="effective-date"><?= $escape(self::displayDate($effective_date)) ?></span></p>
<p><strong><?= $escape($form_labels['planned_use']['label'] ?? __('Description de l’utilisation prévue', 'protocolsmanager')) ?> :</strong></p><div class="free"><?= $escape($planned_use) ?></div>
<?php endif; ?>
<?php if ($chapter_enabled('observations')): ?><h2>3. <?= $escape($chapter_title('observations', __('Observations éventuelles', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('observations') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('observations'))) ?></p><?php endif; ?><?php if (!$observations_intro_has_token): ?><div class="free"><?= $escape($observations) ?></div><?php endif; ?><?php endif; ?>
<?php if ($chapter_enabled('model_photo')): ?><h2>4. <?= $escape(__('Annexes', 'protocolsmanager')) ?></h2><?php $photo_intro_source = $chapter_intro_source('model_photo'); if ($photo_intro_source === '') { $photo_intro_source = '{photo}'; } $rendered_photo_intro = $render_commission_content($photo_intro_source); ?><?php if (trim($rendered_photo_intro) !== ''): ?><p class="free"><?= $rendered_photo_intro ?></p><?php endif; ?>
<?php if (!str_contains($photo_intro_source, '{photo}')): ?><?php foreach ($assets as $asset): ?><div class="model-photo-item"><strong><?= $escape($asset['name']) ?></strong><br><?php if ($asset['model_photo'] !== ''): ?><img src="<?= $escape($asset['model_photo']) ?>" alt="<?= $escape($asset['name']) ?>"><?php else: ?><p><?= $escape(__('Aucune photo principale enregistrée pour ce modèle.', 'protocolsmanager')) ?></p><?php endif; ?></div><?php endforeach; ?><?php endif; ?>
<?php endif; ?>
<?php if ($chapter_enabled('validation')): ?><h2 class="chapter-four">5. <?= $escape($chapter_title('validation', __('Validations', 'protocolsmanager'))) ?></h2><?php if ($chapter_intro('validation') !== ''): ?><p class="free"><?= nl2br($escape($chapter_intro('validation'))) ?></p><?php endif; ?>
<table class="signature"><colgroup><col class="role-col"><col class="name-col"><col class="sign-col"><col class="date-col"></colgroup><tr><th><?= $escape(__('Fonction', 'protocolsmanager')) ?></th><th><?= $escape(__('Nom', 'protocolsmanager')) ?></th><th><?= $escape(__('Signature', 'protocolsmanager')) ?></th><th><?= $escape(__('Date', 'protocolsmanager')) ?></th></tr>
<?php foreach ($validator_roles as $validator): ?><tr><td><?= $escape($validator['label']) ?></td><td></td><td></td><td></td></tr><?php endforeach; ?></table>
<p><?= $escape(__('Document établi le', 'protocolsmanager')) ?> : <?= $escape(date('d/m/Y')) ?><?php if ($city_label !== ''): ?>, <?= $escape(__('lieu', 'protocolsmanager')) ?> <?= $escape($city_label) ?><?php endif; ?></p>
<?php endif; ?>
<?php foreach (($pv_template['custom_chapters'] ?? []) as $custom_chapter): ?><?php $custom_title = trim((string)($custom_chapter['title'] ?? '')); ?><?php if (!empty($custom_chapter['enabled']) && $custom_title !== '' && !preg_match('/^annexe\s*1\b/i', $custom_title)): ?><h2><?= $escape($custom_title) ?></h2><?php if (trim((string)($custom_chapter['content'] ?? '')) !== ''): ?><div class="free"><?= $render_commission_content((string)$custom_chapter['content']) ?></div><?php endif; ?><?php endif; ?><?php endforeach; ?>
</body></html>
<?php
		$html = ob_get_clean();
		try {
			$options = new Options();
			$options->set('defaultFont', 'DejaVu Sans');
			$pdf = new Dompdf($options);
			$pdf->loadHtml($html, 'UTF-8');
			$pdf->setPaper('A4', 'portrait');
			$pdf->render();
			PluginProtocolsmanagerPdf::addFooter($pdf, $logo_path, __('Procès-verbal de mise en service', 'protocolsmanager'), $immo);
			$pdf_content = $pdf->output();
		} catch (\Throwable $exception) {
			error_log('ProtocolsManager commissioning PDF rendering failed: ' . $exception->getMessage());
			Session::addMessageAfterRedirect(__('Le PDF n’a pas pu être généré. Consultez les journaux GLPI pour le détail.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$filename = 'PV_mise_en_service_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $immo) . '_' . date('Ymd_His') . '.pdf';
		$path = GLPI_UPLOAD_DIR . '/' . $filename;
		if (file_put_contents($path, $pdf_content) === false) {
			Session::addMessageAfterRedirect(__('Le PDF n’a pas pu être enregistré.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		$document = new Document();
		try {
			$document_id = $document->add([
				'name' => $filename,
				'upload_file' => $filename,
				'documentcategories_id' => 0,
				'mime' => 'application/pdf',
				'users_id' => $user_id,
				'entities_id' => Session::getActiveEntity(),
				'comment' => __('Procès-verbal de mise en service', 'protocolsmanager') . ' — ' . $immo,
			]);
		} catch (\Throwable $exception) {
			error_log('ProtocolsManager commissioning document registration failed: ' . $exception->getMessage());
			@unlink($path);
			Session::addMessageAfterRedirect(__('Le PDF a été créé, mais GLPI n’a pas pu enregistrer le document. Consultez les journaux GLPI pour le détail.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		if (!$document_id) {
			@unlink($path);
			Session::addMessageAfterRedirect(__('Le document PDF n’a pas pu être enregistré dans GLPI.', 'protocolsmanager'), false, ERROR);
			return false;
		}
		foreach ($assets as $asset) {
			$link = new Document_Item();
			$link->add(['documents_id' => $document_id, 'itemtype' => $asset['itemtype'], 'items_id' => $asset['id']]);
		}
		Session::addMessageAfterRedirect(__('Le procès-verbal de mise en service a été généré.', 'protocolsmanager'), true, INFO);
		return true;
	}
}
