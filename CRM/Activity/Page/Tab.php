<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 * Main page for viewing activities,
 */
class CRM_Activity_Page_Tab extends CRM_Core_Page {

  /**
   * Browse all activities for a particular contact.
   */
  public function browse() {
    $this->assign('admin', FALSE);
    $this->assign('context', 'activity');

    // also create the form element for the activity filter box
    $controller = new CRM_Core_Controller_Simple(
      'CRM_Activity_Form_ActivityFilter',
      ts('Activity Filter'),
      NULL,
      FALSE, FALSE, TRUE
    );
    $controller->set('contactId', $this->_contactId);
    $controller->setEmbedded(TRUE);
    $controller->run();
    $this->ajaxResponse['tabCount'] = CRM_Contact_BAO_Contact::getCountComponent('activity', $this->_contactId);
  }

  /**
   * Edit tab.
   *
   * @return mixed
   */
  public function edit() {
    // used for ajax tabs
    $context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $this);
    $this->assign('context', $context);

    $this->_id = CRM_Utils_Request::retrieve('id', 'Integer', $this);

    $this->_caseId = CRM_Utils_Request::retrieve('caseid', 'Integer', $this);

    $activityTypeId = CRM_Utils_Request::retrieve('atype', 'Positive', $this);

    // Email and Create Letter activities use a different form class
    $emailTypeValue = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity',
      'activity_type_id',
      'Email'
    );

    $letterTypeValue = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity',
      'activity_type_id',
      'Print PDF Letter'
    );

    switch ($activityTypeId) {
      case $emailTypeValue:
        $wrapper = new CRM_Utils_Wrapper();
        $arguments = ['attachUpload' => 1];
        return $wrapper->run('CRM_Contact_Form_Task_Email', ts('Email a Contact'), $arguments);

      case $letterTypeValue:
        $wrapper = new CRM_Utils_Wrapper();
        $arguments = ['attachUpload' => 1];
        return $wrapper->run('CRM_Contact_Form_Task_PDF', ts('Create PDF Letter'), $arguments);

      default:
        $controller = new CRM_Core_Controller_Simple('CRM_Activity_Form_Activity',
          ts('Contact Activities'),
          $this->_action,
          FALSE, FALSE, FALSE, TRUE
        );
    }

    $controller->setEmbedded(TRUE);

    $controller->set('contactId', $this->_contactId);
    $controller->set('atype', $activityTypeId);
    $controller->set('id', $this->_id);
    $controller->set('pid', $this->get('pid'));
    $controller->set('action', $this->_action);
    $controller->set('context', $context);

    $controller->process();
    $controller->run();
  }

  /**
   * Heart of the viewing process.
   *
   * The runner gets all the meta data for the contact and calls the appropriate type of page to view.
   */
  public function preProcess() {
    $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    $this->assign('contactId', $this->_contactId);
    // FIXME: need to fix this conflict
    $this->assign('contactID', $this->_contactId);

    // check logged in url permission
    CRM_Contact_Page_View::checkUserPermission($this);

    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'browse');
    $this->assign('action', $this->_action);
    $this->assign('allow_edit_inbound_emails', CRM_Activity_BAO_Activity::checkEditInboundEmailsPermissions());

    // also create the form element for the activity links box
    $controller = new CRM_Core_Controller_Simple(
      'CRM_Activity_Form_ActivityLinks',
      ts('Activity Links'),
      NULL,
      FALSE, FALSE, TRUE
    );
    $controller->setEmbedded(TRUE);
    $controller->run();
  }

  public function delete() {
    $controller = new CRM_Core_Controller_Simple(
      'CRM_Activity_Form_Activity',
      ts('Activity Record'),
      $this->_action
    );
    $controller->set('id', $this->_id);
    $controller->setEmbedded(TRUE);
    $controller->process();
    $controller->run();
  }

  /**
   * Perform actions and display for activities.
   */
  public function run() {
    $context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $this);
    $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this);
    $action = CRM_Utils_Request::retrieve('action', 'String', $this);
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    // Do check for view/edit operation.
    if ($this->_id &&
      in_array($action, [CRM_Core_Action::UPDATE, CRM_Core_Action::VIEW])
    ) {
      if (!CRM_Activity_BAO_Activity::checkPermission($this->_id, $action)) {
        CRM_Core_Error::statusBounce(ts('You are not authorized to access this page.'));
      }
    }

    if ($context == 'standalone' || (!$contactId && ($action != CRM_Core_Action::DELETE) && !$this->_id)) {
      $this->_action = CRM_Core_Action::ADD;
      $this->assign('action', $this->_action);
    }
    else {
      // we should call contact view, preprocess only for activity in contact summary
      $this->preProcess();
    }

    // route behaviour of contact/view/activity based on action defined
    if ($this->_action & (CRM_Core_Action::UPDATE | CRM_Core_Action::ADD | CRM_Core_Action::VIEW)
    ) {
      $this->edit();
      $activityTypeId = CRM_Utils_Request::retrieve('atype', 'Positive', $this);

      // Email and Create Letter activities use a different form class
      $emailTypeValue = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity',
        'activity_type_id',
        'Email'
      );

      $letterTypeValue = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity',
        'activity_type_id',
        'Print PDF Letter'
      );

      if (in_array($activityTypeId, [$emailTypeValue, $letterTypeValue])) {
        return;
      }
    }
    elseif ($this->_action & (CRM_Core_Action::DELETE | CRM_Core_Action::DETACH)) {
      $this->delete();
    }
    else {
      $this->browse();
    }

    return parent::run();
  }

}
