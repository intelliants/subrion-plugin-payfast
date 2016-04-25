<?php
//##copyright##

$transaction = $iaTransaction->getById($temp_transaction['id']);

switch ($action)
{
	case 'completed':
		$payer = explode(' ', $transaction['fullname']);

		$order = array(
			'payment_gross' => $transaction['amount'],
			'payment_date' => $transaction['date_paid'],
			'payment_status' => iaLanguage::get($transaction['status']),
			'first_name' => $payer[0],
			'last_name' => empty($payer[1]) ? '' : $payer[1],
			'payer_email' => $transaction['email'],
			'txn_id' => $transaction['reference_id'],
			'mc_currency' => $transaction['currency']
		);

		break;

	case 'canceled':
		$error = true;
		$messages[] = iaLanguage::get('oops');
}