<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Extension by Plesk.

class Modules_domainoffensiveCB_Logger
{
    private $log = [];

    public function info($message)
    {
        $this->log('info', $message);
    }

    public function warn($message)
    {
        $this->log('warn', $message);
    }

    public function err($message)
    {
        $this->log('err', $message);
        static::pushErrorMessage($message);
    }

    private function log($type, $message)
    {
        echo "$message\n";
        $this->log[$type][] = [
            'timestamp' => time(),
            'message' => $message,
        ];
    }

    public function hasErrors()
    {
        return !empty($this->log['err']);
    }

    public static function getErrorMessages()
    {
        $history = explode("\n", pm_Settings::get('errorMessages', ''));
        pm_Settings::set('errorMessages', '');
        return array_filter($history);
    }

    public static function pushErrorMessage($message)
    {
        $history = explode("\n", pm_Settings::get('errorMessages', ''));
        $history[] = $message;
        pm_Settings::set('errorMessages', implode("\n", array_filter($history)));
    }
}
