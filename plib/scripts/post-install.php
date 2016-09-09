<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Plugin by Plesk.

pm_Loader::registerAutoload();
pm_Context::init('domainoffensiveCB');

try {
    if (substr(PHP_OS, 0, 3) == 'WIN') {
        $cmd = '"' . PRODUCT_ROOT . '\bin\extension.exe"';
    } else {
        $cmd = '"' . PRODUCT_ROOT . '/bin/extension"';
    }

    $script = $cmd . ' --exec ' . pm_Context::getModuleId() . ' domainoffensiveCB.php';
    $result = pm_ApiCli::call('server_dns', array('--enable-custom-backend', $script));
} catch (pm_Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
exit(0);
