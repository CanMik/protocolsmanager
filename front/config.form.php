<?php
	
	// checkRight() redirige y aborta si no tiene permiso; haveRight() sólo devuelve bool
	Session::checkRight("config", UPDATE);
	
	Html::header(PluginProtocolsmanagerConfig::getTypeName(2), '', "config", "PluginProtocolsmanagerMenu");
			   
	$PluginProtocolsmanagerConfig = new PluginProtocolsmanagerConfig();
	
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
	
	Html::footer();
	
?>

