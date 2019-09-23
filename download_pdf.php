<?php
include_once(__DIR__."/Libraries/PDF/FPDF/PDF_MemImage.php");

global $user_rights;
$project_id = $_GET['pid'];
$record = $_GET['id'];
$instrument = $_GET['instrument'];
$event_id = $_GET['event_id'];
$group_id = $user_rights['group_id'];
$repeat_instance = $_GET['instance'];
$module = new \Vanderbilt\CustomPDFTables\CustomPDFTables($project_id);

$Proj = new \Project($project_id);

$module->record_id = $record;
foreach ($Proj->events as $eventInfo) {
    if (isset($eventInfo['events'][$event_id])) {
        $module->event_arm = $eventInfo['name'];
    }
}

$pdf = new \Plugin\PDF_MemImage();
$pdf->AliasNbPages();
$currentY = 0;
$formMetadata = \REDCap::getDataDictionary($project_id,"array",TRUE,NULL,$instrument);
$fullMetadata = \REDCap::getDataDictionary($project_id,"array",TRUE,NULL);
$formTitle = $Proj->forms[$instrument]["menu"];
$recordData = \REDCap::getData($project_id, 'array', array($record), array(), array($event_id), array(), false, false, false);

$theMeta = $module->processTableSettings($recordData,$project_id, $record, $instrument, $event_id, $group_id, $repeat_instance);
$singleRecord = $recordData[$record];
echo "<pre>";
print_r($singleRecord);
echo "</pre>";
if (isset($singleRecord['repeat_instances'])) {
    $singleRecord = $singleRecord['repeat_instances'][$event_id][''][$repeat_instance];
}
$currentY = $module->generateCustomTable($pdf,$currentY,array(),$instrument,$formTitle,$theMeta);
$currentY = $module->generateFormForRecord($pdf,$currentY,$singleRecord,$formMetadata,$fullMetadata,$formTitle,array());

$pdf->Output("Test.pdf","D");