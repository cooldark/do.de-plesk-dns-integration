<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Extension by Plesk.

class Modules_domainoffensiveCB_Form_Settings extends pm_Form_Simple
{
    public function init()
    {
        parent::init();

        $this->addElement('select', 'LoginType', array(
            'label' => $this->lmsg('LoginTypeLabel'),
            'value' => pm_Settings::get('LoginType'),
            'class' => 'f-large-size',
            'multiOptions' => array( 'partnerid' => 'Partner', 'resellerid' => 'Reseller' ), 
        ));

        $this->addElement('text', 'LoginID', array(
            'label' => $this->lmsg('LoginIDLabel'),
            'value' => pm_Settings::get('LoginID'),
            'class' => 'f-large-size',
            'required' => true,
            'validators' => array(
                array('NotEmpty', true),
            ),
        ));
        $this->addElement('text', 'Benutzername', array(
            'label' => $this->lmsg('BenutzernameLabel'),
            'value' => pm_Settings::get('Benutzername'),
            'class' => 'f-large-size',
            'required' => true,
            'validators' => array(
                array('NotEmpty', true),
            ),
        ));
        $this->addElement('text', 'Passwort', array(
            'label' => $this->lmsg('PasswortLabel'),
            'value' => pm_Settings::get('Passwort'),
            'class' => 'f-large-size',
            'required' => true,
            'validators' => array(
                array('NotEmpty', true),
            ),
        ));
        $this->addElement('checkbox', 'enabled', array(
            'label' => $this->lmsg('enabledLabel'),
            'value' => pm_Settings::get('enabled'),
        ));

        $this->addControlButtons(array(
            'cancelLink' => pm_Context::getModulesListUrl(),
        ));
    }

    public function isValid($data)
    {
        if ($data['enabled']) {
            libxml_disable_entity_loader(false);

            $client = new SoapClient("https://soap.resellerinterface.de/robot.wsdl");

            if($data['LoginType']=='partnerid'){
                $result = $client->AuthPartner($data['LoginID'], $data['Benutzername'], $data['Passwort']);
            }
            elseif($data['LoginType']=='resellerid'){
                $result = $client->AuthReseller($data['LoginID'], $data['Benutzername'], $data['Passwort']);
            }
            
            if($result['result']!='success'){
                $this->markAsError();
                $this->getElement('LoginID')->addError(pm_Settings::get('WrongAuthentication'));
                $this->getElement('Benutzername')->addError(pm_Settings::get('WrongAuthentication'));
                $this->getElement('Passwort')->addError(pm_Settings::get('WrongAuthentication'));
                return false;
            }
        } else {
            $this->getElement('LoginID')->setRequired(false);
            $this->getElement('Benutzername')->setRequired(false);
            $this->getElement('Passwort')->setRequired(false);
        }

        return parent::isValid($data);
    }

    public function process()
    {
        pm_Settings::set('LoginType', $this->getValue('LoginType'));
        pm_Settings::set('LoginID', $this->getValue('LoginID'));
        pm_Settings::set('Benutzername', $this->getValue('Benutzername'));
        pm_Settings::set('Passwort', $this->getValue('Passwort'));
        pm_Settings::set('enabled', $this->getValue('enabled'));
    }
}