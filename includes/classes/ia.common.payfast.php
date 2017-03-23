<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2017 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link https://subrion.org/
 *
 ******************************************************************************/
class iaPayfast extends abstractPlugin
{
    const HOST = 'www.payfast.co.za';
    const HOST_DEMO = 'sandbox.payfast.co.za';

    const VALID_RESPONSE = 'VALID';

    protected $_pluginName = 'payfast';

    private $_configuration = array(
        'merchant_id' => '10002972',
        'merchant_key' => 'qnyof935i52rw'
    );

    protected $_demoMode;


    public function init()
    {
        if (!in_array('curl', get_loaded_extensions())) {
            throw new Exception('Payfast: could not perform HTTP request since cUrl extension does not detected.');
        }

        parent::init();

        $this->_demoMode = (bool)$this->iaCore->get('payfast_demo');

        if (!$this->_demoMode) {
            $this->_configuration['merchant_id'] = $this->iaCore->get('payfast_merchant_id');
            $this->_configuration['merchant_key'] = $this->iaCore->get('payfast_merchant_key');
        }
    }

    public function getPluginName()
    {
        return $this->_pluginName;
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
            'notify_url' => IA_URL . sprintf('ipn/payfast/%s/', $transaction['sec_key']),

            'name_first' => array_shift($userName),
            'name_last' => implode(' ', $userName),
            'email_address' => $userData['email'],

            'm_payment_id' => $transaction['id'],
            'amount' => $plan['cost'],
            'item_name' => $plan['title']
        );

        $this->_demoMode && $result['email_address'] = 'sbtu01@payfast.co.za';

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

        foreach ($params as $key => $value) {
            if ($value) {
                $result .= $key . '=' . urlencode(trim($value)) . '&';
            }
        }

        return md5(substr($result, 0, -1));
    }

    public function validateTransaction($params, $transaction)
    {
        if (empty($params) || !$this->_checkHost($_SERVER['REMOTE_ADDR'])) {
            return false;
        }

        is_array($params) || $params = array();

        if (($transaction['id'] != $params['m_payment_id'])
            || (abs(floatval($transaction['amount']) - floatval($params['amount_gross'])) > 0.01)
        ) {
            return false;
        }

        //
        $requestData = $params;
        $paramsString = '';
        unset($requestData['signature']);

        foreach ($requestData as $key => $value) {
            $paramsString .= $key . '=' . urlencode(stripslashes($value)) . '&';
        }
        $paramsString = substr($paramsString, 0, -1);

        if ($params['signature'] != md5($paramsString)) {
            return false;
        }
        //

        $request = $this->_httpRequest($paramsString);

        if (!$request) {
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
        foreach ($hosts as $hostName) {
            if ($ips = gethostbynamel($hostName)) {
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
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL => $this->getHost('eng/query/validate'),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData
        ));

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function handleIpn(array $params, $transactionId)
    {
        $transaction = array(
            'reference_id' => $params['pf_payment_id'],
            'amount' => $params['amount_gross'],
            'date_paid' => date(iaDb::DATETIME_FORMAT),
            'fullname' => $params['name_first'] . ' ' . $params['name_last'],
            'email' => $params['email_address'],
            'demo' => $this->_demoMode,
            'notes' => '<IPN updated>'
        );

        switch ($params['payment_status']) {
            case 'COMPLETE':
                $transaction['status'] = iaTransaction::PASSED;
                break;
            case 'FAILED':
                $transaction['status'] = iaTransaction::FAILED;
        }

        $this->iaCore->factory('transaction')->update($transaction, $transactionId);

        $this->_sendEmailNotification($transaction);
    }

    protected function _sendEmailNotification(array $transaction)
    {
        $emailTemplate = 'payfast_ipn_admin';

        if (!$this->iaCore->get($emailTemplate)) {
            return true;
        }

        $iaMailer = $this->iaCore->factory('mailer');

        $iaMailer->loadTemplate($emailTemplate);
        $iaMailer->setReplacements($transaction);

        return $iaMailer->sendToAdministrators();
    }
}