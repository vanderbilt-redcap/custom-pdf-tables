<?php

use DateTimeRC;
use System;

require_once('tfpdf.php');
require_once('VariableStream.php');

class PDF_MemImage extends tFPDF
{
	const TABLE_WIDTH_SETTINGS = "table_width";

	const FONT_NAME = "font_name";
	const FONT_STYLE = "font_style";
	const FONT_SIZE = "font_size";
	const FONT_WIDTH = "font_width";
	const FONT_COLOR = "font_color";
	const FONT_ALIGN = "font_align";
	const FONT_ORIENTATION = "orientation";

	const HEADER_FONT_SIZE = "header_font_size";
	const HEADER_FILL_COLOR = "header_fill_color";
	const HEADER_TEXT_COLOR = "header_text_color";
	const HEADER_ALIGN = "header_align";
	const HEADER_PADDING = "header_padding";
	const HEADER_BORDER = "header_border";
	const HEADER_FONT_STYLE = "header_font_style";

	const CELL_BORDERS = "cell_borders";
	const NUMBER_DECIMALS = "number_decimals";
	const DECIMALS_MANUAL = "manual";

	const VERTICAL_ORIENTATION = "vertical_orientation";

	const WRAP_WITHIN_CELL_MIN_HEIGHT = "in_cell_page_wrap";


	function __construct($orientation='P', $unit='mm', $format='A4')
	{
		parent::__construct($orientation, $unit, $format);

		$this->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
		$this->AddFont('DejaVu','B','DejaVuSansCondensed-Bold.ttf',true);

		$existed = in_array("var", stream_get_wrappers());
		if ($existed) {
			stream_wrapper_unregister("var");
		}
		// Register var stream protocol
		stream_wrapper_register('var', 'Vanderbilt\CustomPDFTables\VariableStream');
	}

	function MemImage($data, $x=null, $y=null, $w=0, $h=0, $link='')
	{
		// Display the image contained in $data
		$v = 'img'.md5($data);
		$GLOBALS[$v] = $data;
		$a = getimagesize('var://'.$v);
		if(!$a)
			$this->Error('Invalid image data');
		$type = substr(strstr($a['mime'],'/'),1);
		$this->Image('var://'.$v, $x, $y, $w, $h, $type, $link);
		unset($GLOBALS[$v]);
	}

	function GDImage($im, $x=null, $y=null, $w=0, $h=0, $link='')
	{
		// Display the GD image associated with $im
		ob_start();
		imagepng($im);
		$data = ob_get_clean();
		$this->MemImage($data, $x, $y, $w, $h, $link);
	}

	## Add default settings for anything that wasn't defined
	function setSettingDefaults($tableSettings = null) {
		$tableSettings[self::HEADER_FONT_SIZE] = array_key_exists(self::HEADER_FONT_SIZE,$tableSettings) ? $tableSettings[self::HEADER_FONT_SIZE] : 12;
		$tableSettings[self::HEADER_FILL_COLOR] = array_key_exists(self::HEADER_FILL_COLOR,$tableSettings) ? $tableSettings[self::HEADER_FILL_COLOR] : [64,128,200];
		$tableSettings[self::HEADER_ALIGN] = array_key_exists(self::HEADER_ALIGN,$tableSettings) ? $tableSettings[self::HEADER_ALIGN] : 'C';
		$tableSettings[self::HEADER_PADDING] = array_key_exists(self::HEADER_PADDING,$tableSettings) ? $tableSettings[self::HEADER_PADDING] : $tableSettings[self::HEADER_FONT_SIZE]/2;
		$tableSettings[self::HEADER_FONT_STYLE] = array_key_exists(self::HEADER_FONT_STYLE,$tableSettings) ? $tableSettings[self::HEADER_FONT_STYLE] : "B";
		$tableSettings[self::HEADER_BORDER] = array_key_exists(self::HEADER_BORDER,$tableSettings) ? $tableSettings[self::HEADER_BORDER] : 1;

		$tableSettings[self::FONT_NAME] = array_key_exists(self::FONT_NAME,$tableSettings) ? $tableSettings[self::FONT_NAME] : "DejaVu";
		$tableSettings[self::FONT_SIZE] = array_key_exists(self::FONT_SIZE,$tableSettings) ? $tableSettings[self::FONT_SIZE] : 10;
		$tableSettings[self::NUMBER_DECIMALS] = array_key_exists(self::NUMBER_DECIMALS,$tableSettings) ? $tableSettings[self::NUMBER_DECIMALS] : 0;
		$tableSettings[self::WRAP_WITHIN_CELL_MIN_HEIGHT] = array_key_exists(self::WRAP_WITHIN_CELL_MIN_HEIGHT,$tableSettings) ? $tableSettings[self::WRAP_WITHIN_CELL_MIN_HEIGHT] : 30;
		$tableSettings[self::FONT_COLOR] = array_key_exists(self::FONT_COLOR,$tableSettings) ? $tableSettings[self::FONT_COLOR] : [0,0,0];
		$tableSettings[self::CELL_BORDERS] = array_key_exists(self::CELL_BORDERS,$tableSettings) ? $tableSettings[self::CELL_BORDERS] : 1;

		return $tableSettings;
	}

