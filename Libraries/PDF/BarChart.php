<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 6/14/2016
 * Time: 4:04 PM
 */

namespace Plugin;

global $Core;
$Core->Libraries(array("GeneralChart"),false);

class BarChart extends GeneralChart {
	const DATA_KEY = "data";
	const AXIS_KEY = "axis";
	const ABSCISSA_KEY = "abscissa";
	const BIG_TITLE = "big_title";
	const CHART_TITLE = "chart_title";
	const SHOW_LEGEND = "show_legend";
	const IMAGE_WIDTH = "image_width";
	const IMAGE_HEIGHT = "image_height";
	const BACKGROUND_COLOR = "background_color";

	/**
	 * @param $chartInfo array
	 * @return null|resource
	 * @throws \Exception
	 */
	public static function generateChart($chartInfo) {
		$chartData = self::generateChartData($chartInfo);

		$yOffset = 0;
		$bottomOffset = 0;

		$chartPicture = self::generateChartPicture($chartInfo,$chartData, $yOffset, $bottomOffset);

		/* Draw the scale and the 1st chart */
		$chartPicture->setGraphArea(30,25 + $yOffset,$chartInfo[self::IMAGE_WIDTH] * 0.9,$chartInfo[self::IMAGE_HEIGHT] - 25 - $bottomOffset);
		$chartPicture->drawFilledRectangle(30,25 + $yOffset,$chartInfo[self::IMAGE_WIDTH] * 0.9,$chartInfo[self::IMAGE_HEIGHT] - 25 - $bottomOffset,array("R"=>255,"G"=>255,"B"=>255,"Surrounding"=>-200,"Alpha"=>10));
		$chartPicture->drawScale(array("DrawSubTicks"=>TRUE,"Mode"=>SCALE_MODE_START0,"CycleBackground"=>TRUE));
		$chartPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10));
		$chartPicture->setFontProperties(array("FontName"=>dirname(__FILE__)."/PChart/fonts/calibri.ttf","FontSize"=>($chartInfo[self::SCALE_FONT_SIZE] ? $chartInfo[self::SCALE_FONT_SIZE] : 10)));
		$chartPicture->drawBarChart(array("DisplayValues"=>TRUE,"DisplayColor"=>DISPLAY_MANUAL,"Rounded"=>FALSE,"Surrounding"=>30));
		$chartPicture->setShadow(FALSE);

		if ( $chartPicture->TransparentBackground ) { imagealphablending($chartPicture->Picture,false); imagesavealpha($chartPicture->Picture,true); }

		return $chartPicture->Picture;
	}
}