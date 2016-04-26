<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2016 Intelliants, LLC <http://www.intelliants.com>
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
 * @link http://www.subrion.org/
 *
 ******************************************************************************/

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	iaBreadcrumb::remove(iaBreadcrumb::POSITION_LAST);
	$iaView->set('nocsrf', true);
	$iaView->set('nodebug', true);

	if (empty($_POST) || 1 != count($iaCore->requestPath))
	{
		return iaView::errorPage(iaView::ERROR_NOT_FOUND);
	}

	$iaTransaction = $iaCore->factory('transaction');
	$iaPayfast = $iaCore->factoryPlugin('payfast', 'common');

	$transaction = $iaTransaction->getBy('sec_key', $iaCore->requestPath[0]);

	if (!$transaction || !$iaPayfast->validateTransaction($_POST, $transaction))
	{
		$iaTransaction->addIpnLogEntry($iaPayfast->getPluginName(), $_POST, 'Invalid');

		return iaView::errorPage(iaView::ERROR_NOT_FOUND);
	}

	$iaTransaction->addIpnLogEntry($iaPayfast->getPluginName(), $_POST, 'Valid');

	$iaView->disableLayout();
	$iaView->display(iaView::NONE);

	$iaPayfast->handleIpn($_POST, $transaction['id']);
}