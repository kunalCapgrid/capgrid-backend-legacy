<?php

namespace Drupal\capgrid_tweaks\Form;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Drupal\media\Entity\Media;
use \Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use \Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Controller Class for Custom Operation.
 */
class UploadSupplier extends FormBase {
	/**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['upload_supplier'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Upload Supplier'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
	$uri = 'modules/custom/capgrid_tweaks/data/supplier_details.xlsx';
	// $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
	// $abs_file_path = \Drupal::service('file_system')->realpath($uri);
	// $reader->setReadDataOnly(TRUE);
	// $spreadsheet = $reader->load($abs_file_path);
	// $worksheet = $spreadsheet->getActiveSheet(); 

	$batch = array(
      'title' => t('Uploading Suppliers...'),
      'operations' => array(
        array(
          '\Drupal\capgrid_tweaks\CapgridTweaks::importSupplier',[$worksheet] 
        ),
      ),
      'finished' => '\Drupal\capgrid_tweaks\CapgridTweaks::finishedSupplierUpload',
    );

    batch_set($batch);
  }

}