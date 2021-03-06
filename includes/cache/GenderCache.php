<?php

/**
 * Caches user genders when needed to use correct namespace aliases.
 * @author Niklas Laxström
 * @since 1.18
 */
class GenderCache {
	protected $cache = array();
	protected $default;
	protected $misses = 0;
	protected $missLimit = 1000;

	/**
	 * @return GenderCache
	 */
	public static function singleton() {
		static $that = null;
		if ( $that === null ) {
			$that = new self();
		}
		return $that;
	}

	protected function __construct() {}

	/**
	 * Returns the default gender option in this wiki.
	 * @return String
	 */
	protected function getDefault() {
		if ( $this->default === null ) {
			$this->default = User::getDefaultOption( 'gender' );
		}
		return $this->default;
	}

	/**
	 * Returns the gender for given username.
	 * @param $username String: username
	 * @param $caller String: the calling method
	 * @return String
	 */
	public function getGenderOf( $username, $caller = '' ) {
		global $wgUser;

		$username = strtr( $username, '_', ' ' );
		if ( !isset( $this->cache[$username] ) ) {

			if ( $this->misses >= $this->missLimit && $wgUser->getName() !== $username ) {
				if( $this->misses === $this->missLimit ) {
					$this->misses++;
					wfDebug( __METHOD__ . ": too many misses, returning default onwards\n" );
				}
				return $this->getDefault();

			} else {
				$this->misses++;
				if ( !User::isValidUserName( $username ) ) {
					$this->cache[$username] = $this->getDefault();
				} else {
					$this->doQuery( $username, $caller );
				}
			}

		}

		/* Undefined if there is a valid username which for some reason doesn't
		 * exist in the database.
		 */
		return isset( $this->cache[$username] ) ? $this->cache[$username] : $this->getDefault();
	}

	/**
	 * Wrapper for doQuery that processes raw LinkBatch data.
	 *
	 * @param $data
	 * @param $caller
	 */
	public function doLinkBatch( $data, $caller = '' ) {
		$users = array();
		foreach ( $data as $ns => $pagenames ) {
			if ( !MWNamespace::hasGenderDistinction( $ns ) ) continue;
			foreach ( array_keys( $pagenames ) as $username ) {
				if ( isset( $this->cache[$username] ) ) continue;
				$users[$username] = true;
			}
		}

		$this->doQuery( array_keys( $users ), $caller );
	}

	/**
	 * Preloads genders for given list of users.
	 * @param $users array|String: userNames
	 * @param $caller String: the calling method
	 */
	public function doQuery( $users, $caller = '' ) {
		$default = $this->getDefault();

		// SUS-2779
		$users = $this->filterListOfUsers($users, $default);

		if ( count( $users ) === 0 ) {
			return;
		}

		foreach ( $this->getGenderOfUsersFromDB($users, $caller) as $row ) {
			$this->cache[$row->user_name] = $row->up_value ? $row->up_value : $default;
		}
	}

	private function filterListOfUsers( $users, $default ) {
		foreach ( (array)$users as $index => $value ) {
			$name = strtr( $value, '_', ' ' );
			if ( isset( $this->cache[$name] ) ) {
				// Skip users whose gender setting we already know
				unset( $users[$index] );
			} elseif ( !User::isValidUserName( $name ) ) {
				// SUS-3215 - do not perform queries for IP addresses
				unset( $users[$index] );
			} else {
				$users[$index] = $name;
				// For existing users, this value will be overwritten by the correct value
				$this->cache[$name] = $default;
			}
		}

		return $users;
	}

	/**
	 * @param $userNames
	 * @param $caller
	 * @return ResultWrapper
	 */
	private function getGenderOfUsersFromDB( $userNames, $caller ): ResultWrapper {
		global $wgExternalSharedDB;
		$dbr = wfGetDB( DB_SLAVE, [], $wgExternalSharedDB );

		$table = [ '`user`', 'user_properties' ];
		$fields = [ 'user_name', 'up_value' ];
		$conds = [ 'user_name' => $userNames ];
		$joins = [
			'user_properties' => [
				'LEFT JOIN', [ 'user_id = up_user', 'up_property' => 'gender' ],
			],
		];

		$comment = __METHOD__;
		if ( strval( $caller ) !== '' ) {
			$comment .= "/$caller";
		}

		return $dbr->select( $table, $fields, $conds, $comment, $joins, $joins );
	}
}
