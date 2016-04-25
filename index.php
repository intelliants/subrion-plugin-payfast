<?php
//##copyright##

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