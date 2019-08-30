<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 6/20/2016
 * Time: 4:55 PM
 */
namespace Plugin;

global $Core;
$Core->Libraries(array("GeneralChart","PChart/class/pPie.class"),false);

use \pImage,\pData, \pPie;

class PieChart extends GeneralChart {
	/**
	 * @param $chartInfo array
	 * @return null|resource
	 * @throws \Exception
	 */
	public static function generateChart($chartInfo) {
		$chartData = self::generateChartData($chartInfo);

		$yOffset = 0;
		$bottomOffset = 0;
		$drawPieLegend = false;

		if($chartInfo[self::SHOW_LEGEND]) {
			$drawPieLegend = true;
			$chartInfo[self::SHOW_LEGEND] = false;
		}

		$chartPicture = self::generateChartPicture($chartInfo, $chartData, $yOffset, $bottomOffset);

		/* Create the pPie object */
		$PieChart = new pPie($chartPicture,$chartData);

		/* Set the default font properties */
		$chartPicture->setFontProperties(array("FontName"=>dirname(__FILE__)."/PChart/fonts/calibri.ttf",
				"FontSize"=>($chartInfo[self::LEGEND_FONT_SIZE] ? $chartInfo[self::LEGEND_FONT_SIZE] : 8),"R"=>0,"G"=>0,"B"=>0));

		if($drawPieLegend) {
			$legendCount = count($chartInfo[self::DATA_KEY]);

			if(array_key_exists($chartInfo[self::ABSCISSA_KEY],$chartInfo[self::DATA_KEY])) {
				$legendCount--;
			}

			/* Write the legend */
			$PieChart->drawPieLegend(10,$chartInfo[self::IMAGE_HEIGHT] - 6 - 10 * $legendCount,array("Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_VERTICAL));
			$bottomOffset += 6 + 10 * $legendCount;
		}

		$chartPicture->setFontProperties(array("FontName"=>dirname(__FILE__)."/PChart/fonts/calibri.ttf",
				"FontSize"=>($chartInfo[self::SCALE_FONT_SIZE] ? $chartInfo[self::SCALE_FONT_SIZE] : 10),"R"=>0,"G"=>0,"B"=>0));
		/* Draw an AA pie chart */
		$PieChart->draw2DPie($chartInfo[self::IMAGE_WIDTH] * 0.5,($chartInfo[self::IMAGE_HEIGHT] - $yOffset - $bottomOffset) * 0.5 + $yOffset,
				array("WriteValues"=>PIE_VALUE_AND_PERCENTAGE,"Border"=>TRUE,"ValueR" => 0, "ValueG" => 0, "ValueB" => 0,
				"Radius"=>min(($chartInfo[self::IMAGE_WIDTH]-25) * 0.35,($chartInfo[self::IMAGE_HEIGHT] - 25 - $bottomOffset - $yOffset) * 0.35)));

		return $chartPicture->Picture;
	}
}