<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

class UniteCreatorAddonType_Elementor_Template extends UniteCreatorAddonType_Layout{
	
	
	/**
	 * init the addon type
	 */
	protected function initChild(){
		
		parent::initChild();
		
		$this->typeName = GlobalsUnlimitedElements::ADDONSTYPE_ELEMENTOR_TEMPLATE;
				
		$this->isBasicType = false;
		$this->layoutTypeForCategory = $this->typeName;
		$this->displayType = self::DISPLAYTYPE_MANAGER;
		
		$this->allowDuplicateTitle = false;
		$this->defaultBlankTemplate = false;
		
		$this->allowWebCatalog = true;
		$this->allowManagerWebCatalog = true;
		$this->allowManagerLocalLayouts = false;
				
		$this->showDescriptionField = false;
		
		$this->allowNoCategory = false;
		$this->defaultCatTitle = "Main";	//can't translate here
		
		$this->postType = GlobalsUnlimitedElements::POSTTYPE_UNLIMITED_ELEMENS_LIBRARY;
		$this->isBloxPage = false;
		
		$this->catalogKey = "elementor_template";
		
		//$this->arrCatalogExcludeCats = array("basic");
		
		$this->textPlural = "Templates";
		$this->textSingle = "Template";
		$this->textShowType = "Elementor Template";
		
		$this->browser_textBuy = "Activate Plugin";
		$this->browser_textHoverPro = "This template is available<br>when the plugin is activated.";
		
		$urlLicense = HelperUC::getViewUrl(GlobalsUnlimitedElements::VIEW_LICENSE_ELEMENTOR);		
		$this->browser_urlBuyPro = $urlLicense;
		
		$responseAssets = UniteProviderFunctionsUC::setAssetsPath("ac_assets", true);
		
		$this->pathAssets = UniteFunctionsUC::getVal($responseAssets, "path_assets");
		$this->urlAssets = UniteFunctionsUC::getVal($responseAssets, "url_assets");
				
		$this->addonView_urlBack = HelperUC::getViewUrl(GlobalsUnlimitedElements::VIEW_TEMPLATES_ELEMENTOR);
		
		$this->hasParents = true;
		
		$this->isWebCatalogMode = true;
		$this->allowNoCategory = true;
		
		UniteProviderFunctionsUC::doAction("uc_init_elementor_template_addontype", $this);
		
	}
	
	
	/**
	 * init by master plugin
	 */
	public function initByMaster(){
		
		$this->allowWebCatalog = false;
		
	}
	
}
