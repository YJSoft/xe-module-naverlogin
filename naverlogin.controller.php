<?php
/**
 * @class  naverloginController
 * @author YJSoft (yjsoft@yjsoft.pe.kr)
 * @brief  naverlogin module controller class.
 */

class naverloginController extends naverlogin
{
	private $error_message;
	private $redirect_Url;

	function init()
	{
	}

	function triggerDisablePWChk($args)
	{
		$cond = new stdClass();
		$cond->srl = $args->member_srl;
		$output = executeQuery('naverlogin.getNaverloginMemberbySrl', $cond);
		if(isset($output->data->enc_id)) $_SESSION['rechecked_password_step'] = 'INPUT_DATA';
		return;
	}

	/**
	 * @brief 회원 탈퇴시 네이버 로그인 DB에서도 삭제
	 * @param $args->member_srl
	 * @return mixed
	 */
	function triggerDeleteNaverloginMember($args)
	{
		$cond = new stdClass();
		$cond->srl = $args->member_srl;
		$output = executeQuery('naverlogin.deleteNaverloginMember', $cond);

		return;
	}

	/**
	 * @brief 아무 것도 안함
	 * @param void
	 * @return void
	 */
	function triggerChkID($args)
	{
		return;
	}

	/**
	 * @brief 네이버로부터 인증코드를 받아와서 Auth키 발급후 회원가입여부 확인뒤 가입 혹은 로그인 처리
	 * @param void
	 * @return mixed
	 */
	function procNaverloginOAuth()
	{
		$this->redirect_Url='';

		//네이버에서 넘겨주는 state값과 code값을 얻어옴
		$state = Context::get("state");
		$code = Context::get("code");
		if(Context::get("error")!="") return new Object(-1, Context::get("error_description"));
		$stored_state = $_SESSION['naverlogin_state'];
		$oMemberController = getController('member');

		//세션변수 비교(CSRF 방지)
		if( $state != $stored_state ) {
			return new Object(-1, 'msg_invalid_request');
		}
		else
		{
			//API 전솔 실패
			if(!$this->send($stored_state,$code))
			{
				return new Object(-1, $this->error_message);
			}
			else
			{
				$this->setRedirectUrl($this->redirect_Url);
			}
		}
	}

