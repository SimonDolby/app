<?php

class ImageTweaksHooks {

	static private $isWikiaMobile = null;
	static private $isOasis = null;

	static function init(){
		$app = F::app();

		if ( self::$isOasis === null )
			self::$isOasis = $app->checkSkin( 'oasis' );

		if ( self::$isWikiaMobile === null )
			self::$isWikiaMobile = ( !empty( $isOasis ) ) ? false : $app->checkSkin( 'wikiamobile' );
	}

	// Only run on mobile now
	static public function onThumbnailAfterProduceHTML( $frameParams, $thumb, $origHTML, &$html ){
		global $wgRTEParserEnabled;
		if ( !empty( $wgRTEParserEnabled ) ) {
			return true;
		}

		wfProfileIn( __METHOD__ );

		$linked = !empty( $frameParams['link-url'] ) || !empty( $frameParams['link-title'] );
		$caption = ( !empty( $frameParams['caption'] ) ) ? $frameParams['caption'] : null;

		if( is_object( $thumb ) ) {
			$isSmall = WikiaMobileMediaService::isSmallImage( $thumb->getWidth(), $thumb->getHeight() );
		} else {
			$isSmall = false;
		}

		$html = F::app()->sendRequest(
			'WikiaMobileMediaService',
			'renderFigureTag',
			array(
				'class' => [( $linked ) ? 'link' : 'thumb'],
				'content' => $origHTML,
				//force the caption wrapper to exist if it's a linked image without caption
				'caption' => ( $linked && empty( $caption ) ) ? '' :  $caption,
				'isSmall' => $isSmall
			),
			true
		)->toString();

		wfProfileOut( __METHOD__ );

		return true;
	}

	// Only run on mobile now
	static public function onImageAfterProduceHTML( $frameParams, $thumb, $origHTML, &$html ){
		global $wgRTEParserEnabled;
		if ( !empty( $wgRTEParserEnabled ) ) {
			return true;
		}

		if ( is_null(self::$isWikiaMobile) ) {
			self::init();
		}

		/**
		 * WikiaMobile - non-framed non-thumb images should have the same markup as thumbed/framed images/thumbnails
		 * @author Federico "Lox" Lucignano <federico@wikia-inc.com>
		 */
		if ( self::$isWikiaMobile ) {
			wfProfileIn( __METHOD__ );

			$linked = !empty( $frameParams['link-url'] ) || !empty( $frameParams['link-title'] );
			$caption = ( !empty( $frameParams['caption'] ) ) ? $frameParams['caption'] : null;

			if( is_object( $thumb ) ) {
				$isSmall = WikiaMobileMediaService::isSmallImage( $thumb->getWidth(), $thumb->getHeight() );
			} else {
				$isSmall = true;
			}

			$html = F::app()->sendRequest(
				'WikiaMobileMediaService',
				'renderFigureTag',
				array(
					'class' => [( $linked ) ? 'link' : 'thumb'],
					'content' => $origHTML,
					//force the caption wrapper to exist if it's a linked image without caption
					'caption' => ( $linked && empty( $caption ) ) ? '' : $caption,
					'isSmall' => $isSmall
				),
				true
			)->toString();

			wfProfileOut( __METHOD__ );
		}

		return true;
	}

