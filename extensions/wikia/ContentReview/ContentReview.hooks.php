<?php

namespace Wikia\ContentReview;

use Wikia\ContentReview\Helper;
use Wikia\ContentReview\Models\CurrentRevisionModel;
use Wikia\ContentReview\Models\ReviewModel;

class Hooks {
	const CONTENT_REVIEW_MONOBOOK_DROPDOWN_ACTION = 'content-review';

	public static function onGetRailModuleList( Array &$railModuleList ) {
		global $wgCityId, $wgTitle;

		if ( self::userCanEditJsPage() ) {
			$pageStatus = \F::app()->sendRequest(
				'ContentReviewApiController',
				'getPageStatus',
				[
					'wikiId' => $wgCityId,
					'pageId' => $wgTitle->getArticleID(),
				],
				true
			)->getData();

			$railModuleList[1503] = [ 'ContentReviewModule', 'Render', [
				'pageStatus' => $pageStatus,
				'latestRevisionId' => $wgTitle->getLatestRevID(),
			] ];
		}

		return true;
	}

	public static function onMakeGlobalVariablesScript( &$vars ) {
		$helper = new Helper();

		$vars['contentReviewExtEnabled'] = true;
		$vars['contentReviewTestModeEnabled'] = $helper->isContentReviewTestModeEnabled();
		$vars['contentReviewScriptsHash'] = $helper->getSiteJsScriptsHash();

		return true;

	}

	public static function onBeforePageDisplay( \OutputPage $out, \Skin $skin ) {
		$helper = new Helper();

		/* Add assets for custom JS test mode */
		if ( $helper->isContentReviewTestModeEnabled() || self::userCanEditJsPage() ) {
			\Wikia::addAssetsToOutput( 'content_review_test_mode_js' );
			\JSMessages::enqueuePackage( 'ContentReviewTestMode', \JSMessages::EXTERNAL );
		}

		/* Add Content Review Module assets for Monobook  */
		if ( $skin->getSkinName() !== 'monobook' ||
			self::userCanEditJsPage()
		) {
			\Wikia::addAssetsToOutput('content_review_module_monobook_js');
			\Wikia::addAssetsToOutput('content_review_module_monobook_scss');
		}

		return true;
	}

	public static function onArticleContentOnDiff( $diffEngine, \OutputPage $output ) {
		global $wgTitle, $wgCityId, $wgRequest;
		$diff = $wgRequest->getInt( 'diff' );

		$helper = new Helper();
		if ( $wgTitle->inNamespace( NS_MEDIAWIKI )
			&& $wgTitle->isJsPage()
			&& $wgTitle->userCan( 'content-review' )
			&& $helper->isDiffPageInReviewProcess( $wgCityId, $wgTitle->getArticleID(), $diff )
		) {
			\Wikia::addAssetsToOutput( 'content_review_diff_page_js' );
			\Wikia::addAssetsToOutput( 'content_review_diff_page_scss' );
			\JSMessages::enqueuePackage( 'ContentReviewDiffPage', \JSMessages::EXTERNAL );

			$output->prependHTML( $helper->getToolbarTemplate() );
		}

		return true;
	}

	/**
	 * Replace content shown on raw action for JS pages with last approved revision
	 * @param \RawAction $rawAction
	 * @param $text
	 * @return bool
	 */
	public static function onRawPageViewBeforeOutput( \RawAction $rawAction, &$text ) {
		global $wgCityId, $wgJsMimeType;

		$title = $rawAction->getTitle();

		if ( $title->inNamespace( NS_MEDIAWIKI )
			&& ( $title->isJsPage() || $rawAction->getContentType() == $wgJsMimeType )
		) {
			$pageId = $title->getArticleID();
			$latestRevId = $title->getLatestRevID();

			$latestReviewedRev = ( new CurrentRevisionModel() )->getLatestReviewedRevision( $wgCityId, $pageId );
			$helper = new Helper();

			if ( $latestReviewedRev['revision_id'] != $latestRevId
				&& !$helper->isContentReviewTestModeEnabled()
			) {
				$revision = \Revision::newFromId( $latestReviewedRev['revision_id'] );

				if ( $revision ) {
					$text = $revision->getRawText();
				} else {
					$text = '';
				}
			}
		}

		return true;
	}

	/*
	 * Adds review status item to top nav tabs in Monobook skin.
	 * This is an entrypoint for checking review status and submitting changes for review/
	 * This is attached to the MediaWiki 'SkinTemplateNavigation' hook.
	 * @param SkinTemplate $skin
	 * @param array $links Navigation links
	 * @return bool true
	 */
	public static function onSkinTemplateNavigation( $skin, &$links ) {
		global $wgCityId, $wgTitle;

		if ( $skin->getSkinName() !== 'monobook' ||
			!self::userCanEditJsPage()
		) {
			return true;
		}

		/* Get page status */
		$pageStatus = \F::app()->sendRequest(
			'ContentReviewApiController',
			'getPageStatus',
			[
				'wikiId' => $wgCityId,
				'pageId' => $wgTitle->getArticleID(),
			],
			true
		)->getData();

		/* Determine review status */
		$latestRevisionId = $wgTitle->getLatestRevID();
		$latestRevisionId = (int)$latestRevisionId;
		if ( $latestRevisionId === 0 ) {
			$latestStatus = \ContentReviewModuleController::STATUS_NONE;
		} elseif ( $latestRevisionId === (int)$pageStatus['liveId'] ) {
			$latestStatus = \ContentReviewModuleController::STATUS_LIVE;
		} elseif ( $latestRevisionId === (int)$pageStatus['latestId'] &&
			Helper::isStatusAwaiting( $pageStatus['latestStatus'] )
		) {
			$latestStatus = \ContentReviewModuleController::STATUS_AWAITING;
		} elseif ( $latestRevisionId === (int)$pageStatus['lastReviewedId'] &&
			(int)$pageStatus['lastReviewedStatus'] === ReviewModel::CONTENT_REVIEW_STATUS_REJECTED
		) {
			$latestStatus = \ContentReviewModuleController::STATUS_REJECTED;
		} elseif ( $latestRevisionId > (int)$pageStatus['liveId'] &&
			$latestRevisionId > (int)$pageStatus['latestId'] &&
			$latestRevisionId > (int)$pageStatus['lastReviewedId']
		) {
			$latestStatus = \ContentReviewModuleController::STATUS_UNSUBMITTED;
		}

		/* Add link to nav tabs customized with status class name */
		$links['views'][self::CONTENT_REVIEW_MONOBOOK_DROPDOWN_ACTION] = [
			'href' => '#',
			'text' => wfMessage( 'content-review-status-link-text' )->escaped(),
			'class' => 'content-review-status ' . 'content-review-cactions-status-' . $latestStatus,
		];

		return true;
	}

	public static function onUserLogoutComplete( \User $user, &$injected_html, $oldName) {
		$request = $user->getRequest();

		$key = \ContentReviewApiController::CONTENT_REVIEW_TEST_MODE_KEY;
		$wikis = $request->getSessionData( $key );

		if ( !empty( $wikis ) ) {
			$request->setSessionData( $key, null );
		}

		return true;
	}

	private static function userCanEditJsPage() {
		global $wgTitle, $wgUser;

		return $wgTitle->inNamespace( NS_MEDIAWIKI ) && $wgTitle->isJsPage() && $wgTitle->userCan( 'edit', $wgUser );
	}
}
