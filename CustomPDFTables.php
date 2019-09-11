<?php

namespace Vanderbilt\CustomPDFTables;

use DateTimeRC;
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use LogicTester;
use Plugin\PDF_MemImage;
use System;

include_once(__DIR__."/Libraries/PDF/FPDF/PDF_MemImage.php");

class CustomPDFTables extends AbstractExternalModule
{
    const FILL_COLOR = "fill_color";
    public $record_id,$event_arm;
    public $colorConversions = array(
        array("name"=>"black","hex"=>"#000000","rgb"=>[0,0,0]),
        array("name"=>"white","hex"=>"#FFFFFF","rgb"=>[255,255,255]),
        array("name"=>"red","hex"=>"#FF0000","rgb"=>[255,0,0]),
        array("name"=>"lime","hex"=>"#00FF00","rgb"=>[0,255,0]),
        array("name"=>"blue","hex"=>"#0000FF","rgb"=>[0,0,255]),
        array("name"=>"yellow","hex"=>"#FFFF00","rgb"=>[255,255,0]),
        array("name"=>"cyan","hex"=>"#00FFFF","rgb"=>[0,255,255]),
        array("name"=>"aqua","hex"=>"#00FFFF","rgb"=>[0,255,255]),
        array("name"=>"magenta","hex"=>"#FF00FF","rgb"=>[255,0,255]),
        array("name"=>"fuchsia","hex"=>"#FF00FF","rgb"=>[255,0,255]),
        array("name"=>"silver","hex"=>"#C0C0C0","rgb"=>[192,192,192]),
        array("name"=>"gray","hex"=>"#808080","rgb"=>[128,128,128]),
        array("name"=>"grey","hex"=>"#808080","rgb"=>[128,128,128]),
        array("name"=>"maroon","hex"=>"#800000","rgb"=>[128,0,0]),
        array("name"=>"olive","hex"=>"#808000","rgb"=>[128,128,0]),
        array("name"=>"green","hex"=>"#008000","rgb"=>[0,128,0]),
        array("name"=>"purple","hex"=>"#800080","rgb"=>[128,0,128]),
        array("name"=>"teal","hex"=>"#008080","rgb"=>[0,128,128]),
        array("name"=>"navy","hex"=>"#000080","rgb"=>[0,0,128]),
        array("name"=>"dark red","hex"=>"#8B0000","rgb"=>[139,0,0]),
        array("name"=>"brown","hex"=>"#A52A2A","rgb"=>[165,42,42]),
        array("name"=>"firebrick","hex"=>"#B22222","rgb"=>[178,34,34]),
        array("name"=>"crimson","hex"=>"#DC143C","rgb"=>[220,20,60]),
        array("name"=>"tomato","hex"=>"#FF6347","rgb"=>[255,99,71]),
        array("name"=>"coral","hex"=>"#FF7F50","rgb"=>[255,127,80]),
        array("name"=>"indian red","hex"=>"#CD5C5C","rgb"=>[205,92,92]),
        array("name"=>"light coral","hex"=>"#F08080","rgb"=>[240,128,128]),
        array("name"=>"dark salmon","hex"=>"#E9967A","rgb"=>[233,150,122]),
        array("name"=>"salmon","hex"=>"#FA8072","rgb"=>[250,128,114]),
        array("name"=>"light salmon","hex"=>"#FFA07A","rgb"=>[255,160,122]),
        array("name"=>"orange red","hex"=>"#FF4500","rgb"=>[255,69,0]),
        array("name"=>"dark orange","hex"=>"#FF8C00","rgb"=>[255,140,0]),
        array("name"=>"orange","hex"=>"#FFA500","rgb"=>[255,165,0]),
        array("name"=>"gold","hex"=>"#FFD700","rgb"=>[255,215,0]),
        array("name"=>"dark golden rod","hex"=>"#B8860B","rgb"=>[184,134,11]),
        array("name"=>"golden rod","hex"=>"#DAA520","rgb"=>[218,165,32]),
        array("name"=>"pale golden rod","hex"=>"#EEE8AA","rgb"=>[238,232,170]),
        array("name"=>"dark khaki","hex"=>"#BDB76B","rgb"=>[189,183,107]),
        array("name"=>"khaki","hex"=>"#F0E68C","rgb"=>[240,230,140]),
        array("name"=>"yellow green","hex"=>"#9ACD32","rgb"=>[154,205,50]),
        array("name"=>"dark olive green","hex"=>"#556B2F","rgb"=>[85,107,47]),
        array("name"=>"olive drab","hex"=>"#6B8E23","rgb"=>[107,142,35]),
        array("name"=>"lawn green","hex"=>"#7CFC00","rgb"=>[124,252,0]),
        array("name"=>"chart reuse","hex"=>"#7FFF00","rgb"=>[127,255,0]),
        array("name"=>"green yellow","hex"=>"#ADFF2F","rgb"=>[173,255,47]),
        array("name"=>"dark green","hex"=>"#006400","rgb"=>[0,100,0]),
        array("name"=>"forest green","hex"=>"#228B22","rgb"=>[34,139,34]),
        array("name"=>"lime green","hex"=>"#32CD32","rgb"=>[50,205,50]),
        array("name"=>"light green","hex"=>"#90EE90","rgb"=>[144,238,144]),
        array("name"=>"pale green","hex"=>"#98FB98","rgb"=>[152,251,152]),
        array("name"=>"dark sea green","hex"=>"#8FBC8F","rgb"=>[143,188,143]),
        array("name"=>"medium spring green","hex"=>"#00FA9A","rgb"=>[0,250,154]),
        array("name"=>"spring green","hex"=>"#00FF7F","rgb"=>[0,255,127]),
        array("name"=>"sea green","hex"=>"#2E8B57","rgb"=>[46,139,87]),
        array("name"=>"medium aqua marine","hex"=>"#66CDAA","rgb"=>[102,205,170]),
        array("name"=>"medium sea green","hex"=>"#3CB371","rgb"=>[60,179,113]),
        array("name"=>"light sea green","hex"=>"#20B2AA","rgb"=>[32,178,170]),
        array("name"=>"dark slate gray","hex"=>"#2F4F4F","rgb"=>[47,79,79]),
        array("name"=>"dark cyan","hex"=>"#008B8B","rgb"=>[0,139,139]),
        array("name"=>"light cyan","hex"=>"#E0FFFF","rgb"=>[224,255,255]),
        array("name"=>"dark turquoise","hex"=>"#00CED1","rgb"=>[0,206,209]),
        array("name"=>"turquoise","hex"=>"#40E0D0","rgb"=>[64,224,208]),
        array("name"=>"medium turquoise","hex"=>"#48D1CC","rgb"=>[72,209,204]),
        array("name"=>"pale turquoise","hex"=>"#AFEEEE","rgb"=>[175,238,238]),
        array("name"=>"aqua marine","hex"=>"#7FFFD4","rgb"=>[127,255,212]),
        array("name"=>"powder blue","hex"=>"#B0E0E6","rgb"=>[176,224,230]),
        array("name"=>"cadet blue","hex"=>"#5F9EA0","rgb"=>[95,158,160]),
        array("name"=>"steel blue","hex"=>"#4682B4","rgb"=>[70,130,180]),
        array("name"=>"corn flower blue","hex"=>"#6495ED","rgb"=>[100,149,237]),
        array("name"=>"deep sky blue","hex"=>"#00BFFF","rgb"=>[0,191,255]),
        array("name"=>"dodger blue","hex"=>"#1E90FF","rgb"=>[30,144,255]),
        array("name"=>"light blue","hex"=>"#ADD8E6","rgb"=>[173,216,230]),
        array("name"=>"sky blue","hex"=>"#87CEEB","rgb"=>[135,206,235]),
        array("name"=>"light sky blue","hex"=>"#87CEFA","rgb"=>[135,206,250]),
        array("name"=>"midnight blue","hex"=>"#191970","rgb"=>[25,25,112]),
        array("name"=>"dark blue","hex"=>"#00008B","rgb"=>[0,0,139]),
        array("name"=>"medium blue","hex"=>"#0000CD","rgb"=>[0,0,205]),
        array("name"=>"royal blue","hex"=>"#4169E1","rgb"=>[65,105,225]),
        array("name"=>"blue violet","hex"=>"#8A2BE2","rgb"=>[138,43,226]),
        array("name"=>"indigo","hex"=>"#4B0082","rgb"=>[75,0,130]),
        array("name"=>"dark slate blue","hex"=>"#483D8B","rgb"=>[72,61,139]),
        array("name"=>"slate blue","hex"=>"#6A5ACD","rgb"=>[106,90,205]),
        array("name"=>"medium slate blue","hex"=>"#7B68EE","rgb"=>[123,104,238]),
        array("name"=>"medium purple","hex"=>"#9370DB","rgb"=>[147,112,219]),
        array("name"=>"dark magenta","hex"=>"#8B008B","rgb"=>[139,0,139]),
        array("name"=>"dark gray","hex"=>"#A9A9A9","rgb"=>[169,169,169]),
        array("name"=>"dark grey","hex"=>"#A9A9A9","rgb"=>[169,169,169]),
        array("name"=>"light gray","hex"=>"#D3D3D3","rgb"=>[211,211,211]),
        array("name"=>"light grey","hex"=>"#D3D3D3","rgb"=>[211,211,211]),
        array("name"=>"light yellow","hex"=>"#FFFFE0","rgb"=>[255,255,224])
    );

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($this->getModuleDirectoryName());
        $url = ExternalModules::getUrl($prefix, "download_pdf.php")."&pid=$project_id&id=$record&instrument=$instrument&event_id=$event_id&instance=$repeat_instance";
        $javaString = "";
        $instrumentList = $this->getProjectSetting('form');