	// still used on desktop and mobile
	static public function onThumbnailImageHTML( $options, $linkAttribs, $imageAttribs, File $file, &$html ){
		global $wgRTEParserEnabled;
		if ( !empty( $wgRTEParserEnabled ) ) {
			return true;
		}

		// video thumbnails use a template now.
		if ( WikiaFileHelper::isVideoFile( $file ) ) {
			return true;
		}

		wfProfileIn( __METHOD__ );
		if ( is_null(self::$isWikiaMobile) ) {
			self::init();
		}
		if (
			/**
			 * Images SEO project
			 * @author: Marooned
			 */
			(
				empty( $options['custom-url-link'] ) &&
				empty( $options['custom-title-link'] ) &&
				!empty( $options['desc-link'] ) &&
				self::$isOasis
			) ||
			/**
			 * in the WikiaMobile skin we need all the images to be treated the same
			 * @author Federico "Lox" Lucignano <federico@wikia-inc.com>
			 */
			self::$isWikiaMobile
		) {
			$fullImageUrl = false;
			$link = null;

			if ( is_array( $linkAttribs ) ) {
				if ( !empty( $file ) ) {
					// $fullImageUrl only used on mobile
					$fullImageUrl = wfReplaceImageServer( $file->getUrl(), $file->getTimestamp() );
				}

				if ( !empty ( $options['custom-url-link'] ) ) {
					// $link only used on mobile
					$link = $options['custom-url-link'];
				} elseif (
					!empty( $options['custom-title-link'] ) &&
					$options['custom-title-link'] instanceof Title
				) {
					// Caption is set but image is not a "thumb" so the caption gets set as the title attribute
					$title = $options['custom-title-link'];
					$linkAttribs['title'] = $title->getFullText();
					$link = $title->getLinkUrl();
				} elseif ( !empty( $options['file-link'] ) && empty( $options['desc-link'] ) ) {
					$linkAttribs['class'] = empty($linkAttribs['class']) ? ' lightbox' : $linkAttribs['class'] . ' lightbox';
				}

				//override any previous value if title is passed as an option
				if ( !empty( $options['title'] ) ) {
					$linkAttribs['title'] = $options['title'];
				}
			}

			//remove the empty alt attribute which we print pretty everywhere (meh)
			if ( empty( $imageAttribs['alt'] ) ) {
				unset( $imageAttribs['alt'] );
			}

			$contents = Xml::element( 'img', $imageAttribs );

			if ( self::$isWikiaMobile ) {
				$imageParams = array();

				if ( !empty( $link ) ) {
					$linkAttribs['href'] = $link;
				}

				if ( empty( $linkAttribs['class'] ) ) {
					$linkAttribs['class'] = 'image';
				}

				if ( !empty( $imageAttribs['data-image-key'] ) ) {
					$imageParams['name'] = htmlspecialchars( $imageAttribs['data-image-key'] );
				}

				if ( !empty( $fullImageUrl ) ) {
					$imageParams['full'] = $fullImageUrl;
				} else {
					$imageParams['full'] = $imageAttribs['src'];
				}

				if ( !empty( $options['caption'] ) ) {
					$imageParams['capt'] = 1;
				}

				//images set to be less than 64px are probably
				//being used as icons in templates
				//do not resize them
				if (
					( (!empty( $imageAttribs['width'] ) && $imageAttribs['width'] > 64 ) || empty( $imageAttribs['width'] ) )
					&& $file instanceof File
				) {
					// TODO: this resizes every image with a width over 64px regardless of where it appears.
					// We may want to add the ability to allow custom image widths (like on the file page history table for example)
					$size = WikiaMobileMediaService::calculateMediaSize( $file->getWidth(), $file->getHeight() );
					$thumb = $file->transform( $size );
					$imageAttribs['src'] = wfReplaceImageServer( $thumb->getUrl(), $file->getTimestamp() );
					$imageAttribs['width'] = $size['width'];
					$imageAttribs['height'] = $size['height'];
				}

				/**
				 *WikiaMobile: lazy loading images in a SEO-friendly manner
				 *@author Federico "Lox" Lucignano <federico@wikia-inc.com
				 */
				$html = F::app()->sendRequest(
					'WikiaMobileMediaService',
					'renderImageTag',
					[
						'attributes' => $imageAttribs,
						'parameters' => [ $imageParams ],
						'anchorAttributes' => $linkAttribs,
						'linked' => !empty( $link ),
						'noscript' => $contents,
						'isSmall' => WikiaMobileMediaService::isSmallImage($imageAttribs['width'],  $imageAttribs['height'])
					],
					true
				)->toString();
			} else {
				$html = ( $linkAttribs ) ? Xml::tags( 'a', $linkAttribs, $contents ) : $contents;
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	// Only used on mobile
	static public function onThumbnailVideoHTML( $options, $linkAttribs, $imageAttribs, File $file, &$html ){
		global $wgRTEParserEnabled;
		if ( !empty( $wgRTEParserEnabled ) ) {
			return true;
		}

		if ( is_null(self::$isWikiaMobile) ) {
			self::init();
		}

		if ( self::$isWikiaMobile ) {
			wfProfileIn( __METHOD__ );

			/**
			 * WikiaMobile: lazy loading images in a SEO-friendly manner
			 * @author Federico "Lox" Lucignano <federico@wikia-inc.com
			 * @author Artur Klajnerok <arturk@wikia-inc.com>
			 */
			$origImg = Xml::element( 'img', $imageAttribs, '', true );

			if ( empty( $imageAttribs['alt'] ) ) {
				unset( $imageAttribs['alt'] );
			}

			//Not all 'files' have getProviderName defined
			if ( is_callable( [ $file, 'getProviderName' ] ) ) {
				$provider = $file->getProviderName();
			} else {
				$provider = '';
			}

			$imageParams = array(
				'type' => 'video',
				'provider' => $provider,
				'full' => $imageAttribs['src']
			);

			if ( !empty($imageAttribs['data-video-key'] ) ) {
				$imageParams['name'] = htmlspecialchars( $imageAttribs['data-video-key'] );
			}

			if ( !empty( $options['caption'] ) ) {
				$imageParams['capt'] = 1;
			}

			// TODO: this resizes every video thumbnail with a width over 64px regardless of where it appears.
			// We may want to add the ability to allow custom image widths (like on the file page history table for example)
			$size = WikiaMobileMediaService::calculateMediaSize( $file->getWidth(), $file->getHeight() );
			$thumb = $file->transform( $size );
			$imageAttribs['src'] = wfReplaceImageServer( $thumb->getUrl(), $file->getTimestamp() );
			$imageAttribs['width'] = $size['width'];
			$imageAttribs['height'] = $size['height'];


			$data = [
				'attributes' => $imageAttribs,
				'parameters' => [ $imageParams ],
				'anchorAttributes' => $linkAttribs,
				'noscript' => $origImg,
				'isSmall' => WikiaMobileMediaService::isSmallImage($imageAttribs['width'], $imageAttribs['height'])
			];

			$title = $file->getTitle()->getDBKey();
			$titleText = $file->getTitle()->getText();
			$views = MediaQueryService::getTotalVideoViewsByTitle( $title );

			$data['content'] = Xml::element(
				'span',
				[ 'class' => 'videoInfo' ],
				"{$titleText} (" . $file->getHandler()->getFormattedDuration() .
				", " . wfMessage( 'wikiamobile-video-views-counter', $views )->inContentLanguage()->text() .
				')'
			);

			$html = F::app()->sendRequest(
				'WikiaMobileMediaService',
				'renderImageTag',
				$data,
				true
			)->toString();

			wfProfileOut( __METHOD__ );
		}

		return true;
	}
}
