<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


$headerTitle = esc_html__("Assets Manager", "unlimited-elements-for-elementor");
require HelperUC::getPathTemplate("header");


$objAssets = new UniteCreatorAssetsWork();
$objAssets->initByKey("assets_manager");

?>
<div class="uc-assets-manager-wrapper">

	<?php 
	$objAssets->putHTML();
	?>
	
</div>

<?php

	$script = 'jQuery(document).ready(function(){
	
		var objAdmin = new UniteCreatorAdmin();
		objAdmin.initAssetsManagerView();
	
	});';

	UniteProviderFunctionsUC::printCustomScript($script, true); 
