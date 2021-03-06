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

$transaction = $iaTransaction->getById($temp_transaction['id']);

switch ($action) {
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