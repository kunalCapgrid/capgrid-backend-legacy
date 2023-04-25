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
		$pdf_media_id = 0;
		$postParam = json_decode($request->getContent(), TRUE);
		$pdf_content = $postParam['ndaContent'];
		$supplier_list = $postParam['supplierList'];
		$request_type = $postParam['requestType'];

		$drupal_config = \Drupal::config('capgrid_tweaks.adminsettings');
		$accessToken = $drupal_config->get('docusign_access_token');
		$accountId = $drupal_config->get('docusign_account_id');
		//$accessToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6IjY4MTg1ZmYxLTRlNTEtNGNlOS1hZjFjLTY4OTgxMjIwMzMxNyJ9.eyJUb2tlblR5cGUiOjUsIklzc3VlSW5zdGFudCI6MTU4OTgzNDIyOCwiZXhwIjoxNTg5ODYzMDI4LCJVc2VySWQiOiIxMDI3YmVkYy1mNmJjLTQzODQtYmU1OS04MTI3YzIxM2UwZmYiLCJzaXRlaWQiOjEsInNjcCI6WyJzaWduYXR1cmUiLCJjbGljay5tYW5hZ2UiLCJvcmdhbml6YXRpb25fcmVhZCIsInJvb21fZm9ybXMiLCJncm91cF9yZWFkIiwicGVybWlzc2lvbl9yZWFkIiwidXNlcl9yZWFkIiwidXNlcl93cml0ZSIsImFjY291bnRfcmVhZCIsImRvbWFpbl9yZWFkIiwiaWRlbnRpdHlfcHJvdmlkZXJfcmVhZCIsImR0ci5yb29tcy5yZWFkIiwiZHRyLnJvb21zLndyaXRlIiwiZHRyLmRvY3VtZW50cy5yZWFkIiwiZHRyLmRvY3VtZW50cy53cml0ZSIsImR0ci5wcm9maWxlLnJlYWQiLCJkdHIucHJvZmlsZS53cml0ZSIsImR0ci5jb21wYW55LnJlYWQiLCJkdHIuY29tcGFueS53cml0ZSJdLCJhdWQiOiJmMGYyN2YwZS04NTdkLTRhNzEtYTRkYS0zMmNlY2FlM2E5NzgiLCJhenAiOiJmMGYyN2YwZS04NTdkLTRhNzEtYTRkYS0zMmNlY2FlM2E5NzgiLCJpc3MiOiJodHRwczovL2FjY291bnQtZC5kb2N1c2lnbi5jb20vIiwic3ViIjoiMTAyN2JlZGMtZjZiYy00Mzg0LWJlNTktODEyN2MyMTNlMGZmIiwiYW1yIjpbImludGVyYWN0aXZlIl0sImF1dGhfdGltZSI6MTU4OTgzNDIyNSwicHdpZCI6IjY1OTgyYjFhLWE2OGMtNGEwYS1hYmRhLTNkNjE0Mjk0NTk1ZiJ9.G66s4ClNa1P0EazJevFePVFGBAhV7Fr6L1iJ3L028gOyX30hT0H6YvAx7dJEDrX9ZDtSoNEMNtz0f0ySn1W6BRMkYxrNj07GCnyMjyNeQQtQDmAi-ipJBJYyT40ashI3iYABognd6-Jveoq5HUCXKgX4f6DXC2sZNq3GVNrrg_xvTeyXgilFrM_QPWb9IaIsSUqhS0q3UFeI7tneIIt8Uq6MFs-caE2sfDuYNMbhILUZrSlDLM3byblczcdjIrnRFF1iBhGTCY5itsA1Am0QhFpXVCl5cDX09WSvVsEnD1dCMokHI8Uk-rWaxP3zB4pBuERdXtuGwJznigF8dVbH_Q';
		//$accountId = '10217966';
		$basePath = 'https://demo.docusign.net/restapi';
		$appPath = getcwd();
		
		$this->currentUser = User::load(\Drupal::currentUser()->id());
		$user_org = $this->currentUser->get('field_organization_name')->value;
		$user_nda_authority = $this->currentUser->get('field_nda_authority_name')->value;
		$user_nda_authority_designation = $this->currentUser->get('field_nda_authority_designation')->value;
		$purchaser_email = $this->currentUser->getEmail();
		$purchaser_email = "tiwari.dheeraj@gmail.com";

		$cc1 = new \DocuSign\eSign\Model\CarbonCopy([
			'email' => $this->currentUser->getEmail(), 'name' => $user_nda_authority,
			'recipient_id' => "2", 'routing_order' => "2"]);

		$signer_users = [];
		$order = 0;
		$routing_order = 0;
		foreach($supplier_list as $supplier=>$parent_paragraph) {
			$supplier_details = reset(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $supplier]));
			$supplier_name = $supplier_details->get('field_company_name')->value;
			//$supplier_email = 'sudipta.123045@gmail.com';//explode(",", $supplier_details->get('field_contact_email')->getValue())[0];
			//$supplier_email = explode(",", $supplier_details->get('field_contact_email')->getValue())[0];
			$supplier_email = $supplier_details->get('field_contact_email')->getValue()[0]['value'];
			//return new JsonResponse($supplier_email, $supplier_name);
			$supplier_email = "B14027@ASTRA.XLRI.AC.IN";
			$signerName = $supplier_name;
			// $signer_users = [
			// 	'email' => $supplier_email, 
			// 	'name' => 'Test Supplier',
			// 	'recipient_id' => "1", 
			// 	'routing_order' => "1"
			// ];
			$signer = new \DocuSign\eSign\Model\Signer([ 
        		'email' => $supplier_email, 'name' => $signerName, 'recipient_id' => "1", 'routing_order' => "1"
			]);
			
			$signerPurchaser = new \DocuSign\eSign\Model\Signer([ 
        		'email' => $purchaser_email, 'name' => $user_nda_authority, 'recipient_id' => "2", 'routing_order' => "2"
			]);
			
			$signHere = new \DocuSign\eSign\Model\SignHere([ 
				'document_id' => '1', 'page_number' => '2', 'recipient_id' => '1', 
				'tab_label' => 'SignHereTab', 'x_position' => '335', 'y_position' => '400'
			]);

			$signHere1 = new \DocuSign\eSign\Model\SignHere([ 
				'document_id' => '1', 'page_number' => '2', 'recipient_id' => '2', 
				'tab_label' => 'SignHereTab', 'x_position' => '50', 'y_position' => '400'
			]);
			$signer->setTabs(new \DocuSign\eSign\Model\Tabs(['sign_here_tabs' => [$signHere]]));
			$signerPurchaser->setTabs(new \DocuSign\eSign\Model\Tabs(['sign_here_tabs' => [$signHere1]]));
			$account = reset(\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' =>trim($supplier_email)]));
			$digits = 6;
			$random_password = rand(pow(10, $digits-1), pow(10, $digits)-1);
			$credentials = "<p>Login credentials are as follows: <br/> Username: ".$supplier_email."<br/>Password: ".$random_password."</p>";
			if(!empty($account)) {
				if($account->get('access')->value !== "0") {
					$credentials = "";
				}
			}
			else {
				$new_supplier_user = User::create([
					'name'=>explode("@",$supplier_email)[0],
					'pass'=>$random_password,
					'mail'=>$supplier_email,
					'field_supplier_profile'=>$supplier_details->id(),
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
			if($pdf_media_id === 0) {
				$file_name = 'Capgrid'.time().'.pdf';

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
					$base64FileContent =  base64_encode ($pdf_data);
					$document = new \DocuSign\eSign\Model\Document([  
						'document_base64' => $base64FileContent, 
						'name' => 'NDA Document', # can be different from actual file name
						'file_extension' => 'pdf', # many different document types are accepted
						'document_id' => '1' # a label used to reference the doc
					]);
					$nda_pdf = file_save_data($pdf_data, $file_ref . $file_name, FileSystemInterface::EXISTS_REPLACE);
					$pdf_file_id = $nda_pdf->id();
				}
				else {
					\Drupal::logger('request-nda-error')->error("Error in file creation");
					//throw new AccessDeniedHttpException("Error while creating image file.  $file_ref either not writable or not exist.");
				}
				$media_image = Media::create([
					'bundle' => 'document',
					'name' => $user_org.'_'.$supplier_name.'_'.time(),
					'field_media_document' => [
						'target_id' => $pdf_file_id,
					]]);
				$media_image->save();
				$pdf_media_id = $media_image->id();
			}
			
			$supplier_parent_details = end(\Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['uuid' => $parent_paragraph]));
			$supplier_parent_details->set('field_request_nda', 1);
			$supplier_parent_details->set('field_nda_document', $pdf_media_id);

			if($request_type === "attachment") {
				$supplier_parent_details->set("field_nda_as_attachment", 1);
			}
			else{
				$envelopeDefinition = new \DocuSign\eSign\Model\EnvelopeDefinition([
					'email_subject' => "Please sign this NDA document",
					'documents' => [$document], # The order in the docs array determines the order in the envelope
					# The Recipients object wants arrays for each recipient type
					'recipients' => new \DocuSign\eSign\Model\Recipients(['signers' => [$signer, $signerPurchaser]]),  //, 'carbon_copies' => [$cc1]
					'status' => "sent" # requests that the envelope be created and sent.
				]);
				$config = new \DocuSign\eSign\Configuration();
				$config->setHost($basePath);
				$config->addDefaultHeader("Authorization", "Bearer " . $accessToken);
				$apiClient = new \DocuSign\eSign\Client\ApiClient($config);
				$envelopeApi = new \DocuSign\eSign\Api\EnvelopesApi($apiClient);
				$results = $envelopeApi->createEnvelope($accountId, $envelopeDefinition);	
				$supplier_parent_details->set("field_nda_as_attachment", 0);
				$supplier_parent_details->set('field_docusign_envelope_id', $results['envelope_id']);
			}
			$supplier_parent_details->save();
		}

		return new JsonResponse($postParam);
	}

	public function finishedSupplierUpload($success, $results, $operations) {
		// The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One supplier processed.', '@count supplier processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    $this->messenger()->addMessage($message);
  }
	
	public function importSupplier() {
		try {
			$created_item_no = 0;
			$updated_item_no = 0;
			$count_total = 0;
			$message = 'Importing Supplier Data';
			$uri = 'modules/custom/capgrid_tweaks/data/test_list_1.xlsx';
			//require_once 'vendor/custom_excel_lib/vendor/autoload.php';
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
				$supplier_query = \Drupal::entityQuery('node')
					->condition('type', 'supplier_details')
					->condition('title', trim($supplier_name), '=')
					->condition('status', 1, '=');
				$supplier_details	= $supplier_query->execute();
				if(!empty($supplier_details)) {
					continue;
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

				$export_rating = $worksheet->getCell('BR' . $row)->getValue();
				$customer_strength = $worksheet->getCell('BS' . $row)->getValue();
				$financial_strength = $worksheet->getCell('BT' . $row)->getValue();
				$product_diverification_score = $worksheet->getCell('BU' . $row)->getValue();
				
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
			}
			// $context['message'] = $message;
    	// $context['results'] = $count_total;
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

	public function setSuppliers(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$selected_suppliers = array_keys($postParam['selected_supplier']);
		$all_supplier_details = [];
		foreach($selected_suppliers as $val) {
			$supplier_para = Paragraph::create([
				'type'=> explode("paragraph--",$postParam['paragraph_type'])[1],
				'field_priority'=> isset($postParam['supplier_priority'][$val]) ? $postParam['supplier_priority'][$val] : "",
				'field_supplier'=> ['target_id'=>$val],
				'field_supplier_match'=>$postParam['selected_supplier'][$val]['match'] !== "" 
											? (int)$postParam['selected_supplier'][$val]['match'] : "",
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

	public function uploadRequirementDocs(Request $request) {
		$rfq_id = $request->get('rfq_id');
		$file_ext = $request->request->get('extension');
		$pdf_data = file_get_contents($_FILES['files']['tmp_name'][0]);
		$file_name = 'rfq_document_'. time() . $_FILES['files']['name'][0] . "." . $file_ext;
		$nda_pdf = file_save_data($pdf_data, 'public://nda-pdf/' . $file_name, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);
		$pdf_file_id = $nda_pdf->id();
		$node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid'=>$rfq_id]);
		if(!empty($node)) {
			$node = reset($node);
		}
		$media_image = Media::create([
			'bundle' => 'document',
			'name' => "RFQ Documents ".time(),
			'field_media_document' => [
				'target_id' => $pdf_file_id,
			]]);
		$media_image->save();
		$pdf_media_id = $media_image->id();
		$node->get('field_requirement_docs')->appendItem([
			'target_id' => $pdf_media_id,
		]);
		$node->save();
		$rfq_docs = $node->get('field_requirement_docs')->getValue();
		$media_urls = [];
		foreach($rfq_docs as $docs) {
			$media = Media::load($docs['target_id']);
			$media_file = \Drupal\file\Entity\File::load($media->get('field_media_document')->target_id);
			$media_urls[] = \Drupal::request()->getSchemeAndHttpHost() . "/sites/default/files" . explode("public:/", $media_file->getFileUri())[1];
		}
		return new JsonResponse(implode(",", $media_urls));
	}

	public function setSupplierCart(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$user = User::load(\Drupal::currentUser()->id());
		//echo \Drupal::currentUser()->id();exit;
		//print_r($user);exit;
		$user->set('field_cart_suppliers', array_keys($postParam));
		$user->save();
		$suppliers = array_keys($postParam);
		$result = ["total"=>count($suppliers)];
		foreach($suppliers as $supplier) {
			$supplier_details = \Drupal\node\Entity\Node::load($supplier);
			$result['supplier_details'][$supplier_details->id()] = $supplier_details->label();
		}
		return new JsonResponse($result);
	}

	public function getSupplierCart(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$user = User::load(\Drupal::currentUser()->id());
		$cart_suppliers = $user->get('field_cart_suppliers')->getValue();
		$result = ["total"=>count($cart_suppliers)];
		foreach($cart_suppliers as $supplier) {
			$supplier_details = \Drupal\node\Entity\Node::load($supplier['target_id']);
			$result['supplier_details'][$supplier_details->id()] = $supplier_details->label();
		}
		return new JsonResponse($result);
	}

	public function deleteSupplierCart(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$query = \Drupal::entityQuery('node')
		->condition('type', 'supplier_details', '=')
		->condition('title', $postParam["name"], '=');
		$supplier_id = $query->execute();
		$supplier_id = reset($supplier_id);
		$user = User::load(\Drupal::currentUser()->id());
		$cart_item = $user->get('field_cart_suppliers')->getValue();
		$result = ["total"=>count($cart_item) - 1];
		foreach($cart_item as $key=>$supplier) {
			if($supplier['target_id'] === $supplier_id){
				unset($cart_item[$key]);
			}
			else {
				$supplier_details = \Drupal\node\Entity\Node::load($supplier['target_id']);
				$result['supplier_details'][$supplier_details->id()] = $supplier_details->label();
			}
		}
		$user->set('field_cart_suppliers', array_values($cart_item));
		$user->save();
		return new JsonResponse($result);
	}

	public function saveCartItem(Request $request) {
		$user = User::load(\Drupal::currentUser()->id());
		$cart_item = $user->get('field_cart_suppliers')->getValue();
		$result_arr = [];
		foreach($cart_item as $item) {
			$part_category_para = Paragraph::create([
				'type'=>'supplier_shortlist',
				'field_supplier'=> $item['target_id']
			]);
			$part_category_para1 = $part_category_para->save();
			$material_grade_ids = [
				'target_id'=>$part_category_para->id(), 
				'target_revision_id'=>$part_category_para->getRevisionId()
			];
			$result_arr[] = $material_grade_ids;
		}
		$purchase_req = \Drupal\node\Entity\Node::create([
				"title"=>strtoupper($user->getUsername()) . "_" . date('dmY') . "_" . date("His"),
				"type"=>"purchaser_requirements",
				"status"=>1,
				"field_shortlist_suppliers"=>$result_arr,
				"field_status"=>'draft',
				"field_create_from_cart"=>1	
			]
		); 
		$purchase_req->save();
		$uuid = $purchase_req->get('uuid')->value;
		$user->set("field_cart_suppliers", []);
		$user->save();
		return new JsonResponse([
			"status"=>1,
			"uuid"=>$uuid
		]);		

	}

	public function addNewSupplier(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$node_uuid = $postParam['requirement_id'];
		$id = "";
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$node_uuid]);
		if(empty($req_node)){
			return new JsonResponse([
				"status"=>"content not exist"
			]);
		}
		$req_node = reset($req_node);
		$purchase_supplier = \Drupal\node\Entity\Node::create([
			"title"=> $postParam['supplierData']['name'],
			"type"=>"purchaser_supplier",
			"status"=>1,
			"field_company_name"=>$postParam['supplierData']['name'],
			"field_contact_email"=>$postParam['supplierData']['email'],
			"field_contact_phone"=>$postParam['supplierData']['phone'],
			"field_production_facilities_city"=>$postParam['supplierData']['region']
		]); 
		$purchase_supplier->save();
		$part_category_para = Paragraph::create([
			'type'=>'supplier_shortlist',
			'field_supplier'=> $purchase_supplier->id()
		]);
		$part_category_para1 = $part_category_para->save();
		$material_grade_ids = [
			'target_id'=>$part_category_para->id(), 
			'target_revision_id'=>$part_category_para->getRevisionId()
		];
		$suppliers = $req_node->get("field_shortlist_suppliers")->getValue();
		array_push($suppliers, $material_grade_ids);
		$req_node->set("field_shortlist_suppliers", $suppliers);
		$req_node->save();
		return new JsonResponse([
			"status"=>"content successfully created with id: ".$purchase_supplier->id(),
			"supplier_details"=>[
				"type"=>"paragraph--supplier_shortlist", 
				"id"=>$part_category_para->uuid(),
				"meta"=>[
					"target_revision_id"=>$part_category_para->getRevisionId()
				]
			]
		]);
	}

	public function addRFQDetails(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$postParam = $postParam['post_data'];
		$generic_info = $postParam['generic_info'];
		$rfq_data = $postParam['rfq_data'];
		$node_uuid = $generic_info['rfq_id'];
		$save_type = $request->query->get("type");
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$node_uuid]);
		if(empty($req_node)) {
			return new JsonResponse([
				"status"=>"content not exist"
			]);
		}
		else {
			$req_node = reset($req_node);
			$req_node->set("field_rfq_details", json_encode(["rfq_data"=>$rfq_data]));
			if($generic_info['due_date'] !== ""){
				$jsDateTS = strtotime( $generic_info['due_date']);
				$req_node->set('field_rfq_due_date', date('Y-m-d', $jsDateTS ));
			}
			$req_node->set('field_rfq_initation_date', date('Y-m-d'));
			$req_node->set('field_demand_name', $generic_info['demand_name']);
		}
		if($save_type === "update") {
			$all_short_list_suppliers = $req_node->get("field_shortlist_suppliers")->getValue();
			foreach($all_short_list_suppliers as $supplier) {
				$para_data = Paragraph::load($supplier['target_id']);
				if($para_data->get("field_shortlist_rfq")->value == "1"){
					$para_data->set('field_rfq_details', json_encode(["rfq_data"=>$rfq_data]));
					$para_data->set("field_invite_rfq", 1);
					$para_data->save();
					$supplier_details = Node::load($para_data->get('field_supplier')->target_id); 
					$supplier_email = 'sudipta.123045@gmail.com';//$supplier_details->get("field_contact_email")->getValue()[0]['value'];
					$account = reset(\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' =>trim($supplier_email)]));
					$digits = 6;
					$random_password = rand(pow(10, $digits-1), pow(10, $digits)-1);
					$credentials = "<p>Login credentials are as follows: <br/> Username: ".$supplier_email."<br/>Password: ".$random_password."</p>";
					if(!empty($account)) {
						if($account->get('access')->value !== "0") {
							$credentials = "";
						}
					}
					else {
						$new_supplier_user = User::create([
							'name'=>explode("@",$supplier_email)[0],
							'pass'=>$random_password,
							'mail'=>$supplier_email,
							'field_supplier_profile'=>$supplier_details->id(),
						]);
						$new_supplier_user->save();
					}
					$current_user = User::load(\Drupal::currentUser()->id());
					$user_org = $current_user->get("field_organization_name")->value;
					$mailManager = \Drupal::service('plugin.manager.mail');
					$module = 'capgrid_tweaks';
					$key = 'send_rfq_document';
					$to = $supplier_email;
					$params['message'] = 'Hi '.$supplier_details->get('field_company_name')->value.', <br/>
					<p>You are receiving this email because '.$user_org.' is shared a RFQ with you.
					<p>In order to access the RFQ and fill it, please login to the CapGrid portal.</p>
					'.$credentials.'
					<p>Steps to access and fill up RFQ:</p>
					
					<p>1. Login to the CapGrid portal using this link: https://capgridsolutions.com/login</p>
					<p>2. Go to the OEM Requirements section</p>
					
					<p>You will need to access the CapGrid portal to view the drawings / other details and carry out all the steps of the sourcing process. Please make sure to check the portal for further updates and notifications.</p>
					
					<p>Thanks<br/>
					CapGrid Team</p>';

					$params['subject'] = 'RFQ Proposal';
					$langcode = \Drupal::currentUser()->getPreferredLangcode();
					$send = true;
					$mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
				}
			}
			$req_node->set('field_status', 'rfp');

		}
		$req_node->save();
		return new JsonResponse($postParam);
	}

	public function getSupplierRFQDetails(Request $request) {
		$rfq_id = $request->get('rfq_id');
		$data = [];
		$user = User::load(\Drupal::currentUser()->id());
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$rfq_id]);
		if(empty($req_node)) {
			return new JsonResponse([
				"status"=>"content not exist"
			]);
		}
		else {
			$req_node = reset($req_node);
			$demand_name = $req_node->get("field_demand_name")->value;
			$rfq_due_date = $req_node->get("field_rfq_due_date")->value;
			$supplier_profile_id = $user->get('field_supplier_profile')->target_id;
			$para = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
				'parent_id'=>$req_node->id(),
				'type'=>"supplier_shortlist",
				'field_supplier'=>$supplier_profile_id
			]);
			if(empty($para)){
				return new JsonResponse([
					"status"=>"content not exist"
				]);
			}
			else {
				$para = end($para);
				$rfq_shared = $para->get('field_shared_rfq')->value;
				$rfq_details = $para->get('field_rfq_details')->value;
			}
			$data = [ "data"=> [
					'rfq_details'=>$rfq_details,
					'is_rfq_shared'=>$rfq_shared,
					'demand_name'=>$demand_name,
					'rfq_due_date'=>$rfq_due_date
				]
			];	
		}
		return new JsonResponse($data);
	}

	public function setSupplierRFQDetails(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$postParam = $postParam['post_data'];
		$generic_info = $postParam['generic_info'];
		$rfq_data = $postParam['rfq_data'];
		$node_uuid = $generic_info['rfq_id'];
		$save_type = $generic_info['save_type'];
		$data = [];
		$user = User::load(\Drupal::currentUser()->id());
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$rfq_id]);
		if(empty($req_node)) {
			return new JsonResponse([
				"status"=>"content not exist"
			]);
		}
		else {
			$req_node = reset($req_node);
			$supplier_profile_id = $user->get('field_supplier_profile')->target_id;
			$para = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
				'parent_id'=>$req_node->id(),
				'type'=>"supplier_shortlist",
				'field_supplier'=>$supplier_profile_id
			]);
			if(empty($para)){
				return new JsonResponse([
					"status"=>"content not exist"
				]);
			}
			else {
				$check_para = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
					'parent_id'=>$req_node->id(),
					'type'=>"supplier_shortlist",
					'field_supplier'=>$supplier_profile_id,
					'field_shared_rfq'=>1
				]);
				if(empty($check_para)) {
					$req_node->set('field_first_bid_received_date', date('Y-m-d'));
				}
				$para = end($para);
				$para->set('field_rfq_details', json_encode(["rfq_data"=>$rfq_data]));
				if($save_type === "share_rfq") {
					$para->set('field_shared_rfq', 1);
				}
				$para->save();
				$req_node->save();
			}
			$data = [ "data"=> [
					"status"=>'success'
				]
			];	
		}
		return new JsonResponse($data);
	}

	public function getMessageContent(Request $request) {
		$rfq_id = $request->get('rfq_id');
		$user_id = \Drupal::currentUser()->id();
		$supplier_id = $request->query->get('sid');
		$supplier_id = $supplier_id ? $supplier_id : "";
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$rfq_id]);
		$req_node = reset($req_node);
		$para = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
			'parent_id'=>$req_node->id(),
			'type'=>"supplier_shortlist",
			'field_supplier'=>$supplier_id
		]);
		$para = end($para);
		$question = $para->get('field_question_response')->getValue();
		$response = [];
		if(empty($question )) {
			return new JsonResponse(["res"=>[]]);
		}
		else{
			foreach($question as $q) {
				$q_para = reset(\Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties(['id'=>$q['target_id']]));
				$type = "left";
				if($q_para->get('field_message_owner')->target_id == $user_id) {
					$type = "right";
				}
				$response[] = [
					"type"=>$type,
					"message"=>$q_para->get('field_message_body')->value,
					'time'=>date('h:ia M d Y', $q_para->getCreatedTime())
				];
			}
		}
		$data  = [
			"res"=>$response
		];
		return new JsonResponse($data);
	}

	public function setMessageContent(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$rfq_id = $request->get('rfq_id');
		$user_id = \Drupal::currentUser()->id();
		$supplier_id = $postParam['sid'];
		$supplier_id = $supplier_id ? $supplier_id : "";
		$message_content = $postParam['message_content'];
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$rfq_id]);
		$req_node = reset($req_node);
		$para = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
			'parent_id'=>$req_node->id(),
			'type'=>"supplier_shortlist",
			'field_supplier'=>$supplier_id
		]);
		$para = end($para);
		$question = $para->get('field_question_response')->getValue();
		$part_category_para = Paragraph::create([
			'type'=>'question_response',
			'field_message_body'=>strip_tags($message_content),
			'field_message_owner'=>$user_id,
		]);
		$part_category_para1 = $part_category_para->save();
		$material_grade_ids = [
			'target_id'=>$part_category_para->id(), 
			'target_revision_id'=>$part_category_para->getRevisionId()
		];
		array_push($question, $material_grade_ids);
		$para->set('field_question_response', $question);
		$para->save();
		//$req_node->save();
		$data = ["status"=>"message delivered successfully"];
		return new JsonResponse($data);
	}

	public function shortlistRFQ(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$rfq_id = $postParam['rfq_id'];
		$user_id = \Drupal::currentUser()->id();
		$supplier_list = array_values($postParam['supplier']);
		$req_node = \Drupal::entityTypeManager()->getStorage("node")->loadByProperties(['uuid'=>$rfq_id]);
		$req_node = reset($req_node);
		foreach($supplier_list as $supplier) {
			$para_details = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
				'parent_id'=>$req_node->id(),
				'type'=>"supplier_shortlist",
				'uuid'=>$supplier			
			]);
			$para_details = reset($para_details);
			$para_details->set("field_shortlist_rfq", 1);
			$part_category_para1 = $para_details->save();
		}
		$req_node->set("field_status", "rfq");
		$req_node->set("field_rfq_prep_start_date", date('Y-m-d'));
		$req_node->save();
		$data = ["status"=>"Success"];
		return new JsonResponse($data);
	}

	public function getMatchingSuppliers(Request $request){
		$postParam = json_decode($request->getContent(), TRUE);
		$supplier_name = trim($postParam['supplier_name']);
		$supplier_name = $supplier_name;
		$supplier_list = file_get_contents('modules/custom/capgrid_tweaks/data/results.json');
		$suppliers = json_decode($supplier_list, true);
		$matching_supplier = $suppliers[$supplier_name];
		$result = [];
		foreach($matching_supplier as $supplier){
			$res = reset(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title'=>$supplier]));
			if(empty($res)){
				continue;
			}
			$clients = $parts = [];
			if($res->get('field_business_segment')->target_id) {
				$business_segment = Paragraph::load($res->get('field_business_segment')->target_id);
				$clients = array_map(function($arr){return $arr['value'];}, $business_segment->get('field_key_clients_segment')->getValue());
			}
			if($res->get('field_part_category_service')->target_id){
				$part_category = Paragraph::load($res->get('field_part_category_service')->target_id);
				$parts = array_map(function($arr){return $arr['value'];},$part_category->get('field_list_of_parts')->getValue());
			}
			$result[] = [
				"title"=>[$supplier],
				"client_names"=>$clients,
				"part_category_details"=>$parts, 
				"uuid"=>[$res->uuid()], 
				"nid"=>[$res->id()],
				"customer_strength"=>$res->get('field_customer_strength')->value,
				"export_rating"=>$res->get('field_export_rating')->value,
				"financial_strength"=>$res->get('field_financial_strength')->value,
				"product_div_score"=>$res->get('field_product_diversification_sc')->value
			];
		}
		return new JsonResponse($result);
	}

	public function getRFQDocs(Request $request) {
		$postParam = json_decode($request->getContent(), TRUE);
		$media_ids = explode(",", $postParam['post_data']);
		$rfq_docs = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['mid'=>$media_ids]);
		$file_urls = [];
		foreach($rfq_docs as $docs) {
			$file = File::load($docs->get('field_media_document')->target_id);
			$file_urls[] = explode("public://", $file->getFileUri())[1];
		}
		return new JsonResponse($file_urls);
	}

	public function getSupplierQuestions(Request $request) {
		$rfq_id = $request->get('rfq_id');
		$data = [];
		if($rfq_id !== "") {
			$node = reset(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid'=>$rfq_id]));
			$short_listed = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties([
				'type'=>'supplier_shortlist',
				'parent_id'=>$node->id(),
				'field_invite_rfq'=>1
			]);
			$supplier_details = [];
			$question_arr = [];
			foreach($short_listed as $supplier_para) {
				$supplier = Node::load($supplier_para->get('field_supplier')->target_id);
				$supplier_details[] = [
					"id"=>$supplier->id(), 
					"name"=>$supplier->get("field_company_name")->value
				];
				$question = \Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties([
					'type'=>'question_response',
					'parent_id'=>$supplier_para->id(),
				]);
				$response = [];
				foreach($question as $q) {
					$type = "left";
					if($q->get('field_message_owner')->target_id == \Drupal::currentUser()->id()) {
						$type = "right";
					}
					$response[] = [
						"type"=>$type,
						"message"=>$q->get('field_message_body')->value,
						'time'=>date('h:ia M d Y', $q->getCreatedTime())
					];
				}
				$question_arr[$supplier->id()] = $response;
			}
			$data = ['suppliers'=>$supplier_details, 'questions'=>$question_arr];
		}
		return new JsonResponse($data);
	}

	public function setUserLogout(Request $request) {
		$user = User::load(\Drupal::currentUser()->id());
		user_logout();
		$data = ["message"=>"User Successfully Logout"];
		return new JsonResponse($data);
	}

	public function setSignedNDA(Request $request) {
		$supplier_id = $request->get('supplier_id');
		$rfq_id = $request->get('rfq_id');

		$file_ext = $request->request->get('extension');
		$pdf_data = file_get_contents($_FILES['files']['tmp_name'][0]);
		$file_name = 'signed_nda_'. time() . $_FILES['files']['name'][0] . "." . $file_ext;
		$nda_pdf = file_save_data($pdf_data, 'public://nda-pdf/' . $file_name, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);
		$pdf_file_id = $nda_pdf->id();
		$node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid'=>$rfq_id]);
		if(!empty($node)) {
			$node = reset($node);
		}
		$media_image = Media::create([
			'bundle' => 'document',
			'name' => "Signed NDA".time(),
			'field_media_document' => [
				'target_id' => $pdf_file_id,
			]]);
		$media_image->save();
		$pdf_media_id = $media_image->id();
		$supplier_para = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties([
			'type'=>'supplier_shortlist',
			'parent_id'=>$node->id(),
			'field_nda_as_attachment'=>1,
			'field_supplier'=>$supplier_id
		]);
		$supplier_para = end($supplier_para);
		$supplier_para->set('field_signed_nda_document', $pdf_media_id);
		$supplier_para->save();
		return new JsonResponse(["NDA Document Successfully Uploaded"]);
	}

	public function resetUserPass(Request $request) {
		$email = $request->get("email");
		$data = [];
		if(empty($email)) {
			$data = ['status'=>'Failed', 'message'=>'Please enter a valid email'];
		}
		else {
			$query = \Drupal::entityQuery('user')
				->condition('mail', $email);
			$user_id = $query->execute();
			if(empty($user_id)) {
				$data = ['status'=>'Failed', 'message'=>'Please enter a valid email'];
			}
			else{
				$user = \Drupal\user\Entity\User::load(reset($user_id));
				$user_email = $user->get('mail')->value;
				$digits = 6;
				$random_password = rand(pow(10, $digits-1), pow(10, $digits)-1);
				$user->set('pass', $random_password);
				$user->save();
				$mailManager = \Drupal::service('plugin.manager.mail');
				$module = 'capgrid_tweaks';
				$key = 'send_reset_password';
				$to = $user_email;
				$credentials = "<p>Login credentials are as follows: 
				<br/> Username: <b>".$supplier_email."</b><br/>Password: <b>".$random_password."</b></p>";
				$params['message'] = 'Hi '.$user->get('name')->value.', <br/>
				<p>You have successfully reset your password. Please use the below given credentials to login to your account</p>				<p>Steps to access and fill up RFQ:</p>
				<br/>'
				
				.$credentials.
				
				'<p>Login to the CapGrid portal using this link: https://capgridsolutions.com/login</p>
				
				<p>Thanks,<br/>
				CapGrid Team</p>';

				$params['subject'] = 'Reset Password';
				$langcode = \Drupal::currentUser()->getPreferredLangcode();
				$send = true;
				$mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
				$data = ['status'=>'Success', 'message'=>'Please check your email for further instruction.'];
			}
		}
		return new JsonResponse($data);
	}

}