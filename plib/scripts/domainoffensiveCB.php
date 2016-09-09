<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Route Script by plesk


require_once(__DIR__ . '/../library/Logger.php');

pm_Loader::registerAutoload();
pm_Context::init('domainoffensiveCB');
if (!pm_Settings::get('enabled')) {
    exit(0);
}

/**
 * Integration config
 */
$config = array(
    /** TTL below you don't want to delete RR Records (FlexDNS) */
    'ttl' => 300,
    /*** Exportable Resource Record types   */
    'supportedTypes' => array(
        'A',
        'TXT',
        'CNAME',
        'MX',
        'SRV',
        'SPF',
        'AAAA',
        'NS',
        //'SOA',
    )
);

$data = json_decode(file_get_contents('php://stdin'));


$log = new Modules_domainoffensiveCB_Logger();

libxml_disable_entity_loader(false);
$client = new SoapClient("https://soap.resellerinterface.de/robot.wsdl");

if(pm_Settings::get('LoginType')=='partnerid'){
    $loginResult = $client->AuthPartner(pm_Settings::get('LoginID'), pm_Settings::get('Benutzername'), pm_Settings::get('Passwort'));
}
elseif(pm_Settings::get('LoginType')=='resellerid'){
    $loginResult = $client->AuthReseller(pm_Settings::get('LoginID'), pm_Settings::get('Benutzername'), pm_Settings::get('Passwort'));
}


if($loginResult['result']!='success'){
    $log->err(pm_Settings::get('WrongAuthentication'));
    exit(0);
}


foreach ($data as $record) {

    switch ($record->command) {
        /**
         * Zone created or updated
         */
        case 'create':
        case 'update':

            // Check if the Domain is from do.de
            $domainDetailsResult = $client->GetDomainDetails(rtrim($record->zone->name,'.'));
            if(empty($domainDetailsResult)){
                $log->err("Domain ".rtrim($record->zone->name)." not in do.de DNS area");
                continue;
                exit(255);
            }
            //disabled
            elseif($domainDetailsResult['active'] == 0){
                $log->err('Domain unavailable - MSG: '.$domainDetailsResult['status']);
                continue;
                exit(255);
            }
            
            $zoneData = $client->GetFullZoneData(rtrim($record->zone->name,'.'));

            // Try to create the Zone
            if (!array_key_exists('soa', $zoneData)) {
                // Find out NS
                $nameserversList = array();
                foreach($record->zone->rr as $rr) {
                    if($rr->type=='NS'){
                        $nameserversList[] = $rr->value;
                    }
                }
                if(empty($nameserversList)){
                    $nameserversList[] = 'ns1.resellerinterface.de';
                }
                else{
                    sort($nameserversList);
                }

                $zoneCreateResult = $client->CreateZone(rtrim($record->zone->name,'.'), $nameserversList[0], $record->zone->soa->email, $record->zone->soa->ttl, $record->zone->soa->refresh, $record->zone->soa->retry, $record->zone->soa->expire, $record->zone->soa->minimum);

                if($zoneCreateResult['result']=='success'){
                    $log->info("Zone SOA successfully created");
                    $zoneData = $client->GetFullZoneData(rtrim($record->zone->name,'.'));
                }
                else{
                    $log->err("Could not create Zone ");
                    exit(255);
                }


            } else {

                /**
                 * Zone exists, remove old Resource Records
                 */

                if(count($zoneData['rr'])>0){

                    foreach ($zoneData['rr'] as $modelRR) {

                        if (!in_array($modelRR['type'], $config['supportedTypes'])) {
                            continue;
                        }

                        // FlexDNS Fix
                        if($modelRR['type'] == 'A' && $modelRR['ttl'] < $config['ttl']){
                            continue;
                        }


                        $deleteResult = $client->DeleteRR(rtrim($record->zone->name,'.'), $modelRR['id']);
                        if($deleteResult['result']!='success'){
                            $log->err('Could not delete RR Record '.$modelRR['id']);
                        }
                        else{
                            $log->info('Successfully deleted RR '.$modelRR['id']);
                        }
                    }
                }
                else{
                    $log->info('No RR existing therfore nothing deleted');
                }
            }

            /**
             * Add Resource records to zone
             */

            $nameserversList = array();

            foreach($record->zone->rr as $rr) {

                if (!in_array($rr->type, $config['supportedTypes'])) {
                    continue;
                }

                // Save Namservers to propagate primary Nameserver
                if($rr->type=='NS'){
                    $nameserversList[] = $rr->value;
                }

                if(!is_numeric($rr->opt)){
                    $rr->opt = 0;
                }

                $resultCreate = $client->CreateRR(rtrim($record->zone->name,'.'), rtrim($rr->host,'.'), $rr->type, $rr->value, $rr->opt, $record->zone->soa->ttl);

                if($resultCreate['result']!='success'){
                        $log->err('Could not create RR Record '.rtrim($rr->host,'.'));
                    }
                else{
                    $log->info('Successfully created RR '.rtrim($rr->host,"."));
                }
                
            }


            // Sort the Namservers Array alphabetically, so that the ns1 / a.ns is in front
            if(!empty($nameserversList)){
                sort($nameserversList);
            }
            else{
                $nameserversList[0] = $zoneData['soa']['ns'];
            }

            // Update the SOA Records
            $zoneUpdateResult = $client->UpdateZone(rtrim($record->zone->name,'.'), $nameserversList[0], $record->zone->soa->email, $record->zone->soa->ttl, $record->zone->soa->refresh, $record->zone->soa->retry, $record->zone->soa->expire, $record->zone->soa->minimum);

            // Update SOA Domain Nameservers
            if($nameserversList[0]!=$domainDetailsResult['nameserver'][0]['ns']){
                $nameServerUpdateResult = $client->UpdateDomain(rtrim($record->zone->name,'.'), false, false, false, false, $nameserversList, array());
                if($nameServerUpdateResult['result']=='success'){
                    $log->info("Domain NS successfully updated");
                }
                else{
                    $log->err("Could not update Domain NS");
                }
            }

            if($zoneUpdateResult['result']=='success'){
                $log->info("Zone SOA successfully updated");
            }
            else{
                $log->err("Could not update Zone SOA");
            }

            break;

        case 'delete':

            $zoneData = $client->GetFullZoneData(rtrim($record->zone->name,'.'));

            if (!array_key_exists('soa', $zoneData)) {
                continue;
            }

            $deleteZoneResult = $client->DeleteZone(rtrim($record->zone->name,'.'));

            if($deleteZoneResult['result']=='success'){
                $log->info("Zone SOA successfully deleted");
            }
            else{
                $log->err("Could not delete Zone SOA");
            }

            break;
    }
}