	## Move the header fonts and styles to the table fonts so that the header style info can be used in the same functions
	## That check table body settings
	function shiftHeaderSettingsToTable($tableSettings) {
		$tableSettings[self::FONT_SIZE] = $tableSettings[self::HEADER_FONT_SIZE];
		$tableSettings[self::FONT_COLOR] = $tableSettings[self::HEADER_FILL_COLOR];
		$tableSettings[self::FONT_STYLE] = $tableSettings[self::HEADER_FONT_STYLE];

		return $tableSettings;
	}

	## Pull table widths out of TABLE_WIDTH_SETTINGS and set a default width if not specified
	function getColumnWidths($tableRow,$tableSettings) {
		$widthArray = [0];
		$currentWidth = 0;

		$maxColumns = count($tableRow);
		foreach(array_keys($tableRow) as $thisKey) {
			if(!is_numeric($thisKey)) {
				$maxColumns = array_sum($tableRow);
				break;
			}
		}
		## Check in case something slips through and system tries to add too many columns
		if($maxColumns > 255) {
			$maxColumns = count($tableRow);
		}

		for($currentColumn = 0; $currentColumn < $maxColumns; $currentColumn++) {
			$currentWidth += (isset($tableSettings[self::TABLE_WIDTH_SETTINGS][$currentColumn]) ? $tableSettings[self::TABLE_WIDTH_SETTINGS][$currentColumn] : 60);
			$widthArray[] = $currentWidth;
		}

		return $widthArray;
	}

	## Check if keys on the row contain the column number or the actual cell text
	## If contains text, this row's values actually contain the number of rows they should span
	function rowSpecifiesColumnWidth($tableRow) {
		if(array_keys($tableRow)[0] === 0) {
			return false;
		}
		return true;
	}

