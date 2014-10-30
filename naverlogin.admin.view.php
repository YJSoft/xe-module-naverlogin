<?php
/**
 * @class  naverloginAdminView
 * @author NAVER (developers@xpressengine.com)
 * @brief  naverlogin module admin view class.
 */
class naverloginAdminView extends naverlogin
{
	function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile(strtolower(str_replace('dispNaverloginAdmin', '', $this->act)));
	}

	function dispNaverloginAdminConfig()
	{
		$oNaverloginModel = getModel('naverlogin');
		$module_config = $oNaverloginModel->getConfig();

		if(substr($module_config->def_url,-1)!='/')
		{
			$module_config->def_url .= '/';
		}

		Context::set('module_config', $module_config);
	}
}

/* End of file naverlogin.admin.view.php */
/* Location: ./modules/naverlogin/naverlogin.admin.view.php */
