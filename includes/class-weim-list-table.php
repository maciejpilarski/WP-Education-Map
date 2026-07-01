<?php
/**
 * List table for browsing institutions in the Dashboard.
 *
 * @package WP_Education_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WEIM_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'institution',
				'plural'   => 'institutions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'name'        => __( 'Institution', 'wp-education-map' ),
			'city'        => __( 'City', 'wp-education-map' ),
			'country'     => __( 'Country', 'wp-education-map' ),
			'program'     => __( 'Program', 'wp-education-map' ),
			'event_count' => __( 'Events', 'wp-education-map' ),
			'coordinates' => __( 'Coordinates', 'wp-education-map' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'    => array( 'name', false ),
			'city'    => array( 'city', false ),
			'program' => array( 'program', false ),
		);
	}

	public function no_items() {
		esc_html_e( 'No institutions have been added yet.', 'wp-education-map' );
	}

	public function prepare_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
		$program = isset( $_REQUEST['program'] ) ? sanitize_key( wp_unslash( $_REQUEST['program'] ) ) : '';

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = WEIM_DB::get_all( $program );
	}

	protected function column_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'page' => 'weim-add-new',
				'id'   => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'weim-institutions',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'weim_delete_institution_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'wp-education-map' ) ),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this institution? This cannot be undone.', 'wp-education-map' ) ),
				esc_html__( 'Delete', 'wp-education-map' )
			),
		);

		return sprintf( '<strong><a href="%s">%s</a></strong>%s', esc_url( $edit_url ), esc_html( $item->name ), $this->row_actions( $actions ) );
	}

	protected function column_program( $item ) {
		$programs = WEIM_DB::get_programs();
		return isset( $programs[ $item->program ] ) ? esc_html( $programs[ $item->program ] ) : esc_html( $item->program );
	}

	protected function column_coordinates( $item ) {
		return esc_html( $item->latitude . ', ' . $item->longitude );
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}
}
