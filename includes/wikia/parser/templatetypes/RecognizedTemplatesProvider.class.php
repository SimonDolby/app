<?php

/**
 * Class provides methods to retrieve a list of templates defined as recognized
 * Also provides possibility to restrict list of templates to those used only on pages within certain namespaces
 * (both directly and indirectly)
 * e.g. wgContentNamespaces
 */

class RecognizedTemplatesProvider {
	const ERROR_MESSAGE = 'ExternalTemplatesProviderError';

	private $tcs;
	private $wikiId;
	private $namespaces;
	private $namespacesTemplates;

	public function __construct( TemplateClassificationService $tcs, $wikiId, $namespaces = [] ) {
		$this->tcs = $tcs;
		$this->wikiId = $wikiId;
		$this->namespaces = $namespaces;
	}

	public function getRecognizedTemplates() {
		$recognizedTemplates = $this->getTemplates( true );
		if ( empty( $this->namespaces ) ) {
			return $recognizedTemplates;
		}
		return $this->intersectSets( $recognizedTemplates, $this->getNamespacesTemplates() );
	}

	public function getNotRecognizedTemplates() {
		$notRecognizedTemplates = $this->getTemplates( false );
		if ( empty( $this->namespaces ) ) {
			return $notRecognizedTemplates;
		}
		return $this->intersectSets( $notRecognizedTemplates, $this->getNamespacesTemplates() );
	}

	public static function isUnrecognized( $type ) {
		return in_array( $type, [
			TemplateClassificationService::TEMPLATE_UNKNOWN,
			AutomaticTemplateTypes::TEMPLATE_UNCLASSIFIED,
			AutomaticTemplateTypes::TEMPLATE_OTHER
		] );
	}

	private function getTemplates( $getRecognized = true ) {
		try {
			$templates = $this->tcs->getTemplatesOnWiki( $this->wikiId );
		} catch ( \Swagger\Client\ApiException $exception ) {
			$context = [ 'TCSApiException' => $exception ];
			$this->handleException( $context );
			return [];
		}
		foreach ( $templates as $pageId => $type ) {
			if ( !( $getRecognized xor self::isUnrecognized( $type ) ) ) {
				unset( $templates[$pageId] );
			}
		}
		return $templates;
	}

	public function getNamespacesTemplates() {
		if ( isset( $this->namespacesTemplates ) ) {
			return $this->namespacesTemplates;
		}

		if ( empty( $this->namespaces ) ) {
			return [];
		}

		$this->namespacesTemplates = ( new \WikiaSQL() )
			->SELECT()->DISTINCT( 'p2.page_id as temp_id' )
			->FROM( 'page' )->AS_( 'p' )
			->INNER_JOIN( 'templatelinks' )->AS_( 't' )
			->ON( 't.tl_from', 'p.page_id' )
			->INNER_JOIN( 'page' )->AS_( 'p2' )
			->ON( 'p2.page_title', 't.tl_title' )
			->WHERE( 'p.page_namespace' )->IN( $this->namespaces )
			->AND_( 'p2.page_namespace' )->EQUAL_TO( NS_TEMPLATE )
			->AND_( 'p.page_id' )->NOT_EQUAL_TO( Title::newMainPage()->getArticleID() )
			->runLoop( $this->getDB(), function ( &$pages, $row ) {
				$pages[$row->temp_id] = $row->temp_id;
			} );

		return $this->namespacesTemplates;
	}

	/**
	 * Remove templates from second set that doesn't exist in first set
	 */
	private function intersectSets( $templates, $namespacesTemplates ) {
		return array_intersect_key( $templates, $namespacesTemplates );
	}

	private function getDB() {
		$wikiaDBName = WikiFactory::IDtoDB( $this->wikiId );
		return wfGetDB( DB_SLAVE, [], $wikiaDBName );
	}

	/**
	 * @desc handle exceptions
	 *
	 * @param array $context
	 */
	private function handleException( $context ) {
		\Wikia\Logger\WikiaLogger::instance()->error( self::ERROR_MESSAGE, $context );
	}
}
