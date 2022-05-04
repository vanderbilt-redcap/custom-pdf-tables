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
$instrumentList = $module->getProjectSetting('form');
$usersDisplay = $module->getProjectSetting('user-download');

$instrumentIndex = array_search($instrument,$instrumentList);

if (is_numeric($instrumentIndex) && (!isset($usersDisplay[$instrumentIndex]) || $usersDisplay[$instrumentIndex] == $user_rights['role_id'] || SUPER_USER)) {
    $Proj = new \Project($project_id);

    $module->record_id = $record;
    foreach ($Proj->events as $eventInfo) {
        if (isset($eventInfo['events'][$event_id])) {
            $module->event_arm = $eventInfo['name'];
        }
    }

    $pdf = new PDF_MemImage();
    $pdf->AliasNbPages();
    $currentY = 0;
    $formMetadata = \REDCap::getDataDictionary($project_id, "array", TRUE, NULL, $instrument);

    $fullMetadata = \REDCap::getDataDictionary($project_id, "array", TRUE, NULL);
    $formTitle = $Proj->forms[$instrument]["menu"];
    $recordData = \REDCap::getData($project_id, 'array', array($record), array(), array($event_id), array(), false, false, false);

    $singleRecord = $recordData[$record];
    $metaRecord = $recordData;

    if (isset($singleRecord['repeat_instances'])) {
        $subRecord = $singleRecord['repeat_instances'];
        $singleRecord = array();
        $singleRecord[$event_id] = $subRecord[$event_id][''][$repeat_instance];
        $metaRecord[$record][$event_id] = $subRecord[$event_id][''][$repeat_instance];
    }

    $theMeta = $module->processTableSettings($metaRecord, $project_id, $record, $instrument, $event_id, $group_id, $repeat_instance);

    $currentY = $module->generateCustomTable($pdf, $currentY, array(), $instrument, $formTitle, $theMeta);
    $currentY = $module->generateFormForRecord($pdf, $currentY, $singleRecord, $formMetadata, $fullMetadata, $formTitle, array());

//exit;
    $pdf->Output("$instrument-$record " . date("Ymd") . ".pdf", "D");
}
else {
    echo "You don't have permissions to view this page. Go away.";
}