	/**
	 * @param $state
	 * @param $code
	 * @return bool
	 */
	function send($state,$code) {
		//오류 메세지 변수 초기화
		$this->error_message = '';

		$oModuleModel = getModel('module');
		$oModuleConfig = $oModuleModel->getModuleConfig('naverlogin');

		$oMemberModel = getModel('member');
		$oMemberController = getController('member');

		//설정이 되어있지 않은 경우 리턴
		if(!$oModuleConfig->clientid || !$oModuleConfig->clientkey || !$oModuleConfig->def_url)
		{
			//TODO 다국어화
			$this->error_message = '설정이 되어 있지 않습니다.';
			return false;
		}

		//ssl 연결을 지원하지 않는 경우 리턴(API 연결은 반드시 https 연결이여야 함)
		if(!$this->checkOpenSSLSupport())
		{
			//TODO 다국어화
			$this->error_message = 'OpenSSL 지원이 필요합니다.';
			return false;
		}

		//API 서버에 code와 state값을 보내 인증키를 받아 온다
		$ping_url = 'https://nid.naver.com/oauth2.0/token?client_id=' . $oModuleConfig->clientid . '&client_secret=' . $oModuleConfig->clientkey . '&grant_type=authorization_code&state=' . $state . '&code=' . $code;
		$ping_header = array();
		$ping_header['Host'] = 'nid.naver.com';
		$ping_header['Pragma'] = 'no-cache';
		$ping_header['Accept'] = '*/*';

		$request_config = array();
		$request_config['ssl_verify_peer'] = false;

		$buff = FileHandler::getRemoteResource($ping_url, null, 10, 'GET', 'application/x-www-form-urlencoded', $ping_header, array(), array(), $request_config);
		$data= json_decode($buff);

		//받아온 인증키로 바로 회원 정보를 얻어옴
		$ping_url = 'https://apis.naver.com/nidlogin/nid/getUserProfile.xml';
		$ping_header = array();
		$ping_header['Host'] = 'apis.naver.com';
		$ping_header['Pragma'] = 'no-cache';
		$ping_header['Accept'] = '*/*';
		$ping_header['Authorization'] = sprintf("Bearer %s", $data->access_token);

		$request_config = array();
		$request_config['ssl_verify_peer'] = false;

		$buff = FileHandler::getRemoteResource($ping_url, null, 10, 'GET', 'application/x-www-form-urlencoded', $ping_header, array(), array(), $request_config);

		//받아온 결과 파싱(XML)
		$xml = new XmlParser();
		$xmlDoc= $xml->parse($buff);

		//회원 설정 불러옴
		$config = $oMemberModel->getMemberConfig();

		if($xmlDoc->data->result->resultcode->body != '00')
		{
			if(!$buff)
			{
				$this->error_message = 'Socket connection error. Check your Server Environment.';
			}
			else
			{
				$this->error_message = $xmlDoc->data->result->message->body;
			}
			return false;
		}

		//enc_id로 회원이 있는지 조회
		$cond = new stdClass();
		$cond->enc_id=$xmlDoc->data->response->enc_id->body;
		$output = executeQuery('naverlogin.getNaverloginMemberbyEncID', $cond);

		//srl이 있다면(로그인 시도)
		if(isset($output->data->srl))
		{
			$member_Info = $oMemberModel->getMemberInfoByMemberSrl($output->data->srl);
			if($config->identifier == 'email_address')
			{
				$oMemberController->doLogin($member_Info->email_address,'',false);
			}
			else
			{
				$oMemberController->doLogin($member_Info->user_id,'',false);
			}

			//회원정보 변경시 비밀번호 입력 없이 변경 가능하도록 수정
			$_SESSION['rechecked_password_step'] = 'INPUT_DATA';

			if($config->after_login_url) $this->redirect_Url = $config->after_login_url;
			$this->redirect_Url = getUrl('');

			return true;
		}
		else
		{
			// call a trigger (before)
			$trigger_output = ModuleHandler::triggerCall ('member.procMemberInsert', 'before', $config);
			if(!$trigger_output->toBool ()) return $trigger_output;
			// Check if an administrator allows a membership
			if($config->enable_join != 'Y') return $this->stop ('msg_signup_disabled');

			$args = new stdClass();
			list($args->email_id, $args->email_host) = explode('@', $xmlDoc->data->response->email->body);
			$args->allow_mailing="N";
			$args->allow_message="Y";
			$args->email_address=$xmlDoc->data->response->email->body;
			$args->find_account_answer=md5($code) . '@' . $args->email_host;
			$args->find_account_question="1";
			$args->nick_name=$xmlDoc->data->response->nickname->body;
			$args->password=md5($code) . "a1#";
			$args->user_id=substr($args->email_id,0,20);
			while($oMemberModel->getMemberInfoByUserID($args->user_id)){
				$args->user_id=substr($args->email_id,0,10) . substr(md5($code . rand(0,9999)),0,10);
			}
			$args->user_name=$xmlDoc->data->response->nickname->body;

			// remove whitespace
			$checkInfos = array('user_id', 'nick_name', 'email_address');
			$replaceStr = array("\r\n", "\r", "\n", " ", "\t", "\xC2\xAD");
			foreach($checkInfos as $val)
			{
				if(isset($args->{$val}))
				{
					$args->{$val} = str_replace($replaceStr, '', $args->{$val});
				}
			}

			$output = $oMemberController->insertMember($args);
			if(!$output->toBool()) return $output;

			$site_module_info = Context::get('site_module_info');
			if($site_module_info->site_srl > 0)
			{
				$columnList = array('site_srl', 'group_srl');
				$default_group = $oMemberModel->getDefaultGroup($site_module_info->site_srl, $columnList);
				if($default_group->group_srl)
				{
					$this->addMemberToGroup($args->member_srl, $default_group->group_srl, $site_module_info->site_srl);
				}

			}

			$naverloginmember = new stdClass();
			$naverloginmember->srl = $args->member_srl;
			$naverloginmember->enc_id = $xmlDoc->data->response->enc_id->body;

			$output = executeQuery('naverlogin.insertNaverloginMember', $naverloginmember);
			if(!$output->toBool())
			{
				return false;
			}

			$tmp_file = sprintf('./files/cache/tmp/%d', md5(rand(111111,999999).$args->email_id));
			if(!is_dir('./files/cache/tmp')) FileHandler::makeDir('./files/cache/tmp');

			$ping_header = array();
			$ping_header['Pragma'] = 'no-cache';
			$ping_header['Accept'] = '*/*';

			$request_config = array();
			$request_config['ssl_verify_peer'] = false;

			FileHandler::getRemoteFile($xmlDoc->data->response->profile_image->body, $tmp_file,null, 10, 'GET', null,$ping_header,array(),array(),$request_config);

			if(file_exists($tmp_file))
			{
				$oMemberController->insertProfileImage($args->member_srl, $tmp_file);
			}

			if($config->identifier == 'email_address')
			{
				$oMemberController->doLogin($args->email_address);
			}
			else
			{
				$oMemberController->doLogin($args->user_id);
			}

			$_SESSION['rechecked_password_step'] = 'INPUT_DATA';

			if($config->redirect_url) $this->redirect_Url = $config->redirect_url;
			else $this->redirect_Url = getUrl('', 'act', 'dispMemberModifyInfo');

			FileHandler::removeFile($tmp_file);

			return true;
		}
	}
}

/* End of file naverlogin.controller.php */
/* Location: ./modules/naverlogin/naverlogin.controller.php */
