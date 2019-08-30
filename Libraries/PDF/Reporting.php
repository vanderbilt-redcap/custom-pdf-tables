<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 6/22/2016
 * Time: 1:05 PM
 */

namespace Plugin;

global $Core;
$Core->Libraries(array("Record","Core","Project", "RecordSet", "MetadataCollection", "Metadata", "PPEType"),false);
$Core->Libraries(array("FPDF/PDF_MemImage","BarChart","PieChart","SurveyTemplate","Hospital","PPEType"),false);


class Reporting {
	const RECEIVED_THIS_QUARTER = "order";
	const INVENTORY_COUNT = "inventory";
	const DEMAND_RATE = "depletion";
	const PER_100_BEDS = "per_100_beds";
	const UOM = "uom";
	const DAYS_SUPPLY = "days_supply";
	const OLD_INVENTORY = "previous_inventory";
	const DAYS = "days";

	private static $allHospitalLatestQuarterly = [];
	private static $quarterlyTemplates;
	public static $pageCount = 1;

	public static $currentHeader = "";

	/**
	 * @param $surveyRecord \Plugin\Record
	 * @param $eventYear string
	 * @return array
	 */
	public static function pullInventoryDataBySurvey($surveyRecord,$eventYear) {
		$metadataColl = $surveyRecord->getProjectObject()->getMetadata();

		$templateProject = new Project(TEMPLATE_PROJECT_ID);
		$hospital = new Hospital($surveyRecord->getDetails(SURVEY_HOSPITAL_FIELD));

		$allSurveyRecords = $hospital->getAllSurveys();
		foreach($allSurveyRecords->getRecords() as $currentRecord) {
			if($currentRecord->getDetails(SURVEY_TEMPLATE_ID) == ANNUAL_SURVEY_TEMPLATE && $currentRecord->getDetails(SURVEY_EVENT_YEAR_FIELD) == $eventYear) {
				$lastAnnualRecord = $currentRecord;
				break;
			}
		}

		if($lastAnnualRecord == "") {
			$staffedBedsAnnual = 1;
		}
		else {
			$staffedBedsAnnual = $lastAnnualRecord->getDetails(ANNUAL_STAFFED_BEDS);
		}

		$template = new SurveyTemplate(Record::createRecordFromId($templateProject, $surveyRecord->getDetails(SURVEY_TEMPLATE_ID)));
		list($priorEvent, $priorYear) = $template->getPriorEventAndYear($surveyRecord->getDetails(SURVEY_EVENT_NAME_FIELD),$surveyRecord->getDetails(SURVEY_EVENT_YEAR_FIELD));
		$priorRecord = $allSurveyRecords->filterRecords([SURVEY_EVENT_YEAR_FIELD => $priorYear, SURVEY_EVENT_NAME_FIELD => $priorEvent,SURVEY_TEMPLATE_ID => $surveyRecord->getDetails(SURVEY_TEMPLATE_ID)])->getRecords()[0];

		$inventoryData = [];
		$component = "";

		/** @var \Plugin\Metadata $metadata */
		foreach($metadataColl as $metadata) {
			$miscField = $metadata->getMisc();
			if (preg_match('/#RECEIVEDINVENTORY/', $miscField)){
				$fieldName = $metadata->getFieldName();
				preg_match("/#TITLE\\(([A-Za-z0-9\\_\\s]+)\\)/", $miscField, $matches);
				if($matches[1] != "") {
					$component = str_replace("_", " ", $matches[1]);
				}
				preg_match("/#TYPE\\(([A-Za-z0-9\\_\\s]+)\\)/", $miscField, $matches);
				$type = $matches[1];
				$subType = $metadata->getElementLabel();
				$rawInv = $surveyRecord->getDetails($fieldName);
				if($rawInv == ""){
					continue;
				}
				$invArray = PPEType::buildInventoryFromString($rawInv);
				if($priorRecord) {
					$rawPrior = $priorRecord->getDetails($fieldName);
					$priorInvArray = PPEType::buildInventoryFromString($rawPrior);
				}

				foreach($invArray as $make => $makeInfo){
					foreach($makeInfo as $model => $modelInfo){
						$uom = "Each";
						if (isset($modelInfo['paired'])){
							if(is_array($modelInfo['paired'])) {
								$uom = "Singles";
							}
							else {
								$uom = (($modelInfo['paired'] == 2) ? "Pairs" : "Singles");
							}
							unset($modelInfo['paired']);
						}
						foreach($modelInfo as $size => $sizeInfo) {
							$inventory = $make." ".$model." ".$size;
							$received = $sizeInfo[self::RECEIVED_THIS_QUARTER];
							$count = $sizeInfo[self::INVENTORY_COUNT];
							$beginningCount = $priorInvArray[$make][$model][$size][self::INVENTORY_COUNT];
							$days = self::daysPerQuarter($surveyRecord);

							if($beginningCount == 0) {
								$demandRate = "";
								$daysSupply = "";
							}
							else {
								$demandRate = round((($beginningCount + $received) - $count) / $days,2);
								$daysSupply = round($count / $demandRate,1);
							}
							$totalPer100Beds = round(($count / $staffedBedsAnnual) * 100,2);

							$inventoryData[$component][$type][$subType][$inventory] = array(self::RECEIVED_THIS_QUARTER => $received, self::INVENTORY_COUNT => $count,self::UOM => $uom,
											self::DEMAND_RATE => $demandRate, self::PER_100_BEDS  => $totalPer100Beds, self::DAYS_SUPPLY => $daysSupply,
											self::OLD_INVENTORY => $beginningCount,self::DAYS => $days);
						}
					}
				}
			}

		}
		return $inventoryData;
	}

	/**
	 * @param $hospital \Plugin\Hospital
	 * @param $eventName string
	 * @param $eventYear string
	 * @return PDF_MemImage
	 */
	public static function generateReportPdf($hospital, $eventName, $eventYear) {
		global $inventoryTemplates,$inventoryFieldAppend;

		$surveyTemplateProject = new Project(TEMPLATE_PROJECT_ID);

		$surveyTemplates = self::getQuarterlyTemplates();

		$allSurveyRecords = $hospital->getAllSurveys();
		foreach($allSurveyRecords->getRecords() as $currentRecord) {
			if($currentRecord->getDetails(SURVEY_TEMPLATE_ID) == ANNUAL_SURVEY_TEMPLATE && $currentRecord->getDetails(SURVEY_EVENT_YEAR_FIELD) == $eventYear) {
				$lastAnnualRecord = $currentRecord;
				break;
			}
		}

		if($lastAnnualRecord == "") {
			echo "Skipping ".$hospital->getHospital()." because they don't have an annual survey yet<Br />\n";
			return false;
		}

		$lastPreBaselineRecord = $allSurveyRecords->filterRecords([SURVEY_TEMPLATE_ID => PREBASELINE_TEMPLATE])->getRecords()[0];

		$staffedBedsAnnual = $lastAnnualRecord->getDetails(ANNUAL_STAFFED_BEDS);

		$allAnnualRecords = self::getAllHospitalRecordsByEvent(ANNUAL_SURVEY_EVENT, $eventYear);
		$allHospitalRecords = self::getAllHospitalRecordsByEvent($eventName, $eventYear);
		$currentTemplate = $surveyTemplates[FITTEST_QUARTERLY_TEMPLATE];

		$priorEvent = $currentTemplate->getPriorEvent($eventName);
		$priorYear = $eventYear;
		## Check if the new current event should be from the year prior
		if($currentTemplate->getEventTime($eventName,$eventYear) <= $currentTemplate->getEventTime($priorEvent,$eventYear)) {
			$priorYear--;
		}

		$allPriorHospitalRecords = self::getAllHospitalRecordsByEvent($priorEvent, $priorYear);

		$matchingSurveyRecords = $allSurveyRecords->filterRecords([SURVEY_EVENT_NAME_FIELD => $eventName,
				SURVEY_EVENT_YEAR_FIELD => $eventYear]);

		$currentSurveys = [];
		$quarterNames = [];
		## Check if the current quarterly event surveys have all been submitted
		foreach($matchingSurveyRecords->getRecords() as $matchingRecord) {
			/** @var \Plugin\SurveyTemplate $matchingTemplate */
			$matchingTemplate = $surveyTemplates[$matchingRecord->getDetails(SURVEY_TEMPLATE_ID)];
			if($matchingTemplate == "") {
				continue;
			}
			$currentSurveys[$matchingRecord->getDetails(SURVEY_TEMPLATE_ID)] = [$matchingRecord];
			if($matchingRecord && $matchingRecord->getDetails(SURVEY_QUARTER)) {
				$quarterNames[0] = str_replace(" ","\n",$matchingRecord->getLabelData(SURVEY_QUARTER)).", ".$matchingRecord->getLabelData(SURVEY_EVENT_YEAR_FIELD);

			}

			if($matchingRecord->getDetails($matchingTemplate->getSurveyForm()."_complete") != 2) {
				echo "Skipping ".$hospital->getHospital()." event $eventName, $eventYear because surveys not complete<Br />\n";
				return false;
			}

			## Get the 3 previous survey records for this template too. They'll be NULL if they don't exist
			$templateRecords = $allSurveyRecords->filterRecords([SURVEY_TEMPLATE_ID => $matchingRecord->getDetails(SURVEY_TEMPLATE_ID)]);
			$currentYear = $eventYear;
			$currentEvent = $eventName;

			for($eventCount = 1; $eventCount < 4; $eventCount++) {
				list($currentEvent,$currentYear) = $matchingTemplate->getPriorEventAndYear($currentEvent,$currentYear);

				$previousRecord = $templateRecords->filterRecords([SURVEY_EVENT_NAME_FIELD => $currentEvent,
						SURVEY_EVENT_YEAR_FIELD => $currentYear]);

				if(count($previousRecord->getRecordIds()) > 1) {
					echo "Error: Found duplicate surveys for ".$hospital->getHospital()."'s event $currentEvent for $currentYear<br />";
					return false;
				}
				else {
					$thisCurrentSurvey = reset($previousRecord->getRecords());
					$currentSurveys[$matchingRecord->getDetails(SURVEY_TEMPLATE_ID)][] = $thisCurrentSurvey;
					if($thisCurrentSurvey && $thisCurrentSurvey->getDetails(SURVEY_QUARTER)) {
						$quarterNames[$eventCount] = str_replace(" ","\n",$thisCurrentSurvey->getLabelData(SURVEY_QUARTER)).", ".$thisCurrentSurvey->getLabelData(SURVEY_EVENT_YEAR_FIELD);
					}
				}
			}
		}

		define("GROUP_LABEL_X",5);
		define("GROUP_CHART_X",20);

		$ppeSelections = [];
		$ebolaGarmentChoiceMapping = ["garment" => ["impermiable_garment_wet","gown_i_","coverall_i_wo_",
				"coverall_i_hood_","other","gown_","coverall_fr_hood_","coverall_fr_wo_"],
				"apron" => ["apron_wet","apron_i_","other","apron_"],
				"respirator" => ["respiratory_prot_wet",["papr_","hoods_papr_wo_shield_","hoods_w_shield_"],
						["papr_","hoods_papr_w_shield_"],
						["n95_","hoods_wo_shield_","shield_"],
						["elastomeric_","hoods_wo_shield_","shield_"],
						["n95_","coverall_i_hood_","shield_"],
						["elastomeric_","coverall_i_hood_","shield_"], "other","mask_","hoods_w_shield_","hoods_wo_shield_",
						"hoods_papr_w_shield_","hoods_papr_wo_shield_","shield_","n95_","papr_","elastomeric_"],
				"gloves" => ["hand_covers_wet",["gloves_e_w_","gloves_e_wo_"],"gloves_e_w_","other","gloves_e_wo_"],
				"boot_covers" => ["feet_covers_wet","bootcovers_i_","shoecover_","other","bootcovers_"]];

		$ebolaGarmentNames = ["garment" => "Impermeable Garments",
				"apron" => "Aprons",
				"respirator" => "Respiratory Protection and Head Covers",
				"gloves" => "Hand Covers",
				"boot_covers" => "Feet Covers"];

		foreach($ebolaGarmentChoiceMapping as $location => $fieldList) {
			$locationName = $fieldList[$lastAnnualRecord->getDetails($fieldList[0])];
			if($locationName == "other") {
				$locationName = $fieldList[$lastAnnualRecord->getDetails($fieldList[0]) + $lastAnnualRecord->getDetails($fieldList[0]."_other")];
			}

			$ppeSelections[$location] = $locationName;
		}


		$pdf = new PDF_MemImage();
		$pdf->SetAutoPageBreak(false);
		$subHeaderInfo = [PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::FONT_SIZE => 9,PDF_MemImage::HEADER_PADDING => 0,
				PDF_MemImage::HEADER_FILL_COLOR => [180,200,255], PDF_MemImage::HEADER_TEXT_COLOR => [0,0,0]];

		$fontInfo = [PDF_MemImage::FONT_STYLE => "",PDF_MemImage::FONT_SIZE => 12, PDF_MemImage::FONT_NAME => "helvetica",
				PDF_MemImage::FONT_COLOR => [0,0,0],PDF_MemImage::FONT_ALIGN => 'C'];

		##########################################################
		####### Directory Section? ###############################
		##########################################################
		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Hospital Directory");

		$currentY = 30;
		if($lastPreBaselineRecord) {
			$cmsCert = $lastPreBaselineRecord->getDetails("pb_cms_num");
			$address1 = $lastPreBaselineRecord->getDetails("pb_street");
			$address2 = $lastPreBaselineRecord->getDetails("pb_city").", ".$lastPreBaselineRecord->getLabelData("pb_state").
					" ".$lastPreBaselineRecord->getDetails("pb_zip");
		}

		$pdf->printText(GROUP_LABEL_X, $currentY += 6,"CMS Certification #: ".$cmsCert,$fontInfo);
		$pdf->printText(GROUP_LABEL_X, $currentY += 6,"Address: ".$address1,$fontInfo);
		$pdf->printText(GROUP_LABEL_X + 18, $currentY += 6,$address2,$fontInfo);

		$coalitionName = $lastAnnualRecord->getDetails("healthcare_coalition_name") ? $lastAnnualRecord->getDetails("healthcare_coalition_name") : "None";
		$currentY = 30;
		$pdf->printText(GROUP_LABEL_X + 100, $currentY += 6,"Ebola Tier: ".$lastAnnualRecord->getLabelData("ebola_tier"),$fontInfo);
		$pdf->printText(GROUP_LABEL_X + 100, $currentY += 6,"Coalition: ".$coalitionName, $fontInfo);

		$currentY = 55;
		$contactInfo = [];
		$contactInfo[] = [["State HAI Contact","Site Coordinator"],
				[$lastAnnualRecord->getDetails("name_health_contact")." \n".$lastAnnualRecord->getDetails("email_health_contact")." \n".
				$lastPreBaselineRecord->getDetails("pb_state_hai"),
				$lastAnnualRecord->getDetails("survresfirst")." ".$lastAnnualRecord->getDetails("survreslast")."\n".
				$lastAnnualRecord->getDetails("survresemail")." \n".$lastPreBaselineRecord->getDetails("pb_site_coord")]];

		foreach([["Emergency Preparedness\nRespondent" => ["emerg_prepare","pb_ep_resp"],
				"Occupational Health\nRespondent" => ["occup_health","pb_occ_resp"]],
				["Hospital Admin\nRespondent" => ["hosp_admin","pb_hos_admin_resp"],
				"Fit Test\nRespondent" => ["safety","pb_ftt_resp"]],
				["Infection Control\nRespondent" => ["infect_ctrl","pb_ip_resp"],
				"Supply Chain\nLogistics Respondent" => ["purchasing","pb_sc_resp"]],
				[$lastAnnualRecord->getDetails(SURVEY_OTHER_FORM_NAME)."\nRespondent" => ["other",""]]] as $contactDetails) {
			$firstRow = [];
			$secondRow = [];

			foreach($contactDetails as $contactLabel => $contactField) {
				if($lastAnnualRecord->getDetails($contactField[0]."_email") != "") {
					$firstRow[] = $contactLabel;
					$secondRow[] = $lastAnnualRecord->getDetails($contactField[0]."_fn")." ".$lastAnnualRecord->getDetails($contactField[0]."_ln")."\n".
							$lastAnnualRecord->getDetails($contactField[0]."_email")."\n".($contactField[1] == "" ? "" :$lastPreBaselineRecord->getDetails($contactField[1]));
				}
			}

			if(count($firstRow) > 0) {
				$contactInfo[] = [$firstRow,$secondRow];
			}
		}

		$currentY = $pdf->printTable(GROUP_LABEL_X,$currentY,["Contacts" => 2],[],
				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [68,68]]);