if ($log->hasErrors()) {
    exit(255);
}
else{
    exit(0);
}




//Example:
//[
//    {"command": "update", "zone": {"name": "domain.tld.", "displayName": "domain.tld.", "soa": {"email": "amihailov@parallels.com", "status": 0, "type": "master", "ttl": 86400, "refresh": 10800, "retry": 3600, "expire": 604800, "minimum": 10800, "serial": 1363228965, "serial_format": "UNIXTIMESTAMP"}, "rr": [
//        {"host": "www.domain.tld.", "displayHost": "www.domain.tld.", "type": "CNAME", "displayValue": "domain.tld.", "opt": "", "value": "domain.tld."},
//        {"host": "1.2.3.4", "displayHost": "1.2.3.4", "type": "PTR", "displayValue": "domain.tld.", "opt": "24", "value": "domain.tld."},
//        {"host": "domain.tld.", "displayHost": "domain.tld.", "type": "TXT", "displayValue": "v=spf1 +a +mx -all", "opt": "", "value": "v=spf1 +a +mx -all"},
//        {"host": "ftp.domain.tld.", "displayHost": "ftp.domain.tld.", "type": "CNAME", "displayValue": "domain.tld.", "opt": "", "value": "domain.tld."},
//        {"host": "ipv4.domain.tld.", "displayHost": "ipv4.domain.tld.", "type": "A", "displayValue": "1.2.3.4", "opt": "", "value": "1.2.3.4"},
//        {"host": "mail.domain.tld.", "displayHost": "mail.domain.tld.", "type": "A", "displayValue": "1.2.3.4", "opt": "", "value": "1.2.3.4"},
//        {"host": "domain.tld.", "displayHost": "domain.tld.", "type": "MX", "displayValue": "mail.domain.tld.", "opt": "10", "value": "mail.domain.tld."},
//        {"host": "webmail.domain.tld.", "displayHost": "webmail.domain.tld.", "type": "A", "displayValue": "1.2.3.4", "opt": "", "value": "1.2.3.4"},
//        {"host": "domain.tld.", "displayHost": "domain.tld.", "type": "A", "displayValue": "1.2.3.4", "opt": "", "value": "1.2.3.4"},
//        {"host": "ns.domain.tld.", "displayHost": "ns.domain.tld.", "type": "A", "displayValue": "1.2.3.4", "opt": "", "value": "1.2.3.4"}
//    ]}},
//    {"command": "createPTRs", "ptr": {"ip_address": "1.2.3.4", "hostname": "domain.tld"}},
//    {"command": "createPTRs", "ptr": {"ip_address": "2002:5bcc:18fd:000c:0001:0002:0003:0004", "hostname": "domain.tld"}}
//]

