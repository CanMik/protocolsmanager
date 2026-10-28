<?php
	
	Session::haveRight("config", UPDATE);
	
	Html::header(PluginProtocolsmanagerConfig::getTypeName(2), '', "config", "PluginProtocolsmanagerMenu");
			   
	$PluginProtocolsmanagerConfig = new PluginProtocolsmanagerConfig();

	if (isset($_POST['save_pv_template'])) {
		$saved = PluginProtocolsmanagerConfig::savePvTemplate();
		if ($saved) {
			Session::addMessageAfterRedirect(__('Modèle de procès-verbal enregistré.', 'protocolsmanager'));
		}
		Html::back();
	}

	if (isset($_POST['add_pv_chapter'])) {
		$saved = PluginProtocolsmanagerConfig::addPvCustomChapter();
		if ($saved) {
			Session::addMessageAfterRedirect(__('Chapitre ajouté au modèle de procès-verbal.', 'protocolsmanager'));
		}
		Html::back();
	}

	if (isset($_POST['add_pv_list_entry'])) {
		$saved = PluginProtocolsmanagerConfig::addPvListEntry();
		if ($saved) {
			Session::addMessageAfterRedirect(__('Entrée ajoutée au modèle de procès-verbal.', 'protocolsmanager'));
		}
		Html::back();
	}

	if (isset($_POST['move_pv_chapter'])) {
		$saved = PluginProtocolsmanagerConfig::movePvCustomChapter();
		if ($saved) {
			Session::addMessageAfterRedirect(__('Ordre des chapitres enregistré.', 'protocolsmanager'));
		}
		Html::back();
	}

	if (isset($_POST['remove_pv_row'])) {
		$saved = PluginProtocolsmanagerConfig::removePvTemplateRow();
		if ($saved) {
			Session::addMessageAfterRedirect(__('Ligne supprimée du modèle de procès-verbal.', 'protocolsmanager'));
		}
		Html::back();
	}

	if (isset($_POST['save_branding'])) {
		$saved = PluginProtocolsmanagerConfig::saveBranding();
		if ($saved) {
			Session::addMessageAfterRedirect(__('Identité visuelle enregistrée.', 'protocolsmanager'));
		}
		Html::back();
	}
	
	if (isset($_REQUEST['save'])) {
		$PluginProtocolsmanagerConfig::saveConfigs();
		$_SESSION['menu_mode'] = 't';
		Html::back();
		unset($_SESSION["menu_mode"]);
	}	
	
	if (isset($_REQUEST['delete'])) {
		$PluginProtocolsmanagerConfig::deleteConfigs();
		$_SESSION['menu_mode'] = 't';
		Html::back();
		unset($_SESSION["menu_mode"]);
	}

	if (isset($_REQUEST['save_email'])) {
		$PluginProtocolsmanagerConfig::saveEmailConfigs();
		$_SESSION['menu_mode'] = 'e';
		Html::back();
		unset($_SESSION["menu_mode"]);
	}	
	
	if (isset($_REQUEST['delete_email'])) {
		$PluginProtocolsmanagerConfig::deleteEmailConfigs();
		$_SESSION['menu_mode'] = 'e';
		Html::back();
		unset($_SESSION["menu_mode"]);
	}	
	
	if (isset($_REQUEST['cancel'])) {
		$_SESSION['menu_mode'] = 't';
		Html::back();
		unset($_SESSION["menu_mode"]);
	}	
	
	if (isset($_REQUEST['cancel_email'])) {
		$_SESSION['menu_mode'] = 'e';
		Html::back();
		unset($_SESSION["menu_mode"]);
	}
	

	
	$PluginProtocolsmanagerConfig->showFormProtocolsmanager();
	unset($_SESSION["menu_mode"]);
	
	
?>

<script>

/* $(function(){
	$("#template_button").click(function(){
		$("#template_settings").show();
		$("#show_configs").show();
		$("#email_settings").hide();
		$("#show_emailconfigs").hide();
	});	
	$("#email_button").click(function(){
		$("#template_settings").hide();
		$("#show_configs").hide();
		$("#email_settings").show();
		$("#show_emailconfigs").show();
	});
});	*/

</script>