		foreach($contactInfo as $subTableDetails) {
			$currentY = $pdf->printTable(GROUP_LABEL_X,$currentY,$subTableDetails[0],[$subTableDetails[1]],
					array_merge([PDF_MemImage::TABLE_WIDTH_SETTINGS => [68,68]],$subHeaderInfo));
		}

		$otherCurrentY = 55;

		$fastData = [];
		foreach(["Employees" => [ANNUAL_SURVEY_TEMPLATE,"employee_stats"],str_replace("\n"," ",$quarterNames[0])." Admissions" => [HOSPITAL_ADMIN_QUARTERLY_TEMPLATE,"hospadmmonth"],
				        str_replace("\n"," ",$quarterNames[0])." Patient Days" => [HOSPITAL_ADMIN_QUARTERLY_TEMPLATE,"patdaysmonth"],
				        "Staffed Beds" => [ANNUAL_SURVEY_TEMPLATE,ANNUAL_STAFFED_BEDS],
				        str_replace("\n"," ",$quarterNames[0])." Occupancy Rate" => [HOSPITAL_ADMIN_QUARTERLY_TEMPLATE,"occratemonth"]] as $labelName => $fieldDetails) {
			if($fieldDetails[0] == ANNUAL_SURVEY_TEMPLATE) {
				$fastData[] = [$labelName, $lastAnnualRecord->getDetails($fieldDetails[1])];
			}
			else {
				$fastData[] = [$labelName,self::getCountFromSurveys($currentSurveys,$fieldDetails[0],$fieldDetails[1],0)];
			}
		}

		$otherCurrentY = $pdf->printTable(GROUP_LABEL_X + 140,$otherCurrentY,["Fast Facts" => 2],$fastData,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [30,20]]);

		$otherCurrentY += 5;
		$distributorEnum = Project::convertEnumToArray($lastAnnualRecord->getProjectObject()->getMetadata("hospital_distributors")->getElementEnum());
		$ppeDistributor = [];

		## Need to parse this field individually because it's apparently a checkbox and not a radio or dropdown
		$ppeDistributorData = $lastAnnualRecord->getDetails("hospital_distributors");
		foreach($ppeDistributorData as $distributorName) {
			if($distributorName == 5) {
				$ppeDistributor[] = $lastAnnualRecord->getDetails("hospital_distributors_oth");
			}
			else {
				$ppeDistributor[] = $distributorEnum[$distributorName];
			}
		}
		$otherCurrentY = $pdf->printTable(GROUP_LABEL_X + 140,$otherCurrentY,["Medical Supply\nDistributer For PPE"],
				[[implode(", ",$ppeDistributor)]],[PDF_MemImage::TABLE_WIDTH_SETTINGS => [50],PDF_MemImage::FONT_ORIENTATION => 'C']);

		$otherCurrentY += 5;
		$immunizationEnum = Project::convertEnumToArray($lastAnnualRecord->getProjectObject()->getMetadata("immunization_require")->getElementEnum());

		### TODO combine this with the other $ebolaGarmentNames loop, for now, needed to calculate just if $equipmentCount is positive foreach garment section
		$ebolaKitEquipmentTotal = 0;
		$ebolaKitAvailable = 0;

		foreach($ebolaGarmentNames as $ppeType => $headerLabel) {
			if(!is_array($ppeSelections[$ppeType])) {
				$ppeSelections[$ppeType] = [$ppeSelections[$ppeType]];
			}

			foreach($ppeSelections[$ppeType] as $fieldFragment) {
				$ebolaKitEquipmentTotal++;

				$equipmentCount = self::getCountFromSurveys($currentSurveys,"all",$fieldFragment."purchase_",0,"inventory");

				if($equipmentCount > 0) {
					$ebolaKitAvailable++;
				}
			}
		}

		$hygiene = $lastAnnualRecord->getDetails("hygiene");
		$knowledgeAndBeliefs = $lastAnnualRecord->getDetails("kab");
		$trackImmunizations = $lastAnnualRecord->getDetails("fluimm");
		$immunizations = $lastAnnualRecord->getDetails("immunization_require");
		$immunizationData = [];

		$trainingEnum = Project::convertEnumToArray($lastAnnualRecord->getProjectObject()->getMetadata("ebolappe")->getElementEnum());

		$ebolaTraining = $lastAnnualRecord->getLabelData("ebolappe");
		$respiratorTraining = $lastAnnualRecord->getLabelData("n95ppe");
		$paprTraining = $lastAnnualRecord->getLabelData("paprppe");
		$bsNumber = round($ebolaKitAvailable / $ebolaKitEquipmentTotal * 100,0)."%";

		foreach($immunizations as $immunizationValue) {
			if($immunizationValue == 5) {
				$immunizationData[] = $lastAnnualRecord->getDetails("immunization_require_oth");
			}
			else {
				$immunizationData[] = $immunizationEnum[$immunizationValue];
			}
		}

		$ebolaTrainingData = [];
		$respiratorTrainingData = [];
		$paprTrainingData = [];
		##TODO Fix these 3 boxes, it's 1 AM and I'm too tired to condense this to 1 loop
		foreach($ebolaTraining as $immunizationValue) {
			$ebolaTrainingData[] = $trainingEnum[$immunizationValue];
		}
		foreach($respiratorTraining as $immunizationValue) {
			$respiratorTrainingData[] = $trainingEnum[$immunizationValue];
		}
		foreach($paprTraining as $immunizationValue) {
			$paprTrainingData[] = $trainingEnum[$immunizationValue];
		}

