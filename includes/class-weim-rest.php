<?php
/**
 * REST API endpoint that feeds the public-facing map.
 *
 * @package WP_Education_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WEIM_REST {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'wp-education-map/v1',
			'/institutions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_institutions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'program' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Return institutions as a JSON-friendly array for the frontend map.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_institutions( WP_REST_Request $request ) {
		$program      = $request->get_param( 'program' );
		$institutions = WEIM_DB::get_all( is_string( $program ) ? $program : '' );
		$programs     = WEIM_DB::get_programs();

		$data = array_map(
			function ( $institution ) use ( $programs ) {
				return array(
					'id'           => (int) $institution->id,
					'name'         => $institution->name,
					'city'         => $institution->city,
					'country'      => $institution->country,
					'latitude'     => (float) $institution->latitude,
					'longitude'    => (float) $institution->longitude,
					'program'      => $institution->program,
					'programLabel' => $programs[ $institution->program ] ?? $institution->program,
					'eventCount'   => (int) $institution->event_count,
					'website'      => esc_url_raw( $institution->website ),
				);
			},
			$institutions
		);

		return new WP_REST_Response( $data, 200 );
	}
}
