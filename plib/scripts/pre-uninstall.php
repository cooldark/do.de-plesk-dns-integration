<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Plugin by Plesk.

pm_Loader::registerAutoload();
pm_Context::init('domainoffensiveCB');

try {
    $result = pm_ApiCli::call('server_dns', array('--disable-custom-backend'));
} catch (pm_Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
exit(0);