//				["% of CDC Recommended Ebola Wet Ensemble Components Stocked By This Hospital", $bsNumber],
		$culturalData = [["Hand Hygiene", $hygiene == 3 ? "Unknown" : ""],["Knowledge, Attitudes, and Beliefs", $knowledgeAndBeliefs == 3 ? "Unknown" : ""],["Respirator Training", implode(" \n",$respiratorTrainingData)],
				["PAPR Training", implode(" \n",$paprTrainingData)],["Ebola Training", implode(" \n",$ebolaTrainingData)],

				["HCP Immunizations", $trackImmunizations == 3 ? "Unknown" : implode(" \n",$immunizationData)]];

		$currentY += 4;
		$otherCurrentY = max($currentY, $otherCurrentY);
		$pdf->printTable(GROUP_LABEL_X + 100,$otherCurrentY,["Cultural Perspective" => 2],$culturalData,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [50,30]]);

		if($hygiene != 3) {
			$pdf->print_checkbox(GROUP_LABEL_X + 163,$otherCurrentY + 13,$hygiene == 1,4,4);
		}
		if($knowledgeAndBeliefs != 3) {
			$pdf->print_checkbox(GROUP_LABEL_X + 163,$otherCurrentY + 19,$knowledgeAndBeliefs == 1,4,4);
		}



		$systemData = [];
		foreach(["Medical Records" => "emr",
				"Patient Isolation Orders" => "pio",
				"Laboratory Systems" => "ls",
				"Provider Order Entry" => "por",
				"State Notifiable Infectious Diseases" => "snid",
				"Hospital Bed Activity" => "hba",
				"HAI in Healthcare Personnel" => "haihw",
				"Supply Manufacturer Recalls" => "smr",
				"Fit Test Data Recordkeeping" => "ftdro",
				"Materials Management System" => "mm",
				"Inventory/Purchasing System" => "inventory"] as $labelName => $fieldFragment) {
			if($lastAnnualRecord->getDetails($fieldFragment."_system_type") != "" || $lastAnnualRecord->getDetails($fieldFragment."_system_name") != "") {
				$systemData[] = [$labelName,$lastAnnualRecord->getLabelData($fieldFragment."_system_type"),
						$lastAnnualRecord->getDetails($fieldFragment."_system_name")];;
			}
		}

		$currentY = $pdf->printTable(GROUP_LABEL_X,$currentY,["System Information"],[],[PDF_MemImage::TABLE_WIDTH_SETTINGS => [95]]);
		$currentY = $pdf->printTable(GROUP_LABEL_X,$currentY,["System Category","Text/\nElectronic","Vendor\nName"],
				$systemData,array_merge([PDF_MemImage::TABLE_WIDTH_SETTINGS => [50,20,25]],$subHeaderInfo));

		$currentY += 4;
		$reportingData = [];
		$checkboxData = [];
		$diseaseList = ["Ebola" => ["ebola"],"MERS/SARS" => ["mers_sars"],
				"Measles" => ["measles"],"Active Tuberculosis" => ["tb"],
				"Varicella/Zoster" => ["vz"],"Smallpox" => ["smallpox"],
				"Other" => ["other"]];
		foreach($diseaseList as $labelName => $fieldName) {
			$reportingData[] = [$labelName,"",""];
			$checkboxData[$labelName] = [in_array("3",$lastAnnualRecord->getDetails($fieldName[0])),in_array("2",$lastAnnualRecord->getDetails($fieldName[0]))];
		}

		####### Start New Page ################
		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Hospital Directory");

		$currentY = 40;
		$newY = $pdf->printTable(GROUP_LABEL_X,$currentY,["Reporting To The State" => 2,"Medical\nSurveillance" => 1],$reportingData,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [50,30,30]]);

		$currentY += 12;
		foreach($checkboxData as $labelName => $checkedValue) {
			if(strlen($labelName) > 30) {
				$currentY += 2;
			}
			$pdf->print_checkbox(GROUP_LABEL_X + 63,$currentY + 1,$checkedValue[0],4,4);
			$pdf->print_checkbox(GROUP_LABEL_X + 93,$currentY + 1,$checkedValue[1],4,4);
			if(strlen($labelName) > 30) {
				$currentY += 2;
			}
			$currentY += 6;
		}
		$currentY += 10;

		##########################################################
		####### Isolation Section ################################
		##########################################################
//		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Preparedness");

		$pdf->SetFillColor(64,128,200);
		$pdf->Rect(GROUP_LABEL_X, $currentY,200,1,'F');

		$currentY += 10;

		$fontInfo = [PDF_MemImage::FONT_STYLE => "",PDF_MemImage::FONT_SIZE => 30, PDF_MemImage::FONT_NAME => "helvetica",
				PDF_MemImage::FONT_COLOR => [64,128,200],PDF_MemImage::FONT_ALIGN => 'C',PDF_MemImage::FONT_WIDTH => 200];
		$pdf->printText(GROUP_LABEL_X,$currentY,"Preparedness",$fontInfo);

		$currentY += 15;

		$fontInfo = [PDF_MemImage::FONT_STYLE => "",PDF_MemImage::FONT_SIZE => 30, PDF_MemImage::FONT_NAME => "helvetica",
				PDF_MemImage::FONT_COLOR => [64,128,200],PDF_MemImage::FONT_ORIENTATION => PDF_MemImage::VERTICAL_ORIENTATION,
				PDF_MemImage::FONT_ALIGN => 'C'];

		$fontInfo[PDF_MemImage::FONT_WIDTH] = 75;
		$pdf->printText(GROUP_LABEL_X,$currentY + 70,"Isolation",$fontInfo);

		$aiirsData = ["My Hospital" => [$lastAnnualRecord->getDetails("numaiirs")],
				"Mean of Others" => round(self::getAverageFromSurveys($allAnnualRecords,ANNUAL_SURVEY_TEMPLATE,"numaiirs",$hospital->getHospital()),0)];
		$aiirsData["Labels"] = ["# AIIRS"];

		$chartOne = BarChart::generateChart([GeneralChart::DATA_KEY => $aiirsData,
				GeneralChart::ABSCISSA_KEY => ["Labels"],
				GeneralChart::IMAGE_WIDTH => 200,GeneralChart::IMAGE_HEIGHT => 300, GeneralChart::SHOW_LEGEND => true,
				GeneralChart::CHART_TITLE => "Airborne Infection Isolation Rooms"]);

		$aiirsDataNormal = ["My Hospital" => round($lastAnnualRecord->getDetails("numaiirs") / $staffedBedsAnnual * 100,1),
				"Mean of Others" => round(self::getAverageFromSurveys($allAnnualRecords,ANNUAL_SURVEY_TEMPLATE,"numaiirs",$hospital->getHospital()) /
						self::getAverageFromSurveys($allAnnualRecords,ANNUAL_SURVEY_TEMPLATE,"staffed_beds_stats",$hospital->getHospital()) * 100,1)];
		$aiirsDataNormal["Labels"] = ["# AIIRS"];

		$chartTwo = BarChart::generateChart([GeneralChart::DATA_KEY => $aiirsDataNormal,
				GeneralChart::ABSCISSA_KEY => ["Labels"],
				GeneralChart::IMAGE_WIDTH => 200,GeneralChart::IMAGE_HEIGHT => 300, GeneralChart::SHOW_LEGEND => true,
				GeneralChart::CHART_TITLE => "Airborne Infection Isolation Rooms per 100 Staffed Beds"]);

		$pdf->GDImage($chartOne, GROUP_CHART_X,$currentY,50,75);
		$pdf->GDImage($chartTwo, GROUP_CHART_X + 55,$currentY,50,75);

		$isolationData = [["# Rooms Meeting CDC\nGuidance for Ebola PUI", $lastAnnualRecord->getDetails("cdc_ebola")],
				["# Portable HEPA Filters", self::getCountFromSurveys($currentSurveys,"all","hepa_purchase_",0,"inventory")],
				["# Temporary Environmental\nContainment Units", self::getCountFromSurveys($currentSurveys,"all","tecu_purchase_",0,"inventory")]];

		$pdf->printTable(GROUP_LABEL_X + 125,$currentY,["Isolation Expansion Capacity" => 2],$isolationData,
				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [60,15]]);

		$currentY += 80;
//		$pdf->SetFillColor(64,128,200);
//		$pdf->Rect(GROUP_LABEL_X, 115,200,1,'F');

		##########################################################
		####### Training Section #################################
		##########################################################
		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Preparedness");
		$currentY = 35;
		$fontInfo[PDF_MemImage::FONT_WIDTH] = 70;
		$pdf->printText(GROUP_LABEL_X,$currentY + 140,"Training",$fontInfo);

		$fitTestCount = [];
		$fitTestStaff = [];
		$ebolaTestCount = [];
		$ebolaTestStaff = [];
		for($quarter = 0; $quarter < 4; $quarter++) {
			$fitTestCount[$quarter] = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"fittesttotal",$quarter);
