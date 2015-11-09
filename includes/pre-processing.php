<?php
//##copyright##

$iaPayfast = $iaCore->factoryPlugin('payfast', 'common');

$iaView->assign('payfast', $iaPayfast->getForm($plan, $transaction));
$iaView->assign('payfast_host', $iaPayfast->getHost('eng/process'));