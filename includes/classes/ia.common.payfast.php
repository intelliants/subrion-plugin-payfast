<?php
//##copyright##

class iaPayfast extends abstractPlugin
{
	const HOST = 'www.payfast.co.za';
	const HOST_DEMO = 'sandbox.payfast.co.za';

	const VALID_RESPONSE = 'VALID';

	private $_configuration = array(
		'merchant_id' => '10000100',
		'merchant_key' => '46f0cd694581a'
	);

	protected $_demoMode;


	public function init()
	{
		if (!in_array('curl', get_loaded_extensions()))
		{
			throw new Exception('Payfast: could not perform HTTP request since cUrl extension does not detected.');
		}

		parent::init();

		$this->_demoMode = (bool)$this->iaCore->get('payfast_demo');

		if (!$this->_demoMode)
		{
			$this->_configuration['merchant_id'] = $this->iaCore->get('payfast_merchant_id');
			$this->_configuration['merchant_key'] = $this->iaCore->get('payfast_merchant_key');
		}
	}

	public function getForm($plan, $transaction)
	{
		$userData = iaUsers::getIdentity(true);
		$userName = explode(' ', $userData['fullname']);

		$result = array(
			'merchant_id' => $this->_configuration['merchant_id'],
			'merchant_key' => $this->_configuration['merchant_key'],

			'return_url' => IA_RETURN_URL . 'completed/',
			'cancel_url' => IA_RETURN_URL . 'canceled/',
			'notify_url' => IA_RETURN_URL . 'completed/',

			'name_first' => array_shift($userName),
			'name_last' => implode(' ', $userName),
			'email_address' => $userData['email'],

			'm_payment_id' => $transaction['id'],
			'amount' => $plan['cost'],
			'item_name' => $plan['title'],
		);

		$result['signature'] = $this->_generateSignature($result);

		return $result;
	}

	public function getHost($uri)
	{
		return 'https://' . ($this->_demoMode ? self::HOST_DEMO : self::HOST) . '/' . $uri;
	}

	protected function _generateSignature(array $params)
	{
		$result = '';

		foreach ($params as $key => $value)
		{
			if ($value)
			{
				$result .= $key .'='. urlencode(trim($value)) . '&';
			}
		}

		return md5(substr($result, 0, -1));
	}

	public function validateTransaction($params, $transaction)
	{
		if (empty($params) || !$this->_checkHost($_SERVER['REMOTE_ADDR']))
		{
			return false;
		}

		is_array($params) || $params = array();

		if (($transaction['id'] != $params['m_payment_id'])
			|| (abs(floatval($transaction['amount']) - floatval($params['amount_gross'])) > 0.01))
		{
			return false;
		}

		//
		$requestData = $params;
		$paramsString = '';
		unset($requestData['signature']);

		foreach ($requestData as $key => $value)
		{
			$paramsString .= $key . '=' . urlencode(stripslashes($value)) . '&';
		}
		$paramsString = substr($paramsString, 0, -1);

		if ($params['signature'] != md5($paramsString))
		{
			return false;
		}
		//

		$request = $this->_httpRequest($paramsString);

		if (!$request)
		{
			return false;
		}

		return (0 === strcmp($request, self::VALID_RESPONSE));
	}

	protected function _checkHost($ipAddress)
	{
		$hosts = array(
			'www.payfast.co.za',
			'sandbox.payfast.co.za',
			'w1w.payfast.co.za',
			'w2w.payfast.co.za',
		);

		$validIps = array();
		foreach ($hosts as $hostName)
		{
			if ($ips = gethostbynamel($hostName))
			{
				$validIps = array_merge($validIps, $ips);
			}
		}

		$validIps = array_unique($validIps);

		return in_array($ipAddress, $validIps);
	}

	protected function _httpRequest($postData)
	{
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_SSL_VERIFYHOST => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_URL => $this->getHost('eng/query/validate'),
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $postData
		));

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}
}