//			$fitTestCount[$quarter] = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"fittest_safety_quarterly_survey",$quarter,"inventory");
			$fitTestStaff[$quarter] = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"numhcpelig",$quarter);
			$ebolaTestCount[$quarter] = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"ebola_ppe_trainining",$quarter);
			$ebolaTestStaff[$quarter] = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"eligible_eboloa_train",$quarter);
		}

		$respiratorInfo = ["Test" => [$fitTestCount[0], $fitTestStaff[0] - $fitTestCount[0]]];
		$respiratorInfo["Labels"] = ["Rostered HCP Fit Tested/Trained in the Last Year","Rostered HCP Not Fit Tested/Trained in the Last Year"];

		$chartThree = PieChart::generateChart([BarChart::DATA_KEY => $respiratorInfo,
				BarChart::ABSCISSA_KEY => ["Labels"],
				BarChart::IMAGE_WIDTH => 350,BarChart::IMAGE_HEIGHT => 250, BarChart::SHOW_LEGEND => true,
				BarChart::CHART_TITLE => "Ready to Wear N95 or PAPR"]);

		$ebolaPpeInfo = ["Test" => [$ebolaTestCount[0], $ebolaTestStaff[0] - $ebolaTestCount[0]]];
		$ebolaPpeInfo["Labels"] = ["Rostered HCP Trained to Use Ebola Ensemble in the Last Year","Rostered HCP Not Trained to Use Ensemble in the Last Year"];

		$chartFour = PieChart::generateChart([BarChart::DATA_KEY => $ebolaPpeInfo,
				BarChart::ABSCISSA_KEY => ["Labels"],
				BarChart::IMAGE_WIDTH => 350,BarChart::IMAGE_HEIGHT => 250, BarChart::SHOW_LEGEND => true,
				BarChart::CHART_TITLE => "Ready to Wear Ebola PPE Ensemble"]);

		$pdf->GDImage($chartThree, GROUP_CHART_X,$currentY,85,60);
		$pdf->GDImage($chartFour, GROUP_CHART_X + 90,$currentY,85,60);
		$currentY += 64;

		$respiratorHeader = ["Respirator"];
		$respiratorData = [["# of Rostered HCP Fit Tested/Trained to\nUse Respirator in the Last Year"],
				["# of Rostered HCP Fit Tested/Trained to\nUse Respirator in the Last Year per\n100 Staffed Beds"]];

		$ebolaHeader = ["Primary Ebola PPE Wet Ensemble"];
		$ebolaData = [["# of Rostered HCP Trained to Use Ebola\nEnsemble in the Last Year"],
				["# of Rostered HCP Trained to Use Ebola\nEnsemble in the Last Year per 100\nStaffed Beds"]];

		foreach($quarterNames as $quarter => $thisName) {
			$respiratorHeader[] = $thisName;
			$ebolaHeader[] = $thisName;
			$respiratorData[0][] = $fitTestCount[$quarter];
			$respiratorData[1][] = round($fitTestCount[$quarter] / $staffedBedsAnnual * 100,1);
			$ebolaData[0][] = $ebolaTestCount[$quarter];
			$ebolaData[1][] = round($ebolaTestCount[$quarter] / $staffedBedsAnnual * 100,1);
		}

		$currentY = $pdf->printTable(GROUP_CHART_X,$currentY,$respiratorHeader,
				$respiratorData, [PDF_MemImage::TABLE_WIDTH_SETTINGS => [70,25,25,25,25,25],
						PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::FONT_SIZE => 9,
						PDF_MemImage::FONT_ORIENTATION => ['J','C','C','C','C','C']]);
		$currentY += 4;

		$currentY = $pdf->printTable(GROUP_CHART_X,$currentY,$ebolaHeader,
				$ebolaData, [PDF_MemImage::TABLE_WIDTH_SETTINGS => [70,25,25,25,25,25],
						PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::FONT_SIZE => 9,
						PDF_MemImage::FONT_ORIENTATION => ['J','C','C','C','C','C']]);
		$currentY += 4;

		## Get a record from the current quarter and the last quarter in order to calculate quarter days
		$priorRecord = "";
		$newRecord = "";
		foreach($inventoryTemplates as $templateId) {
			if($newRecord == "" && $currentSurveys[$templateId][0] && $currentSurveys[$templateId][0]->getDetails(ANNUAL_DATE) != "") {
				$newRecord = $currentSurveys[$templateId][0];
			}
			if($priorRecord == "" && $currentSurveys[$templateId][1] && $currentSurveys[$templateId][1]->getDetails(ANNUAL_DATE) != "") {
				$priorRecord = $currentSurveys[$templateId][1];
			}

			if($newRecord !== "" && $priorRecord !== "") break;
		}

		$pairCountConvertor = ["gloves_e_w_" => "hand_covers_2_wet","gloves_e_wo_" => "hand_covers_1_wet",
				"bootcovers_i_" => "feet_covers_1_wet", "shoecover_" => "feet_covers_2_wet","bootcovers_fr_" => "feet_covers_3_wet"];
		$n95Convertor = ["primary" => ["Primary N95 Respirator", "primary_inventory_annual_survey_training"],
				"secondary" => ["Secondary N95 Respirator", "secondary_inventory_annual_survey_training"]];
		$quarterDays = self::daysPerQuarter($newRecord);

		$maxKits = "";
		$maxDays = "";
		$demandRatePerArea = [];
		$n95DemandRate = [];
		$n95DaysSupply = [];
		$n95Inventory = [];
		$n95FitTest = [];
		$n95OtherDemand = [];
		$n95OtherDaysSupply = [];

		$allRecordsByHospital = [];

		## Convert prior and current other hospital records into [$hospital][survey_template][quarter] format
		foreach([0 => $allHospitalRecords, 1 => $allPriorHospitalRecords] as $quarter => $recordSet) {
			foreach($recordSet as $templateId => $thisTemplateRecords) {
				foreach($thisTemplateRecords as $thisRecord) {
					$allRecordsByHospital[$thisRecord->getDetails(SURVEY_HOSPITAL_FIELD)][$templateId][$quarter] = $thisRecord;
				}
			}
		}

		foreach($n95Convertor as $equipmentType => $conversionMatrix) {
			if($lastAnnualRecord->getDetails($conversionMatrix[1]) != "") {
				$respiratorType = $lastAnnualRecord->getDetails($conversionMatrix[1]);
				$equipmentCount = self::getCountFromSurveys($currentSurveys,"all","n95_purchase_",0,"inventory",$respiratorType);
				$equipmentPurchase = self::getCountFromSurveys($currentSurveys,"all","n95_purchase_",0,"order",$respiratorType);
				$oldEquipmentCount = self::getCountFromSurveys($currentSurveys,"all","n95_purchase_",1,"inventory",$respiratorType);

				if($quarterDays != 0 && $oldEquipmentCount > 0) {
					$n95DemandRate[$equipmentType] =  round(($equipmentPurchase + ($oldEquipmentCount - $equipmentCount)) / $quarterDays,2);
					$n95DaysSupply[$equipmentType] =  round($equipmentCount / $n95DemandRate[$equipmentType],1);
				}

				$parEquipment = self::getCountFromSurveys($currentSurveys,SUPPLY_CHAIN_QUARTERLY_TEMPLATE,"n95_purchase_".$inventoryFieldAppend[SUPPLY_CHAIN_QUARTERLY_TEMPLATE],0,"inventory",$respiratorType);
				$emergencyEquipment = self::getCountFromSurveys($currentSurveys,EMERGENCY_PREP_QUARTERLY_TEMPLATE,"n95_purchase_".$inventoryFieldAppend[EMERGENCY_PREP_QUARTERLY_TEMPLATE],0,"inventory",$respiratorType);
				$fitTestEquipment = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"n95_purchase_".$inventoryFieldAppend[FITTEST_QUARTERLY_TEMPLATE],0,"inventory",$respiratorType);
				$otherEquipment = self::getCountFromSurveys($currentSurveys,OTHER_QUARTERLY_TEMPLATE,"n95_purchase_".$inventoryFieldAppend[OTHER_QUARTERLY_TEMPLATE],0,"inventory",$respiratorType);
				$n95Inventory[$equipmentType] = [$conversionMatrix[0]." Inventory Count", $parEquipment, $emergencyEquipment, $fitTestEquipment,$otherEquipment,$equipmentCount];

				$n95FitTest[$equipmentType] = self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"fittest_safety_quarterly_survey",0,"inventory",$respiratorType);
				$n95FitTest[$equipmentType] += self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"fittest_safety_quarterly_survey_papr",0,"inventory",$respiratorType);
				$n95FitTest[$equipmentType] += self::getCountFromSurveys($currentSurveys,FITTEST_QUARTERLY_TEMPLATE,"fittest_safety_quarterly_survey_elastomeric",0,"inventory",$respiratorType);
			}

			$hospitalCount = 0;
			foreach($allAnnualRecords[ANNUAL_SURVEY_TEMPLATE] as $otherAnnualRecord) {
				if($otherAnnualRecord->getDetails($conversionMatrix[1]) != "" && $otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD) != $hospital->getHospital()) {
					$respiratorType = $otherAnnualRecord->getDetails($conversionMatrix[1]);
					$equipmentCount = self::getCountFromSurveys($allRecordsByHospital[$otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD)],"all","n95_purchase_",0,"inventory",$respiratorType);
					$equipmentPurchase = self::getCountFromSurveys($allRecordsByHospital[$otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD)],"all","n95_purchase_",0,"order",$respiratorType);
					$oldEquipmentCount = self::getCountFromSurveys($allRecordsByHospital[$otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD)],"all","n95_purchase_",1,"inventory",$respiratorType);

					if($quarterDays != 0 && $oldEquipmentCount > 0) {
						$n95OtherDemand[$equipmentType][$otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD)] = round(($equipmentPurchase + ($oldEquipmentCount - $equipmentCount)) / $quarterDays,2);
						$n95OtherDaysSupply[$equipmentType][$otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD)] = round($equipmentCount / $n95DemandRate[$equipmentType],1);
					}
				}
			}
		}
//		var_dump($n95OtherDemand);die();


//		################# Start Next Page ##################################
//		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Preparedness");

//		$pdf->printText(GROUP_LABEL_X,110,"Training",$fontInfo);

		$fitTestData = ["Test" => [$n95FitTest["primary"],$n95FitTest["secondary"]]];
		$fitTestData["Labels"] = ["# Fit with Primary Respirator this Quarter","# Fit with Secondary Respirator this Quarter"];

		$chartThree = PieChart::generateChart([BarChart::DATA_KEY => $fitTestData,
				BarChart::ABSCISSA_KEY => ["Labels"],
				BarChart::IMAGE_WIDTH => 350,BarChart::IMAGE_HEIGHT => 300, BarChart::SHOW_LEGEND => true,
				BarChart::CHART_TITLE => "Primary vs. Secondary Fit Test"]);

		$respiratorCountChart = ["Test" => [$n95Inventory["primary"][5],$n95Inventory["secondary"][5]]];
		$respiratorCountChart["Labels"] = [$n95Convertor["primary"][0],$n95Convertor["secondary"][0]];

		$chartFour = PieChart::generateChart([BarChart::DATA_KEY => $respiratorCountChart,
				BarChart::ABSCISSA_KEY => ["Labels"],
				BarChart::IMAGE_WIDTH => 350,BarChart::IMAGE_HEIGHT => 300, BarChart::SHOW_LEGEND => true,
				BarChart::CHART_TITLE => "Primary and Secondary N95 Respirators Inventory Count"]);

		$pdf->GDImage($chartThree, GROUP_CHART_X,$currentY,70,60);
		$pdf->GDImage($chartFour, GROUP_CHART_X + 75,$currentY,70,60);
		$currentY += 64;

		$pdf->printTable(GROUP_CHART_X,$currentY,["Primary and Secondary N95 Respirators","\nHospital PAR\nPPE/Equipment\nInventory",
				"Emergency\nPreparedness\nPPE/Equipment\nInventory","Fit Test/Training\nPPE/Equipment\nInventory",
				"Other\nPPE/Equipment\nInventory","\nTotal All\nInventory\nLocations"],
				$n95Inventory, [PDF_MemImage::TABLE_WIDTH_SETTINGS => [60,25,25,25,25,25],
						PDF_MemImage::HEADER_FONT_SIZE => 8,PDF_MemImage::FONT_SIZE => 8,
						PDF_MemImage::FONT_ORIENTATION => ['J','C','C','C','C','C']]);


//		$pdf->SetFillColor(64,128,200);
//		$pdf->Rect(GROUP_LABEL_X, 95.5+32+3,200,1,'F');

		##########################################################
		####### Primary Ebola PPE Section ########################
		##########################################################
		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Preparedness");
		$currentY = 35;
		$fontInfo[PDF_MemImage::FONT_WIDTH] = 60;
		$fontInfo[PDF_MemImage::FONT_SIZE] = 24;
		$pdf->printText(GROUP_LABEL_X,160,"Primary Ebola Wet PPE Ensemble",$fontInfo);

