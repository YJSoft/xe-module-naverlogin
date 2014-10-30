<?php
/**
 * @class  naverloginAdminController
 * @author YJSoft (yjsoft@yjsoft.pe.kr)
 * @brief  naverlogin module admin controller class.
 */

class naverloginAdminController extends naverlogin
{
	function init()
	{
	}

	function procNaverloginAdminInsertConfig()
	{
		$oModuleController = getController('module');
		$oNaverloginModel = getModel('naverlogin');

		$vars = Context::getRequestVars();
		$section = $vars->_config_section;

		$config = $oNaverloginModel->getConfig();
		$config->clientid = $vars->clientid;
		$config->clientkey = $vars->clientkey;
		$config->def_url = $vars->def_url;

		$oModuleController->updateModuleConfig('naverlogin', $config);


		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispNaverloginAdminConfig'));
	}
}

/* End of file naverlogin.admin.controller.php */
/* Location: ./modules/naverlogin/naverlogin.admin.controller.php */
