<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 6/21/2016
 * Time: 11:00 AM
 */

namespace Plugin;

global $Core;
$Core->Libraries(array("PChart/class/pData.class","PChart/class/pDraw.class","PChart/class/pImage.class"),false);

use \pImage,\pData;

class GeneralChart {
	const DATA_KEY = "data";
	const AXIS_KEY = "axis";
	const ABSCISSA_KEY = "abscissa";
	const BIG_TITLE = "big_title";
	const CHART_TITLE = "chart_title";
	const SHOW_LEGEND = "show_legend";
	const IMAGE_WIDTH = "image_width";
	const IMAGE_HEIGHT = "image_height";
	const BACKGROUND_COLOR = "background_color";
	const SCALE_FONT_SIZE = "scale_font_size";
	const LEGEND_FONT_SIZE = "legend_font_size";

	/**
	 * @param $chartInfo array
	 * @return pData
	 * @throws \Exception
	 */
	public static function generateChartData($chartInfo) {
		$chartData = new pData();
		$chartData->loadPalette(dirname(__FILE__)."/PChart/palettes/blind.color",TRUE);

		foreach([self::DATA_KEY] as $requiredTag) {
			if(!array_key_exists($requiredTag,$chartInfo)) {
				throw new \Exception("Required tags not included in the Barchart");
			}
		}

		foreach($chartInfo[self::DATA_KEY] as $label => &$dataArray) {
			# Round before printing to prevent some sillyness
			if(is_array($dataArray) && $label != $chartInfo[self::ABSCISSA_KEY][0]) {
				foreach($dataArray as &$dataValues) {
					$dataValues = round($dataValues,3);
				}
			}
			else if($label != $chartInfo[self::ABSCISSA_KEY][0]) {
				$dataArray = round($dataArray,3);
			}
			$chartData->addPoints($dataArray,$label);
			$chartData->setSerieTicks($label);
		}

		foreach($chartInfo[self::AXIS_KEY] as $axisId => $axisName) {
			$chartData->setAxisName($axisId, $axisName);
		}

		foreach($chartInfo[self::ABSCISSA_KEY] as $abscissaLabel) {
			$chartData->setAbscissa($abscissaLabel);
		}

		return $chartData;
	}

	/**
	 * @param $chartInfo array
	 * @param $chartData pData
	 * @param $yOffset int
	 * @param $bottomOffset int
	 * @return pImage
	 * @throws \Exception
	 */
	public static function generateChartPicture($chartInfo, $chartData, &$yOffset, &$bottomOffset) {
		foreach([self::IMAGE_HEIGHT,self::IMAGE_WIDTH] as $requiredTag) {
			if(!array_key_exists($requiredTag,$chartInfo)) {
				throw new \Exception("Required tags not included in the Barchart");
			}
		}

		/* Create the pChart object */
		$chartPicture = new pImage($chartInfo[self::IMAGE_WIDTH],$chartInfo[self::IMAGE_HEIGHT],$chartData);

		/* Draw a solid background */
		//, "Dash"=>1, "DashR"=>200, "DashG"=>200, "DashB"=>200
//		$Settings = array("R"=>255, "G"=>255, "B"=>255);
//		$myPicture->drawFilledRectangle(0,0,$chartInfo[self::IMAGE_WIDTH],$chartInfo[self::IMAGE_HEIGHT],$Settings);

		/* Add a border to the picture */
		$chartPicture->drawRectangle(0,0,$chartInfo[self::IMAGE_WIDTH]-1,$chartInfo[self::IMAGE_HEIGHT]-1,array("R"=>0,"G"=>0,"B"=>0));

		if($chartInfo[self::BIG_TITLE]) {
			/* Write the picture title */
			$chartPicture->drawGradientArea(0,$yOffset,$chartInfo[self::IMAGE_WIDTH],20 + $yOffset,DIRECTION_VERTICAL,array("StartR"=>0,"StartG"=>0,"StartB"=>0,"EndR"=>50,"EndG"=>50,"EndB"=>50,"Alpha"=>100));
			$chartPicture->setFontProperties(array("FontName"=>dirname(__FILE__)."/PChart/fonts/calibri.ttf","FontSize"=>11));
			$chartPicture->drawText(10,17 + $yOffset,$chartInfo[self::BIG_TITLE],array("R"=>255,"G"=>255,"B"=>255));
			$yOffset += 20;
		}

		$chartPicture->setFontProperties(array("FontName"=>dirname(__FILE__)."/PChart/fonts/calibri.ttf",
				"FontSize"=>($chartInfo[self::LEGEND_FONT_SIZE] ? $chartInfo[self::LEGEND_FONT_SIZE] : 11)));
		if($chartInfo[self::CHART_TITLE]) {
			$outputArray = [$chartInfo[self::CHART_TITLE]];

			## Check the title for bounding box issues
			$titleFontSize = 16;
			$textBounds = imagettfbbox($titleFontSize,0,dirname(__FILE__)."/PChart/fonts/calibri.ttf",$chartInfo[self::CHART_TITLE]);

			if(($textBounds[2] - $textBounds[0]) > $chartInfo[self::IMAGE_WIDTH]) {
				$newTexts = explode(" ",$chartInfo[self::CHART_TITLE]);
				$currentText = "";
				$outputArray = [];
				foreach($newTexts as $currentWord) {
					$currentText .= ($currentText == "" ? "" : " ").$currentWord;
					if(strlen($currentText) > (strlen($chartInfo[self::CHART_TITLE]) / 2)) {
						$outputArray[] = $currentText;
						$currentText = "";
					}
				}
				$outputArray[] = $currentText;
			}

			foreach($outputArray as $outputLine) {
				$textBounds = imagettfbbox($titleFontSize,0,dirname(__FILE__)."/PChart/fonts/calibri.ttf",$outputLine);
				if(($textBounds[2] - $textBounds[0]) > $chartInfo[self::IMAGE_WIDTH]) {
					$titleFontSize = 12;
					break;
				}
			}
			/* Write the chart title */
			foreach($outputArray as $outputLine) {
				$chartPicture->drawText($chartInfo[self::IMAGE_WIDTH]/2,35 + $yOffset,$outputLine,array("FontSize"=>$titleFontSize,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE));
				$yOffset += 25;
			}
		}

		if($chartInfo[self::SHOW_LEGEND]) {
			$legendCount = count($chartInfo[self::DATA_KEY]);

			if(array_key_exists($chartInfo[self::ABSCISSA_KEY],$chartInfo[self::DATA_KEY])) {
				$legendCount--;
			}

			/* Write the chart legend */
			$chartPicture->drawLegend(10,$chartInfo[self::IMAGE_HEIGHT] - 10*$legendCount - 2,array("Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_VERTICAL,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
			$bottomOffset += 10*$legendCount + 6;
		}

		return $chartPicture;
	}


}