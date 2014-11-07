<?php
/**
 * @class  naverloginView
 * @author YJSoft (yjsoft@yjsoft.pe.kr)
 * @brief naverlogin view class of the module
 */
class naverloginView extends naverlogin
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile(strtolower(str_replace('dispNaverlogin', '', $this->act)));
	}

	/**
	 * @brief General request output
	 */
	function dispNaverloginOAuth()
	{
		$oNaverloginModel = getModel('naverlogin');
		$module_config = $oNaverloginModel->getConfig();

		if(substr($module_config->def_url,-1)!='/')
		{
			$module_config->def_url .= '/';
		}

		$_SESSION['naverlogin_state'] = $oNaverloginModel->generate_state();
		$module_config->state=$_SESSION['naverlogin_state'];

		Context::set('module_config', $module_config);
	}
}
/* End of file naverlogin.view.php */
/* Location: ./modules/naverlogin/naverlogin.view.php */