//		$currentY = 133;

		$currentY = $pdf->printTable(GROUP_CHART_X,$currentY,["Primary Ebola PPE Wet Ensemble","# per\nEnsemble","Unit of\nMeasure","Inventory\nCount","Max # of\nEnsembles\nthis\nsub-type"],
				[], [PDF_MemImage::TABLE_WIDTH_SETTINGS => [60,23,23,23,23],
						PDF_MemImage::HEADER_FONT_SIZE => 10]);

		$tableOptions = array_merge($subHeaderInfo,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [60,23,23,23,23],PDF_MemImage::FONT_ORIENTATION => ['J','C','C','C','C'],
				PDF_MemImage::HEADER_ALIGN => 'L',PDF_MemImage::NUMBER_DECIMALS => [0,0,0,0,0]]);


		foreach($ebolaGarmentNames as $ppeType => $headerLabel) {
			$equipmentData = [];
			$maxBurnRate = "";

			if(!is_array($ppeSelections[$ppeType])) {
				$ppeSelections[$ppeType] = [$ppeSelections[$ppeType]];
			}

			foreach($ppeSelections[$ppeType] as $fieldFragment) {
				if(array_key_exists($fieldFragment,$pairCountConvertor)) {
					$equipmentRequired = $lastAnnualRecord->getDetails($pairCountConvertor[$fieldFragment]);
				}
				else {
					$equipmentRequired = 1;
				}

				$fieldMetadata = $lastAnnualRecord->getProjectObject()->getMetadata($fieldFragment."inventory_annual_survey_hospital");

				if(strpos($fieldMetadata->getMisc(),"#PAIRED") !== false) {
					$unitType = "Pairs";
				}
				else {
					$unitType = "Each";
				}

				$equipmentCount = self::getCountFromSurveys($currentSurveys,"all",$fieldFragment."purchase_",0,"inventory") / ($unitType == "Pairs" ? 2 : 1);
				$oldEquipmentCount = self::getCountFromSurveys($currentSurveys,"all",$fieldFragment."purchase_",1,"inventory") / ($unitType == "Pairs" ? 2 : 1);
				$equipmentPurchase = self::getCountFromSurveys($currentSurveys,"all",$fieldFragment."purchase_",0,"order") / ($unitType == "Pairs" ? 2 : 1);
				if($equipmentCount == 0) {
					$burnRate = "";
				}
				else {
					$burnRate = round(($equipmentPurchase + ($oldEquipmentCount - $equipmentCount)) / $quarterDays,2);
				}

				$ebolaKitCount = floor($equipmentCount / $equipmentRequired);
				$patientDaySupply = round($ebolaKitCount / $lastAnnualRecord->getDetails("single_patient_ppe"),1);

				$maxKits = $maxKits === "" ? $ebolaKitCount : min($ebolaKitCount,$maxKits);
				$maxDays = $maxDays === "" ? $patientDaySupply : min($patientDaySupply,$maxDays);

				$equipmentData[] = [$fieldMetadata->getElementLabel(),
						$equipmentRequired,$unitType,
						$equipmentCount,
						$ebolaKitCount];
				if($burnRate !== "") {
					$maxBurnRate = $maxBurnRate === "" ? $burnRate : min($burnRate,$maxBurnRate);
				}
//				echo "Test:$ppeType ~~ $fieldFragment : $equipmentCount ~ $oldEquipmentCount ~ $equipmentPurchase $quarterDays<br />";
			}
			$demandRatePerArea[$ppeType] = $maxBurnRate;

			$currentY = $pdf->printTable(GROUP_CHART_X,$currentY,[$headerLabel => 5],$equipmentData, $tableOptions);
		}
//		var_dump($demandRatePerArea);
//		die();
		$currentY += 5;
//["Primary Ebola PPE Patient Days Supply",$maxDays],,
//		["Estimated # of Ebola PPE Ensembles per Day",$lastAnnualRecord->getDetails("single_patient_ppe")]
		$ppeSupplyData = [["Maximum # of Primary Ebola Wet PPE Ensembles",$maxKits]];

		$pdf->printTable(GROUP_CHART_X,$currentY,[], $ppeSupplyData,
				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [90,20],
						PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::FONT_SIZE => 9,
						PDF_MemImage::FONT_ORIENTATION => ['J','C'],PDF_MemImage::NUMBER_DECIMALS => PDF_MemImage::DECIMALS_MANUAL]);
		$currentY += 6*count($ppeSupplyData)+2+4;

		$tinyType = [PDF_MemImage::FONT_SIZE => 7];
		$pdf->printText(GROUP_CHART_X,$currentY,"*Note: Link to CDC's Guidance on PPE for Ebola: ",$tinyType);
		$pdf->SetTextColor(0,0,255);
		$pdf->Cell(100,3,"http://www.cdc.gov/vhf/ebola/healthcare-us/ppe/guidance.html",0,0,'',false,"http://www.cdc.gov/vhf/ebola/healthcare-us/ppe/guidance.html");

//		################# Start Next Page ##################################
//		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Preparedness");

//		$fontInfo[PDF_MemImage::FONT_WIDTH] = 60;
//		$fontInfo[PDF_MemImage::FONT_SIZE] = 24;
//		$pdf->printText(GROUP_LABEL_X,140,"Primary Ebola Wet PPE Ensemble",$fontInfo);

		## Calculate available ebola kits for each of the previous 4 quarters
//		$currentY = 35;
		$ppeSupplyData = [];
		for($quarter = 3; $quarter >= 0; $quarter--) {
			/** @var \Plugin\Record $quarterRecord */
			if($quarterRecord = $currentSurveys[10][$quarter]) {
				$newPpeData = [$quarterRecord->getLabelData(SURVEY_QUARTER)];

				$maxKits = "";
				$maxDays = "";

				foreach($ebolaGarmentNames as $ppeType => $headerLabel) {
					foreach($ppeSelections[$ppeType] as $fieldFragment) {
						if(array_key_exists($fieldFragment,$pairCountConvertor)) {
							$equipmentRequired = $lastAnnualRecord->getDetails($pairCountConvertor[$fieldFragment]);
						}
						else {
							$equipmentRequired = 1;
						}

						$fieldMetadata = $lastAnnualRecord->getProjectObject()->getMetadata($fieldFragment."inventory_annual_survey_hospital");
						if(strpos($fieldMetadata->getMisc(),"#PAIRED") !== false) {
							$unitType = "Pairs";
						}
						else {
							$unitType = "Each";
						}

						$equipmentCount = self::getCountFromSurveys($currentSurveys,"all",$fieldFragment."purchase_",$quarter,"inventory") / ($unitType == "Pairs" ? 2 : 1);
						$ebolaKitCount = floor($equipmentCount / $equipmentRequired);
						$patientDaySupply = round($ebolaKitCount / $lastAnnualRecord->getDetails("single_patient_ppe"),1);

						$maxKits = $maxKits === "" ? $ebolaKitCount : min($ebolaKitCount,$maxKits);
						$maxDays = $maxDays === "" ? $patientDaySupply : min($patientDaySupply,$maxDays);
					}
				}
				$newPpeData[] = $maxKits;
//				$newPpeData[] = $maxDays;

				$ppeSupplyData[] = $newPpeData;
			}
		}

		$hospitalCount = 0;
		$totalKits = 0;
		$totalDays = 0;
		$otherDemandRatePerArea = [];

		## Calculate average number of kits and patient days for all other hospitals
		foreach($allAnnualRecords[ANNUAL_SURVEY_TEMPLATE] as $otherAnnualRecord) {
			if($otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD) == $hospital->getHospital()) continue;

			$thisHospital = $otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD);
			$thisHospitalRecords = [];

			foreach($inventoryTemplates as $templateId) {
				foreach($allHospitalRecords[$templateId] as $otherQuarterlyRecord) {
					if($otherQuarterlyRecord->getDetails(SURVEY_HOSPITAL_FIELD) == $thisHospital) {
						$thisHospitalRecords[$templateId][0] = $otherQuarterlyRecord;
					}
				}
			}

			if(count($thisHospitalRecords) > 0) {
				# Calculate ebola garment and max number creatable for this hospital
				$thisPpeSelections = [];
				foreach($ebolaGarmentChoiceMapping as $location => $fieldList) {
					$locationName = $fieldList[$otherAnnualRecord->getDetails($fieldList[0])];
					if($locationName == "other") {
						$locationName = $fieldList[$otherAnnualRecord->getDetails($fieldList[0]) + $otherAnnualRecord->getDetails($fieldList[0]."_other")];
					}

					$thisPpeSelections[$location] = $locationName;
				}
//				echo "<pre>";var_dump($thisPpeSelections);echo "</pre><br />";

				$maxKits = "";
				$maxDays = "";

				foreach($ebolaGarmentNames as $ppeType => $headerLabel) {
					$maxBurnRate = "";
					if(!is_array($thisPpeSelections[$ppeType])) {
						$thisPpeSelections[$ppeType] = [$thisPpeSelections[$ppeType]];
					}
					foreach($thisPpeSelections[$ppeType] as $fieldFragment) {
						if(array_key_exists($fieldFragment,$pairCountConvertor)) {
							$equipmentRequired = $otherAnnualRecord->getDetails($pairCountConvertor[$fieldFragment]);
						}
						else {
							$equipmentRequired = 1;
						}
						$fieldMetadata = $otherAnnualRecord->getProjectObject()->getMetadata($fieldFragment."inventory_annual_survey_hospital");
						if(strpos($fieldMetadata->getMisc(),"#PAIRED") !== false) {
							$unitType = "Pairs";
						}
						else {
							$unitType = "Each";
						}

						$equipmentCount = self::getCountFromSurveys($thisHospitalRecords,"all",$fieldFragment."purchase_",0,"inventory") / ($unitType == "Pairs" ? 2 : 1);
						$oldEquipmentCount = self::getCountFromSurveys($thisHospitalRecords,"all",$fieldFragment."purchase_",1,"inventory") / ($unitType == "Pairs" ? 2 : 1);
						$equipmentPurchase = self::getCountFromSurveys($thisHospitalRecords,"all",$fieldFragment."purchase_",0,"order") / ($unitType == "Pairs" ? 2 : 1);
						if($equipmentCount == 0) {
							$burnRate = "";
						}
						else {
							$burnRate = round(($equipmentPurchase + ($oldEquipmentCount - $equipmentCount)) / $quarterDays,2);
						}
//						echo $otherAnnualRecord->getDetails(SURVEY_HOSPITAL_FIELD)." $ppeType $fieldFragment => $equipmentCount <br />";

						$ebolaKitCount = floor($equipmentCount / $equipmentRequired);
						$patientDaySupply = round($ebolaKitCount / $otherAnnualRecord->getDetails("single_patient_ppe"),1);

						$maxKits = $maxKits === "" ? $ebolaKitCount : min($ebolaKitCount,$maxKits);
						$maxDays = $maxDays === "" ? $patientDaySupply : min($patientDaySupply,$maxDays);
						if($burnRate !== "") {
							$maxBurnRate = $maxBurnRate === "" ? $burnRate : min($burnRate,$maxBurnRate);
						}
					}
					if(!isset($otherDemandRatePerArea[$ppeType])) {
						$otherDemandRatePerArea[$ppeType] = 0;
					}
					$otherDemandRatePerArea[$ppeType] += $maxBurnRate;
				}

				$totalKits += $maxKits;
				$totalDays += $maxDays;
				$hospitalCount++;
			}
		}
		$ppeSupplyData[] = ["Mean of Others",round($totalKits / $hospitalCount,0)];
		foreach($otherDemandRatePerArea as $ppeType => $totalBurnRate) {
			$otherDemandRatePerArea[$ppeType] = $totalBurnRate / $hospitalCount;
		}

		$currentY += 10;

		$pdf->printTable(GROUP_CHART_X,$currentY,["" => 1, "Maximum Primary Ebola Wet\nPPE Ensembles" => 1], $ppeSupplyData,
				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [40,50,50],
						PDF_MemImage::HEADER_FONT_SIZE => 9,PDF_MemImage::FONT_SIZE => 9,
						PDF_MemImage::FONT_ORIENTATION => ['J','C','C'],PDF_MemImage::NUMBER_DECIMALS => [0,0,1]]);
		$currentY += 6*count($ppeSupplyData)+2+10;

		foreach(["primary","secondary"] as $ordinality) {
			$othersN95Mean[$ordinality] = round(array_sum($n95OtherDaysSupply[$ordinality]) / count($n95OtherDaysSupply[$ordinality]),1);
			$othersN95Mean[$ordinality] = $othersN95Mean[$ordinality] == 0 ? "" : $othersN95Mean[$ordinality];
		}

		$ppeSupplyData = [["Primary & Secondary N95 Respirator Choices","My Hospital","Mean of Others"],
				["Primary N95 Respirator",$n95DaysSupply["primary"],$othersN95Mean["primary"]],
				["Secondary N95 Respirator",$n95DaysSupply["secondary"],$othersN95Mean["secondary"]]];

		$pdf->printTable(GROUP_CHART_X,$currentY,["Days Supply" => 3], $ppeSupplyData,
				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [70,40,40],
						PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::FONT_SIZE => 9,
						PDF_MemImage::FONT_ORIENTATION => ['J','C','C'],PDF_MemImage::NUMBER_DECIMALS => [0,0,0]]);
		$currentY += 6*count($ppeSupplyData)+2 + 10;

		$appendixLink = $pdf->AddLink();

		$pdf->SetY($currentY);
		$pdf->SetX(GROUP_CHART_X);
		$pdf->SetFont("","",7);

		$pdf->Cell($pdf->GetStringWidth("See "),floor(7 / 2),"See ");
		$pdf->SetTextColor(0,0,255);
		$pdf->Cell($pdf->GetStringWidth("Appendix A"),floor(7 / 2),"Appendix A",0,0,'',false,$appendixLink);
		$pdf->printText(GROUP_CHART_X + $pdf->GetStringWidth("See Appendix A"),$currentY," for complete PPE/Equipment Inventory Counts for all of your hospital PPE/Equipment Inventory Locations.",$tinyType);

		##########################################################
		####### Responsiveness Section ###########################
		##########################################################

		################# Start Next Page ##################################
		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Responsiveness");
		$currentY = 35;

		$ppeSupplyData = [];
		foreach($ebolaGarmentNames as $ppeType => $headerLabel) {
			$ppeSupplyData[] = [$headerLabel, $demandRatePerArea[$ppeType],$otherDemandRatePerArea[$ppeType]];
		}

		$ppeSupplyData2 = [];
		foreach($n95Convertor as $respiratorType => $label) {
			$ppeSupplyData2[] = [$label[0], $n95DemandRate[$respiratorType],round(array_sum($n95OtherDemand[$respiratorType]) / count($n95OtherDemand[$respiratorType]), 1)];
		}

		$tableOptions = array_merge($subHeaderInfo,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [90,40,40],PDF_MemImage::FONT_ORIENTATION => ['J','C','C'],PDF_MemImage::HEADER_ALIGN => ['L','C','C']]);
