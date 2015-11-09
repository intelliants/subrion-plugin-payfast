<?php
//##copyright##

$transaction = $temp_transaction;

switch ($action)
{
	case 'completed':
		if ($params = $_POST)
		{
			$order = array(
				'payment_gross' => $params['amount_gross'],
				'payment_date' => date(iaDb::DATETIME_SHORT_FORMAT),
				'payment_status' => $params['payment_status'],
				'first_name' => $params['name_first'],
				'last_name' => $params['name_last'],
				'payer_email' => $params['email_address'],
				'txn_id' => $params['pf_payment_id'],
				'mc_currency' => 'R'
			);

			$transaction['reference_id'] = $params['pf_payment_id'];

			if ($iaCore->factoryPlugin('payfast', 'common')->validateTransaction($params, $transaction))
			{
				switch ($params['payment_status'])
				{
					case 'COMPLETE':
						$transaction['email'] = $params['email_address'];
						$transaction['fullname'] = $params['name_first'] . ' ' . $params['name_last'];

						$transaction['status'] = iaTransaction::PASSED;
						break;
					case 'FAILED':
						$transaction['status'] = iaTransaction::FAILED;
				}
			}
		}

		break;

	case 'canceled':
		$error = true;
		$messages[] = iaLanguage::get('oops');

		$transaction['status'] = iaTransaction::FAILED;
}