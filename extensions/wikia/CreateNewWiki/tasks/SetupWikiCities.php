<?php

namespace Wikia\CreateNewWiki\Tasks;

use Wikia\Logger\Loggable;

class SetupWikiCities implements Task {
	use Loggable;

	const DEFAULT_SLOT = "slot1";

	private $taskContext;

	public function __construct( TaskContext $taskContext ) {
		$this->taskContext = $taskContext;
	}

	public function prepare() {
		return TaskResult::createForSuccess();
	}

	public function check() {
		return TaskResult::createForSuccess();
	}

	public function run() {
		if ( !$this->addToCityList() ) {
			$this->debug( implode( ":", [ 'CreateWiki', __METHOD__, "Cannot set data in city_list table" ] ) );
			wfProfileOut( __METHOD__ );
			return TaskResult::createForError( 'Cannot add wiki to city_list' );
		}

		// set new city_id
		// check the insert ID of insert to city_list executed inside addToCityList method
		$cityId = $insertId = $this->taskContext->getSharedDBW()->insertId();

		$this->taskContext->setCityId( $insertId );

		if ( empty($insertId) ) {
			wfProfileOut( __METHOD__ );
			return TaskResult::createForError( 'Cannot set data in city_list table. city_id is empty after insert' );
		}

		$this->debug( implode( ":", [ "CreateWiki", __METHOD__, "Row added added into city_list table, city_id = {$cityId}" ] ) );

		// add domain and www.domain to the city_domains table
		if ( !$this->addToCityDomains() ) {
			wfProfileOut( __METHOD__ );
			return TaskResult::createForError( "Cannot set data in city_domains table" );
		}

		$this->debug(
			implode( ":",
				[ "CreateWiki", __METHOD__, "Row added into city_domains table, city_id = {$cityId}" ]
			)
		);

		$this->taskContext->getSharedDBW()->commit( __METHOD__ ); // commit shared DB changes

		return TaskResult::createForSuccess();
	}

	private function addToCityList() {
		global $wgRequest;
		$founder = $this->taskContext->getFounder();

		$insertFields = [
			'city_title' => $this->taskContext->getSiteName(),
			'city_dbname' => $this->taskContext->getDbName(),
			'city_url' => $this->taskContext->getURL(),
			'city_founding_user' => $founder->getId(),
			'city_founding_email' => $founder->getEmail(),
			'city_founding_ip' => ip2long( $wgRequest->getIP() ),
			'city_path' => self::DEFAULT_SLOT,
			'city_description' => $this->taskContext->getSiteName(),
			'city_lang' => $this->taskContext->getLanguage(),
			'city_created' => wfTimestamp( TS_DB, time() ),
			'city_umbrella' => $this->taskContext->getWikiName()
		];
		if ( CreateDatabase::ACTIVE_CLUSTER ) {
			$insertFields["city_cluster"] = CreateDatabase::ACTIVE_CLUSTER;
		}

		return $this->taskContext->getSharedDBW()->insert( "city_list", $insertFields, __METHOD__ );
	}

	private function addToCityDomains() {
		return $this->taskContext->getSharedDBW()->insert(
			"city_domains",
			[
				[
					'city_id' => $this->taskContext->getCityId(),
					'city_domain' => $this->taskContext->getDomain()
				], [
				'city_id' => $this->taskContext->getCityId(),
				'city_domain' => sprintf( "www.%s", $this->taskContext->getDomain() )
			]
			],
			__METHOD__
		);
	}
}
