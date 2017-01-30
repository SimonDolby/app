<?php

class MercuryApiCategoryModel {

	// TODO update to 200
	const CATEGORY_MEMBERS_PER_PAGE = 30;

	/**
	 * @param string $categoryDBKey
	 * @param int $page
	 *
	 * @return array
	 */
	public static function getMembersGroupedByFirstLetter( string $categoryDBKey, int $page ) {
		$offset = ( $page - 1 ) * self::CATEGORY_MEMBERS_PER_PAGE;
		$membersTitles = self::getAlphabeticalList(
			$categoryDBKey,
			self::CATEGORY_MEMBERS_PER_PAGE,
			$offset
		);
		$membersGrouped = [];

		foreach ( $membersTitles as $memberTitle ) {
			$titleText = $memberTitle->getText();
			$firstLetter = mb_substr( $titleText, 0, 1, 'utf-8' );

			if ( !isset( $membersGrouped[$firstLetter] ) ) {
				$membersGrouped[$firstLetter] = [];
			}

			array_push( $membersGrouped[$firstLetter], [
				'title' => $titleText,
				'url' => $memberTitle->getLocalURL(),
				'isCategory' => $memberTitle->inNamespace( NS_CATEGORY )
			] );
		}

		return $membersGrouped;
	}

	/**
	 * @param $categoryDBKey
	 * @param $limit
	 * @param $offset
	 *
	 * @return Title[]
	 */
	private static function getAlphabeticalList( string $categoryDBKey, int $limit, int $offset ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			[ 'page', 'categorylinks' ],
			[ 'page_id', 'page_title' ],
			[ 'cl_to' => $categoryDBKey ],
			__METHOD__,
			[
				'ORDER BY' => 'page_title',
				'LIMIT' => $limit,
				'OFFSET' => $offset
			],
			[ 'categorylinks' => [ 'INNER JOIN', 'cl_from = page_id' ] ]
		);

		$pages = [];
		while ( $row = $res->fetchObject() ) {
			$title = Title::newFromID( $row->page_id );

			if ( $title instanceof Title) {
				array_push( $pages, $title );
			}
		}

		return $pages;
	}

	/**
	 * @param string $categoryDBKey
	 *
	 * @return int
	 */
	public static function getNumberOfPagesAvailable( string $categoryDBKey ) {
		$dbr = wfGetDB( DB_SLAVE );

		$numberOfPages = $dbr->select(
			[ 'page', 'categorylinks' ],
			[ 'COUNT(page_id) as count' ],
			[ 'cl_to' => $categoryDBKey ],
			__METHOD__,
			[],
			[ 'categorylinks' => [ 'INNER JOIN', 'cl_from = page_id' ] ]
		);

		return floor( $numberOfPages->fetchObject()->count / self::CATEGORY_MEMBERS_PER_PAGE );
	}

	public static function getNextPage( int $page, int $pages ) {
		if ( $page >= $pages ) {
			return null;
		} else {
			return $page + 1;
		}
	}

	public static function getPrevPage( int $page ) {
		if ( $page <= 1 ) {
			return null;
		} else {
			return $page - 1;
		}
	}
}