	## For every string in the $tableData, wrap the text with a newline character every time the current line
	## exceeds the column width.
	function fitDataToColumnWidth($tableData,$tableSettings) {
		$tableSettings = $this->setSettingDefaults($tableSettings);

		$this->SetFont($tableSettings[self::FONT_NAME],$tableSettings[self::FONT_STYLE],$tableSettings[self::FONT_SIZE]);

		foreach($tableData as $rowKey => $tableRow) {
			$currentColumn = 0;
			$widthArray = $this->getColumnWidths($tableRow,$tableSettings);
			$columnWidthType = $this->rowSpecifiesColumnWidth($tableRow);

			$newTableRow = [];

			foreach($tableRow as $columnKey => $tableVal) {
				$columnWidth = 1;
				if($columnWidthType) {
					$columnWidth = $tableVal;
					$tableVal = $columnKey;
				}

				$cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];

				## Code to try manually inserting line breaks to tables as needed
				$maxWidth = $cellWidth - 2;

				$newString = $tableVal;
				$stringWidth = $this->GetStringWidth($newString);

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
							if($this->GetStringWidth($currentLine.$thisWord." ") > $maxWidth) {
								$condensedString .= "\n";
								$currentLine = "";
							}
							$currentLine .= $thisWord." ";
							$condensedString .= $thisWord." ";
						}
					}
					$newString = $condensedString;
				}

				if($columnWidthType) {
					$newTableRow[$newString] = $columnWidth;
				}
				else {
					$newTableRow[$columnKey] = $newString;
				}

				$currentColumn++;
			}
			$tableData[$rowKey] = $newTableRow;
		}

		return $tableData;
	}

	## If a cell would overflow the maxHeight, instead split the string into multiple rows for each page
	function splitRowByHeight($tableRow,$tableSettings, $maxHeight) {
		$tableSettings = $this->setSettingDefaults($tableSettings);
		$columnWidthType = $this->rowSpecifiesColumnWidth($tableRow);

		$firstRow = [];
		$secondRow = [];

		foreach($tableRow as $tableKey => $tableVal) {
			if($columnWidthType) {
				$columnWidth = $tableVal;
				$tableVal = $tableKey;
			}

			$firstVal = $tableVal;
			$newVal = "";
			if((substr_count($tableVal,"\n") + 1) * $tableSettings[self::FONT_SIZE] / 2 > $maxHeight) {
				$firstVal = "";
				$currentHeight = $tableSettings[self::FONT_SIZE] / 2;
				$explodedString = explode("\n",$tableVal);
				$splitRow = count($explodedString);
				foreach($explodedString as $thisRow => $thisLine) {
					$firstVal .= ($firstVal == "" ? "" : "\n").$thisLine;
					$currentHeight += $tableSettings[self::FONT_SIZE] / 2;

					if($currentHeight > $maxHeight) {
						$splitRow = $thisRow;
						break;
					}
				}

				if($splitRow < count($explodedString)) {
					$newVal = implode("\n",array_slice($explodedString,$splitRow + 1));
				}
			}

			if($columnWidthType) {
				$firstRow[$firstVal] = $columnWidth;
				$secondRow[$newVal] = $columnWidth;
			}
			else {
				$firstRow[$tableKey] = $firstVal;
				$secondRow[$tableKey] = $newVal;
			}
		}

		foreach($secondRow as $tableVal) {
			if($tableVal != "") {
				return [$firstRow,$secondRow];
			}
		}
		return [$firstRow];
	}


	## Find the row with the highest number of newline characters and translate that into cell height for PDF
	function getRowHeight($tableRow,$tableSettings) {
		$tableSettings = $this->setSettingDefaults($tableSettings);
		$maxHeight = 6;
		$columnWidthType = $this->rowSpecifiesColumnWidth($tableRow);

		foreach($tableRow as $tableKey => $tableVal) {
			if($columnWidthType) {
				$tableVal = $tableKey;
			}
			$maxHeight = max($maxHeight, (substr_count($tableVal,"\n") + 1) * $tableSettings[self::FONT_SIZE] / 2);
		}

		return $maxHeight;
	}

	## Perform getRowHeight on all header and table rows to get the total table height
	function getTableHeight($headerData,$tableData,$tableSettings = null) {
		$tableSettings = $this->setSettingDefaults($tableSettings);

		$headerSettings = $this->shiftHeaderSettingsToTable($tableSettings);
		$tableHeight = $this->getRowHeight($headerData,$headerSettings);

		$tableData = $this->fitDataToColumnWidth($tableData,$tableSettings);

		foreach($tableData as $tableRow) {
			$maxHeight = $this->getRowHeight($tableRow,$tableSettings);

			$tableHeight += $maxHeight;
		}

		return $tableHeight;
	}

	function printTableIfRoom($x,$y,$headerData,$tableData,$tableSettings = null) {
		$headerHeight = $this->getRowHeight($headerData,$this->shiftHeaderSettingsToTable($tableSettings));

		if(($headerHeight + $y) > ($this->h - $tableSettings[self::WRAP_WITHIN_CELL_MIN_HEIGHT])) {
			error_log("Overflows: ".implode("||",$headerData));
			return false;
		}
		else {
			return $this->printTable($x,$y,$headerData,$tableData,$tableSettings);
		}
	}

	/**
	 * @param $x
	 * @param $y
	 * @param $headerData
	 * @param $tableData
	 * @param null $tableSettings
	 * @param null $overflowFunction callable
	 * @return float|int|mixed
	 */
	function printTable($x,$y,$headerData,$tableData,$tableSettings = null,$overflowFunction = null,$overflowParameters = null) {
		$tableSettings = $this->setSettingDefaults($tableSettings);

		## Replace tabs with spaces so they show up correctly
        if (!is_array($tableData[0])) $tableData[0] = array();
		foreach($tableData as $column => $tableVal) {
			$tableData[$column] = str_replace("\t","    ",$tableVal);
		}

		## Set defaults for starting the printing of the headers
		$this->SetFillColor($tableSettings[self::HEADER_FILL_COLOR][0],$tableSettings[self::HEADER_FILL_COLOR][1],$tableSettings[self::HEADER_FILL_COLOR][2]);
		$this->SetTextColor($tableSettings[self::HEADER_TEXT_COLOR][0],$tableSettings[self::HEADER_TEXT_COLOR][1],$tableSettings[self::HEADER_TEXT_COLOR][2]);
		$this->SetFont($tableSettings[self::FONT_NAME],$tableSettings[self::HEADER_FONT_STYLE],$tableSettings[self::HEADER_FONT_SIZE]);

		$alignmentArray = [];

		for($currentColumn = 0; $currentColumn < max(count($tableData[0]),count($headerData),array_sum($headerData)); $currentColumn++) {
			$alignmentArray[] = $tableSettings[self::FONT_ORIENTATION][$currentColumn] == "" ? 'J' : $tableSettings[self::FONT_ORIENTATION][$currentColumn];
		}

		## Check if header needs to be stretched onto more rows
		$newHeaderData = $this->fitDataToColumnWidth([$headerData],$this->shiftHeaderSettingsToTable($tableSettings));
		$newHeaderData = $newHeaderData[0];

		## Get header height
		$maxHeaderHeight = $this->getRowHeight($newHeaderData,$this->shiftHeaderSettingsToTable($tableSettings)) + $tableSettings[self::HEADER_PADDING];

		$columnWidthType = $this->rowSpecifiesColumnWidth($headerData);
		$widthArray = $this->getColumnWidths($newHeaderData,$tableSettings);

		$currentX = $x;
		$currentY = $y;
		$currentColumn = 0;

		foreach($newHeaderData as $headerKey => $headerVal) {
			$this->SetY($currentY);
			$this->SetX($currentX);

			$columnWidth = 1;
			if($columnWidthType) {
				$columnWidth = $headerVal;
				$headerVal = $headerKey;
			}

			$thisAlign = is_array($tableSettings[self::HEADER_ALIGN]) ? $tableSettings[self::HEADER_ALIGN][$currentColumn] : $tableSettings[self::HEADER_ALIGN];

			$cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];
//			$this->Cell($cellWidth,12,$headerVal,1,0,'',true);
//			error_log("Header: ".$headerVal);
			$this->MultiCell($cellWidth,$maxHeaderHeight / (substr_count($headerVal,"\n") + 1),$headerVal,$tableSettings[self::HEADER_BORDER],$thisAlign,true);
			$currentX += $cellWidth;
			$currentColumn++;
		}

		if(count($headerData) > 0) {
			$currentY += $maxHeaderHeight;
		}

		## Start printing the table data
		$this->SetFillColor(255,255,255);
		$this->SetTextColor($tableSettings[self::FONT_COLOR][0],$tableSettings[self::FONT_COLOR][1],$tableSettings[self::FONT_COLOR][2]);
		$this->SetFont($tableSettings[self::FONT_NAME],$tableSettings[self::FONT_STYLE],$tableSettings[self::FONT_SIZE]);

		## Stretch $tableData as need to fit into column widths
		$tableData = $this->fitDataToColumnWidth($tableData,$tableSettings);

		$rowToAdd = [];
		for($i = 0; ($i < count($tableData) || count($rowToAdd) > 0); $i++) {
			## Sometimes new rows will be added in order to wrap cells onto multiple pages
			if(count($rowToAdd) > 0) {
//				error_log("Row to Add at $currentY: ".implode("||",$rowToAdd));
				$tableRow = $rowToAdd;
//				$this->printText(0,5,"Starting overflow",[PDF_MemImage::FONT_SIZE => 9,PDF_MemImage::FONT_COLOR => [0,0,0],PDF_MemImage::FONT_NAME => "helvetica"]);
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
					if($this->h > 220) {
						$this->AddPage("P", "A4");
					}
					else {
						$this->AddPage("L", "A4");
					}
				}
				if($currentY == "") {
					$currentY = 10;
				}
				$this->SetFillColor(255,255,255);
				$this->SetTextColor($tableSettings[self::FONT_COLOR][0],$tableSettings[self::FONT_COLOR][1],$tableSettings[self::FONT_COLOR][2]);
				$this->SetFont($tableSettings[self::FONT_NAME],$tableSettings[self::FONT_STYLE],$tableSettings[self::FONT_SIZE]);
//				error_log("Wrapping from new row $currentY");
			}
			else {
				$tableRow = $tableData[$i];
			}
			$currentX = $x;
			$maxHeight = $this->getRowHeight($tableRow,$tableSettings);
			$widthArray = $this->getColumnWidths($tableRow,$tableSettings);