        if (in_array($instrument,$instrumentList)) {
            echo "<script>
                jQuery(document).ready(function() {
                    var customPDFURL = encodeURI('$url');
                    //console.log(customPDFURL);
                    jQuery('#dataEntryTopOptionsButtons').after('<button style=\"padding:3px;margin-left:55px;margin-top:5px;\" class=\"jqbuttonmed ui-button ui-corner-all ui-widget\" id=\"customPdfDownload\" onclick=\"window.location.href=\''+customPDFURL+'\'\">Download Custom PDF</button>');
                });
            </script>";
        }
        echo $javaString;
        /*$testCalc = "if ([site] = 1, if (([phq9_01score] > 0 AND [phq9_01score] < 5) AND [thoughts] < 1, [phq9_01score], ''),if ([site] != '', if (([phq9_t_score_2] > 0 AND [phq9_t_score_2] < 5) AND [thoughts_2] < 1, [phq9_t_score_2], ''),''))";
        $recordData = \Records::getData();
        $calcVal = $this->getCalculatedData($testCalc,$recordData,$event_id,$project_id,'teen_phq');
        echo "Calc Val: $calcVal<br/>";*/
        //echo "<a href='$url'>Testing</a>";
    }

    function processTableSettings($recordData, $project_id, $record, $instrument, $event_id, $group_id, $repeat_instance=null) {
        $tablePosition = $this->getProjectSetting('position');
        $tableForm = $this->getProjectSetting('form');
        $settingsString = $this->getProjectSetting('table-settings');
        $combinedSettings = array();

        foreach ($tableForm as $index => $form) {
            $combinedSettings[$index]['form'] = $form;
            $combinedSettings[$index]['position'] = $tablePosition[$index];
            if ($settingsString[$index] != "") {
                $settingsArray = $this->convertJSONtoArray($settingsString[$index]);

                if ($settingsArray === false) {
                    $settingsArray = array();
                }
                $combinedSettings[$index]['table-settings'] = $this->parseDefaultSettings($recordData, $project_id,$record,$event_id,$instrument,$group_id,$repeat_instance,$settingsArray);
            }
        }
        $project = new \Project($project_id);
        $projectMetaData = $project->metadata;
        $projectForms = $project->forms;

        $pdf = new \Plugin\PDF_MemImage();

        foreach ($projectForms as $formName => $formData) {
            $formMeta = array();
            foreach ($formData['fields'] as $fieldName) {
                $formMeta[$fieldName] = $projectMetaData[$fieldName];
            }
        }

        //return $pdf->Output("Test.pdf",'S');
        return $combinedSettings;
    }

    function parseDefaultSettings($recordData,$project_id,$record_id,$event_id,$instrument,$group_id,$repeat_instance,$settingsArray) {
        $pdf = new \Plugin\PDF_MemImage();
        $tableSettings = array();

        //$recordData = \Records::getData();
        if (isset($settingsArray['header']) && !empty($settingsArray['header'])) {
            $header = $settingsArray['header'];
            $tableSettings['header'][$pdf::HEADER_FONT_SIZE] = $header['font_size'] != "" && is_numeric($header['font_size']) ? $header['font_size'] : 12;
            $tableSettings['header'][$pdf::HEADER_FILL_COLOR] = isset($header['fill_color']) ? $header['fill_color'] : [64,128,200];
            $tableSettings['header'][$pdf::HEADER_TEXT_COLOR] = isset($header['font_color']) ? $header['font_color'] : [0,0,0];
            $tableSettings['header'][$pdf::FONT_NAME] = isset($header['font_name']) && $header['font_name'] != "" ? $header['font_name'] : "DejaVu";
            $tableSettings['header'][$pdf::HEADER_ALIGN] = in_array($header['font_align'],array("C","L","R")) ? $header['font_align'] : 'C';
            $tableSettings['header'][$pdf::HEADER_PADDING] = (is_numeric($header['padding']) && $header['padding'] != "") ? $header['padding'] : $tableSettings['header'][$pdf::HEADER_FONT_SIZE]/2;
            $tableSettings['header'][$pdf::HEADER_FONT_STYLE] = preg_match('/^[BIU]++$/',$header['font_style']) ? $header['font_style'] : "B";
            $tableSettings['header'][$pdf::HEADER_BORDER] = $header['border'] != "" && is_numeric($header['border']) ? $header['border'] : 1;
        }
        if (isset($settingsArray['table_body']) && isset($settingsArray['table_body'])) {
            $rowSettings = array();
            foreach ($settingsArray['table_body'] as $row => $rowData) {
                $rowSettings[$pdf::FONT_NAME] = isset($rowData['font_name']) && $rowData['font_name'] != "" ? $rowData['font_name'] : "DejaVu";
                $rowSettings[$pdf::FONT_SIZE] = $rowData['font_size'] != "" && is_numeric($rowData['font_size']) ? $rowData['font_size'] : 10;
                $rowSettings[$this::FILL_COLOR] = isset($rowData[$this::FILL_COLOR]) ? $rowData[$this::FILL_COLOR] : [255,255,255];
                $rowSettings[$pdf::NUMBER_DECIMALS] = $rowData['num_decimal'] != "" && is_numeric($rowData['num_decimal']) ? $rowData['num_decimal'] : 0;
                $rowSettings[$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT] = $rowData['wc_height'] != "" && is_numeric($rowData['wc_height']) ? $rowData['wc_height'] : 30;
                $rowSettings[$pdf::FONT_COLOR] = isset($rowData['font_color']) && $rowData['font_color'] != "" ? $rowData['font_color'] : [0,0,0];
                $rowSettings[$pdf::FONT_STYLE] = preg_match('/^[BIU]++$/',$rowData['font_style']) ? $rowData['font_style'] : "";
                $rowSettings[$pdf::CELL_BORDERS] = isset($rowData['border']) && is_numeric($rowData['border']) ? $rowData['border'] : 1;
                $rowSettings[$pdf::FONT_ORIENTATION] = isset($rowData['font_orientation']) ? $rowData['font_orientation'] : "C";
                $rowSettings[$pdf::TABLE_WIDTH_SETTINGS] = isset($rowData[$pdf::TABLE_WIDTH_SETTINGS]) && is_numeric($rowData[$pdf::TABLE_WIDTH_SETTINGS]) ? $rowData[$pdf::TABLE_WIDTH_SETTINGS] : 30;

                foreach ($rowData['columns'] as $column => $columnData) {
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::FONT_NAME] = isset($columnData['font_name']) && $columnData['font_name'] != "" ? $columnData['font_name'] : $rowSettings[$pdf::FONT_NAME];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::FONT_SIZE] = $columnData['font_size'] != "" && is_numeric($columnData['font_size']) ? $columnData['font_size'] : $rowSettings[$pdf::FONT_SIZE];
                    $tableSettings['table_body']['settings'][$row][$column][$this::FILL_COLOR] = isset($columnData[$this::FILL_COLOR]) ? $columnData[$this::FILL_COLOR] : $rowSettings[$this::FILL_COLOR];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::NUMBER_DECIMALS] = $columnData['num_decimal'] != "" && is_numeric($columnData['num_decimal']) ? $columnData['num_decimal'] : $rowSettings[$pdf::NUMBER_DECIMALS];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT] = $columnData['wc_height'] != "" && is_numeric($columnData['wc_height']) ? $columnData['wc_height'] : $rowSettings[$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::FONT_COLOR] = isset($columnData['font_color']) && $columnData['font_color'] != "" ? $columnData['font_color'] : $rowSettings[$pdf::FONT_COLOR];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::FONT_STYLE] = preg_match('/^[BIU]++$/',$columnData['font_style']) ? $columnData['font_style'] : $rowSettings[$pdf::FONT_STYLE];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::CELL_BORDERS] = isset($columnData['border']) && is_numeric($columnData['border']) ? $columnData['border'] : $rowSettings[$pdf::CELL_BORDERS];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::FONT_ORIENTATION] = isset($columnData['font_orientation']) ? $columnData['font_orientation'] : $rowSettings[$pdf::FONT_ORIENTATION];
                    $tableSettings['table_body']['settings'][$row][$column][$pdf::TABLE_WIDTH_SETTINGS] = isset($columnData[$pdf::TABLE_WIDTH_SETTINGS]) ? $columnData[$pdf::TABLE_WIDTH_SETTINGS] : $rowSettings[$pdf::TABLE_WIDTH_SETTINGS];
                    echo "Value is: ".$columnData['value'].", $event_id,$project_id,$instrument<br/>";
                    $tableSettings['table_body']['data'][$row][$column] = $this->getCalculatedData($columnData['value'],$recordData,$event_id,$project_id,$instrument,$repeat_instance);
                    echo "Calculated is ".$tableSettings['table_body']['data'][$row][$column]."<br/>";
                }
            }
        }

        return $tableSettings;
    }

    function getCalculatedData($calcString,$recordData,$event_id,$project_id,$repeat_instrument,$repeat_instance=null) {
        $formatCalc = \Calculate::formatCalcToPHP($calcString);
        //echo "The string!!<br/>$formatCalc<br/>";
        $parser = new \LogicParser();
        try {
            list($funcName, $argMap) = $parser->parse($formatCalc, $event_id, true, false);
            $thisInstanceArgMap = $argMap;
            $Proj = new \Project($project_id);
            foreach ($thisInstanceArgMap as &$theseArgs) {
                $theseArgs[0] = $event_id;
            }
            //echo "Form: ".$Proj->metadata['age']['form_name']."<br/>";

            if ($repeat_instance != "") {
                foreach ($thisInstanceArgMap as &$theseArgs) {
                    // If there is no instance number for this arm map field, then proceed
                    if ($theseArgs[3] == "") {
                        $thisInstanceArgEventId = ($theseArgs[0] == "") ? $event_id : $theseArgs[0];
                        $thisInstanceArgEventId = is_numeric($thisInstanceArgEventId) ? $thisInstanceArgEventId : $Proj->getEventIdUsingUniqueEventName($thisInstanceArgEventId);
                        $thisInstanceArgField = $theseArgs[1];
                        $thisInstanceArgFieldForm = $Proj->metadata[$thisInstanceArgField]['form_name'];
                        // If this event or form/event is repeating event/instrument, the add the current instance number to arg map
                        if ( // Is a valid repeating instrument?
                            ($repeat_instrument != '' && $thisInstanceArgFieldForm == $repeat_instrument && $Proj->isRepeatingForm($thisInstanceArgEventId, $thisInstanceArgFieldForm))
                            // Is a valid repeating event?
                            || ($repeat_instrument == '' && $Proj->isRepeatingEvent($thisInstanceArgEventId) && in_array($thisInstanceArgFieldForm, $Proj->eventsForms[$thisInstanceArgEventId]))) {
                            $theseArgs[3] = $repeat_instance;
                        }
                    }
                }
                unset($theseArgs);
            }
            /*echo "<pre>";
            print_r($thisInstanceArgMap);
            echo "</pre>";*/
            foreach ($recordData as $record => &$this_record_data1) {
                $calculatedCalcVal = \LogicTester::evaluateCondition(null, $this_record_data1, $funcName, $thisInstanceArgMap, null);
                foreach (parseEnum(strip_tags(label_decode($Proj->metadata[$thisInstanceArgMap[count($thisInstanceArgMap) - 1][1]]['element_enum']))) as $this_code => $this_choice) {
                    if ($calculatedCalcVal === $this_code) {
                        $calculatedCalcVal = $this_choice;
                        break;
                    }
                }
            }
        }
        catch (\Exception $e) {
            if (strpos($e->getMessage(),"Parse error in input:") === 0 || strpos($e->getMessage(),"Unable to find next token in") === 0) {
                return $calcString;
            }
            else {
                return "";
            }
        }
        return $calculatedCalcVal;
    }

    function convertJSONtoArray($json) {
        if (!is_string($json)) return false;
        $settingsArray = json_decode($json,true);
        if (!is_array($settingsArray)) return false;

        return $settingsArray;
    }

    public function generateFormForRecord(PDF_MemImage &$pdf,$currentY,$recordData,$formMetadata,$fullMetaData,$formTitle,$tableSettings) {
        //$pdf = new \Plugin\PDF_MemImage();
        $pdf->SetAutoPageBreak(false);

        $headerFont = [\Plugin\PDF_MemImage::FONT_SIZE => 12,\Plugin\PDF_MemImage::FONT_COLOR => [127,0,0],\Plugin\PDF_MemImage::FONT_STYLE => "B",
            \Plugin\PDF_MemImage::FONT_NAME => "DejaVu", \Plugin\PDF_MemImage::FONT_ALIGN => 'C'];

        $tableHeader1 = [\Plugin\PDF_MemImage::HEADER_FONT_SIZE => 12,\Plugin\PDF_MemImage::HEADER_FILL_COLOR => [219,229,241],
            \Plugin\PDF_MemImage::FONT_STYLE => "B", \Plugin\PDF_MemImage::FONT_NAME => "DejaVu",
            \Plugin\PDF_MemImage::HEADER_ALIGN => 'L', \Plugin\PDF_MemImage::HEADER_TEXT_COLOR => [0,0,0],
            \Plugin\PDF_MemImage::TABLE_WIDTH_SETTINGS => [70,120],
            \Plugin\PDF_MemImage::HEADER_PADDING => 0];

        $tableBody1 = [\Plugin\PDF_MemImage::FONT_SIZE => 9,\Plugin\PDF_MemImage::FONT_COLOR => [0,0,0],\Plugin\PDF_MemImage::FONT_STYLE => "",
            \Plugin\PDF_MemImage::FONT_NAME => "DejaVu", \Plugin\PDF_MemImage::FONT_ALIGN => 'L',\Plugin\PDF_MemImage::TABLE_WIDTH_SETTINGS => [70,120],
            \Plugin\PDF_MemImage::FONT_ORIENTATION => ['L','L']];

        $currentStyle = array_merge($tableHeader1,$tableBody1);

        foreach($recordData as $thisRecordData) {
            if ($currentY == 0) {
                $currentY = self::addNewPage($pdf);
                $pdf->printText(10,$currentY,$formTitle,$headerFont);
            }

            $currentY += 5;

            $tableData = [];
            $colorMapping = [];
            $currentTableData = ["header" => "","rows" => []];
            foreach($formMetadata as $fieldName => $fieldDetails) {
                $sectionHeader = preg_replace("/\\<.*?\\>/","",$fieldDetails["section_header"]);
                if($sectionHeader) {
                    $tableData[] = $currentTableData;
                    $currentTableData = ["header" => $sectionHeader,"rows" => [],"colors"=>[]];
                }

                $value = $this->br2nl($thisRecordData[$fieldName]);
                $isVisible = true;

                ## Check action tags for hiddenness
                if (\Form::hasHiddenOrHiddenSurveyActionTag($fieldDetails['misc'])) {
                    $isVisible = false;
                }

                ## Check if branching_logic will hide the data
                $logic = $fieldDetails['branching_logic'];
                if ($logic != '' && $isVisible) {
                    $isVisible = self::evaluateBranchingLogic($thisRecordData,$logic);
                }

                if($isVisible) {
                    $enum = $fieldDetails["select_choices_or_calculations"];

                    if($enum != "") {
                        $enumChoices = explode("|",$enum);
                        $enumArray = [];
                        foreach($enumChoices as $keyValuePair) {
                            if (strpos($keyValuePair, ",")) {
                                $pos = strpos($keyValuePair, ",");
                                $thisValue = trim(substr($keyValuePair, 0, $pos));
                                $thisText = trim(substr($keyValuePair, $pos+1));

                                $enumArray[$thisValue] = $thisText;
                            }
                        }
                    }
                    $label = $this->br2nl($fieldDetails["field_label"]);
                    //echo "Label: ".htmlspecialchars(json_encode($label))."<br/>";
                    $colorInfo = $this->colorTextMapping($label);
                    ## Remove HTML tags from field label
                    $label = preg_replace("/\\<.*?\\>/","",$this->br2nl($fieldDetails["field_label"]));

                    ## Replace piping with record values
                    $label = preg_replace_callback("/(\\[)([a-z][a-z|_|0-9]*?)(\\])/", function ($matches) use ($thisRecordData,$fullMetaData) {
                        $fieldDetails = $fullMetaData[$matches[2]];
                        $fieldValue = $thisRecordData[$matches[2]];

                        $enum = $fieldDetails["select_choices_or_calculations"];

                        if($enum != "" && $fieldDetails['field_type'] !== 'calc') {
                            $enumChoices = explode("|",$enum);
                            $enumArray = [];
                            foreach($enumChoices as $keyValuePair) {
                                if (strpos($keyValuePair, ",")) {
                                    $pos = strpos($keyValuePair, ",");
                                    $thisValue = trim(substr($keyValuePair, 0, $pos));
                                    $thisText = trim(substr($keyValuePair, $pos+1));

                                    $enumArray[$thisValue] = $this->br2nl($thisText);
                                }
                            };
                            if (is_array($fieldValue)) {
                                $tmpString = "";
                                foreach ($fieldValue as $index => $theVal) {
                                    if ($theVal != 1) continue;
                                    if ($tmpString != "") {
                                        $tmpString .= ", ";
                                    }
                                    $tmpString .= $enumArray[$index];
                                }
                                $fieldValue = $tmpString;
                            }
                            else {
                                $fieldValue = $enumArray[$fieldValue];
                            }
                        }
                        return ($fieldValue == "" ? "''" : $this->br2nl($fieldValue));
                    }, $label);

                    switch($fieldDetails["field_type"]) {
                        case "descriptive":
                            $currentTableData["rows"][] = [$label => 2];
                            break;
                        case "checkbox":
                            $newValue = "";
                            foreach($value as $enumValue => $checked) {
                                if($checked) {
                                    $newValue .= ((strlen($newValue) > 0) ? ", " : "").$enumArray[$enumValue];
                                }
                            }
                            $value = $newValue;

                            $currentTableData["rows"][] = [$label,$value];
                            break;
                        case "file":
                            $value = $value ? "Attachment" : "";
                            $currentTableData["rows"][] = [$label,$value];
                            break;
                        case "yesno":
                            $value = ($value == 1 ? "Yes" : "No");
                            $currentTableData["rows"][] = [$label,$value];
                            break;
                        default:
                            if($enum != "") {
                                $value = $enumArray[$value];
                            }
                            $currentTableData["rows"][] = [$label,$value];
                    }
                    /*echo "<pre>";
                    print_r($colorInfo);
                    echo "</pre>";*/
                    $currentTableData["colors"][] = $colorInfo;
                }
            }
            $tableData[] = $currentTableData;

            foreach($tableData as $thisTable) {
                $headerArray = [];
                if($thisTable["header"] != "") {
                    $headerArray = [$thisTable["header"] => 2];
                }

                //			$currentY = $pdf->printTableIfRoom(10,$currentY + 5,$headerArray,$thisTable["rows"],$currentStyle);

                if(($currentY + 20) > $pdf->h) {
                    $currentY = -5 + self::addNewPage($pdf);
                }
                /*echo "Header array:<br/>";
                echo "<pre>";
                print_r($headerArray);
                echo "</pre>";
                echo "Table Data:<br/>";
                echo "<pre>";
                print_r($thisTable['rows']);
                echo "</pre>";
                echo "Styling:<br/>";
                echo "<pre>";
                print_r($currentStyle);
                echo "</pre>";*/
                /*echo "Doing a table row:<br/>";
                echo "<pre>";
                print_r($thisTable['rows']);
                echo "</pre>";*/
                if (array_filter($thisTable['colors'])) {
                    $currentY = $this->printColorTable(10, $currentY + 5, $headerArray, $thisTable["rows"], $currentStyle, "\\Plugin\\PDF_MemImage::addNewPage", [$pdf], $thisTable['colors']);
                }
                else {
                    $currentY = $pdf->printTable(10, $currentY + 5, $headerArray, $thisTable["rows"], $currentStyle, "\\Plugin\\PDF_MemImage::addNewPage", [$pdf]);
                }
            }
        }

        //return $pdf->Output("Test.pdf",'S');
        return $currentY;
    }

    public function generateCustomTable(PDF_MemImage &$pdf,$currentY,$recordData,$formMetadata,$formTitle,$tableOptions) {
        //$pdf = new \Plugin\PDF_MemImage();
        $pdf->SetAutoPageBreak(false);
        $overflowFunction = "addNewPage";
        $overflowParameters = [$pdf];
        $x = 10;
        foreach ($tableOptions as $index => $subSettings) {
            $currentX = 10;
            $currentColumn = 0;
            if ($currentY == 0) {
                $headerFont = [$pdf::FONT_SIZE => 12,$pdf::FONT_COLOR => [127,0,0],$pdf::FONT_STYLE => "B", $pdf::FONT_NAME => "DejaVu", $pdf::FONT_ALIGN => 'C'];
                $currentY = self::addNewPage($pdf);
                $pdf->printText(10,$currentY,$formTitle,$headerFont);
            }

            $currentY += 10;

            if ($formMetadata != $subSettings['form']) continue;
            if (isset($subSettings['table-settings']['table_body'])) {
                $tableSettings = $subSettings['table-settings']['table_body']['settings'][0][0];
                //$tableData = $subSettings['table-settings']['table_body']['data'];
                $tableData = $this->fitCustomDatatoWidth($pdf,$subSettings['table-settings']['table_body']['data'],$subSettings['table-settings']['table_body']['settings']);

                //$tableData = $pdf->fitDataToColumnWidth($subSettings['table-settings']['table_body']['data'],$tableSettings);

                    /*echo "TRow:<br/>";
                    echo "<pre>";
                    print_r($tRow);
                    echo "</pre>";*/
                    ## Stretch $tableData as need to fit into column widths

                    //$tableData = $pdf->fitDataToColumnWidth($tRow['data'],$tableSettings);

                    $rowToAdd = [];
                    /*echo "My new tabledata:<br/>";
                    echo "<pre>";
                    print_r($tableData);
                    echo "</pre>";*/
                    for($i = 0; ($i < count($tableData) || count($rowToAdd) > 0); $i++) {
                        $alignmentArray = [];

                        for($currentColumn = 0; $currentColumn < max(count($tableData[$i])); $currentColumn++) {
                            $alignmentArray[] = $tableSettings[$pdf::FONT_ORIENTATION][$currentColumn] == "" ? 'J' : $tableSettings[$pdf::FONT_ORIENTATION][$currentColumn];
                        }
                        ## Sometimes new rows will be added in order to wrap cells onto multiple pages
                        if(count($rowToAdd) > 0) {
//				error_log("Row to Add at $currentY: ".implode("||",$rowToAdd));
                            $tableRow = $rowToAdd;
//				$pdf->printText(0,5,"Starting overflow",[PDF_MemImage::FONT_SIZE => 9,PDF_MemImage::FONT_COLOR => [0,0,0],PDF_MemImage::FONT_NAME => "helvetica"]);
                            $rowToAdd = [];
                            $i--;

                            $currentY = "";
                            ## Wrap to new page when having a "rowToAdd"
                            if($overflowFunction) {
                                if($overflowParameters) {
                                    $currentY = call_user_func($overflowFunction,$overflowParameters[0],$overflowParameters[1],$overflowParameters[2],
                                        $overflowParameters[3],$overflowParameters[4],$overflowParameters[5]);
                                }
                                else {
                                    $currentY = call_user_func($overflowFunction);
                                }
                            }
                            else {
                                if($pdf->h > 220) {
                                    //$pdf->AddPage("P", "A4");
                                    self::addNewPage($pdf);
                                }
                                else {
                                    //$pdf->AddPage("L", "A4");
                                    self::addNewPage($pdf);
                                }
                            }
                            if($currentY == "") {
                                $currentY = 10;
                            }
                            $pdf->SetFillColor(255,255,255);
                            $pdf->SetTextColor($tableSettings[$pdf::FONT_COLOR][0],$tableSettings[$pdf::FONT_COLOR][1],$tableSettings[$pdf::FONT_COLOR][2]);
                            $pdf->SetFont($tableSettings[$pdf::FONT_NAME],$tableSettings[$pdf::FONT_STYLE],$tableSettings[$pdf::FONT_SIZE]);
//				error_log("Wrapping from new row $currentY");
                        }
                        else {
                            $tableRow = $this->br2nl($tableData[$i]);
                            //$tableRow = $tableData[$i];
                        }
                        $currentX = $x;
                        /*echo "<pre>";
                        print_r($tableRow);
                        echo "</pre>";*/
                        foreach ($tableRow as $column => &$columnVal) {
                            $maxWidth = $subSettings['table-settings']['table_body']['settings'][$i][$column][$pdf::TABLE_WIDTH_SETTINGS] - 4;

                            $newString = $columnVal;
                            $stringWidth = $pdf->GetStringWidth($newString);

                            if($stringWidth > $maxWidth) {
                                $condensedString = "";
                                $explodedString = explode("\n",$newString);
                                $currentLine = "";
                                foreach($explodedString as $thisKey => $thisLine) {
                                    if($thisKey > 0) {
                                        $condensedString .= "\n";
                                        $currentLine = "";
                                    }
                                    $explodedLine = explode(" ",$thisLine);
                                    foreach($explodedLine as $thisWord) {
                                        if($pdf->GetStringWidth($currentLine.$thisWord." ") > $maxWidth) {
                                            $condensedString .= "\n";
                                            $currentLine = "";
                                        }
                                        $currentLine .= $thisWord." ";
                                        $condensedString .= $thisWord." ";
                                    }
                                }
                                $newString = $condensedString;
                            }
                            $columnVal = $newString;
                        }

                        $maxHeight = $pdf->getRowHeight($tableRow,$tableSettings);
                        $widthArray = $pdf->getColumnWidths($tableRow,$tableSettings);

//			error_log("Need to wrap? $currentY $maxHeight ~ ".$tableSettings[$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT]." > ".($pdf->h - 10)."");
                        ## If this row will exceed the remaining height in the page, but there's enough room to start the table, split the current row
                        if($tableSettings[$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT] < ($pdf->h - $currentY) && ($currentY + $maxHeight) > ($pdf->h - 10)) {
//				error_log("Splitting this thing");

                            $maxHeight = $pdf->getRowHeight($tableRow,$tableSettings);
                        }

                        ## If there's not enough room to print even part of this row, create new page and print it there.
                        ## This will wrap onto another page if there still isn't enough room
                        if(($currentY + $maxHeight) > ($pdf->h - 10)) {
//				error_log("Wrapping");
                            $currentY = "";
                            if($overflowFunction) {
                                if($overflowParameters) {
                                    $currentY = call_user_func($overflowFunction,$overflowParameters[0],$overflowParameters[1],$overflowParameters[2],
                                        $overflowParameters[3],$overflowParameters[4],$overflowParameters[5]);
                                }
                                else {
                                    $currentY = call_user_func($overflowFunction);
                                }
                            }
                            else {
                                if($pdf->h > 220) {
                                    //$pdf->AddPage("P", "A4");
                                    self::addNewPage($pdf);
                                }
                                else {
                                    //$pdf->AddPage("L", "A4");
                                    self::addNewPage($pdf);
                                }
                            }
                            if($currentY == "") {
                                $currentY = 10;
                            }

                            $pdf->SetFillColor(255,255,255);
                            $pdf->SetTextColor($tableSettings[$pdf::FONT_COLOR][0],$tableSettings[$pdf::FONT_COLOR][1],$tableSettings[$pdf::FONT_COLOR][2]);
                            $pdf->SetFont($tableSettings[$pdf::FONT_NAME],$tableSettings[$pdf::FONT_STYLE],$tableSettings[$pdf::FONT_SIZE]);

                            ## Check if this single cell will overflow and attempt to wrap the text to another page
                            if(($currentY + $maxHeight) > ($pdf->h - 10)) {
                                $splitRows = $pdf->splitRowByHeight($tableRow,$tableSettings,($pdf->h - 10 - $currentY));
                                $tableRow = $splitRows[0];
                                $rowToAdd = $splitRows[1];
                                $maxHeight = $pdf->getRowHeight($tableRow,$tableSettings);
                            }
                        }
                        $currentColumn = 0;
                        $columnWidthType = $pdf->rowSpecifiesColumnWidth($tableRow);

                        foreach($tableRow as $tableKey => $tableVal) {
                            $pdf->SetY($currentY);
                            $pdf->SetX($currentX);

                            $columnWidth = 1;
                            if($columnWidthType) {
                                $columnWidth = $tableVal;
                                $tableVal = $tableKey;
                            }
                            //$cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];
                            $cellWidth = $subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::TABLE_WIDTH_SETTINGS];

                            if(is_array($tableSettings[$pdf::NUMBER_DECIMALS])) {
                                $thisDecimal = $tableSettings[$pdf::NUMBER_DECIMALS][$currentColumn];
                            }
                            else {
                                $thisDecimal = $tableSettings[$pdf::NUMBER_DECIMALS];
                            }

                            ## Round the number based on required decimal places/turn to scientific notation is won't fit
                            if(is_numeric($tableVal) && abs($tableVal) > 999999) {
//					$origNumber = $tableVal;
                                $tableVal = round($tableVal,(4-strlen(round($tableVal,0))));
                                $tableVal = sprintf("%.3e",$tableVal);
//					echo "Found large $origNumber => $tableVal <Br />";
                            }
                            else {
                                $tableVal = (is_numeric($tableVal) && $thisDecimal !== $pdf::DECIMALS_MANUAL) ? number_format($tableVal,$thisDecimal) : $tableVal;
                            }

                            ## Then print the actual cell text second
//				error_log("Printing: ".$tableVal);
                            $pdf->SetFillColor($subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$this::FILL_COLOR][0],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$this::FILL_COLOR][1],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$this::FILL_COLOR][2]);
                            $pdf->SetTextColor($subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_COLOR][0],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_COLOR][1],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_COLOR][2]);
                            $pdf->SetFont($subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_NAME],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_STYLE],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_SIZE]);
                            /*echo "I'm printing out the cell for ".htmlspecialchars(json_encode($tableVal))."<br/>";
                            echo "Width: ".$cellWidth.", Height: ".$maxHeight.", Orient: ".$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_ORIENTATION]."<br/>";
                            echo "Actual height: ".(($subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_SIZE] / 2) + 1)."<br/>";*/
                            $pdf->cMargin = 0;
                            $pdf->MultiCell($cellWidth,$maxHeight / (substr_count($tableVal,"\n") + 1),$tableVal,$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::CELL_BORDERS],$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::FONT_ORIENTATION],true);

                            ## Print borders first if needed
                            /*if($subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::CELL_BORDERS]) {
                                $pdf->MultiCell($cellWidth,$maxHeight,"",$subSettings['table-settings']['table_body']['settings'][$i][$currentColumn][$pdf::CELL_BORDERS]);
                                $pdf->SetY($currentY);
                                $pdf->SetX($currentX);
                            }*/

                            $currentX += $cellWidth;
                            $currentColumn += $columnWidth;
                        }
                        $currentY += $maxHeight;
                        //echo "Current custom table Y is $currentY with value $tableVal<br/>";
                    }
            }
        }

        //return $pdf->Output("Test.pdf",'S');
        return $currentY;
    }

    ## Mostly copied from \Plugin\MetadataCollection::isVisible()
    ## Returns the same results as REDCap's branching logic parser as of v7.3.5
    public static function evaluateBranchingLogic($recordData,$fieldDetails) {
        if(is_array($fieldDetails)) {
            $branchingLogic = $fieldDetails["branching_logic"];
        }
        else {
            $branchingLogic = $fieldDetails;
        }

        $newValue = preg_replace_callback("/(\\[)([a-z][a-z|_|0-9]*?)(\\])/", function ($matches) use ($recordData) {
            return ($recordData[$matches[2]] == "" ? "''" : "'".$recordData[$matches[2]]."'");
        }, $branchingLogic);
        $newValue = preg_replace_callback("/(\\[)([a-z][a-z|_|0-9]*?)\\(([0-9a-zA-Z]+)\\)(\\])/", function ($matches) use ($recordData) {
            return (in_array($matches[3], $recordData[$matches[2]]) ? "1" : "0");
        }, $newValue);

        $newValue = str_replace(" or ", ") || (", $newValue);
        $newValue = str_replace(" and ", ") && (", $newValue);
        $newValue = "(" . $newValue . ")";

        $parser = new \LogicParser();
        list($logicCode) = $parser->parse($newValue);

        return call_user_func_array($logicCode, array());
    }

    function addNewPage(PDF_MemImage $pdf) {
        global $Proj;
        //echo "Adding a new page<br/>";
        $pdf->AddPage("P", "A4");
        $pdf->SetY(-5);
        $pdf->SetFont('DejaVu','',8);
        // Set the current date/time as the left-hand footer
        $pdf->Cell(40,0,DateTimeRC::format_ts_from_ymd(NOW));
        // Append custom text to footer
        // Set "Powered by REDCap" text
        $pdf->Cell(90,0,'');
        $pdf->SetTextColor(128,128,128);
        $pdf->Cell(0,0,System::powered_by_redcap);
        $pdf->SetTextColor(0,0,0);

        $pdf->SetY(5);
        $pdf->Cell(0,0,'Confidential',0,1,'L');
        $pdf->Cell(0,5,'Page '.$pdf->PageNo().($GLOBALS['project_encoding'] == 'chinese_utf8' ? '' : ' of {nb}'),0,1,'R');
        $pdf->SetFont('DejaVu','B',8);
        $pdf->Cell(0,2,"Record ID ".$this->record_id." (".$this->event_arm.")",0,1,'R');
        $pdf->image(APP_PATH_DOCROOT . "Resources/images/"."redcap-logo-small.png",176, 289, 24, 7);
        ## Return the y coordinate to start
        return 15;
    }

    function br2nl($string) {
        $breaks = array("<br />","<br>","<br/>");
        $returnString = str_ireplace($breaks, "\n", $string);
        return $returnString;
    }

    function colorTextMapping($regString) {
        $coloredStrings = array();

        preg_match_all("/color\s*[=:](.*?)<\//s",$regString,$matches,PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $index => $values) {
            $results = explode(">",$values[0],2);

            $colorCode = trim(str_replace(array(";","'","\""),'',strtolower($results[0])));

            foreach ($this->colorConversions as $colorData) {
                if (($colorData['name'] == $colorCode || preg_replace('/\s+/', '', $colorData['name']) == $colorCode) || $colorData['hex'] == $colorCode) {
                    $coloredStrings[strip_tags($results[1])] = $colorData['rgb'];
                    break;
                }
            }
        }

        return $coloredStrings;
    }

    function printColorTable($x,$y,$headerData,$tableData,$tableSettings = null,$overflowFunction = null,$overflowParameters = null,$colorData = array()) {
        $pdf = $overflowParameters[0];
        $tableSettings = $pdf->setSettingDefaults($tableSettings);

        ## Replace tabs with spaces so they show up correctly
        foreach($tableData as $column => $tableVal) {
            $tableData[$column] = str_replace("\t","    ",$tableVal);
        }

        ## Set defaults for starting the printing of the headers
        $pdf->SetFillColor($tableSettings[$pdf::HEADER_FILL_COLOR][0],$tableSettings[$pdf::HEADER_FILL_COLOR][1],$tableSettings[$pdf::HEADER_FILL_COLOR][2]);
        $pdf->SetTextColor($tableSettings[$pdf::HEADER_TEXT_COLOR][0],$tableSettings[$pdf::HEADER_TEXT_COLOR][1],$tableSettings[$pdf::HEADER_TEXT_COLOR][2]);
        $pdf->SetFont($tableSettings[$pdf::FONT_NAME],$tableSettings[$pdf::HEADER_FONT_STYLE],$tableSettings[$pdf::HEADER_FONT_SIZE]);

        $alignmentArray = [];

        for($currentColumn = 0; $currentColumn < max(count($tableData[0]),count($headerData),array_sum($headerData)); $currentColumn++) {
            $alignmentArray[] = $tableSettings[$pdf::FONT_ORIENTATION][$currentColumn] == "" ? 'J' : $tableSettings[$pdf::FONT_ORIENTATION][$currentColumn];
        }

        ## Check if header needs to be stretched onto more rows
        $newHeaderData = $pdf->fitDataToColumnWidth([$headerData],$pdf->shiftHeaderSettingsToTable($tableSettings));
        $newHeaderData = $newHeaderData[0];

        ## Get header height
        $maxHeaderHeight = $pdf->getRowHeight($newHeaderData,$pdf->shiftHeaderSettingsToTable($tableSettings)) + $tableSettings[$pdf::HEADER_PADDING];

        $columnWidthType = $pdf->rowSpecifiesColumnWidth($headerData);
        $widthArray = $pdf->getColumnWidths($newHeaderData,$tableSettings);

        $currentX = $x;
        $currentY = $y;
        $currentColumn = 0;

        foreach($newHeaderData as $headerKey => $headerVal) {
            $pdf->SetY($currentY);
            $pdf->SetX($currentX);

            $columnWidth = 1;
            if($columnWidthType) {
                $columnWidth = $headerVal;
                $headerVal = $headerKey;
            }

            $thisAlign = is_array($tableSettings[$pdf::HEADER_ALIGN]) ? $tableSettings[$pdf::HEADER_ALIGN][$currentColumn] : $tableSettings[$pdf::HEADER_ALIGN];

            $cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];
//			$pdf->Cell($cellWidth,12,$headerVal,1,0,'',true);
//			error_log("Header: ".$headerVal);
            $pdf->MultiCell($cellWidth,$maxHeaderHeight / (substr_count($headerVal,"\n") + 1),$headerVal,$tableSettings[$pdf::HEADER_BORDER],$thisAlign,true);
            $currentX += $cellWidth;
            $currentColumn++;
        }

        if(count($headerData) > 0) {
            $currentY += $maxHeaderHeight;
        }

        ## Start printing the table data
        $pdf->SetFillColor(255,255,255);
        $pdf->SetTextColor($tableSettings[$pdf::FONT_COLOR][0],$tableSettings[$pdf::FONT_COLOR][1],$tableSettings[$pdf::FONT_COLOR][2]);
        $pdf->SetFont($tableSettings[$pdf::FONT_NAME],$tableSettings[$pdf::FONT_STYLE],$tableSettings[$pdf::FONT_SIZE]);

        ## Stretch $tableData as need to fit into column widths
        $tableData = $pdf->fitDataToColumnWidth($tableData,$tableSettings);

        $rowToAdd = [];
        /*echo "Table:<br/>";
        echo "<pre>";
        print_r($tableData);
        echo "</pre>";
        echo "Colors: <br/>";
        echo "<pre>";
        print_r($colorData);
        echo "</pre>";*/
        for($i = 0; ($i < count($tableData) || count($rowToAdd) > 0); $i++) {
            ## Sometimes new rows will be added in order to wrap cells onto multiple pages
            if(count($rowToAdd) > 0) {
//				error_log("Row to Add at $currentY: ".implode("||",$rowToAdd));
                $tableRow = $rowToAdd;
//				$pdf->printText(0,5,"Starting overflow",[PDF_MemImage::FONT_SIZE => 9,PDF_MemImage::FONT_COLOR => [0,0,0],PDF_MemImage::FONT_NAME => "helvetica"]);
                $rowToAdd = [];
                $i--;

                $currentY = "";
                ## Wrap to new page when having a "rowToAdd"
                if($overflowFunction) {
                    if($overflowParameters) {
                        $currentY = call_user_func($overflowFunction,$overflowParameters[0],$overflowParameters[1],$overflowParameters[2],
                            $overflowParameters[3],$overflowParameters[4],$overflowParameters[5]);
                    }
                    else {
                        $currentY = call_user_func($overflowFunction);
                    }
                }
                else {
                    if($pdf->h > 220) {
                        $pdf->AddPage("P", "A4");
                    }
                    else {
                        $pdf->AddPage("L", "A4");
                    }
                }
                if($currentY == "") {
                    $currentY = 10;
                }
                $pdf->SetFillColor(255,255,255);
                $pdf->SetTextColor($tableSettings[$pdf::FONT_COLOR][0],$tableSettings[$pdf::FONT_COLOR][1],$tableSettings[$pdf::FONT_COLOR][2]);
                $pdf->SetFont($tableSettings[$pdf::FONT_NAME],$tableSettings[$pdf::FONT_STYLE],$tableSettings[$pdf::FONT_SIZE]);
//				error_log("Wrapping from new row $currentY");
            }
            else {
                $tableRow = $tableData[$i];
            }
            $currentX = $x;
            $maxHeight = $pdf->getRowHeight($tableRow,$tableSettings);
            $widthArray = $pdf->getColumnWidths($tableRow,$tableSettings);

//			error_log("Need to wrap? $currentY $maxHeight ~ ".$tableSettings[$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT]." > ".($pdf->h - 10)."");
            ## If this row will exceed the remaining height in the page, but there's enough room to start the table, split the current row
            if($tableSettings[$pdf::WRAP_WITHIN_CELL_MIN_HEIGHT] < ($pdf->h - $currentY) && ($currentY + $maxHeight) > ($pdf->h - 10)) {
//				error_log("Splitting this thing");
                $splitRows = $pdf->splitRowByHeight($tableRow,$tableSettings,($pdf->h - 10 - $currentY));
                $tableRow = $splitRows[0];
                $rowToAdd = $splitRows[1];
                $maxHeight = $pdf->getRowHeight($tableRow,$tableSettings);
            }

            ## If there's not enough room to print even part of this row, create new page and print it there.
            ## This will wrap onto another page if there still isn't enough room
            if(($currentY + $maxHeight) > ($pdf->h - 10)) {
//				error_log("Wrapping");
                $currentY = "";
                if($overflowFunction) {
                    if($overflowParameters) {
                        $currentY = call_user_func($overflowFunction,$overflowParameters[0],$overflowParameters[1],$overflowParameters[2],
                            $overflowParameters[3],$overflowParameters[4],$overflowParameters[5]);
                    }
                    else {
                        $currentY = call_user_func($overflowFunction);
                    }
                }
                else {
                    if($pdf->h > 220) {
                        $pdf->AddPage("P", "A4");
                    }
                    else {
                        $pdf->AddPage("L", "A4");
                    }
                }
                if($currentY == "") {
                    $currentY = 10;
                }

                $pdf->SetFillColor(255,255,255);
                $pdf->SetTextColor($tableSettings[$pdf::FONT_COLOR][0],$tableSettings[$pdf::FONT_COLOR][1],$tableSettings[$pdf::FONT_COLOR][2]);
                $pdf->SetFont($tableSettings[$pdf::FONT_NAME],$tableSettings[$pdf::FONT_STYLE],$tableSettings[$pdf::FONT_SIZE]);

                ## Check if this single cell will overflow and attempt to wrap the text to another page
                if(($currentY + $maxHeight) > ($pdf->h - 10)) {
                    $splitRows = $pdf->splitRowByHeight($tableRow,$tableSettings,($pdf->h - 10 - $currentY));
                    $tableRow = $splitRows[0];
                    $rowToAdd = $splitRows[1];
                    $maxHeight = $pdf->getRowHeight($tableRow,$tableSettings);
                }
            }
            $currentColumn = 0;
            $columnWidthType = $pdf->rowSpecifiesColumnWidth($tableRow);
            /*echo "Table rows:<br/>";
            echo "<pre>";
            print_r($tableRow);
            echo "</pre>";*/
            foreach($tableRow as $tableKey => $tableVal) {
                $pdf->SetY($currentY);
                $pdf->SetX($currentX);
                $columnWidth = 1;
                if($columnWidthType) {
                    $columnWidth = $tableVal;
                    $tableVal = $tableKey;
                }
                $tableVal = str_replace("\r","",$tableVal);
                $cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];

                if(is_array($tableSettings[$pdf::NUMBER_DECIMALS])) {
                    $thisDecimal = $tableSettings[$pdf::NUMBER_DECIMALS][$currentColumn];
                }
                else {
                    $thisDecimal = $tableSettings[$pdf::NUMBER_DECIMALS];
                }

                ## Round the number based on required decimal places/turn to scientific notation is won't fit
                if(is_numeric($tableVal) && abs($tableVal) > 999999) {
//					$origNumber = $tableVal;
                    $tableVal = round($tableVal,(4-strlen(round($tableVal,0))));
                    $tableVal = sprintf("%.3e",$tableVal);
//					echo "Found large $origNumber => $tableVal <Br />";
                }
                else {
                    $tableVal = (is_numeric($tableVal) && $thisDecimal !== $pdf::DECIMALS_MANUAL) ? number_format($tableVal,$thisDecimal) : $tableVal;
                }

                ## Print borders first if needed
                if($tableSettings[$pdf::CELL_BORDERS]) {
                    $pdf->MultiCell($cellWidth,$maxHeight,"",$tableSettings[$pdf::CELL_BORDERS],$alignmentArray[$currentColumn]);
                    $pdf->SetY($currentY);
                    $pdf->SetX($currentX);
                }
                ## Then print the actual cell text second
//				error_log("Printing: ".$tableVal);
                /*echo "Table: ".htmlspecialchars(json_encode($tableVal))."<br/>";
                echo "Color: <br/>";
                echo "<pre>";
                print_r($colorData[$i]);
                echo "</pre>";*/
                if (!empty($colorData[$i])) {
                    $colorArray = array();
                    $stringArray = array();
                    $previousPosition = 0;
                    //$colorData = $pdf->fitDataToColumnWidth($colorData,$tableSettings);
                    foreach ($colorData[$i] as $searchString => $colorRGB) {
                        $searchString = $this->fitStringtoWidth($pdf,$searchString,$tableSettings);
                        //echo "Search: ".htmlspecialchars(json_encode($searchString))."<br/>";
                        $stringPos = strpos($tableVal,$searchString);
                        //echo "Match? ".(strpos("Suicide risk \nPlease note participant has endorsed number 9 in the PHQ9 \n\"Thoughts that you would be better off dead or of hurting yourself in some way\" ","Please note participant has endorsed number 9 in the PHQ9 \n\"Thoughts that you would be better off dead or of hurting yourself in some way\" ") === false ? "false" : "true")."<br/>";
                        if (is_numeric($stringPos)) {
                            //echo "Stringpos: $stringPos<br/>";
                            $stringArray[] = array('text'=>substr($tableVal,$previousPosition,$stringPos-$previousPosition),'color'=>$tableSettings[$pdf::FONT_COLOR]);
                            $stringArray[] = array('text'=>substr($tableVal,$stringPos,strlen($searchString)),'color'=>$colorRGB);
                            $previousPosition = $stringPos+strlen($searchString);
                            //$colorArray[$stringPos] = array('length'=>strlen($searchString),'color'=>$colorRGB,'string'=>$searchString,'x'=>0,'y'=>0);
                        }
                    }
                    if ($previousPosition != strlen($tableVal)) {
                        $stringArray[] = array('text'=>substr($tableVal,$previousPosition,strlen($tableVal)-$previousPosition),'color'=>$tableSettings[$pdf::FONT_COLOR]);
                    }
                    $this->cellMultiColor($pdf, $tableSettings, $stringArray, 0, $alignmentArray[$currentColumn], false);
                }
                else {
                    $pdf->MultiCell($cellWidth, $tableSettings[$pdf::FONT_SIZE] / 2, $tableVal, 0, $alignmentArray[$currentColumn]);
                }
                $currentX += $cellWidth;
                $currentColumn += $columnWidth;
            }
            $currentY += $maxHeight;
            //echo "PDF current Y is $currentY with value $tableVal<br/>";

        }
        return $currentY;
    }

    function cellMultiColor(PDF_MemImage $pdf, $tableSettings, $stringParts, $border=0, $align='J', $fill=false)
    {
        $currentPointerPosition = $pdf->lMargin;
        $defaultColor = $tableSettings[$pdf::FONT_COLOR];
        $h = $tableSettings[$pdf::FONT_SIZE] / 2;
        foreach ($stringParts as $part) {
            $pdf->SetTextColor($defaultColor[0],$defaultColor[1],$defaultColor[2]);
            $lineCount = 0;
            $neededNewLine = false;
            // Set the pointer to the end of the previous string part
            $pdf->SetX($currentPointerPosition);

            // Get the color from the string part
            $pdf->SetTextColor($part['color'][0], $part['color'][1], $part['color'][2]);
            //echo "Original string: ".htmlspecialchars(json_encode($part['text']))."<br/>";
            //$part['text'] = $this->fitStringtoWidth($pdf,$part['text'],$tableSettings);
            //Set linecount to go to a newline if there is a newline character in the string
            if (strpos($part['text'],"\n") !== false) {
                //$part['text'] = trim($part['text']," ");
                $strings = explode("\n",$part['text']);
                /*echo "<pre>";
                print_r($strings);
                echo "</pre>";*/
                if ($part['text'] == "\n") {
                    $pdf->Cell(0, $h, '', $border, 1, $align, $fill);
                    $pdf->SetX($pdf->lMargin);
                    $currentPointerPosition = $pdf->lMargin;
                }
                else {
                    $lastString = null;
                    /*echo "<pre>";
                    print_r($strings);
                    echo "</pre>";*/
                    foreach ($strings as $index => $newline) {
                        if ($newline == "" && (($lastString == "" && $lastString !== null) || $index == count($strings)-1)) {
                            //echo "Skipping ahead on $newline from $lastString<br/>";
                            /*$pdf->Cell($pdf->GetStringWidth(' '), $h, ' ', $border, 0, $align, $fill);
                            $currentPointerPosition += $pdf->GetStringWidth(' ');*/
                            $lastString = $newline;
                            continue;
                        }
                        if ($newline == "") {
                            $pdf->Cell(0, $h, '', $border, 2, $align, $fill);
                            $currentPointerPosition = $pdf->lMargin;
                        }
                        elseif ($index == count($strings)-1) {
                            $pdf->Cell($pdf->GetStringWidth($newline), $h, $newline, $border, 0, $align, $fill);
                            $currentPointerPosition += $pdf->GetStringWidth($newline);
                        }
                        else {
                            $pdf->Cell($pdf->GetStringWidth($newline), $h, $newline, $border, 2, $align, $fill);
                            $currentPointerPosition = $pdf->lMargin;
                        }
                        //echo "New: ".htmlspecialchars(json_encode($newline))."<br/>";
                        $lastString = $newline;
                        $pdf->SetX($currentPointerPosition);
                    }
                }
                /*if (strpos($part['text'],"\n") == 0) {
                    $part['text'] = str_replace("\n", "", $part['text']);
                    $pdf->Cell(0, $h, '', $border, 1, $align, $fill);
                    $pdf->SetX($pdf->lMargin);
                    $currentPointerPosition = $pdf->lMargin;
                }
                else {
                    echo "<pre>";
                    print_r(explode("\n",$part['text']));
                    echo "</pre>";
                    $part['text'] = str_replace("\n", "", $part['text']);
                    $lineCount = 2;
                }*/
            }
            else {
                $pdf->Cell($pdf->GetStringWidth($part['text']), $h, $part['text'], $border, 0, $align, $fill);
                $currentPointerPosition += $pdf->GetStringWidth($part['text']);
            }

            // Update the pointer to the end of the current string part
            /*if ($lineCount === 2) {
                $currentPointerPosition = $pdf->lMargin;
            }
            else {
                $currentPointerPosition += $pdf->GetStringWidth($part['text']);
            }*/
        }
        $pdf->SetTextColor($defaultColor[0],$defaultColor[1],$defaultColor[2]);
    }

    function fitCustomDatatoWidth(PDF_MemImage $pdf,$tableData,$tableSettings) {
        $returnData = array();
        /*echo "<pre>";
        print_r($tableData);
        echo "</pre>";
        echo "<pre>";
        print_r($tableSettings);
        echo "</pre>";*/
        foreach ($tableData as $row =>$columnData) {
            foreach ($columnData as $column => $string) {
                $newString = $string;
                $stringWidth = $pdf->GetStringWidth($string);
                $columnWidth = (isset($tableSettings[$row][$column]['table_width']) && is_numeric($tableSettings[$row][$column]['table_width']) ? $tableSettings[$row][$column]['table_width'] : 30);
                if ($stringWidth > ($columnWidth -2)) {
                    $condensedString = "";
                    $explodedString = explode("\n", $newString);
                    $currentLine = "";
                    foreach ($explodedString as $thisKey => $thisLine) {
                        if ($thisKey > 0) {
                            $condensedString .= "\n";
                            $currentLine = "";
                        }
                        $explodedLine = explode(" ", $thisLine);
                        foreach ($explodedLine as $thisWord) {
                            if ($pdf->GetStringWidth($currentLine . $thisWord . " ") > $columnWidth) {
                                $condensedString .= "\n";
                                $currentLine = "";
                            }
                            $currentLine .= $thisWord . " ";
                            $condensedString .= $thisWord . " ";
                        }
                    }
                    $newString = $condensedString;
                }
                $returnData[$row][$column] = $newString;
            }
        }
        /*echo "Return:<br/>";
        echo "<pre>";
        print_r($returnData);
        echo "</pre>";*/
        return $returnData;
    }

    function fitStringtoWidth(PDF_MemImage $pdf,$string,$tableSettings)
    {
        $tableSettings = $pdf->setSettingDefaults($tableSettings);

        $pdf->SetFont($tableSettings[$pdf::FONT_NAME], $tableSettings[$pdf::FONT_STYLE], $tableSettings[$pdf::FONT_SIZE]);
        $tableRow = array($string => 2);
        $currentColumn = 0;
        $widthArray = $pdf->getColumnWidths($tableRow, $tableSettings);
        $columnWidthType = $pdf->rowSpecifiesColumnWidth($tableRow);

        $newTableRow = [];

        foreach ($tableRow as $columnKey => $tableVal) {
            $columnWidth = 1;
            if ($columnWidthType) {
                $columnWidth = $tableVal;
                $tableVal = $columnKey;
            }

            $cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];

            ## Code to try manually inserting line breaks to tables as needed
            $maxWidth = $cellWidth - 2;
            $newString = $tableVal;
            $stringWidth = $pdf->GetStringWidth($newString);

            if ($stringWidth > $maxWidth) {
                $condensedString = "";
                $explodedString = explode("\n", $newString);
                $currentLine = "";
                foreach ($explodedString as $thisKey => $thisLine) {
                    if ($thisKey > 0) {
                        $condensedString .= "\n";
                        $currentLine = "";
                    }
                    $explodedLine = explode(" ", $thisLine);
                    foreach ($explodedLine as $thisIndex => $thisWord) {
                        if ($pdf->GetStringWidth($currentLine . $thisWord . " ") > $maxWidth) {
                            $condensedString .= "\n";
                            $currentLine = "";
                        }
                        $currentLine .= $thisWord . ($thisIndex + 1 == count($explodedLine) ? "" : " ");
                        $condensedString .= $thisWord . ($thisIndex + 1 == count($explodedLine) ? "" : " ");
                    }
                }
                $newString = $condensedString;
            }
        }

        return $newString;
    }
}