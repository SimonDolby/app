<?php

class AdSS_BannerAd extends AdSS_Ad {

	// The temporary filename of the file in which the uploaded banner was
	// stored on the server.
	public $tmpBannerPath;

	function __construct() {
		parent::__construct();
		$this->type = 'b';
		$this->tmpBannerPath = null;
	}

	function loadFromForm( $f ) {
		parent::loadFromForm( $f );
		$this->tmpBannerPath = $f->get( 'wpBanner' );
	}

	function loadFromRow( $row ) {
		parent::loadFromRow( $row );
	}

	function save() {
		global $wgAdSS_DBname;

		$dbw = wfGetDB( DB_MASTER, array(), $wgAdSS_DBname );
		if( $this->id == 0 ) {
			$dbw->insert( 'ads',
					array(
						'ad_user_id'      => $this->userId,
						'ad_user_email'   => $this->userEmail,
						'ad_type'         => $this->type,
						'ad_url'          => $this->url,
						'ad_hub_id'       => $this->hubId,
						'ad_wiki_id'      => $this->wikiId,
						'ad_page_id'      => $this->pageId,
						'ad_status'       => $this->status,
						'ad_created'      => wfTimestampNow( TS_DB ),
						'ad_expires'      => wfTimestampOrNull( TS_DB, $this->expires ),
						'ad_weight'       => $this->weight,
						'ad_price'        => $this->price['price'],
						'ad_price_period' => $this->price['period'],
						'ad_pp_token'     => $this->pp_token,
					     ),
					__METHOD__
				    );
			$this->id = $dbw->insertId();
		} else {
			$dbw->update( 'ads',
					array(
						'ad_user_id'      => $this->userId,
						'ad_user_email'   => $this->userEmail,
						'ad_type'         => $this->type,
						'ad_url'          => $this->url,
						'ad_hub_id'       => $this->hubId,
						'ad_wiki_id'      => $this->wikiId,
						'ad_page_id'      => $this->pageId,
						'ad_status'       => $this->status,
						'ad_closed'       => wfTimestampOrNull( TS_DB, $this->closed ),
						'ad_expires'      => wfTimestampOrNull( TS_DB, $this->expires ),
						'ad_weight'       => $this->weight,
						'ad_price'        => $this->price['price'],
						'ad_price_period' => $this->price['period'],
						'ad_pp_token'     => $this->pp_token,
					     ),
					array(
						'ad_id' => $this->id
					     ),
					__METHOD__
				    );
		}
		$dbw->commit();
		if( $this->tmpBannerPath ) {
			move_uploaded_file( $this->tmpBannerPath, $this->getBannerPath() );
			$this->tmpBannerPath = null;
		}
	}

	function render( $tmpl ) {
		$tmpl->set( 'ad', $this );
		return $tmpl->render( 'bannerAd' );
	}

	function getBannerPath() {
		global $wgAdSS_BannerUploadDirectory;
		$dir = $wgAdSS_BannerUploadDirectory;
		if( substr( $dir, -1 ) != '/' ) {
			$dir .= '/';
		}
		return $dir . $this->id;
	}

	function getBannerURL() {
		global $wgAdSS_BannerUploadPath;
		$url = $wgAdSS_BannerUploadPath;
		if( substr( $url, -1 ) != '/' ) {
			$url .= '/';
		}
		return $url . $this->id;
	}
}
