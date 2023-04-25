<?php

namespace Drupal\capgrid_tweaks\Controller;

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

/**
 * Controller Class for Custom Operation.
 */
class CapgridTweaks extends ControllerBase {

	public $currentUser;

	public function __contruct(AccountInterface $user){
		//$this->currentUser = \Drupal::currentUser()->id();
	}

	public static function create(ContainerInterface $container) {
		return new static(
			$container->get('current_user')
		);
	}
    
	public function requestNDA(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$pdf_content = $postParam['ndaContent'];
		$supplier_list = $postParam['supplierList'];

		$this->currentUser = User::load(\Drupal::currentUser()->id());
		$user_org = $this->currentUser->get('field_organization_name')->value;
		$user_nda_authority = $this->currentUser->get('field_nda_authority_name')->value;
		$user_nda_authority_designation = $this->currentUser->get('field_nda_authority_designation')->value;

		foreach($supplier_list as $supplier=>$parent_paragraph) {
			$supplier_details = reset(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $supplier]));
			$supplier_name = $supplier_details->get('field_company_name')->value;
			$supplier_email = 'dsudipta2012@gmail.com';//explode(",", $supplier_details->get('field_contact_email')->value)[0];

			$account = reset(\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' =>trim($supplier_email)]));
			$credentials = "<p>Login credentials are as follows: <br/> Username: ".$supplier_email."<br/>Password: 12345</p>";
			if(!empty($account)) {
				if($account->get('access')->value !== "0"){
					$credentials = "";
				}
			}
			else {
				$new_supplier_user = User::create([
					'name'=>explode("@",$supplier_email)[0],
					'pass'=>'12345',
					'mail'=>$supplier_email,
				]);
				$new_supplier_user->save();
			}

				$mailManager = \Drupal::service('plugin.manager.mail');
				$module = 'capgrid_tweaks';
				$key = 'send_nda_document';
				$to = $supplier_email;
				$params['message'] = 'Hi '.$supplier_name.', <br/>
				<p>You are receiving this email because '.$user_org.' is in the process of looking out for suppliers for a new sourcing requirement.

				For more details on the part requirements, '.$user_org.' wants you to first need to sign the Non-Disclosure Agreement.</p>
				
				<p>In order to access the Non-Disclosure Agreement and sign it digitally, please login to the CapGrid portal.</p>
				'.$credentials.'
				<p>Steps to Access and Sign the NDA:</p>
				
				<p>1. Login to the CapGrid portal using this link: https://capgridsolutions.com/login</p>
				<p>2. Go to the OEM Requirements section</p>
				
				<p>You will need to access the CapGrid portal to view the drawings / other details and carry out all the steps of the sourcing process. Please make sure to check the portal for further updates and notifications.</p>
				
				<p>Thanks<br/>
				CapGrid Team</p>';

				$params['subject'] = 'Request NDA';
				$langcode = \Drupal::currentUser()->getPreferredLangcode();
				$send = true;
				$mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
			
			$file_name = 'ListWidgetPDF'.time().'.pdf';

			$mpdf = new \Mpdf\Mpdf([
				'tempDir' => 'sites/default/files/tmp', 
				'format' => 'A4',
				'default_font_size' => 9,
				'default_font' => "Metropolis-Thin"
				]);
			
			$mpdf->falseBoldWeight = 1;
			$stylesheet = file_get_contents('modules/custom/capgrid_tweaks/css/supplier_nda_pdf.css'); // external css
			$mpdf->WriteHTML($stylesheet, 1);
			$pdf_header = '<h4 class="pdf-title">CONFIDENTIALITY AND NON-DISCLOSURE AGREEMENT</h4>
				<p class="pdf-second-para"><span class="pdf-second-title">THIS CONFIDENTIALITY AND NON-DISCLOSURE AGREEMENT</span> (the “Agreement”) made this</p>
				<p><span class="day-of-date">'.date('dS').'</span> day of 
					<span class="day-of-date">'.date('F').'</span> ,
					<span class="day-of-date">'.date('Y').'</span>	(the "Effective Date") by and between
				<span class="company-name"><strong>'.$supplier_name.'</strong></span> corporation, and 
				<span class="company-name"><strong> '.$user_org.'</strong></span> corporation,  
				(collectively, the "Parties" and each individually a "Party")</p>';
			$pdf_footer = '<div class="footer-wrapper">
				<div class="left-footer-content">
					<p>By: '.$user_org.'</p>
					<p>Name: '.$user_nda_authority.'</p>
					<p>Title: '.$user_nda_authority_designation.'</p>
				</div>
				<div class="right-footer-content">
					<p>By: &nbsp; &nbsp; &nbsp; &nbsp; __________________</p>
					<p>Name: &nbsp;&nbsp;__________________</p>
					<p>Title:&nbsp;__________________ </p>
				</div>
			</div>';

			$pdf_markup = '<div class="header">'.$pdf_header.'</div>
			<div class="pdf-body">'.$pdf_content.'</div>
			<div class="pdf-footer">'.$pdf_footer.'</div>';
			$mpdf->WriteHTML('<html><body class="pdf-body">'.$pdf_markup.'</body></html>', 2);
			$mpdf->Output('sites/default/files/tmp/'.$file_name,'F');
			$pdf_file_id = 0;
			$file_ref = "public://nda-pdf/";
			if(\Drupal::service('file_system')->prepareDirectory($file_ref, FileSystemInterface::CREATE_DIRECTORY)) {
				$pdf_data = file_get_contents('sites/default/files/tmp/'.$file_name);
				$nda_pdf = file_save_data($pdf_data, $file_ref . $file_name, FileSystemInterface::EXISTS_REPLACE);
				$pdf_file_id = $nda_pdf->id();
			}
			else {
				throw new AccessDeniedHttpException("Error while creating image file. $this->dest_path either not writable or not exist.");
			}
			$media_image = Media::create([
				'bundle' => 'document',
				'name' => $user_org.'_'.$supplier_name.'_'.time(),
				'field_media_document' => [
					'target_id' => $pdf_file_id,
				]]);
			$media_image->save();	
			$supplier_parent_details = reset(\Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['uuid' => $parent_paragraph]));
			$supplier_parent_details->set('field_request_nda', 1);
			$supplier_parent_details->set('field_nda_document', $media_image->id());
			$supplier_parent_details->save();
		}
		return new JsonResponse($postParam);
	}
	
	public function importSupplier() {
		try {
			$created_item_no = 0;
			$updated_item_no = 0;
			$count_total = 0;
			$uri = 'modules/custom/capgrid_tweaks/data/supplier_details.xlsx';
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
			$abs_file_path = \Drupal::service('file_system')->realpath($uri);
			$reader->setReadDataOnly(TRUE);
			$spreadsheet = $reader->load($abs_file_path);
			$worksheet = $spreadsheet->getActiveSheet();  

			// $batch = array(
			// 	'title' => t('Importing Supplier Details...'),
			// 	'operations' => [['\Drupal\capgrid_tweaks\CapgridTweaks::batchDataImport', [$worksheet]]],
			// 	'finished' => 	['\Drupal\capgrid_tweaks\CapgridTweaks::batchDataImportFinished'],
			// );
	
			// batch_set($batch);
			$rows = [];
			// Get the highest row number and column letter referenced in the worksheet
			$highestRow = $worksheet->getHighestRow(); // e.g. 10
			$highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
			// Increment the highest column letter
			$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);


			$highestColumn++;
			$rows = [];
			$casting = [];
			$i = 0;
			for ($row = 2; $row <= $highestRow; ++$row) {
				$count_total++;
				$supplier_name = $worksheet->getCell('C' . $row)->getValue();
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
				
				$forging_type = $worksheet->getCell('X' . $row)->getValue();
				$forging_min_weight = $worksheet->getCell('Y' . $row)->getValue();
				$forging_max_weight = $worksheet->getCell('Z' . $row)->getValue();
				$forging_production_capacity = $worksheet->getCell('AA' . $row)->getValue();
				
				$machining_type = $worksheet->getCell('AB' . $row)->getValue();
				$machining_max_weight = $worksheet->getCell('AC' . $row)->getValue();
				$machining_production_capacity = $worksheet->getCell('AD' . $row)->getValue();
				
				$cutting_type = $worksheet->getCell('AE' . $row)->getValue();
				$cutting_production_capacity = $worksheet->getCell('AF' . $row)->getValue();

				$bend_type = $worksheet->getCell('AG' . $row)->getValue();
				$bend_production_capacity = $worksheet->getCell('AH' . $row)->getValue();

				$welding_type = $worksheet->getCell('AI' . $row)->getValue();
				$welding_length = $worksheet->getCell('AJ' . $row)->getValue();
				$welding_tolerance_grade = $worksheet->getCell('AK' . $row)->getValue();
				$welding_production_capacity = $worksheet->getCell('AL' . $row)->getValue();
				
				$assembly_type = $worksheet->getCell('AM' . $row)->getValue();
				$assembly_production_capacity = $worksheet->getCell('AN' . $row)->getValue();
				
				$paint_type = $worksheet->getCell('AO' . $row)->getValue();
				$paint_production_capacity = $worksheet->getCell('AP' . $row)->getValue();
				
				$heat_treatment_type = $worksheet->getCell('AQ' . $row)->getValue();
				$heat_treatment_max_weight = $worksheet->getCell('AR' . $row)->getValue();
				$heat_treatment_production_capacity = $worksheet->getCell('AS' . $row)->getValue();

				$moulding_type = $worksheet->getCell('AT' . $row)->getValue();
				$moulding_size = $worksheet->getCell('AU' . $row)->getValue();
				$moulding_production_capacity = $worksheet->getCell('AV' . $row)->getValue();

				$plate_thickness = $worksheet->getCell('AW' . $row)->getValue();
				$plate_supplier = $worksheet->getCell('AX' . $row)->getValue();

				$material = $worksheet->getCell('AY' . $row)->getValue();
				$material_max_weight = $worksheet->getCell('AZ' . $row)->getValue();
				$material_min_weight = $worksheet->getCell('BA' . $row)->getValue();
				$material_tooling_capabilities = $worksheet->getCell('BB' . $row)->getValue();

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
				
				$located_in_india = $worksheet->getCell('BP' . $row)->getValue();
				$created_date = $worksheet->getCell('BQ' . $row)->getValue();
				$standards = $worksheet->getCell('BR' . $row)->getValue();
				$part_specifications = $worksheet->getCell('BS' . $row)->getValue();
				$tier = $worksheet->getCell('BT' . $row)->getValue();
				
				// $part_category_ids = [];
				// if (!empty($part_cat_arr = explode(",", $service_part_category))) {
				// 	foreach($part_cat_arr as $val) {
				// 		$term_query = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')
				// 			->loadByProperties(['name'=>trim($val), 'vid'=>'parts_category']));
				// 		if(!empty($term_query)){
				// 			$term_id = $term_query->id();
				// 			$term_revision_id = $term_query->getRevisionId();
				// 			$part_category_ids[] = ['target_id'=>$term_id, 'target_revision_id'=>$term_revision_id]; 
				// 		}
				// 	}
				// }

				//part category
				$part_category_ids = [];
				if(!empty($service_part_category) || !empty($service_part_details)){
					$part_category_para = Paragraph::create([
						'type'=>'part_category',
						'field_parts_category'=> $this->getTermIds('parts_category', $service_part_category, true),//$part_category_ids,
						'field_list_of_parts'=> !empty($service_part_details) ? explode(",", $service_part_details) : ""
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
						'field_key_clients_segment'=> !empty($company_segment_key_clients) ? explode(",", $company_segment_key_clients) : ""
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
						'field_material'=> $this->getTermIds('material', $production_type, true),
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
				$state_query = \Drupal::entityQuery('taxonomy_term')
					->condition('vid', 'states')
					->condition('name', $state)
					->execute();
				$state_term = '';
				if(!empty($state_query)){
					$state_term = reset($state_query);
				}
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
					$supplier_node->set('field_contact_name', trim($contact_name));
					$supplier_node->set('field_export_auto_parts', trim($export));
					$supplier_node->set('field_export_countries_name', (!empty($export_countries) ? explode(",", $export_countries) : []));
					$supplier_node->set('field_company_incorporated', trim($inc_year));
					$supplier_node->set('field_headquarters_address', trim($hq));
					$supplier_node->set('field_production_facilities_city', trim($city));
					$supplier_node->set('field_tier_2_and_tier_3_supplier', trim($tier));
					$supplier_node->set('field_supplier_annual_turnover', (!empty($turnover_term) ? $turnover_term : []));
					$supplier_node->set('field_supplier_production_state', (!empty($state_term) ? $state_term : []));

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
					$supplier_node->save();
					$updated_item_no++;
					continue;
				}
				$node_arr = [
					'title'=> $supplier_name,
					'type'=> 'supplier_details',
					'status'=>1,
					'field_company_name'=> $supplier_name,
					'field_contact_email'=> explode(",", trim($contact_email)),
					'field_contact_phone'=> $contact_phone,
					'field_contact_name'=> $contact_name,
					'field_export_auto_parts'=>$export,
					'field_export_countries_name'=>!empty($export_countries) ? explode(",", $export_countries) : [],
					'field_company_incorporated'=>$inc_year,
					'field_headquarters_address'=>$hq,
					//'field_house_tooling_capabilities'=>
					'field_production_facilities_city'=>$city,
					'field_tier_2_and_tier_3_supplier'=>$tier,
					'field_supplier_annual_turnover'=> !empty($turnover_term) ? $turnover_term : [],//$turnover,
					'field_supplier_production_state'=> !empty($state_term) ? $state_term : [],//$state,
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
					// 'field_testing_facility'=>
					// 'field_painting_capability'=>
					'field_moulding_capability'=> $moulding_ids,
					'field_machining_capability'=> $machining_ids,
					// 'field_heat_treatment_capability'=>,
					// 'field_forging_capabilities'=>,
					// 'field_design_and_development_cap'=>,
					// 'field_cutting_capability'=>,
					// 'field_bending_capability'=>
					// 'field_assembly_capability'=>
				];
				$node_supplier = Node::create($node_arr);
				$node_supplier->save();
				$created_item_no++;
			}
		}
		catch(\Exception $e){
			\Drupal::logger('upload_supplier_data')->error($e->getMessage());
		}
		return New Response("Supplier Details Uploaded Successfully. 
			Created: ".$created_item_no.".Updated: ".$updated_item_no."Current Supplier: ".$supplier_name."Current Total: ".$count_total);
	}

	public function getTermIds($vid, $service_part_category, $is_revision = false) {
		$part_category_ids = [];
		if (!empty($part_cat_arr = explode(",", $service_part_category))) {
			foreach($part_cat_arr as $val) {
				if(empty(trim($val))){
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

	public function setSuppliers(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$selected_suppliers = array_keys($postParam['selected_supplier']);
		$all_supplier_details = [];
		foreach($selected_suppliers as $val) {
			$supplier_para = Paragraph::create([
				'type'=> explode("paragraph--",$postParam['paragraph_type'])[1],
				'field_priority'=> isset($postParam['supplier_priority'][$val]) ? $postParam['supplier_priority'][$val] : "",
				'field_supplier'=> ['target_id'=>$val]
			]);
			$supplier_para_details = $supplier_para->save();
			$all_supplier_details[] = [
				'type'=>$postParam['paragraph_type'],
				'id'=>$supplier_para->get('uuid')->value,
				"meta"=> [
					"target_revision_id"=>$supplier_para->getRevisionId()
				]	
			]; 
		}
		return new JsonResponse($all_supplier_details);
	}

}