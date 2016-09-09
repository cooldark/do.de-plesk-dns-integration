<?php
// Copyright 2016. CodarByte (Florian Moker).
// Based on the Amazon AWS 53 Plugin by Plesk.

require_once(__DIR__ . '/../library/Form/Settings.php');

class IndexController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();

        if (!pm_Session::getClient()->isAdmin()) {
            throw new pm_Exception('Permission denied');
        }
        /** @noinspection PhpUndefinedFieldInspection */
        $this->view->pageTitle = $this->lmsg('pageTitle');
    }

    public function indexAction()
    {
        $form = new Modules_domainoffensiveCB_Form_Settings();

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $form->process();

            $this->_status->addMessage('info', $this->lmsg('authDataSaved'));
            $this->_helper->json(array('redirect' => pm_Context::getBaseUrl()));
        }

        $this->view->form = $form;
        $this->view->tabs = $this->_getTabs();
    }

    private function _getTabs()
    {
        $tabs = [];
        $tabs[] = [
            'title' => $this->lmsg('indexPageTitle'),
            'action' => 'index',
        ];
        return $tabs;
    }

   

  
}
