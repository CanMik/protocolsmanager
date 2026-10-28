<?php

if (isset($_POST['generate_commission'])) {
	PluginProtocolsmanagerCommission::generateFromRequest();
} elseif (isset($_POST['generate_disposal'])) {
	PluginProtocolsmanagerCommission::generateDisposalFromRequest();
}

Html::back();
