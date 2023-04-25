<?php

namespace Drupal\capgrid_tweaks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Elasticsearch\ClientBuilder;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Class for Custom Elastic Operation.
 */
class SearchElastic extends ControllerBase {

	/**
	 * AccountInterface $currentUser
	 */
	public $currentUser;

	public $client;

	const START_AT = 'startAt';
	
	const MAX_RESULT = 'maxResult';

	const TOTAL = 'total';

	const RESULT = 'results';

	const STATUS = 'status';

	const MESSAGE = 'message';

	const DEFAULT_MESSAGE = 'Something went wrong, Try again. ';

	const SUPPLIER_NAME = 'supplier_name';

	const CUSTOMERS = 'customers';

	const PART = 'part';

	const PROCESS = 'process';

	const PRODUCT_SEGMENT = 'product_segment';

	const ANNUAL_TURNOVER = 'annual_turnover';

	const REGION = 'state';

	const COUNTRY = 'country';

	public $searchParams = [];
	
	/**
	 * AccountInterface $user
	 */
	public function __construct(AccountInterface $user){
		$this->currentUser = $user;
		$this->client = ClientBuilder::create()->setHosts(['http://localhost'])->setRetries(0)->build();
		$this->searchParams = ['index' => 'elasticsearch_index_capgrid_supplier_index'];
	}

	/**
	 * ContainerInterface $user
	 * 
	 * return AccountInterface $currentUser
	 */
	public static function create(ContainerInterface $container) {
		return new static(
			$container->get('current_user')
		);
	}

	/**
	 * @param Request $request
	 * 
	 * @return array $data
	 */
	protected function getRequestParams(Request $request){
		$data = [];
		try{
			$startAt = $request->query->get(SearchElastic::START_AT);
			$startAt = ($startAt) ? $startAt : 0;
			$maxResults = $request->query->get(SearchElastic::MAX_RESULT);
			$maxResults = ($maxResults) ? $maxResults : 10000;
			$data = [SearchElastic::START_AT=>$startAt,  SearchElastic::MAX_RESULT=>$maxResults];
		}
		catch(\Exception $e){
			$data = [SearchElastic::START_AT=>0,  SearchElastic::MAX_RESULT=>0];
		}
		return $data;
	}

