<?php
/**
 * Database layer for the institutions table.
 *
 * @package WP_Education_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WEIM_DB {

	/**
	 * Allowed program types, including any custom programs added by an admin.
	 *
	 * @return array<string,string>
	 */
	public static function get_programs() {
		return WEIM_Programs::get_all();
	}

	/**
	 * Full table name, including the site's prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'weim_institutions';
	}

	/**
	 * Create the table on first activation, or upgrade it when the schema changes.
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'weim_db_version' );

		if ( WEIM_DB_VERSION === $installed_version ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			city VARCHAR(191) NOT NULL,
			country VARCHAR(191) NOT NULL DEFAULT '',
			latitude DECIMAL(10,6) NOT NULL,
			longitude DECIMAL(10,6) NOT NULL,
			program VARCHAR(32) NOT NULL DEFAULT 'wpcc',
			event_count INT UNSIGNED NOT NULL DEFAULT 0,
			website VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY program (program)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'weim_db_version', WEIM_DB_VERSION );
	}

	/**
	 * Insert a new institution.
	 *
	 * @param array $data Sanitized institution data.
	 * @return int|WP_Error Inserted row ID, or WP_Error on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'name'        => $data['name'],
				'city'        => $data['city'],
				'country'     => $data['country'],
				'latitude'    => $data['latitude'],
				'longitude'   => $data['longitude'],
				'program'     => $data['program'],
				'event_count' => $data['event_count'],
				'website'     => $data['website'],
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'weim_insert_failed', __( 'Could not save the institution.', 'wp-education-map' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing institution.
	 *
	 * @param int   $id   Institution ID.
	 * @param array $data Sanitized institution data.
	 * @return bool|WP_Error
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'name'        => $data['name'],
				'city'        => $data['city'],
				'country'     => $data['country'],
				'latitude'    => $data['latitude'],
				'longitude'   => $data['longitude'],
				'program'     => $data['program'],
				'event_count' => $data['event_count'],
				'website'     => $data['website'],
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'weim_update_failed', __( 'Could not update the institution.', 'wp-education-map' ) );
		}

		return true;
	}

	/**
	 * Delete an institution.
	 *
	 * @param int $id Institution ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get a single institution by ID.
	 *
	 * @param int $id Institution ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Get all institutions, optionally filtered by program.
	 *
	 * @param string $program Optional program key to filter by.
	 * @return array
	 */
	public static function get_all( $program = '' ) {
		global $wpdb;
		$table = self::table_name();

		if ( $program && array_key_exists( $program, self::get_programs() ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE program = %s ORDER BY name ASC", $program ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	/**
	 * Count all institutions.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Count institutions using a given program.
	 *
	 * @param string $program Program key.
	 * @return int
	 */
	public static function count_by_program( $program ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE program = %s", $program ) );
	}
}
