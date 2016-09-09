<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Plugin by Plesk.

pm_Context::init('domainoffensiveCB');

$application = new pm_Application();
$application->run();