//		$pdf->printTable(GROUP_CHART_X,$currentY,["Demand Rate (units/day)" => 3], [],
//				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [90,40,40],PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::HEADER_PADDING => 0]);
//		$currentY += 5;
//		$pdf->printTable(GROUP_CHART_X,$currentY,["Primary Ebola Wet PPE Ensemble","My Hospital","Mean of Others"], $ppeSupplyData,$tableOptions);
//		$currentY += 6*count($ppeSupplyData) + 5;
//		$pdf->printTable(GROUP_CHART_X,$currentY,["Primary & Secondary N95 Respirator Choices","My Hospital","Mean of Others"], $ppeSupplyData2,$tableOptions);
//		$currentY += 6*count($ppeSupplyData2) + 5 + 5;

		$headerFontInfo = array_merge($fontInfo,[PDF_MemImage::FONT_ORIENTATION => '0',PDF_MemImage::FONT_SIZE => 16]);

		$pdf->printText(GROUP_CHART_X,$currentY, "Unprotected Exposure", $headerFontInfo);
		$pdf->printText(GROUP_CHART_X,$currentY+8, "Events", $headerFontInfo);
		$pdf->printText(GROUP_CHART_X + 100,$currentY, "Confirmed Cases of", $headerFontInfo);
		$pdf->printText(GROUP_CHART_X + 100,$currentY+8, "Reportable Pathogens", $headerFontInfo);

		$currentY += 20;

		$tableOptions = array_merge($subHeaderInfo,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [26,14,14,14,14,14],PDF_MemImage::FONT_ORIENTATION => ['J','C','C','C','C','C'],
				PDF_MemImage::HEADER_FONT_SIZE => 7,PDF_MemImage::FONT_SIZE => 7]);
		$diseaseData = [];
		$diseaseData2 = [];
		$diseaseData4 = [];
		$diseaseTableHeaders = [""];
		foreach($quarterNames as $quarter => $quarterLabel) {
			$diseaseTableHeaders[] = $quarterLabel;
		}
		$diseaseTableHeaders[] = $eventName." Mean\nof Others";
		$diseaseList = ["Ebola" => ["expnumebola","pathnumebola","convnumebola"],"MERS/SARS" => ["expnummers","pathnummers","convnummers"],
				"Measles" => ["expnummeas","pathnummeas","convnummeas"],"Active Tuberculosis" => ["expnumtb","pathnumtb","convnumtb"],
				"Varicella/Zoster" => ["expnumvar","pathnumvar","convnumvar"],"Smallpox" => ["expnumsmall","pathnumsmall","convnumsmall"],
				"Other" => ["expnumoth","pathnumoth","convnumoth"]];

		foreach($diseaseList as $diseaseName => $fieldFragment) {
			$exposureEvents = [$diseaseName];
			$reportablePathogens = [$diseaseName];
			$occAcquiredPathogens = [$diseaseName];
			foreach($quarterNames as $quarter => $quarterLabel) {
				$exposureEvents[] = self::getCountFromSurveys($currentSurveys,OCC_HEALTH_QUARTERLY_TEMPLATE,$fieldFragment[0],$quarter);
				$reportablePathogens[] = self::getCountFromSurveys($currentSurveys,INF_PREVENTION_QUARTERLY_TEMPLATE,$fieldFragment[1],$quarter);
				$occAcquiredPathogens[] = self::getCountFromSurveys($currentSurveys,OCC_HEALTH_QUARTERLY_TEMPLATE,$fieldFragment[2],$quarter);
			}
			$exposureEvents[] = self::getAverageFromSurveys($allHospitalRecords,OCC_HEALTH_QUARTERLY_TEMPLATE,$fieldFragment[0],$hospital->getHospital());
			$reportablePathogens[] = self::getAverageFromSurveys($allHospitalRecords,INF_PREVENTION_QUARTERLY_TEMPLATE,$fieldFragment[1],$hospital->getHospital());
			$occAcquiredPathogens[] = self::getAverageFromSurveys($allHospitalRecords,OCC_HEALTH_QUARTERLY_TEMPLATE,$fieldFragment[2],$hospital->getHospital());

			$diseaseData[] = $exposureEvents;
			$diseaseData2[] = $reportablePathogens;
			$diseaseData4[] = $occAcquiredPathogens;
		}

		$pdf->printTable(GROUP_LABEL_X,$currentY,$diseaseTableHeaders,$diseaseData,$tableOptions);
		$pdf->printTable(GROUP_LABEL_X + 100,$currentY,$diseaseTableHeaders, $diseaseData2,$tableOptions);
		$currentY += 6*count($diseaseData) + 20;


		$pdf->printText(GROUP_LABEL_X,$currentY, "Isolated for Suspected/Confirmed Cases of Highly Contagious Diseases", array_merge($headerFontInfo,[PDF_MemImage::FONT_WIDTH => 200]));
		$currentY += 11;

		$tableOptions[PDF_MemImage::TABLE_WIDTH_SETTINGS][0] = 80;

		$diseaseData3 = [];
		foreach(["Unique # Patients Placed in Airborne Isolation" => "ordersuninum",
				"Total Airborne Isolation Days" => "ordersdays",
				"Unique # of Ebola PUIs Placed in Isolation" => "numpuiquarter",
				"Total Ebola Isolation Days" => "numpuidays"] as $fieldLabel => $fieldFragment) {
			$isolatedRow = [$fieldLabel];
			foreach($quarterNames as $quarter => $quarterLabel) {
				$isolatedRow[] = self::getCountFromSurveys($currentSurveys,INF_PREVENTION_QUARTERLY_TEMPLATE,$fieldFragment,$quarter);
			}
			$isolatedRow[] = self::getAverageFromSurveys($allHospitalRecords,INF_PREVENTION_QUARTERLY_TEMPLATE,$fieldFragment,$hospital->getHospital());
			$diseaseData3[] = $isolatedRow;
		}

		$pdf->printTable(GROUP_CHART_X,$currentY,$diseaseTableHeaders,$diseaseData3,$tableOptions);



		##########################################################
		####### Outcomes Section #################################
		##########################################################

		################# Start Next Page ##################################
		self::addNewPageToPdf($pdf, $hospital, $eventName.", ".$eventYear,"Outcomes");
		$currentY = 35;


		$pdf->printText(10,$currentY, "Occupationally Acquired Contagious Pathogens", array_merge($headerFontInfo,[PDF_MemImage::FONT_ALIGN => 'L',PDF_MemImage::FONT_WIDTH => 200]));
		$currentY += 11;

		$tableOptions = array_merge($subHeaderInfo,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [22,15,15,15,15,15],PDF_MemImage::FONT_ORIENTATION => ['J','C','C','C','C','C'],
				PDF_MemImage::HEADER_FONT_SIZE => 8,PDF_MemImage::FONT_SIZE => 8]);

		$pdf->printTable(10,$currentY,$diseaseTableHeaders,$diseaseData4,$tableOptions);
		$currentY += 6*count($diseaseData) + 20;


		$pdf->printText(10,$currentY, "HCP with Newly Acquired Latent TB Infection", array_merge($headerFontInfo,[PDF_MemImage::FONT_ALIGN => 'L',PDF_MemImage::FONT_WIDTH => 200]));
		$currentY += 11;

		$exposureEvents = [];
		$exposureEvents[] = $lastAnnualRecord->getDetails("numtbconv");
		$exposureEvents[] = $lastAnnualRecord->getDetails("tbgroup");
		$exposureEvents[] = round($exposureEvents[0] / $exposureEvents[1] * 10000,1);

		$tbHeaders = ["Number of TB\nConversions\nCalendar Year ".($eventYear - 1),"Number of HCP\nin TB Screening\nGroup","Rate of\nConversion\nper 10,000"];

		foreach($quarterNames as $quarter => $thisName) {
			$tbHeaders[] = "New TB\nConversions\n".$thisName;
			$exposureEvents[] = self::getCountFromSurveys($currentSurveys,OCC_HEALTH_QUARTERLY_TEMPLATE,"convnumtb",$quarter);
		}

		//$exposureEvents[] = round(self::getAverageFromSurveys($allHospitalRecords,OCC_HEALTH_QUARTERLY_TEMPLATE,"convnumtb",$hospital->getHospital()) / self::getAverageFromSurveys($allAnnualRecords,ANNUAL_SURVEY_TEMPLATE,"numtbconv",$hospital->getHospital()),1);
		$tbInformation = [$exposureEvents];

		$pdf->printTable(10,$currentY,$tbHeaders,$tbInformation,
				[PDF_MemImage::TABLE_WIDTH_SETTINGS => [35,35,25,25,25,25,25],
						PDF_MemImage::HEADER_FONT_SIZE => 10,PDF_MemImage::FONT_SIZE => 9,
						PDF_MemImage::FONT_ORIENTATION => ['C','C','C','C','C','C','C']]);
		$currentY += 6*count($diseaseData) + 20;

		##########################################################
		####### Appendix Section #################################
		##########################################################
		$completeInventory = [];
		$inventoryData = [];
		foreach($inventoryTemplates as $inventoryTemplateId) {
			if(!$currentSurveys[$inventoryTemplateId][0]) {
				continue;
			}
			$inventoryData[$inventoryTemplateId] = Reporting::pullInventoryDataBySurvey($currentSurveys[$inventoryTemplateId][0],$eventYear);
//			echo "<pre>";
//			print_r($inventoryData);
//			echo "</pre>";
			$completeInventory = array_merge_recursive($completeInventory,$inventoryData[$inventoryTemplateId]);
		}