	/**
	 * @param Request $request
	 * 
	 * @return JsonResponse $result
	 */
	public function getAllSuppliers(Request $request) {
		$data = [];
		try {  
			$params = $this->getRequestParams($request);
			$supplier_name = !empty($request->query->get(SearchElastic::SUPPLIER_NAME)) 
				? trim($request->query->get(SearchElastic::SUPPLIER_NAME)) 
				: "";
			$customers = !empty($request->query->get(SearchElastic::CUSTOMERS)) 
				? trim($request->query->get(SearchElastic::CUSTOMERS)) 
				: "";
			$part = !empty($request->query->get(SearchElastic::PART)) 
				? trim($request->query->get(SearchElastic::PART)) 
				: "";
			$process = !empty($request->query->get(SearchElastic::PROCESS)) 
				? trim($request->query->get(SearchElastic::PROCESS)) 
				: "";
			$product_segment = !empty($request->query->get(SearchElastic::PRODUCT_SEGMENT)) 
				? trim($request->query->get(SearchElastic::PRODUCT_SEGMENT)) 
				: "";
			$annual_turnover = !empty($request->query->get(SearchElastic::ANNUAL_TURNOVER)) 
				? trim($request->query->get(SearchElastic::ANNUAL_TURNOVER)) 
				: "";
			$region = !empty($request->query->get(SearchElastic::REGION)) 
				? trim($request->query->get(SearchElastic::REGION)) 
				: "";
			$country = !empty($request->query->get(SearchElastic::COUNTRY)) 
				? trim($request->query->get(SearchElastic::COUNTRY)) 
				: "";
			$export_countries = !empty($request->query->get('export_countries'))
				? trim($request->query->get('export_countries'))
				: "";
			$product_brand = !empty($request->query->get('product_brand'))
				? trim($request->query->get('product_brand'))
				: "";
			$debug = !empty($request->query->get('debug')) 
				? trim($request->query->get('debug')) 
				: "";
			$body_json = '{"query":{"match_all":{}},
				"size": 10000,
				"sort" : [
					{ "customer_strength" : {"order" : "asc"}},
					"_score",
					{ "financial_strength" : {"order" : "asc"}},
					{ "export_rating" : {"order" : "asc"}},
					{ "product_div_score" : {"order" : "asc"}}
				]
			}';
			$body_count_json = '{"query":{"match_all":{}}}';
			$query_arr = [];
			if(!empty($supplier_name)) {
				$supplier_name = str_replace('"','',$supplier_name);
				$supplier_name = '[{
						"bool": {
							"must": {
								"simple_query_string": {
									"query": "('.$supplier_name.')",
									"fields": [
										"company_name"
									],
									"auto_generate_synonyms_phrase_query": false,
									"fuzzy_max_expansions": 0,
									"default_operator": "and",
									"minimum_should_match": "100%"
								}
							}
						}
				}]';
				array_push($query_arr, $supplier_name);
			}
			if(!empty($customers)) {
				$customers = strtolower($customers);
				$customers = str_replace('"','',$customers);
				$customers = '[{
						"bool": {
							"must": {
								"simple_query_string": {
									"query": "('.$customers.')",
									"fields": [
										"client_names"
									],
									"auto_generate_synonyms_phrase_query": false,
									"fuzzy_max_expansions": 0,
									"default_operator": "and",
									"minimum_should_match": "100%"
								}
							}
						}
				}]';
				array_push($query_arr, $customers);
			}
			if(!empty($part)) {
				$part = strtolower($part);
				$part = str_replace('"','',$part);
				$part = '[
					{
						"bool": {
							"must": {
								"simple_query_string": {
									"query": "('.$part.')",
									"fields": [
										"field_list_of_parts"
									],
									"default_operator": "and",
									"auto_generate_synonyms_phrase_query": false,
									"fuzzy_max_expansions": 0,
									"minimum_should_match": "100%"
								}
							}
						}
				}]';
				array_push($query_arr, $part);
			}
			if(!empty($process)){
				$process_arr_str = $this->getFormmatedParamValue($process);
				$process = '{
					"bool": {
						"should": [
							{
								"terms": {
									"production_capability": [
										'.strtolower($process_arr_str).'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $process);
			}
			if(!empty($product_segment)){
				$product_segment_str = $this->getFormmatedParamValue($product_segment);
				$product_segment = '{
					"bool": {
						"should": [
							{
								"terms": {
									"vehicles_type": [
										'.strtolower($product_segment_str).'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $product_segment);
			}
			if(!empty($annual_turnover)){
				$annual_turnover_str = $this->getFormmatedParamValue($annual_turnover);
				$annual_turnover = '{
					"bool": {
						"should": [
							{
								"terms": {
									"supplier_annual_turnover": [
										'.$annual_turnover_str.'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $annual_turnover);
			}
			if(!empty($region)){
				$region_str = $this->getFormmatedParamValue($region);
				$region = '{
					"bool": {
						"should": [
							{
								"terms": {
									"supplier_production_state": [
										'.$region_str.'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $region);
			}
			if(!empty($country)){
				$country_str = $this->getFormmatedParamValue($country);
				$country = '{
					"bool": {
						"should": [
							{
								"terms": {
									"supplier_production_country": [
										'.$country_str.'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $country);
			}
			if(!empty($export_countries)){
				$export_countries_arr = $this->getFormmatedParamValue($export_countries);
				$export_countries = '{
					"bool": {
						"should": [
							{
								"terms": {
									"export_countries": [
										'.$export_countries_arr.'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $export_countries);
			}
			if(!empty($product_brand)){
				$paramArr = explode(',', $product_brand);
				$modParamArr = array_map(function($a){return '" '.$a.'"';}, $paramArr);
				$product_brand_arr = implode(",", $modParamArr);
				$product_brand = '{
					"bool": {
						"should": [
							{
								"terms": {
									"product_brand": [
										'.$product_brand_arr.'
									]
								}
							}
						]
					}
				}';
				array_push($query_arr, $product_brand);
			}
			if(count($query_arr) > 0){
				$body_json = '{
					"query": {
						"bool": {
							"must": [
								{
									"bool": {
										"must": [
											'.implode(",", $query_arr).'
										]
									}
								}
							]
						}
					},
					"size": 10000,
					"sort" : [
						{ "customer_strength" : {"order" : "asc"}},
						{ "financial_strength" : {"order" : "asc"}},
						{ "export_rating" : {"order" : "asc"}},
						{ "product_div_score" : {"order" : "asc"}},
						"_score"
					]}';
				$body_count_json = '{
					"query": {
						"bool": {
							"must": [
								{
									"bool": {
										"must": [
											'.implode(",", $query_arr).'
										]
									}
								}
							]
						}
					}
				}';
			}
			if(!empty($debug)) {
				$data = ["array_res"=>$query_arr, "json_body"=> str_replace("\r\n\t","",$body_json)];
				return new JsonResponse([$data, $response]);
			}
			$body_json = str_replace("\r\n\t","",$body_json);
			$this->searchParams['body'] = $body_json;
			$response = $this->client->search($this->searchParams);
			$this->searchParams['body'] = $body_count_json;
			$total_doc_count = $this->client->count($this->searchParams);
			$supplier_res = array_slice($response['hits']['hits'], $params[SearchElastic::START_AT], $params[SearchElastic::MAX_RESULT]);
			$data = [
				SearchElastic::TOTAL=> $total_doc_count['count'],//count($response['hits']['hits']),
				SearchElastic::START_AT=>$params[SearchElastic::START_AT], 
				SearchElastic::MAX_RESULT=>$params[SearchElastic::MAX_RESULT],
				SearchElastic::RESULT=>$supplier_res, 
			];
		}
		catch(\Exception $e){
			$data = [SearchElastic::STATUS=>500, SearchElastic::MESSAGE=>SearchElastic::DEFAULT_MESSAGE.$e->getMessage()];
		}
		return new JsonResponse($data);
	}

	/**
	 * @param Request $request
	 * 
	 * @return JsonResponse $result
	 */
	public function getSuggesitions(Request $request) {
		$data = [];
		try {  
			$params = $this->getRequestParams($request);
			$keyword = $request->query->get('keyword');
			if(empty($keyword)){
				$supplier_res = [];
				$data = [
					SearchElastic::TOTAL=>count($supplier_res), 
					SearchElastic::START_AT=>$params[SearchElastic::START_AT], 
					SearchElastic::MAX_RESULT=>$params[SearchElastic::MAX_RESULT],
					SearchElastic::RESULT=>$supplier_res, 
				];
			}
			else {
				$suggesition_type = $request->get("suggesition_type");
				$field_name = [];
				switch($suggesition_type) {
					case 'supplier_name':
						$field_name = "company_name";
						break;
					case 'customers':
						$field_name = "client_names";
						break;
					case 'part_description':
						$field_name = "field_list_of_parts";
						break;
					case 'process':
						$field_name = "production_capability";
						break;
					default:
						$field_name = "company_name";
				}
				
				$body_json = '{"query":{"bool":{"must":[{"bool":{"must":{"bool":{"should":[{"multi_match":{"query":"'.$keyword.'","fields":["'.$field_name.'"],"type":"cross_fields","operator":"and"}},{"multi_match":{"query":"'.$keyword.'","fields":["'.$field_name.'"],"type":"phrase","operator":"and"}}],"minimum_should_match":"1"}}}}]}},"size":10}';
				$this->searchParams['body'] = $body_json;
				$response = $this->client->search($this->searchParams);
				$suggesition_res = [];
				foreach($response['hits']['hits'] as $res) {
					$suggesition_res = array_merge($suggesition_res, 
					 	!empty($res["_source"][$field_name]) ? $res["_source"][$field_name] : []);
					$suggesition_res = array_unique($suggesition_res);
				}
				$supplier_res = array_slice($suggesition_res, $params[SearchElastic::START_AT], $params[SearchElastic::MAX_RESULT]);
				$data = [
					SearchElastic::TOTAL=>count($supplier_res), 
					SearchElastic::START_AT=>$params[SearchElastic::START_AT], 
					SearchElastic::MAX_RESULT=>$params[SearchElastic::MAX_RESULT],
					SearchElastic::RESULT=>$supplier_res,
				];
			}
		}
		catch(\Exception $e){
			$data = [SearchElastic::STATUS=>500, SearchElastic::MESSAGE=>SearchElastic::DEFAULT_MESSAGE.$e->getMessage()];
		}
		return new JsonResponse($data);
	}

	/**
	 * @param Request $request
	 * 
	 * @return JsonResponse $result
	 */
	public function getFilterOptions(Request $request) {
		$data = [];
		try {  
			$params = $this->getRequestParams($request);
			$suggesition_type = $request->get("option_type");
			$field_name = [];
			switch($suggesition_type) {
				case 'process':
					$field_name = "production_capability";
					break;
				case 'product_segment':
					$field_name = "vehicles_type";
					break;
				case 'annual_turnover':
					$field_name = "supplier_annual_turnover";
					break;
				case 'region':
					$field_name = "supplier_production_state";
					break;
				case 'export_countries':
					$field_name = 'export_countries';
					break;
				case 'product_brand':
					$field_name = 'product_brand';
					break;
				default:
					$field_name = "";
			}
			if($field_name === "supplier_production_state")	{
				$body_json = '{"query":{"match_all":{}},"size":0,"aggs":{"supplier_production_country":{"terms":{"field":"supplier_production_country","size":1000,"order":{"_count":"desc"}}}}}';
				$this->searchParams['body'] = $body_json;
				$response = $this->client->search($this->searchParams);
				$country_list = $response['aggregations']["supplier_production_country"]['buckets'];
				foreach($country_list as $country){
					$name = $country['key'];
					$count = $country['doc_count'];
					$body_json = '{"query":{"bool":{"must":[{"bool":{"must":[{"bool":{"should":[{"terms":{"supplier_production_country":["'.$name.'"]}}]}}]}}]}},"size":0,"aggs":{"supplier_production_state":{"terms":{"field":"supplier_production_state","size":1000,"order":{"_count":"desc"}}}}}';
					$this->searchParams['body'] = $body_json;
					$response = $this->client->search($this->searchParams);
					$state_list = $response['aggregations']["supplier_production_state"]['buckets'];
					$suggesition_res[] = ['key'=>$name, 'doc_count'=>$count, 'is_country'=>true, 'states'=>$state_list];
				}
			}
			else{
				$body_json = '{"query":{"match_all":{}},"size":0,"aggs":{"'.$field_name.'":{"terms":{"field":"'.$field_name.'","size":1000,"order":{"_count":"desc"}}}}}';
				$this->searchParams['body'] = $body_json;
				$response = $this->client->search($this->searchParams);
				$suggesition_res = $response['aggregations'][$field_name]['buckets'];
			}
			$supplier_res = array_slice($suggesition_res, $params[SearchElastic::START_AT], $params[SearchElastic::MAX_RESULT]);
			$data = [
				SearchElastic::TOTAL=>count($supplier_res), 
				SearchElastic::START_AT=>$params[SearchElastic::START_AT], 
				SearchElastic::MAX_RESULT=>$params[SearchElastic::MAX_RESULT],
				SearchElastic::RESULT=>$supplier_res,
			];
		}
		catch(\Exception $e){
			$data = [SearchElastic::STATUS=>500, SearchElastic::MESSAGE=>SearchElastic::DEFAULT_MESSAGE.$e->getMessage()];
		}
		return new JsonResponse($data);
	}

	protected function getFormmatedParamValue($paramValue) {
		$paramArr = explode(',', $paramValue);
		$modParamArr = array_map(function($a){return '"'.$a.'"';}, $paramArr);
		$paramArrStr = implode(",", $modParamArr);
		return $paramArrStr;
	}

	// public function updateSupplierScore() {
	// 	try{
	// 		$created_item_no = 0;
	// 		$updated_item_no = 0;
	// 		$count_total = 0;
	// 		$uri = 'modules/custom/capgrid_tweaks/data/performance_score_prediction.xlsx';
	// 		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
	// 		$abs_file_path = \Drupal::service('file_system')->realpath($uri);
	// 		$reader->setReadDataOnly(TRUE);
	// 		$spreadsheet = $reader->load($abs_file_path);
	// 		$worksheet = $spreadsheet->getActiveSheet();
	// 		$highestRow = $worksheet->getHighestRow(); // e.g. 10
	// 		$highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
	// 		$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);  
	// 		for ($row = 2; $row <= $highestRow; ++$row) {
	// 			$count_total++;
	// 			$supplier_name = $worksheet->getCell('C' . $row)->getValue();
	// 			$supplier_query = \Drupal::entityQuery('node')
	// 				->condition('type', 'supplier_details')
	// 				->condition('title', trim($supplier_name), '=')
	// 				->condition('status', 1, '=');
	// 			$supplier_details	= $supplier_query->execute();
	// 			if(!empty($supplier_details)) {
	// 				continue;
	// 			}
	// 		}
	// 	}
	// 	catch(\Exception $e){
	// 		\Drupal::logger('upload_supplier_data')->error($e->getMessage());
	// 	}
	// 	return New Response("Supplier Details Uploaded Successfully. 
	// 	Updated: ".$updated_item_no."Current Supplier: ".$supplier_name."Current Total: ".$count_total);
	// }
}
