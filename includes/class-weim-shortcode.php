<?php
/**
 * Public-facing [wp_education_map] shortcode.
 *
 * @package WP_Education_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WEIM_Shortcode {

	public function __construct() {
		add_shortcode( 'wp_education_map', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but don't yet enqueue) the map assets, so they only load on pages using the shortcode.
	 */
	public function register_assets() {
		wp_register_style( 'weim-leaflet', WEIM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'weim-leaflet', WEIM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );

		wp_register_style( 'weim-map', WEIM_PLUGIN_URL . 'assets/css/map.css', array( 'weim-leaflet' ), WEIM_VERSION );
		wp_register_script( 'weim-map', WEIM_PLUGIN_URL . 'assets/js/map.js', array( 'weim-leaflet' ), WEIM_VERSION, true );
	}

	/**
	 * Render the map container. Data is fetched client-side from the REST endpoint.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$settings = WEIM_Settings::get();

		$atts = shortcode_atts(
			array(
				'program' => '',
				'height'  => $settings['height'],
				'width'   => $settings['width'],
			),
			$atts,
			'wp_education_map'
		);

		if ( ! WEIM_Settings::is_valid_dimension( $atts['height'] ) ) {
			$atts['height'] = $settings['height'];
		}

		if ( ! WEIM_Settings::is_valid_dimension( $atts['width'] ) ) {
			$atts['width'] = $settings['width'];
		}

		wp_enqueue_style( 'weim-leaflet' );
		wp_enqueue_script( 'weim-leaflet' );
		wp_enqueue_style( 'weim-map' );
		wp_enqueue_script( 'weim-map' );

		static $instance = 0;
		++$instance;
		$id = 'weim-map-' . $instance;

		ob_start();
		?>
		<div class="weim-map-wrapper">
			<div class="weim-map-filters" data-target="<?php echo esc_attr( $id ); ?>" role="group" aria-label="<?php esc_attr_e( 'Filter map by program', 'wp-education-map' ); ?>"></div>
			<div
				id="<?php echo esc_attr( $id ); ?>"
				class="weim-map"
				style="height: <?php echo esc_attr( $atts['height'] ); ?>; width: <?php echo esc_attr( $atts['width'] ); ?>;"
				data-program="<?php echo esc_attr( $atts['program'] ); ?>"
				data-rest-url="<?php echo esc_url( rest_url( 'wp-education-map/v1/institutions' ) ); ?>"
				data-programs="<?php echo esc_attr( wp_json_encode( WEIM_DB::get_programs() ) ); ?>"
				data-label-events="<?php echo esc_attr( __( 'events', 'wp-education-map' ) ); ?>"
				data-label-all="<?php echo esc_attr( __( 'All programs', 'wp-education-map' ) ); ?>"
			></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
