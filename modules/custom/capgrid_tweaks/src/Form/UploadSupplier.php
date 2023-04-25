<?php

namespace Drupal\capgrid_tweaks\Form;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
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
  
  public $worksheetData;
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
    
    $form['supplier_dataset'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Supplier Dataset'),
      '#description'=>'Allowed file types: xlsx',
      //'#upload_location' => '/tmp',
      '#upload_validators' => [
        'file_validate_extensions' => ['xlsx'],
      ],
      '#required'=>true,
    ];

    $form['need_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update Existing Suppliers'),
      '#description'=>'If this is checked it will update existing supplier if exist in the uploaded sheet.',
    ];

    $form['upload_supplier'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Upload Supplier'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_file = $form_state->getValue('supplier_dataset', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      $abs_file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
      $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
      $reader->setReadDataOnly(TRUE);
      $spreadsheet = $reader->load($abs_file_path);
      $worksheet = $spreadsheet->getActiveSheet(); 
      $highestRow = $worksheet->getHighestRow(); 
      if($highestRow > 4500) {
        $form_state->setErrorByName('supplier_dataset', 
          $this->t('More than 4500 data cannot be uploaded at a time. Try Again!'));
      }
      $supplier_first_column_name = $worksheet->getCell('C1')->getValue();
      if(strtolower($supplier_first_column_name) !== "supplier name"){
        $form_state->setErrorByName('supplier_dataset', 
          $this->t('Uploaded file data structure is not correct. Try Again!'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // $batch = array(
    //   'title' => t('Uploading Suppliers...'),
    //   'operations'=>[],
    //   'init_message'     => t('Importing Supplier'),
    //   'progress_message' => t('Processed @current out of @total.'),
    //   'error_message'    => t('An error occurred during processing'),
    //   'finished' => 'capgrid_supplier_batch_finished',
    //   'file' => drupal_get_path('module', 'capgrid_tweaks') . '/capgrid_upload_supplier.batch.inc',
    // );
      
    $form_file = $form_state->getValue('supplier_dataset', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      try{
        $file = File::load($form_file[0]);
        $abs_file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(TRUE);
        $spreadsheet = $reader->load($abs_file_path);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
			// Increment the highest column letter
        $created_item_no = 0;
        $updated_item_no = 0;
        $count_total = 0;

        for ($row = 2; $row <= $highestRow; ++$row) {
          $supplier_name = $worksheet->getCell('C' . $row)->getValue();
          if(empty($supplier_name)){
            //\Drupal::messenger()->addMessage("Row:".$row." Supplier name found empty.");
            continue;
          }
          else{
            $count_total++;
          }
          $supplier_query = \Drupal::entityQuery('node')
            ->condition('type', 'supplier_details')
            ->condition('title', trim($supplier_name), '=')
            ->condition('status', 1, '=');
          $supplier_details	= $supplier_query->execute();
          if($form_state->getValue('need_update') == "0"){
            if(!empty($supplier_details)) {
              \Drupal::logger('upload_supplier_data')->alert($count_total." ".$supplier_name." not uploaded already exist.");
              \Drupal::messenger()->addMessage("\n".$count_total." ".$supplier_name." not uploaded already exist.");
              continue;
            }
          }
          $inc_year = $worksheet->getCell('D' . $row)->getValue();
          $hq = $worksheet->getCell('E' . $row)->getValue();
          $city = $worksheet->getCell('F' . $row)->getValue();
          $state = $worksheet->getCell('G' . $row)->getValue();
          $turnover = $worksheet->getCell('H' . $row)->getValue();
          $contact_name = $worksheet->getCell('I' . $row)->getValue();
          $contact_phone = $worksheet->getCell('J' . $row)->getValue();
          $contact_email = $worksheet->getCell('K' . $row)->getValue();
          $export = $worksheet->getCell('L' . $row)->getValue();
          $export_countries = $worksheet->getCell('M' . $row)->getValue();
          
          $company_segment_vehicles = $worksheet->getCell('N' . $row)->getValue();
          $company_segment_key_clients = $worksheet->getCell('O' . $row)->getValue();
          
          $company_sub_segment_vehicles = $worksheet->getCell('P' . $row)->getValue();
          $company_sub_segment_vehicles_segment = $worksheet->getCell('Q' . $row)->getValue();
          
          $service_part_category = $worksheet->getCell('R' . $row)->getValue();
          $service_part_details = $worksheet->getCell('S' . $row)->getValue();
          
          $casting_type = $worksheet->getCell('T' . $row)->getValue();
          $casting_min_weight = $worksheet->getCell('U' . $row)->getValue();
          $casting_max_weight = $worksheet->getCell('V' . $row)->getValue();
          $casting_production_capacity = $worksheet->getCell('W' . $row)->getValue();
          
          // $forging_type = $worksheet->getCell('X' . $row)->getValue();
          // $forging_min_weight = $worksheet->getCell('Y' . $row)->getValue();
          // $forging_max_weight = $worksheet->getCell('Z' . $row)->getValue();
          // $forging_production_capacity = $worksheet->getCell('AA' . $row)->getValue();
          
          $machining_type = $worksheet->getCell('AB' . $row)->getValue();
          $machining_max_weight = $worksheet->getCell('AC' . $row)->getValue();
          $machining_production_capacity = $worksheet->getCell('AD' . $row)->getValue();
          
          // $cutting_type = $worksheet->getCell('AE' . $row)->getValue();
          // $cutting_production_capacity = $worksheet->getCell('AF' . $row)->getValue();

          // $bend_type = $worksheet->getCell('AG' . $row)->getValue();
          // $bend_production_capacity = $worksheet->getCell('AH' . $row)->getValue();

          $welding_type = $worksheet->getCell('AI' . $row)->getValue();
          $welding_length = $worksheet->getCell('AJ' . $row)->getValue();
          $welding_tolerance_grade = $worksheet->getCell('AK' . $row)->getValue();
          $welding_production_capacity = $worksheet->getCell('AL' . $row)->getValue();
          
          // $assembly_type = $worksheet->getCell('AM' . $row)->getValue();
          // $assembly_production_capacity = $worksheet->getCell('AN' . $row)->getValue();
          
          // $paint_type = $worksheet->getCell('AO' . $row)->getValue();
          // $paint_production_capacity = $worksheet->getCell('AP' . $row)->getValue();
          
          // $heat_treatment_type = $worksheet->getCell('AQ' . $row)->getValue();
          // $heat_treatment_max_weight = $worksheet->getCell('AR' . $row)->getValue();
          // $heat_treatment_production_capacity = $worksheet->getCell('AS' . $row)->getValue();

          $moulding_type = $worksheet->getCell('AT' . $row)->getValue();
          $moulding_size = $worksheet->getCell('AU' . $row)->getValue();
          $moulding_production_capacity = $worksheet->getCell('AV' . $row)->getValue();

          $plate_thickness = $worksheet->getCell('AW' . $row)->getValue();
          $plate_supplier = $worksheet->getCell('AX' . $row)->getValue();

          $material = $worksheet->getCell('AY' . $row)->getValue();
          $material_max_weight = $worksheet->getCell('AZ' . $row)->getValue();
          $material_min_weight = $worksheet->getCell('BA' . $row)->getValue();
          // $material_tooling_capabilities = $worksheet->getCell('BB' . $row)->getValue();

          $production_type = $worksheet->getCell('BC' . $row)->getValue();
          $production_capabilities_in_house = $worksheet->getCell('BD' . $row)->getValue();
          $production_capabilities_outsourced = $worksheet->getCell('BE' . $row)->getValue();
          
          $production_material = $worksheet->getCell('BF' . $row)->getValue();
          $production_material_type = $worksheet->getCell('BG' . $row)->getValue();

          $certifications = $worksheet->getCell('BH' . $row)->getValue();
          $certification_details = $worksheet->getCell('BI' . $row)->getValue();

          $testing_type = $worksheet->getCell('BJ' . $row)->getValue();
          $testing_in_house = $worksheet->getCell('BK' . $row)->getValue();
          $testing_out_sourced = $worksheet->getCell('BL' . $row)->getValue();

          $design_development = $worksheet->getCell('BM' . $row)->getValue();
          $design_development_in_house = $worksheet->getCell('BN' . $row)->getValue();
          $design_development_out_sourced = $worksheet->getCell('BO' . $row)->getValue();
          
          // $located_in_india = $worksheet->getCell('BP' . $row)->getValue();
          // $created_date = $worksheet->getCell('BQ' . $row)->getValue();
          // $standards = $worksheet->getCell('BR' . $row)->getValue();
          // $part_specifications = $worksheet->getCell('BS' . $row)->getValue();
          $tier = $worksheet->getCell('BT' . $row)->getValue();

          $export_rating = $worksheet->getCell('BR' . $row)->getValue();
          $customer_strength = $worksheet->getCell('BS' . $row)->getValue();
          $financial_strength = $worksheet->getCell('BT' . $row)->getValue();
          $product_diverification_score = $worksheet->getCell('BU' . $row)->getValue();

          $production_country = $worksheet->getCell('BV' . $row)->getValue();
          $product_brand = $worksheet->getCell('BW' . $row)->getValue();

          //certifications 
          $certifications_ids = [];
          if(!empty($certifications) || !empty($certification_details)) {
            $part_category_para = Paragraph::create([
              'type'=>'quality_certification',
              'field_certification_type'=> $this->getTermIds('certification_type', $certifications),
              'field_key_clients_segment'=> !empty($certification_details) ? explode(",", $certification_details) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $certifications_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //testing facility
          $testing_ids = [];
          if(!empty($testing_type) || !empty($testing_in_house) || !empty($testing_out_sourced)) {
            $part_category_para = Paragraph::create([
              'type'=>'testing_facility',
              'field_testing_type'=> $this->getTermIds('testing_type', $testing_type),
              'field_in_house'=> !empty($testing_in_house) ? explode(",", $testing_in_house) : "",
              'field_outsourced'=> !empty($testing_out_sourced) ? explode(",", $testing_out_sourced) : "",
            ]);
            $part_category_para1 = $part_category_para->save();
            $testing_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //design development
          $design_ids = [];
          if(!empty($design_development) || !empty($design_development_in_house) || !empty($design_development_out_sourced)) {
            $part_category_para = Paragraph::create([
              'type'=>'design_and_development_capabilit',
              'field_design_and_development_typ'=> $this->getTermIds('design_and_development_type', $design_development),
              //'field_design_and_development_det'=>!empty($design_development) ? $design_development : "",
              'field_in_house'=> !empty($design_development_in_house) ? explode(",", $design_development_in_house) : "",
              'field_outsourced'=> !empty($design_development_out_sourced) ? explode(",", $design_development_out_sourced) : "",
            ]);
            $part_category_para1 = $part_category_para->save();
            $design_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }
          $comma_explode = explode(",", $service_part_details);
          $new_line = explode("\n", $service_part_details);
          $del = ',';
          if(count($comma_explode) < count($new_line)){
            $del = "\n";
          }
          //part category
          $part_category_ids = [];
          if(!empty($service_part_category) || !empty($service_part_details)){
            $part_category_para = Paragraph::create([
              'type'=>'part_category',
              'field_parts_category'=> $this->getTermIds('parts_category', $service_part_category, true),//$part_category_ids,
              'field_list_of_parts'=> !empty($service_part_details) 
                ? array_filter(array_map(function($arr){ return strlen($arr) > 255 ? "" : $arr;}, explode($del, $service_part_details))) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $part_category_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //business segement
          $business_segment_ids = [];
          if(!empty($company_segment_vehicles) || !empty($company_segment_key_clients)) {
            $part_category_para = Paragraph::create([
              'type'=>'business_segment',
              'field_vehicles'=> $this->getTermIds('segment_vechile', $company_segment_vehicles, true),//$part_category_ids,
              'field_key_clients_segment'=> !empty($company_segment_key_clients) && strlen(explode(",", $company_segment_key_clients)[0]) < 256 ? explode(",", $company_segment_key_clients) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $business_segment_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //business sub segment
          $business_sub_segment_ids = [];
          if(!empty($company_sub_segment_vehicles) || !empty($company_sub_segment_vehicles_segment)) {
            $part_category_para = Paragraph::create([
              'type'=>'specific_sub_segment',
              'field_vehicles_sub_segment'=> $this->getTermIds('sub_segments_vehicle', $company_sub_segment_vehicles, true),//$part_category_ids,
              'field_vehicles_segment'=> !empty($company_sub_segment_vehicles_segment) ? explode(",", $company_sub_segment_vehicles_segment) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $business_sub_segment_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //material weight
          $material_weight_ids = [];
          if(!empty($material) || !empty($material_min_weight) || !empty($material_max_weight)) {
            $part_category_para = Paragraph::create([
              'type'=>'material_weight',
              'field_material'=> $this->getTermIds('material', $material, true),//$part_category_ids,
              'field_weight_max'=> !empty($material_max_weight) ? explode(",", $material_max_weight) : "",
              'field_weight_min'=> !empty($material_min_weight) ? explode(",", $material_min_weight) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $material_weight_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //casting type
          $casting_type_ids = [];
          if(!empty($casting_type) || !empty($casting_min_weight) || 
            !empty($casting_max_weight) || !empty($casting_production_capacity)) {
            $part_category_para = Paragraph::create([
              'type'=>'casting_capabilities',
              'field_casting_type'=> $this->getTermIds('casting_type', $casting_type, true),//$part_category_ids,
              'field_maximum_weight'=> !empty($casting_max_weight) ? explode(",", $casting_max_weight) : "",
              'field_minimum_weight'=> !empty($casting_max_weight) ? explode(",", $casting_max_weight) : "",
              'field_production_capacity'=> !empty($casting_production_capacity) ? explode(",", $casting_production_capacity) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $casting_type_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //production capabilities
          $prod_capability_ids = [];
          if(!empty($production_type) || !empty($production_capabilities_in_house) || 
            !empty($production_capabilities_outsourced)) {
            $part_category_para = Paragraph::create([
              'type'=>'major_production_capabilities',
              'field_production_capabilities'=> $this->getTermIds('production_capabilities', $production_type, true),//$part_category_ids,
              'field_in_house'=> !empty($production_capabilities_in_house) ? explode(",", $production_capabilities_in_house) : "",
              'field_outsourced'=> !empty($production_capabilities_outsourced) ? explode(",", $production_capabilities_outsourced) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $prod_capability_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //plate thickness
          $plate_thickness_ids = [];
          if(!empty($plate_thickness) || !empty($plate_supplier)) {
            $part_category_para = Paragraph::create([
              'type'=>'thickness_plate',
              'field_thickness'=> $this->getTermIds('thickness', $plate_thickness, true),//$part_category_ids,
              'field_plate_supplier_name'=> !empty($plate_supplier) ? explode(",", $plate_supplier) : "",
            ]);
            $part_category_para1 = $part_category_para->save();
            $plate_thickness_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //material grade
          $material_grade_ids = [];
          if(!empty($production_material) || !empty($production_material_type)) {
            $part_category_para = Paragraph::create([
              'type'=>'material_grade',
              'field_material'=> $this->getTermIds('material', $production_material, true),
              'field_grades_types'=> !empty($production_material_type) ? explode(",", $production_material_type) : "",
            ]);
            $part_category_para1 = $part_category_para->save();
            $material_grade_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //welding type
          $welding_ids = [];
          if(!empty($welding_type) || !empty($welding_tolerance_grade) 
          || !empty($welding_length) || !empty($welding_production_capacity)) {
            $part_category_para = Paragraph::create([
              'type'=>'welding_capability',
              'field_welding_type'=> $this->getTermIds('welding_type', $welding_type),
              'field_tolerance_grade'=> !empty($welding_tolerance_grade) ? explode(",", $welding_tolerance_grade) : "",
              'field_production_capacity'=> !empty($welding_production_capacity) ? explode(",", $welding_production_capacity) : "",
              'field_length_depth'=> !empty($welding_length) ? explode(",", $welding_length) : "",

            ]);
            $part_category_para1 = $part_category_para->save();
            $welding_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }
          
          //machining
          $machining_ids = [];
          if(!empty($machining_type) || !empty($machining_production_capacity) 
            || !empty($machining_max_weight)) {
            $part_category_para = Paragraph::create([
              'type'=>'machining_capability',
              'field_machining_type'=> $this->getTermIds('machining_type', $machining_type),
              'field_maximum_weight'=> !empty($machining_max_weight) ? explode(",", $machining_max_weight) : "",
              'field_production_capacity'=> !empty($machining_production_capacity) ? explode(",", $machining_production_capacity) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $machining_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }

          //molding
          $moulding_ids = [];
          if(!empty($moulding_type) || !empty($moulding_production_capacity) 
            || !empty($moulding_size)) {
            $part_category_para = Paragraph::create([
              'type'=>'moulding_capability',
              'field_moulding_type'=> $this->getTermIds('moulding_type', $moulding_type),
              'field_production_capacity'=> !empty($moulding_production_capacity) ? explode(",", $moulding_production_capacity) : "",
              'field_size'=> !empty($moulding_size) ? explode(",", $moulding_size) : ""
            ]);
            $part_category_para1 = $part_category_para->save();
            $moulding_ids = [
              'target_id'=>$part_category_para->id(), 
              'target_revision_id'=>$part_category_para->getRevisionId()
            ];
          }
          $supplier_query = \Drupal::entityQuery('node')
            ->condition('type', 'supplier_details')
            ->condition('title', trim($supplier_name), '=')
            ->condition('status', 1, '=');
          $supplier_details	= $supplier_query->execute();
          
          $state_term = '';
          if(!empty(trim($state))) {
            $state_query = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'states')
            ->condition('name', $state)
            ->execute();
            if(!empty($state_query)){
              $state_term = reset($state_query);
            }
            else{
              $state_create = Term::create([
                'vid'=>'states',
                'name'=>trim($state),
              ]);
              $state_create->save();
              $state_term = $state_create->id();
            }
          }
          //$production_country
          $country_term = '';
          if(!empty(trim($production_country))) {
            $country_query = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'country')
            ->condition('name', $production_country)
            ->execute();
            if(!empty($country_query)){
              $country_term = reset($country_query);
            }
            else{
              $country_create = Term::create([
                'vid'=>'country',
                'name'=>trim($production_country),
              ]);
              $country_create->save();
              $country_term = $country_create->id();
            }
          }
          
          $turnover_term = '';
          if(!empty(trim($turnover))){
            $turnover_query = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'annual_turnover')
            ->condition('name', trim($turnover))
            ->execute();
            $turnover_term = '';
            if(!empty($turnover_query)){
              $turnover_term = reset($turnover_query);
            }
            else {
              $turnover_create = Term::create([
                'vid'=>'annual_turnover',
                'name'=>trim($turnover),
              ]);
              $turnover_create->save();
              $turnover_term = $turnover_create->id();
            }
          }
          if(!empty($supplier_details)) {
            $supplier_node = Node::load(reset($supplier_details));
            $supplier_node->set('field_company_name', trim($supplier_name));
            $supplier_node->set('field_contact_email', explode(",",trim($contact_email)) );
            $supplier_node->set('field_contact_phone', trim($contact_phone));
            $supplier_node->set('field_contact_name', explode(",", trim($contact_name)));
            $supplier_node->set('field_export_auto_parts', trim($export));
            $supplier_node->set('field_export_countries_name', (!empty($export_countries) ? explode(",", $export_countries) : []));
            $supplier_node->set('field_company_incorporated', trim($inc_year));
            $supplier_node->set('field_headquarters_address', trim($hq));
            $supplier_node->set('field_production_facilities_city', trim($city));
            $supplier_node->set('field_tier_2_and_tier_3_supplier', trim($tier));
            $supplier_node->set('field_supplier_annual_turnover', (!empty($turnover_term) ? $turnover_term : []));
            $supplier_node->set('field_supplier_production_state', (!empty($state_term) ? $state_term : []));
            $supplier_node->set('field_production_country', (!empty($country_term) ? $country_term : []));

            $supplier_node->set('field_product_brand', (!empty($product_brand) ? explode(",", $product_brand) : []));
                                          $supplier_node->set('field_part_category_service', $part_category_ids);
            $supplier_node->set('field_business_segment', $business_segment_ids);
            $supplier_node->set('field_specific_sub_segment', $business_sub_segment_ids);
            $supplier_node->set('field_types_of_materials', $material_weight_ids);

            $supplier_node->set('field_materials_handle', $material_grade_ids);
            $supplier_node->set('field_type_of_casting_capability', $casting_type_ids);
            $supplier_node->set('field_thickness_metal_plate', $plate_thickness_ids);
            $supplier_node->set('field_major_production', $prod_capability_ids);

            $supplier_node->set('field_welding_capability', $welding_ids);
            $supplier_node->set('field_machining_capability', $machining_ids);
            $supplier_node->set('field_moulding_capability', $moulding_ids);

            $supplier_node->set('field_quality_certification', $certifications_ids);
            $supplier_node->set('field_testing_facility', $testing_ids);
            $supplier_node->set('field_design_and_development_cap', $design_ids);

            $supplier_node->set('field_customer_strength', $customer_strength);
            $supplier_node->set('field_export_rating', $export_rating);
            $supplier_node->set('field_financial_strength', $financial_strength);
            $supplier_node->set('field_product_diversification_sc', $product_diverification_score);
            // 'field_customer_strength'=>$customer_strength,
            // 'field_export_rating'=>$export_rating,
            // 'field_financial_strength'=>$financial_strength,
            // 'field_product_diversification_sc'=>$product_diverification_score
            $supplier_node->save();
            $updated_item_no++;
            continue;  
          }
          else{
            $node_arr = [
              'title'=> trim($supplier_name),
              'type'=> 'supplier_details',
              'status'=>1,
              'field_company_name'=> trim($supplier_name),
              'field_contact_email'=> explode(",", trim($contact_email)),
              'field_contact_phone'=> $contact_phone,
              'field_contact_name'=> explode(",", trim($contact_name)),
              'field_export_auto_parts'=>$export,
              'field_export_countries_name'=>!empty($export_countries) ? explode(",", $export_countries) : [],
              'field_company_incorporated'=>$inc_year,
              'field_headquarters_address'=>$hq,
              //'field_house_tooling_capabilities'=>
              'field_production_facilities_city'=>$city,
              'field_tier_2_and_tier_3_supplier'=>$tier,
              'field_supplier_annual_turnover'=> !empty($turnover_term) ? $turnover_term : [],//$turnover,
              'field_supplier_production_state'=> !empty($state_term) ? $state_term : [],//$state,
              'field_production_country'=> !empty($country_term) ? $country_term : [],//$state,
              'field_product_brand'=> !empty($product_brand) ? explode(",", $product_brand) : [],
              'field_part_category_service'=> $part_category_ids,
              'field_business_segment'=> $business_segment_ids,
              'field_specific_sub_segment'=>$business_sub_segment_ids,
              'field_types_of_materials'=> $material_weight_ids,
              'field_materials_handle'=> $material_grade_ids,
              'field_type_of_casting_capability'=> $casting_type_ids,
              'field_thickness_metal_plate'=>$plate_thickness_ids,
              'field_major_production'=>$prod_capability_ids,
              // 
              'field_welding_capability'=> $welding_ids,
              'field_testing_facility'=>$testing_ids,
              // 'field_painting_capability'=>
              'field_moulding_capability'=> $moulding_ids,
              'field_machining_capability'=> $machining_ids,
              // 'field_heat_treatment_capability'=>,
              // 'field_forging_capabilities'=>,
              'field_design_and_development_cap'=>$design_ids,
              'field_quality_certification'=>$certifications_ids,
              // 'field_cutting_capability'=>,
              // 'field_bending_capability'=>
              // 'field_assembly_capability'=>
              'field_customer_strength'=>$customer_strength,
              'field_export_rating'=>$export_rating,
              'field_financial_strength'=>$financial_strength,
              'field_product_diversification_sc'=>$product_diverification_score
            ];
            $node_supplier = Node::create($node_arr);
            $node_supplier->save();
            $created_item_no++;
            continue;
          }
        }
        \Drupal::logger('upload_supplier_data')->alert($count_total." ".$supplier_name." Created successfully");
      }
      catch(\Exception $e){
        \Drupal::logger('upload_supplier_data')->alert($count_total." ".$supplier_name." not uploaded.");
        \Drupal::logger('upload_supplier_data')->error($e->getMessage());
        \Drupal::messenger()->addMessage($count_total." ".$supplier_name." could not be uploaded due to bad data.".$e->getMessage());
      }
      \Drupal::messenger()->addMessage("Supplier Details Successfully Uploaded . 
		    Created: ".$created_item_no." :: Updated: ".$updated_item_no." :: Current Supplier: ".$supplier_name." :: Current Total: ".$count_total);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTermIds($vid, $service_part_category, $is_revision = false) {
		$part_category_ids = [];
		if (!empty($part_cat_arr = explode(",", $service_part_category))) {
			foreach($part_cat_arr as $val) {
				if(empty(trim($val)) || strlen($val) > 255){
					continue;
				}
				$term_query = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')
					->loadByProperties(['name'=>trim($val), 'vid'=>$vid]));
				if(!empty($term_query)){
					$term_id = $term_query->id();
					$term_revision_id = $term_query->getRevisionId();
					if($is_revision){
						$part_category_ids[] = ['target_id'=>$term_id, 'target_revision_id'=>$term_revision_id];
					}
					else{
						$part_category_ids[] = ['target_id'=>$term_id];
					}
				}
				else{
					$new_term = Term::create([
						'vid'=>$vid,
						'name'=>trim($val)
					]);
					$new_term->save();
					if($is_revision){
						$part_category_ids[] = ['target_id'=>$new_term->id(), 'target_revision_id'=>$new_term->getRevisionId()];
					}
					else{
						$part_category_ids[] = ['target_id'=>$new_term->id()];
					}
				}
			}
		}
		return $part_category_ids;
	}

}