//			error_log("Need to wrap? $currentY $maxHeight ~ ".$tableSettings[self::WRAP_WITHIN_CELL_MIN_HEIGHT]." > ".($this->h - 10)."");
			## If this row will exceed the remaining height in the page, but there's enough room to start the table, split the current row
			if($tableSettings[self::WRAP_WITHIN_CELL_MIN_HEIGHT] < ($this->h - $currentY) && ($currentY + $maxHeight) > ($this->h - 10)) {
//				error_log("Splitting this thing");
				$splitRows = $this->splitRowByHeight($tableRow,$tableSettings,($this->h - 10 - $currentY));
				$tableRow = $splitRows[0];
				$rowToAdd = $splitRows[1];
				$maxHeight = $this->getRowHeight($tableRow,$tableSettings);
			}

			## If there's not enough room to print even part of this row, create new page and print it there.
			## This will wrap onto another page if there still isn't enough room
			if(($currentY + $maxHeight) > ($this->h - 10)) {
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
					if($this->h > 220) {
						$this->AddPage("P", "A4");
					}
					else {
						$this->AddPage("L", "A4");
					}
				}
				if($currentY == "") {
					$currentY = 10;
				}

				$this->SetFillColor(255,255,255);
				$this->SetTextColor($tableSettings[self::FONT_COLOR][0],$tableSettings[self::FONT_COLOR][1],$tableSettings[self::FONT_COLOR][2]);
				$this->SetFont($tableSettings[self::FONT_NAME],$tableSettings[self::FONT_STYLE],$tableSettings[self::FONT_SIZE]);

				## Check if this single cell will overflow and attempt to wrap the text to another page
				if(($currentY + $maxHeight) > ($this->h - 10)) {
					$splitRows = $this->splitRowByHeight($tableRow,$tableSettings,($this->h - 10 - $currentY));
					$tableRow = $splitRows[0];
					$rowToAdd = $splitRows[1];
					$maxHeight = $this->getRowHeight($tableRow,$tableSettings);
				}
			}
			$currentColumn = 0;
			$columnWidthType = $this->rowSpecifiesColumnWidth($tableRow);
