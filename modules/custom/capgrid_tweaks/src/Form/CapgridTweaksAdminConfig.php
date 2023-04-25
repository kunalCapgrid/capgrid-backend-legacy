<?php

namespace Drupal\capgrid_tweaks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CapgridTweaksAdminConfig extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'capgrid_tweaks.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'capgrid_tweak_config_form';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('capgrid_tweaks.adminsettings');
    $form['docusign_access_token'] = [
      '#title'=>'DocuSign Access Token',
      '#type'=>'textarea',
      '#default_value'=>$config->get('docusign_access_token'),
    ];
    $form['docusign_account_id'] = [
      '#title'=>'DocuSign Account ID',
      '#type'=>'textfield',
      '#default_value'=>$config->get('docusign_account_id'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('capgrid_tweaks.adminsettings');
    $config->set('docusign_account_id', $values['docusign_account_id']);
    $config->set('docusign_access_token', $values['docusign_access_token']);
    $config->save();
  }
}
