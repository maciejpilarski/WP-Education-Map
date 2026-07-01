<?php
/**
 * Dashboard settings screens for managing institutions.
 *
 * @package WP_Education_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WEIM_PLUGIN_DIR . 'includes/class-weim-list-table.php';

class WEIM_Admin {

	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'handle_delete' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_submission' ) );
		add_action( 'admin_init', array( $this, 'handle_add_program' ) );
		add_action( 'admin_init', array( $this, 'handle_delete_program' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the top-level "Education Map" Dashboard menu and its submenus.
	 */
	public function register_menu() {
		$hook = add_menu_page(
			__( 'Education Map', 'wp-education-map' ),
			__( 'Education Map', 'wp-education-map' ),
			self::CAPABILITY,
			'weim-institutions',
			array( $this, 'render_list_page' ),
			'dashicons-location-alt',
			58
		);

		add_submenu_page(
			'weim-institutions',
			__( 'Institutions', 'wp-education-map' ),
			__( 'All Institutions', 'wp-education-map' ),
			self::CAPABILITY,
			'weim-institutions',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'weim-institutions',
			__( 'Add New Institution', 'wp-education-map' ),
			__( 'Add New', 'wp-education-map' ),
			self::CAPABILITY,
			'weim-add-new',
			array( $this, 'render_form_page' )
		);

		add_submenu_page(
			'weim-institutions',
			__( 'Programs', 'wp-education-map' ),
			__( 'Programs', 'wp-education-map' ),
			self::CAPABILITY,
			'weim-programs',
			array( $this, 'render_programs_page' )
		);

		add_submenu_page(
			'weim-institutions',
			__( 'Education Map Settings', 'wp-education-map' ),
			__( 'Settings', 'wp-education-map' ),
			self::CAPABILITY,
			'weim-settings',
			array( $this, 'render_settings_page' )
		);

		add_action( "load-{$hook}", array( $this, 'noop' ) );
	}

	public function noop() {}

	/**
	 * Enqueue admin-only CSS on plugin screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'weim-' ) ) {
			return;
		}

		wp_enqueue_style( 'weim-admin', WEIM_PLUGIN_URL . 'assets/css/admin.css', array(), WEIM_VERSION );

		if ( false !== strpos( $hook_suffix, 'weim-add-new' ) ) {
			wp_enqueue_style( 'weim-leaflet', WEIM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
			wp_enqueue_script( 'weim-leaflet', WEIM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
			wp_enqueue_script( 'weim-admin-map', WEIM_PLUGIN_URL . 'assets/js/admin-map.js', array( 'weim-leaflet' ), WEIM_VERSION, true );
		}
	}

	/**
	 * Render the institutions list ("All Institutions") screen.
	 */
	public function render_list_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-education-map' ) );
		}

		$list_table = new WEIM_List_Table();
		$list_table->prepare_items();

		$programs = WEIM_DB::get_programs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
		$current_program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : '';
		$add_new_url     = esc_url( admin_url( 'admin.php?page=weim-add-new' ) );
		?>
		<div class="wrap weim-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Education Map — Institutions', 'wp-education-map' ); ?></h1>
			<a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'wp-education-map' ); ?></a>
			<hr class="wp-header-end">

			<p><?php esc_html_e( 'Manage the WPCC, WPCredits, and Student Club institutions shown on the public map. Use the shortcode [wp_education_map] to display the map on any page or post.', 'wp-education-map' ); ?></p>

			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=weim-institutions' ) ); ?>" class="<?php echo '' === $current_program ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'wp-education-map' ); ?> (<?php echo (int) WEIM_DB::count_all(); ?>)
					</a>
				</li>
				<?php foreach ( $programs as $key => $label ) : ?>
					<li> |
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=weim-institutions&program=' . $key ) ); ?>" class="<?php echo $current_program === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="get">
				<input type="hidden" name="page" value="weim-institutions" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Add New" / edit form screen.
	 */
	public function render_form_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-education-map' ) );
		}

		$editing     = false;
		$institution = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup to pre-fill the edit form, no state change.
		$requested_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $requested_id ) {
			$institution = WEIM_DB::get( $requested_id );
			$editing     = (bool) $institution;
		}

		$programs = WEIM_DB::get_programs();

		$values = array(
			'id'          => $institution->id ?? 0,
			'name'        => $institution->name ?? '',
			'city'        => $institution->city ?? '',
			'country'     => $institution->country ?? '',
			'latitude'    => $institution->latitude ?? '',
			'longitude'   => $institution->longitude ?? '',
			'program'     => $institution->program ?? 'wpcc',
			'event_count' => $institution->event_count ?? 0,
			'website'     => $institution->website ?? '',
		);
		?>
		<div class="wrap weim-wrap">
			<h1><?php echo $editing ? esc_html__( 'Edit Institution', 'wp-education-map' ) : esc_html__( 'Add New Institution', 'wp-education-map' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=weim-add-new' ) ); ?>">
				<?php wp_nonce_field( 'weim_save_institution' ); ?>
				<input type="hidden" name="weim_action" value="save_institution" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $values['id'] ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="weim-name"><?php esc_html_e( 'Institution Name', 'wp-education-map' ); ?></label></th>
						<td><input required name="name" id="weim-name" type="text" class="regular-text" value="<?php echo esc_attr( $values['name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-city"><?php esc_html_e( 'City', 'wp-education-map' ); ?></label></th>
						<td><input required name="city" id="weim-city" type="text" class="regular-text" value="<?php echo esc_attr( $values['city'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-country"><?php esc_html_e( 'Country', 'wp-education-map' ); ?></label></th>
						<td><input name="country" id="weim-country" type="text" class="regular-text" value="<?php echo esc_attr( $values['country'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Location', 'wp-education-map' ); ?></th>
						<td>
							<div
								id="weim-location-picker"
								class="weim-location-picker"
								data-latitude="<?php echo esc_attr( $values['latitude'] ); ?>"
								data-longitude="<?php echo esc_attr( $values['longitude'] ); ?>"
							></div>
							<p class="description"><?php esc_html_e( 'Click on the map, or drag the marker, to set the coordinates below.', 'wp-education-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-latitude"><?php esc_html_e( 'Latitude', 'wp-education-map' ); ?></label></th>
						<td>
							<input required name="latitude" id="weim-latitude" type="text" inputmode="decimal" class="regular-text" value="<?php echo esc_attr( $values['latitude'] ); ?>" placeholder="e.g. 28.6139" />
							<p class="description"><?php esc_html_e( 'Decimal degrees, between -90 and 90.', 'wp-education-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-longitude"><?php esc_html_e( 'Longitude', 'wp-education-map' ); ?></label></th>
						<td>
							<input required name="longitude" id="weim-longitude" type="text" inputmode="decimal" class="regular-text" value="<?php echo esc_attr( $values['longitude'] ); ?>" placeholder="e.g. 77.2090" />
							<p class="description"><?php esc_html_e( 'Decimal degrees, between -180 and 180.', 'wp-education-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-program"><?php esc_html_e( 'Program', 'wp-education-map' ); ?></label></th>
						<td>
							<select name="program" id="weim-program">
								<?php foreach ( $programs as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['program'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-event-count"><?php esc_html_e( 'Number of Events', 'wp-education-map' ); ?></label></th>
						<td><input name="event_count" id="weim-event-count" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr( $values['event_count'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-website"><?php esc_html_e( 'Website (optional)', 'wp-education-map' ); ?></label></th>
						<td><input name="website" id="weim-website" type="url" class="regular-text" value="<?php echo esc_attr( $values['website'] ); ?>" placeholder="https://" /></td>
					</tr>
				</table>

				<?php submit_button( $editing ? __( 'Update Institution', 'wp-education-map' ) : __( 'Add Institution', 'wp-education-map' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Settings" screen, where the map's on-page size is configured.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-education-map' ) );
		}

		$settings = WEIM_Settings::get();
		?>
		<div class="wrap weim-wrap">
			<h1><?php esc_html_e( 'Education Map Settings', 'wp-education-map' ); ?></h1>
			<p><?php esc_html_e( 'Control how large the map appears wherever the [wp_education_map] shortcode is used. Values must be a CSS length, e.g. 520px, 100%, or 60vh.', 'wp-education-map' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=weim-settings' ) ); ?>">
				<?php wp_nonce_field( 'weim_save_settings' ); ?>
				<input type="hidden" name="weim_action" value="save_weim_settings" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="weim-map-width"><?php esc_html_e( 'Map Width', 'wp-education-map' ); ?></label></th>
						<td>
							<input name="width" id="weim-map-width" type="text" class="regular-text" value="<?php echo esc_attr( $settings['width'] ); ?>" placeholder="100%" />
							<p class="description"><?php esc_html_e( 'CSS length, e.g. 100% or 800px.', 'wp-education-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weim-map-height"><?php esc_html_e( 'Map Height', 'wp-education-map' ); ?></label></th>
						<td>
							<input name="height" id="weim-map-height" type="text" class="regular-text" value="<?php echo esc_attr( $settings['height'] ); ?>" placeholder="520px" />
							<p class="description"><?php esc_html_e( 'CSS length, e.g. 520px or 60vh.', 'wp-education-map' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'wp-education-map' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Programs" screen, where custom program types can be added.
	 */
	public function render_programs_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-education-map' ) );
		}

		$programs = WEIM_Programs::get_all();
		?>
		<div class="wrap weim-wrap">
			<h1><?php esc_html_e( 'Programs', 'wp-education-map' ); ?></h1>
			<p><?php esc_html_e( 'Programs (such as WPCC, WPCredits, or Student Club) can be assigned to institutions and used to filter the map.', 'wp-education-map' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Program', 'wp-education-map' ); ?></th>
						<th><?php esc_html_e( 'Institutions', 'wp-education-map' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-education-map' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $programs as $key => $label ) : ?>
						<?php $count = WEIM_DB::count_by_program( $key ); ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo (int) $count; ?></td>
							<td>
								<?php if ( $count > 0 ) : ?>
									<span class="description"><?php esc_html_e( 'In use — cannot delete', 'wp-education-map' ); ?></span>
								<?php else : ?>
									<?php
									$delete_url = wp_nonce_url(
										add_query_arg(
											array(
												'page'   => 'weim-programs',
												'action' => 'delete_program',
												'key'    => $key,
											),
											admin_url( 'admin.php' )
										),
										'weim_delete_program_' . $key
									);
									?>
									<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this program?', 'wp-education-map' ) ); ?>');"><?php esc_html_e( 'Delete', 'wp-education-map' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Add New Program', 'wp-education-map' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=weim-programs' ) ); ?>">
				<?php wp_nonce_field( 'weim_add_program' ); ?>
				<input type="hidden" name="weim_action" value="add_program" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="weim-program-label"><?php esc_html_e( 'Program Name', 'wp-education-map' ); ?></label></th>
						<td><input required name="label" id="weim-program-label" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Meetup Chapter', 'wp-education-map' ); ?>" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Add Program', 'wp-education-map' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the add/edit form submission.
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['weim_action'] ) || 'save_institution' !== $_POST['weim_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-education-map' ) );
		}

		check_admin_referer( 'weim_save_institution' );

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$errors = array();

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$program = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : 'wpcc';
		$website = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$events  = isset( $_POST['event_count'] ) ? absint( $_POST['event_count'] ) : 0;
		$lat_raw = isset( $_POST['latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['latitude'] ) ) : '';
		$lng_raw = isset( $_POST['longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['longitude'] ) ) : '';

		if ( '' === $name ) {
			$errors[] = __( 'Institution name is required.', 'wp-education-map' );
		}

		if ( '' === $city ) {
			$errors[] = __( 'City is required.', 'wp-education-map' );
		}

		if ( ! array_key_exists( $program, WEIM_DB::get_programs() ) ) {
			$errors[] = __( 'Please choose a valid program.', 'wp-education-map' );
		}

		if ( ! is_numeric( $lat_raw ) || (float) $lat_raw < -90 || (float) $lat_raw > 90 ) {
			$errors[] = __( 'Latitude must be a number between -90 and 90.', 'wp-education-map' );
		}

		if ( ! is_numeric( $lng_raw ) || (float) $lng_raw < -180 || (float) $lng_raw > 180 ) {
			$errors[] = __( 'Longitude must be a number between -180 and 180.', 'wp-education-map' );
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'weim_admin_errors', $errors, 60 );
			$redirect_args = array( 'page' => 'weim-add-new' );
			if ( $id ) {
				$redirect_args['id'] = $id;
			}
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$data = array(
			'name'        => $name,
			'city'        => $city,
			'country'     => $country,
			'latitude'    => (float) $lat_raw,
			'longitude'   => (float) $lng_raw,
			'program'     => $program,
			'event_count' => $events,
			'website'     => $website,
		);

		if ( $id ) {
			$result = WEIM_DB::update( $id, $data );
		} else {
			$result = WEIM_DB::insert( $data );
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'weim_admin_errors', array( $result->get_error_message() ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=weim-add-new' ) );
			exit;
		}

		set_transient( 'weim_admin_success', $id ? __( 'Institution updated.', 'wp-education-map' ) : __( 'Institution added.', 'wp-education-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=weim-institutions' ) );
		exit;
	}

	/**
	 * Handle deletion requests from the list table row actions.
	 */
	public function handle_delete() {
		if ( ! isset( $_GET['page'], $_GET['action'], $_GET['id'] ) || 'weim-institutions' !== $_GET['page'] || 'delete' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-education-map' ) );
		}

		$id = absint( $_GET['id'] );
		check_admin_referer( 'weim_delete_institution_' . $id );

		WEIM_DB::delete( $id );

		set_transient( 'weim_admin_success', __( 'Institution deleted.', 'wp-education-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=weim-institutions' ) );
		exit;
	}

	/**
	 * Handle the settings form submission (map width/height).
	 */
	public function handle_settings_submission() {
		if ( ! isset( $_POST['weim_action'] ) || 'save_weim_settings' !== $_POST['weim_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-education-map' ) );
		}

		check_admin_referer( 'weim_save_settings' );

		$defaults = WEIM_Settings::defaults();
		$width    = isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : '';
		$height   = isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '';

		$errors = array();

		if ( ! WEIM_Settings::is_valid_dimension( $width ) ) {
			$errors[] = __( 'Map width must be a valid CSS length, e.g. 100% or 800px.', 'wp-education-map' );
			$width    = $defaults['width'];
		}

		if ( ! WEIM_Settings::is_valid_dimension( $height ) ) {
			$errors[] = __( 'Map height must be a valid CSS length, e.g. 520px or 60vh.', 'wp-education-map' );
			$height   = $defaults['height'];
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'weim_admin_errors', $errors, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=weim-settings' ) );
			exit;
		}

		update_option(
			WEIM_Settings::OPTION_NAME,
			array(
				'width'  => $width,
				'height' => $height,
			)
		);

		set_transient( 'weim_admin_success', __( 'Settings saved.', 'wp-education-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=weim-settings' ) );
		exit;
	}

	/**
	 * Handle submission of the "Add New Program" form.
	 */
	public function handle_add_program() {
		if ( ! isset( $_POST['weim_action'] ) || 'add_program' !== $_POST['weim_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-education-map' ) );
		}

		check_admin_referer( 'weim_add_program' );

		$label  = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$result = WEIM_Programs::add( $label );

		if ( is_wp_error( $result ) ) {
			set_transient( 'weim_admin_errors', array( $result->get_error_message() ), 60 );
		} else {
			set_transient( 'weim_admin_success', __( 'Program added.', 'wp-education-map' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=weim-programs' ) );
		exit;
	}

	/**
	 * Handle deletion of a program from the Programs screen.
	 */
	public function handle_delete_program() {
		if ( ! isset( $_GET['page'], $_GET['action'], $_GET['key'] ) || 'weim-programs' !== $_GET['page'] || 'delete_program' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-education-map' ) );
		}

		$key = sanitize_key( wp_unslash( $_GET['key'] ) );
		check_admin_referer( 'weim_delete_program_' . $key );

		if ( WEIM_DB::count_by_program( $key ) > 0 ) {
			set_transient( 'weim_admin_errors', array( __( 'This program is still assigned to one or more institutions and cannot be deleted.', 'wp-education-map' ) ), 60 );
		} else {
			WEIM_Programs::delete( $key );
			set_transient( 'weim_admin_success', __( 'Program deleted.', 'wp-education-map' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=weim-programs' ) );
		exit;
	}

	/**
	 * Render success/error notices stored in transients after a redirect.
	 */
	public function render_notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'weim-' ) ) {
			return;
		}

		$errors = get_transient( 'weim_admin_errors' );
		if ( $errors ) {
			delete_transient( 'weim_admin_errors' );
			echo '<div class="notice notice-error"><ul>';
			foreach ( (array) $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}

		$success = get_transient( 'weim_admin_success' );
		if ( $success ) {
			delete_transient( 'weim_admin_success' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success ) . '</p></div>';
		}
	}
}