/*echo "Table rows:<br/>";
echo "<pre>";
print_r($tableRow);
echo "</pre>";*/
			foreach($tableRow as $tableKey => $tableVal) {
				$this->SetY($currentY);
				$this->SetX($currentX);

				$columnWidth = 1;
				if($columnWidthType) {
					$columnWidth = $tableVal;
					$tableVal = $tableKey;
				}
				$cellWidth = $widthArray[$currentColumn + $columnWidth] - $widthArray[$currentColumn];

				if(is_array($tableSettings[self::NUMBER_DECIMALS])) {
					$thisDecimal = $tableSettings[self::NUMBER_DECIMALS][$currentColumn];
				}
				else {
					$thisDecimal = $tableSettings[self::NUMBER_DECIMALS];
				}

				## Round the number based on required decimal places/turn to scientific notation is won't fit
				if(is_numeric($tableVal) && abs($tableVal) > 999999) {
//					$origNumber = $tableVal;
					$tableVal = round($tableVal,(4-strlen(round($tableVal,0))));
					$tableVal = sprintf("%.3e",$tableVal);
//					echo "Found large $origNumber => $tableVal <Br />";
				}
				else {
					$tableVal = (is_numeric($tableVal) && $thisDecimal !== self::DECIMALS_MANUAL) ? number_format($tableVal,$thisDecimal) : $tableVal;
				}

				## Print borders first if needed
				if($tableSettings[self::CELL_BORDERS]) {
					$this->MultiCell($cellWidth,$maxHeight,"",$tableSettings[self::CELL_BORDERS],$alignmentArray[$currentColumn]);
					$this->SetY($currentY);
					$this->SetX($currentX);
				}
				## Then print the actual cell text second
//				error_log("Printing: ".$tableVal);
				$this->MultiCell($cellWidth,$tableSettings[self::FONT_SIZE]/2,$tableVal,0,$alignmentArray[$currentColumn]);
				$currentX += $cellWidth;
				$currentColumn += $columnWidth;
			}
			$currentY += $maxHeight;
			//echo "PDF current Y is $currentY with value $tableVal<br/>";

		}
		return $currentY;
	}

	function printText($x,$y,$text,$fontInfo) {
		$fontInfo[self::CELL_BORDERS] = $fontInfo[self::CELL_BORDERS] ? $fontInfo[self::CELL_BORDERS] : 0;

		$this->SetY($y);
		$this->SetX($x);

		if($fontInfo[self::FONT_ORIENTATION] == self::VERTICAL_ORIENTATION) {
			$this->Rotate(90);
		}

		$this->SetTextColor(0,0,0);
		$this->SetFont($fontInfo[self::FONT_NAME],$fontInfo[self::FONT_STYLE],$fontInfo[self::FONT_SIZE]);

		if(is_array($fontInfo[self::FONT_COLOR])) {
			$this->SetTextColor($fontInfo[self::FONT_COLOR][0],$fontInfo[self::FONT_COLOR][1],$fontInfo[self::FONT_COLOR][2]);
		}

		$width = $fontInfo[self::FONT_WIDTH] == "" ? $this->GetStringWidth($text) : $fontInfo[self::FONT_WIDTH];

		$this->Cell($width, floor($fontInfo[self::FONT_SIZE] / 2),$text,$fontInfo[self::CELL_BORDERS],0,$fontInfo[self::FONT_ALIGN]);

		if($fontInfo[self::FONT_ORIENTATION] == self::VERTICAL_ORIENTATION) {
			$this->Rotate(0);
		}
	}

	function Rotate($angle,$x=-1,$y=-1) {

		if($x==-1)
			$x=$this->x;
		if($y==-1)
			$y=$this->y;
		if($this->angle!=0)
			$this->_out('Q');
		$this->angle=$angle;
		if($angle!=0)

		{
			$angle*=M_PI/180;
			$c=cos($angle);
			$s=sin($angle);
			$cx=$x*$this->k;
			$cy=($this->h-$y)*$this->k;

			$this->_out(sprintf('q %.5f %.5f %.5f %.5f %.2f %.2f cm 1 0 0 1 %.2f %.2f cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
		}
	}

	function print_checkbox ( $x, $y, $checked= false,$width = 2, $height=2)
	{

		$this->Rect( $x, $y, $width, $height );

		if ( $checked)
		{
			$this->Line( $x, $y, $x + $width, $y + $height);
			$this->Line( $x, $y + $height, $x + $width, $y);
		}

	}

    function addNewPage(PDF_MemImage $pdf) {
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
        $pdf->Cell(0,2,"Record ID ".$module->record_id." (".$module->event_arm.")",0,1,'R');
        $pdf->image(APP_PATH_DOCROOT . "Resources/images/"."redcap-logo-small.png",176, 289, 24, 7);
        ## Return the y coordinate to start
        return 30;
    }
}