//		echo "Dumping total data<Br /><pre>";
//		var_dump($completeInventory["Hand Coverings"]["Gloves"]["Single-use (disposable) examination gloves with extended cuffs "]);
//die("");
		$inventoryTableSettings = array_merge($subHeaderInfo,[PDF_MemImage::TABLE_WIDTH_SETTINGS => [25,40,50,50,20,20,20,20,20,20],
				PDF_MemImage::FONT_ORIENTATION => ['J','J','J','J','C','C','C','C','C','C'],
				PDF_MemImage::NUMBER_DECIMALS => [0,0,0,0,0,0,0,1,1,0],
				PDF_MemImage::FONT_SIZE => 6, PDF_MemImage::HEADER_FONT_SIZE => 7]);

		$pdf->SetFontSize(6);

		################# Start Next Page ##################################
		self::$currentHeader = "Appendix: Total Inventory All Locations";
		$currentY = self::addAppendixHeader($pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings);

		$pdf->SetLink($appendixLink,0);

		$inventoryOutput = self::convertInventoryForOutput($completeInventory, $pdf);

		$pdf->printTable(GROUP_LABEL_X,$currentY,[],$inventoryOutput,$inventoryTableSettings,"\\Plugin\\Reporting::addAppendixHeader",
				[$pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings]);


		################# Start Next Page ##################################
		self::$currentHeader = "Appendix: Hospital PAR PPE/Equipment Inventory";
		$currentY = self::addAppendixHeader($pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings);

		$inventoryOutput = self::convertInventoryForOutput($inventoryData[12], $pdf);

		$pdf->printTable(GROUP_LABEL_X,$currentY,[],$inventoryOutput,$inventoryTableSettings,"\\Plugin\\Reporting::addAppendixHeader",
				[$pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings]);



		################# Start Next Page ##################################
		self::$currentHeader = "Appendix: Emergency Preparedness PPE/Equipment Inventory";
		$currentY = self::addAppendixHeader($pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings);

		$inventoryOutput = self::convertInventoryForOutput($inventoryData[14], $pdf);

		$pdf->printTable(GROUP_LABEL_X,$currentY,[],$inventoryOutput,$inventoryTableSettings,"\\Plugin\\Reporting::addAppendixHeader",
				[$pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings]);



		################# Start Next Page ##################################
		self::$currentHeader = "Appendix: Fit Test/Training PPE/Equipment Inventory";
		$currentY = self::addAppendixHeader($pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings);

		$inventoryOutput = self::convertInventoryForOutput($inventoryData[10], $pdf);

		$pdf->printTable(GROUP_LABEL_X,$currentY,[],$inventoryOutput,$inventoryTableSettings,"\\Plugin\\Reporting::addAppendixHeader",
				[$pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings]);


		################# Start Next Page ##################################
		self::$currentHeader = "Appendix: Other PPE/Equipment Inventory";
		$currentY = self::addAppendixHeader($pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings);

		$inventoryOutput = self::convertInventoryForOutput($inventoryData[18], $pdf);

		$pdf->printTable(GROUP_LABEL_X,$currentY,[],$inventoryOutput,$inventoryTableSettings,"\\Plugin\\Reporting::addAppendixHeader",
				[$pdf,$hospital, $eventName.", ".$eventYear,"","L",$inventoryTableSettings]);


		return $pdf;
	}

	/**
	 * @param $pdf PDF_MemImage
	 * @param $hospital Hospital
	 * @param $eventString string
	 * @param $currentSection string
	 * @param $orientation string
	 */
	public static function addNewPageToPdf($pdf, $hospital, $eventString, $currentSection, $orientation = "P") {
		$pdf->AddPage($orientation, "A4");

		$fontInfo = [PDF_MemImage::FONT_STYLE => "",PDF_MemImage::FONT_SIZE => 30, PDF_MemImage::FONT_NAME => "helvetica"];
		$pdf->printText(GROUP_LABEL_X,5,$hospital->getHospital(),$fontInfo);

		$fontInfo[PDF_MemImage::FONT_SIZE] = 15;
		$pdf->printText($pdf->GetX()+2,10,$eventString,$fontInfo);


		$fontInfo[PDF_MemImage::FONT_COLOR] = [255,0,0];
		$pdf->printText(120,5,"SENSITIVE BUT UNCLASSIFIED",$fontInfo);

		$fontInfo[PDF_MemImage::FONT_COLOR] = [64,128,200];
		$fontInfo[PDF_MemImage::FONT_SIZE] = 30;
		$fontInfo[PDF_MemImage::FONT_ALIGN] = 'C';
		$fontInfo[PDF_MemImage::FONT_WIDTH] = 210;
		$pdf->printText(0,20,$currentSection,$fontInfo);

		self::addPageNumber($pdf, $orientation);
	}

	/**
	 * @param $pdf PDF_MemImage
	 * @param string $orientation
	 */
	public static function addPageNumber($pdf, $orientation = "P") {
		$fontInfo = [PDF_MemImage::FONT_STYLE => "",PDF_MemImage::FONT_SIZE => 8, PDF_MemImage::FONT_NAME => "helvetica"];
		$pdf->printText($orientation == "P" ? 195 : 283,$orientation == "P" ? 290 : 203,"Page ".self::$pageCount,$fontInfo);
		self::$pageCount++;
	}

	/**
	 * @param $surveyArray array Must be in [templateId][] format
	 * @param $targetTemplate string TemplateId or "all"
	 * @param $fieldFragment string Full field name or part if checking multiple templates
	 * @param $quarterNumber int 0 for most recent, 1,2,3 for 1st,2nd,3rd most recent
	 * @param $inventoryOrCount string if hitting received inventory field, "inventory" or "received" field
	 * @param $makeModel string
	 * @return string
	 */
	public static function getCountFromSurveys($surveyArray,$targetTemplate,$fieldFragment,$quarterNumber = 0, $inventoryOrCount = "",$makeModel = "") {
		global $inventoryTemplates,$inventoryFieldAppend;
		$outputNumber = "";
		if($makeModel != "") {
			$makeModel = explode("~",$makeModel);
		}

		if($targetTemplate == "all") {
			$templatesToCheck = $inventoryTemplates; # All templates with inventories on them
		}
		else {
			$templatesToCheck = [$targetTemplate];
		}

		foreach($templatesToCheck as $templateId) {
			/** @var $targetSurvey \Plugin\Record */
			$targetSurvey = $surveyArray[$templateId][$quarterNumber];

			## Verify survey exists for that quarter
			if($targetSurvey != "") {
				if($targetTemplate == "all") {
					$fieldName = $fieldFragment.$inventoryFieldAppend[$templateId];
				}
				else {
					$fieldName = $fieldFragment;
				}

				$fieldAnnotation = $targetSurvey->getProjectObject()->getMetadata($fieldName)->getMisc();

				if(strpos($fieldAnnotation,"#EQUIPMATRIX") === false) {
					if($outputNumber === "" && ($targetSurvey->getDetails($fieldName) != "" || $targetSurvey->getDetails($fieldName) === "0")) {
						$outputNumber = 0;
						$outputNumber += $targetSurvey->getDetails($fieldName);
					}
				}
				else {
					$inventoryDetails = PPEType::extractInventoryFromRecord($targetSurvey,$fieldName);

					foreach($inventoryDetails as $makeType => $makeDetails) {
						foreach($makeDetails as $modelType => $modelDetails) {
							if($makeModel != "" && ($makeType != $makeModel[0] || $modelType != $makeModel[1])) {
								continue;
							}
							$pairedValue = 1;
							if(array_key_exists("paired",$modelDetails)) {
								$pairedValue = $modelDetails["paired"] == "" ? 1 : $modelDetails["paired"];
								unset($modelDetails["paired"]);
							}
							foreach($modelDetails as $sizeType => $sizeDetails) {
								if($outputNumber === "") {
									$outputNumber = 0;
								}
								$outputNumber += $sizeDetails[$inventoryOrCount] * $pairedValue;
							}
						}
					}
				}
			}
		}

		if(abs($outputNumber) > 999999) {
			$outputNumber = floatval($outputNumber);
		}
		return $outputNumber;
	}

	/**
	 * @param $surveyArray array Must be in [templateId][] format
	 * @param $targetTemplate string TemplateId or "all"
	 * @param $fieldFragment string Full field name or part if checking multiple templates
	 * @param $ignoreHospital string hospital to ignore for the average
	 * @param $inventoryOrCount string if hitting received inventory field, "inventory" or "received" field
	 * @return string
	 */
	public static function getAverageFromSurveys($surveyArray,$targetTemplate,$fieldFragment,$ignoreHospital, $inventoryOrCount = "") {
		global $inventoryTemplates,$inventoryFieldAppend;
		$outputNumber = 0;
		$hospitalCount = 0;

		if($targetTemplate == "all") {
			$templatesToCheck = $inventoryTemplates; # All templates with inventories on them
		}
		else {
			$templatesToCheck = [$targetTemplate];
		}

		foreach($templatesToCheck as $templateId) {
			/** @var $targetSurvey \Plugin\Record */
			foreach($surveyArray[$templateId] as $targetSurvey) {
				$hospitalCounted = false;
				if($targetSurvey->getDetails(SURVEY_HOSPITAL_FIELD) == $ignoreHospital) continue;

				## Verify survey exists for that quarter
				if($targetSurvey != "") {
					if($targetTemplate == "all") {
						$fieldName = $fieldFragment.$inventoryFieldAppend[$templateId];
					}
					else {
						$fieldName = $fieldFragment;
					}

					$fieldAnnotation = $targetSurvey->getProjectObject()->getMetadata($fieldName)->getMisc();

					if(strpos($fieldAnnotation,"#EQUIPMATRIX") === false) {
						if($targetSurvey->getDetails($fieldName) != "" || $targetSurvey->getDetails($fieldName) === "0") {
							$hospitalCount++;
							$outputNumber += $targetSurvey->getDetails($fieldName);
//							if(($outputNumber) > 100000) {
//								echo "Found $fieldName on ".$targetSurvey->getDetails(SURVEY_HOSPITAL_FIELD)." => $templateId<br />";
//							}
						}
					}
					else {
						$inventoryDetails = PPEType::extractInventoryFromRecord($targetSurvey,$fieldName);

						foreach($inventoryDetails as $makeType => $makeDetails) {
							foreach($makeDetails as $modelType => $modelDetails) {
								$pairedValue = 1;
								if(array_key_exists("paired",$modelDetails)) {
									$pairedValue = $modelDetails["paired"];
									unset($modelDetails["paired"]);
								}
								foreach($modelDetails as $sizeType => $sizeDetails) {
									if(!$hospitalCounted) {
										$hospitalCount++;
										$hospitalCounted = true;
									}
//									if(($sizeDetails[$inventoryOrCount] * $pairedValue) < -1000) {
//										echo "Found $makeType ~ $modelType ~ $sizeType on ".$targetSurvey->getDetails(SURVEY_HOSPITAL_FIELD)." => $templateId<br />";
//									}
									$outputNumber += $sizeDetails[$inventoryOrCount] * $pairedValue;
								}
							}
						}
					}
				}
			}
		}

		$outputNumber = round($outputNumber / $hospitalCount,2);
		return $outputNumber;
	}

	public static function getQuarterlyTemplates() {
		if(self::$quarterlyTemplates == "") {
			global $quarterlySurveyTemplates;
			$surveyTemplates = [];
			$surveyTemplateProject = new Project(TEMPLATE_PROJECT_ID);

			foreach($quarterlySurveyTemplates as $templateId) {
				$surveyTemplates[$templateId] = new SurveyTemplate(Record::createRecordFromId($surveyTemplateProject,$templateId));
			}
			self::$quarterlyTemplates = $surveyTemplates;
		}

		return self::$quarterlyTemplates;
	}

	public static function getAllHospitalRecordsByEvent($eventName, $eventYear) {
		if(!isset(self::$allHospitalLatestQuarterly[$eventName."~".$eventYear])) {
			$surveyTemplateProject = new Project(TEMPLATE_PROJECT_ID);
			$surveyTemplates = self::getQuarterlyTemplates();
			$surveyTemplates[ANNUAL_SURVEY_TEMPLATE] = new SurveyTemplate(Record::createRecordFromId($surveyTemplateProject, ANNUAL_SURVEY_TEMPLATE));

			foreach($surveyTemplates as $template) {
				self::$allHospitalLatestQuarterly[$eventName."~".$eventYear][$template->getDetails(TEMPLATE_ID_FIELD)] = [];
			}

			$hospitalProject = new Project(HOSPITAL_PROJECT_ID);
			$hospitalRecords = new RecordSet($hospitalProject, array(RecordSet::getKeyComparatorPair(HOSPITAL_NAME_FIELD, '!=') => ""));

			foreach($hospitalRecords->getRecords() as $hospitalRecord) {
				$thisHospital = new Hospital($hospitalRecord->getDetails(HOSPITAL_NAME_FIELD));
				$allSurveyRecords = $thisHospital->getAllSurveys();

				$matchingSurveyRecords = $allSurveyRecords->filterRecords([RecordSet::getKeyComparatorPair(SURVEY_EVENT_NAME_FIELD,"IN") => [$eventName],
						SURVEY_EVENT_YEAR_FIELD => $eventYear]);

				foreach($matchingSurveyRecords->getRecords() as $matchingRecord) {
					## TODO Probably need to look into how we match $currentYear with events occurring near the end of the year.
					## Could potentially send them the following year instead. May need to add $eventYear to the surveys?

					/** @var \Plugin\SurveyTemplate $matchingTemplate */
					$matchingTemplate = $surveyTemplates[$matchingRecord->getDetails(SURVEY_TEMPLATE_ID)];
					if($matchingTemplate == "") {
						continue;
					}

					if($matchingRecord->getDetails($matchingTemplate->getSurveyForm()."_complete") != 2) {
						continue 2;
					}
					self::$allHospitalLatestQuarterly[$eventName."~".$eventYear][$matchingRecord->getDetails(SURVEY_TEMPLATE_ID)][] = $matchingRecord;
				}
			}
		}
		return self::$allHospitalLatestQuarterly[$eventName."~".$eventYear];
	}

	public static function convertInventoryForOutput($inventoryArray,$pdf) {
		$inventoryOutput = [];

		foreach($inventoryArray as $component => $componentData) {
			$componentOutput = $component;
			foreach($componentData as $type => $typeData) {
				foreach($typeData as $subType => $subTypeData) {
					foreach($subTypeData as $equipmentName => $equipmentData) {
						$receivedCount = $equipmentData[Reporting::RECEIVED_THIS_QUARTER];
						$inventoryCount = $equipmentData[Reporting::INVENTORY_COUNT];
						$uom = $equipmentData[Reporting::UOM];
						$depletionCount = $equipmentData[Reporting::DEMAND_RATE];
						$per100Beds = $equipmentData[Reporting::PER_100_BEDS];
						$daysSupply = $equipmentData[Reporting::DAYS_SUPPLY];
						$beginningCount = $equipmentData[Reporting::OLD_INVENTORY];

						$outputArray = [$componentOutput,$type,$subType,$equipmentName];
						$componentOutput = "";

						foreach([0 => 90, 1 => 120,2 => 150,3 => 120] as $index => $maxWidth) {
							$newString = $outputArray[$index];
							$stringWidth = $pdf->GetStringWidth($newString);

							if($stringWidth > $maxWidth) {
								$condensedString = "";
								$explodedString = explode(" ",$newString);
								$currentLine = "";
								foreach($explodedString as $thisWord) {
									if($pdf->GetStringWidth($currentLine.$thisWord." ") > $maxWidth) {
										$condensedString .= "\n";
										$currentLine = "";
									}
									$currentLine .= $thisWord." ";
									$condensedString .= $thisWord." ";
								}
								$outputArray[$index] = $condensedString;
							}
						}
						$totalBeginningCount = 0;
						$totalReceived = 0;
						$totalInventory = 0;
						$totalDepletion = 0;
						$totalPer100Beds = 0;
						$totalDaysSupply = 0;
						$newUom = $uom;

						## If there's more than one type of uom, everything needs to be re-calculated
						if(is_array($uom)) {
							$days = $equipmentData[Reporting::DAYS][0];

							$currentUom = "";
							$allSameUnit = true;
							foreach($uom as $thisUom) {
								if($currentUom != "" && $currentUom != $thisUom) {
									$allSameUnit = false;
									break;
								}
								$currentUom = $thisUom;
							}
							if($allSameUnit) {
								$newUom = $currentUom;
								$totalBeginningCount = array_sum($beginningCount);
								$totalReceived = array_sum($receivedCount);
								$totalInventory = array_sum($inventoryCount);
								$totalPer100Beds = array_sum($per100Beds);
							}
							else {
								$newUom = "Singles";
								foreach($uom as $makeKey => $thisUom) {
									$totalBeginningCount += $beginningCount[$makeKey] * ($thisUom == "Pairs" ? 2 : 1);
									$totalReceived += $receivedCount[$makeKey] * ($thisUom == "Pairs" ? 2 : 1);
									$totalInventory += $inventoryCount[$makeKey] * ($thisUom == "Pairs" ? 2 : 1);
									$totalPer100Beds += $per100Beds[$makeKey] * ($thisUom == "Pairs" ? 2 : 1);
								}
							}

							if($totalBeginningCount > 0) {
								$totalDepletion = ($totalBeginningCount + $totalReceived - $totalInventory) / $days;
								$totalDaysSupply = $totalInventory / $totalDepletion;
							}
							else {
								$totalDepletion = "";
								$totalDaysSupply = "";
							}
						}
						else {
							$totalReceived = $receivedCount;
							$totalInventory = $inventoryCount;
							$totalDepletion = $depletionCount;
							$totalPer100Beds = $per100Beds;
							$totalDaysSupply = $daysSupply;
						}
						$outputArray = array_merge($outputArray,[
								$totalReceived,
								$totalInventory,
								$newUom,
								$totalDepletion,
								$totalPer100Beds,
								$totalDaysSupply]);
						$inventoryOutput[] = $outputArray;
					}
				}
			}
		}

		return $inventoryOutput;
	}

	public function daysPerQuarter($currentRecord){
		if(!$currentRecord || $currentRecord->getDetails(SURVEY_EVENT_NAME_FIELD) == "" || $currentRecord->getDetails(SURVEY_EVENT_YEAR_FIELD) == "") {
			return 0;
		}
		$eventName = $currentRecord->getDetails(SURVEY_EVENT_NAME_FIELD);
		$eventYear = $currentRecord->getDetails(SURVEY_EVENT_YEAR_FIELD);
		$templateProject = new Project(TEMPLATE_PROJECT_ID);
		$surveyTemplate = new SurveyTemplate(Record::createRecordFromId($templateProject, $currentRecord->getDetails(SURVEY_TEMPLATE_ID)));

		$priorEvent = $surveyTemplate->getPriorEvent($eventName);
		$eventTime = strtotime($surveyTemplate->getEventPrefillData($eventName,$eventYear)[ANNUAL_DATE]);
		$priorYear = $eventYear;
		if($eventTime < strtotime($surveyTemplate->getEventPrefillData($priorEvent, $eventYear)[ANNUAL_DATE])) {
			$priorYear--;
		}

		$timeBetween = $eventTime - strtotime($surveyTemplate->getEventPrefillData($priorEvent,$priorYear)[ANNUAL_DATE]);

		$days = round($timeBetween / (60*60*24),0);

		return $days;


	}

	public static function addAppendixHeader($pdf, $hospital, $headerLabel, $currentSection, $orientation = "P",$tableSettings) {
		$appendixHeaderFont = [PDF_MemImage::FONT_STYLE => "",PDF_MemImage::FONT_SIZE => 20, PDF_MemImage::FONT_NAME => "helvetica",
				PDF_MemImage::FONT_COLOR => [200,0,0],PDF_MemImage::FONT_ORIENTATION => "0",
				PDF_MemImage::FONT_ALIGN => 'C',PDF_MemImage::FONT_WIDTH => 200];

		self::addNewPageToPdf($pdf, $hospital, $headerLabel,$currentSection, $orientation);
		$currentY = 20;
		$pdf->printText(GROUP_LABEL_X,$currentY,self::$currentHeader,$appendixHeaderFont);
		$currentY += 12;

		$inventoryHeaders = ["Component","Type","Sub-Type","Inventory","Received\nThis\nQuarter",
				"Inventory\nCount","Unit of\nMeasure","Demand\nRate\n(units/day)","Inventory\nCount per\n100\nStaffed Beds","Days Supply"];
		$currentY = $pdf->printTable(GROUP_LABEL_X,$currentY,$inventoryHeaders,[],$tableSettings,"");

		return $currentY;
	}
}