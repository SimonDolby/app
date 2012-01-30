<?php

class FiveminVideoHandler extends VideoHandler {
	
	protected $apiName = 'FiveminApiWrapper';
	protected static $aspectRatio = 1.3636;	// 480 x 352
	protected static $urlTemplate = 'http://www.5min.com/Embeded/$1/&autostart=$2';
	
	public function getEmbed( $articleId, $width, $autoplay = false, $isAjax = false ) {
		$height =  $this->getHeight( $width );
		$sAutoPlay = $autoplay  ? 'true' : 'false';
		$url = str_replace( '$1', $this->videoId, self::$urlTemplate );
		$url = str_replace( '$2', $sAutoPlay, $url );
		$embedCode = <<<EOT
<embed src='{$url}' type='application/x-shockwave-flash' width="{$width}" height="{$height}" allowfullscreen='true' allowScriptAccess='always'></embed>
EOT;
		return $embedCode;
	}
}