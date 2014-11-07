<?php
/**
 * @author YJSoft (yjsoft@yjsoft.pe.kr)
 */
class naverloginModel extends naverlogin
{
	private $config;

	function init()
	{
	}

	/**
	 * @brief 모듈 설정 반환
	 */
	function getConfig()
	{
		if(!$this->config)
		{
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('naverlogin');

			$this->config = $config;
		}

		return $this->config;
	}

	/**
	 * @brief random한 state값 생성
	 */
	function generate_state() {
		$mt = microtime();
		$rand = mt_rand();
		return md5($mt . $rand);
	}
}

/* End of file naverlogin.model.php */
/* Location: ./modules/naverlogin/naverlogin.model.php */
