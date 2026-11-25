<?php
/**
 * Dukkan > Checkout Fields tab: edit the checkout form fields.
 *
 * Saves to the same options the checkout fields module reads
 * (wc_fields_billing / wc_fields_shipping / wc_fields_additional and
 * thwcfd_advanced_settings).
 *
 * @since 1.0.70
 */

defined( 'ABSPATH' ) || exit;

class Dukkan_Checkout_Fields_Admin {

	const TAB    = 'checkout_fields';
	const ACTION = 'dukkan_checkout_fields_save';

	/** Field types a custom field can use (all rendered by WooCommerce itself). */
	const TYPES = array(
		'text'     => 'Text',
		'textarea' => 'Textarea',
		'email'    => 'Email',
		'tel'      => 'Phone',
		'number'   => 'Number',
		'url'      => 'URL',
		'password' => 'Password',
		'date'     => 'Date',
		'time'     => 'Time',
		'select'   => 'Select',
		'radio'    => 'Radio',
		'checkbox' => 'Checkbox',
	);

	const VALIDATORS = array( 'email', 'phone', 'number', 'url', 'postcode', 'state' );

	public function __construct() {
		// Late priority: Checkout Fields is the last tab.
		add_filter( 'dukkan_settings_tabs', array( $this, 'add_tab' ), 999 );
		add_action( 'dukkan_settings_tab_content_' . self::TAB, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_tab( $tabs ) {
		$tabs[ self::TAB ] = array(
			'title' => __( 'Checkout Fields', 'dukkan-plugin' ),
			'icon'  => 'dashicons-feedback',
		);
		return $tabs;
	}

	public function enqueue( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_dukkan-settings' !== $hook || ! isset( $_GET['tab'] ) || self::TAB !== $_GET['tab'] ) {
			return;
		}
		$base = plugin_dir_url( __FILE__ );
		wp_enqueue_style( 'dukkan-checkout-fields-admin', $base . 'css/dp-checkout-fields.css', array(), DUKKAN_PLUGIN_VERSION );
		wp_enqueue_script( 'dukkan-checkout-fields-admin', $base . 'js/dp-checkout-fields.js', array( 'jquery', 'jquery-ui-sortable' ), DUKKAN_PLUGIN_VERSION, true );
		wp_localize_script(
			'dukkan-checkout-fields-admin',
			'dukkanCheckoutFields',
			array(
				'confirmDelete' => __( 'Delete this field?', 'dukkan-plugin' ),
				'confirmReset'  => __( 'Reset this section to its default fields? Your changes in this section will be lost.', 'dukkan-plugin' ),
				'badName'       => __( 'Field names may only use lowercase letters, numbers and underscores.', 'dukkan-plugin' ),
				'dupName'       => __( 'A field with this name already exists.', 'dukkan-plugin' ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	/** Fields of a section as the editor shows them (saved, else defaults). */
	public static function fields( string $section ): array {
		$saved = get_option( 'wc_fields_' . $section );
		if ( is_array( $saved ) && array() !== array_filter( $saved ) ) {
			return array_filter( $saved );
		}
		return self::defaults( $section );
	}

	public static function defaults( string $section ): array {
		if ( 'billing' === $section ) {
			return Dukkan_Checkout_Fields::default_billing();
		}
		if ( 'shipping' === $section ) {
			$fields = WC()->countries->get_address_fields( WC()->countries->get_base_country(), 'shipping_' );
		} else {
			$fields = array(
				'order_comments' => array(
					'type'        => 'textarea',
					'class'       => array( 'notes' ),
					'label'       => 'Order notes',
					'placeholder' => 'Notes about your order, e.g. special notes for delivery.',
				),
			);
		}
		foreach ( $fields as $k => $f ) {
			$fields[ $k ] = array_merge(
				array(
					'type'          => 'text',
					'label'         => '',
					'placeholder'   => '',
					'required'      => 0,
					'custom'        => 0,
					'enabled'       => 1,
					'show_in_email' => 1,
					'show_in_order' => 1,
				),
				$f
			);
			$fields[ $k ]['required'] = empty( $f['required'] ) ? 0 : 1;
		}
		return $fields;
	}

	private static function width( $class ): string {
		foreach ( array( 'first', 'last', 'wide' ) as $w ) {
			if ( in_array( 'form-row-' . $w, (array) $class, true ) ) {
				return $w;
			}
		}
		return 'wide';
	}

	private static function options_text( $options ): string {
		if ( is_string( $options ) ) {
			return implode( "\n", array_map( 'trim', explode( '|', $options ) ) );
		}
		$lines = array();
		foreach ( (array) $options as $k => $v ) {
			$lines[] = ( (string) $k === (string) $v ) ? $v : $k . ' | ' . $v;
		}
		return implode( "\n", $lines );
	}

	private static function parse_options( string $text ): array {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, '|' ) ) {
				list( $k, $v ) = array_map( 'trim', explode( '|', $line, 2 ) );
			} else {
				$k = $line;
				$v = $line;
			}
			$out[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	public function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dukkan-plugin' ) );
		}
		check_admin_referer( self::ACTION, 'dukkan_checkout_fields_nonce' );

		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		$back    = array(
			'page'    => 'dukkan-settings',
			'tab'     => self::TAB,
			'section' => $section,
		);

		if ( 'advanced' === $section ) {
			$keys = array( 'enable_label_override', 'enable_placeholder_override', 'enable_class_override', 'enable_priority_override', 'enable_required_override' );
			$adv  = array();
			foreach ( $keys as $k ) {
				if ( ! empty( $_POST['advanced'][ $k ] ) ) {
					$adv[ $k ] = '1';
				}
			}
			update_option( 'thwcfd_advanced_settings', $adv );
			wp_safe_redirect( add_query_arg( $back + array( 'saved' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! in_array( $section, Dukkan_Checkout_Fields::SECTIONS, true ) ) {
			wp_die( esc_html__( 'Unknown section.', 'dukkan-plugin' ) );
		}

		if ( ! empty( $_POST['reset'] ) ) {
			if ( 'billing' === $section ) {
				update_option( 'wc_fields_billing', Dukkan_Checkout_Fields::default_billing(), false );
			} else {
				delete_option( 'wc_fields_' . $section );
			}
			wp_safe_redirect( add_query_arg( $back + array( 'reset' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$current = self::fields( $section );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$posted = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();
		$new    = array();
		$pos    = 0;

		foreach ( $posted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_key( $row['name'] ) : '';
			if ( '' === $name || isset( $new[ $name ] ) ) {
				continue;
			}
			$is_new = ! isset( $current[ $name ] );
			if ( $is_new && empty( $row['custom'] ) ) {
				continue; // only custom fields can be added
			}
			$f = $is_new ? array( 'custom' => 1 ) : $current[ $name ];

			$custom = ! empty( $f['custom'] );
			if ( $custom ) {
				$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'text';
				$f['type'] = isset( self::TYPES[ $type ] ) ? $type : 'text';
				$f['name'] = $name;
				if ( in_array( $f['type'], array( 'select', 'radio' ), true ) ) {
					$f['options'] = self::parse_options( (string) ( $row['options'] ?? '' ) );
				} else {
					unset( $f['options'] );
				}
			}

			$f['label']       = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$f['placeholder'] = sanitize_text_field( (string) ( $row['placeholder'] ?? '' ) );
			$f['default']     = sanitize_text_field( (string) ( $row['default'] ?? '' ) );
			$f['required']    = empty( $row['required'] ) ? 0 : 1;
			$f['enabled']     = empty( $row['enabled'] ) ? 0 : 1;
			$f['show_in_email'] = empty( $row['show_in_email'] ) ? 0 : 1;
			$f['show_in_order'] = empty( $row['show_in_order'] ) ? 0 : 1;

			$validate      = isset( $row['validate'] ) && is_array( $row['validate'] ) ? array_values( array_intersect( array_map( 'sanitize_key', $row['validate'] ), self::VALIDATORS ) ) : array();
			$f['validate'] = $validate ? $validate : '';

			$width = isset( $row['width'] ) && in_array( $row['width'], array( 'first', 'last', 'wide' ), true ) ? $row['width'] : 'wide';
			$class = array_values( array_diff( (array) ( $f['class'] ?? array() ), array( 'form-row-first', 'form-row-last', 'form-row-wide' ) ) );
			array_unshift( $class, 'form-row-' . $width );
			$f['class'] = $class;

			$pos          += 10;
			$f['priority'] = $pos;
			$new[ $name ]  = $f;
		}

		// Default fields can be switched off but never removed.
		foreach ( $current as $name => $f ) {
			if ( ! isset( $new[ $name ] ) && empty( $f['custom'] ) ) {
				$f['enabled']  = 0;
				$pos          += 10;
				$f['priority'] = $pos;
				$new[ $name ]  = $f;
			}
		}

		update_option( 'wc_fields_' . $section, $new, false );
		wp_safe_redirect( add_query_arg( $back + array( 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                              */
	/* ------------------------------------------------------------------ */

	public function render(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'WooCommerce is not active.', 'dukkan-plugin' ) . '</p></div>';
			return;
		}
		$sections = array(
			'billing'    => __( 'Billing', 'dukkan-plugin' ),
			'shipping'   => __( 'Shipping', 'dukkan-plugin' ),
			'additional' => __( 'Additional', 'dukkan-plugin' ),
			'advanced'   => __( 'Advanced', 'dukkan-plugin' ),
		);
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'billing';
		$section = isset( $sections[ $section ] ) ? $section : 'billing';
		$saved   = isset( $_GET['saved'] );
		$reset   = isset( $_GET['reset'] );
		// phpcs:enable
		$idle = Dukkan_Checkout_Fields::idle_reason();
		?>
		<div class="dukkan-cf">
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Checkout fields saved.', 'dukkan-plugin' ); ?></p></div>
			<?php elseif ( $reset ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Section reset to its default fields.', 'dukkan-plugin' ); ?></p></div>
			<?php endif; ?>
			<?php if ( '' !== $idle ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					/* translators: %s: reason */
					echo esc_html( sprintf( __( 'Dukkan checkout fields are not running on the store: %s. Changes here are saved but take effect once it runs.', 'dukkan-plugin' ), $idle ) );
					?>
				</p></div>
			<?php endif; ?>

			<ul class="subsubsub dukkan-cf__sections">
				<?php
				$i = 0;
				foreach ( $sections as $id => $title ) :
					$url = admin_url( 'admin.php?page=dukkan-settings&tab=' . self::TAB . '&section=' . $id );
					?>
					<li><a href="<?php echo esc_url( $url ); ?>" class="<?php echo $id === $section ? 'current' : ''; ?>"><?php echo esc_html( $title ); ?></a><?php echo ++$i < count( $sections ) ? ' |' : ''; ?></li>
				<?php endforeach; ?>
			</ul>
			<br class="clear">

			<?php
			if ( 'advanced' === $section ) {
				$this->render_advanced();
			} else {
				$this->render_section( $section );
			}
			?>
		</div>
		<?php
	}

	private function form_open( string $section ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dukkan-cf__form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
			<?php wp_nonce_field( self::ACTION, 'dukkan_checkout_fields_nonce' ); ?>
		<?php
	}

	private function render_advanced(): void {
		$adv  = get_option( 'thwcfd_advanced_settings' );
		$adv  = is_array( $adv ) ? $adv : array();
		$rows = array(
			'enable_label_override'       => array( __( 'Use my labels for every country', 'dukkan-plugin' ), true ),
			'enable_placeholder_override' => array( __( 'Use my placeholders for every country', 'dukkan-plugin' ), true ),
			'enable_class_override'       => array( __( 'Use my field widths for every country', 'dukkan-plugin' ), false ),
			'enable_priority_override'    => array( __( 'Use my field order for every country', 'dukkan-plugin' ), true ),
			'enable_required_override'    => array( __( 'Use my required settings for every country', 'dukkan-plugin' ), false ),
		);
		$this->form_open( 'advanced' );
		?>
			<div class="dukkan-loyalty-card">
				<div class="dukkan-loyalty-card__head">
					<h2><?php esc_html_e( 'Country address formats', 'dukkan-plugin' ); ?></h2>
					<p><?php esc_html_e( 'WooCommerce changes address labels, order and required fields per country. Tick a setting to keep your own values for every country instead.', 'dukkan-plugin' ); ?></p>
				</div>
				<table class="form-table" role="presentation"><tbody>
					<?php foreach ( $rows as $key => $row ) : ?>
						<?php $on = array_key_exists( $key, $adv ) ? ! empty( $adv[ $key ] ) : ( empty( $adv ) && $row[1] ); ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row[0] ); ?></th>
							<td><label><input type="checkbox" name="advanced[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $on ); ?>> <?php esc_html_e( 'Enabled', 'dukkan-plugin' ); ?></label></td>
						</tr>
					<?php endforeach; ?>
				</tbody></table>
			</div>
			<?php submit_button( __( 'Save changes', 'dukkan-plugin' ) ); ?>
		</form>
		<?php
	}

	private function render_section( string $section ): void {
		$fields = self::fields( $section );
		uasort(
			$fields,
			function ( $a, $b ) {
				return (int) ( $a['priority'] ?? 0 ) <=> (int) ( $b['priority'] ?? 0 );
			}
		);
		$prefix = 'additional' === $section ? '' : $section . '_';
		$this->form_open( $section );
		?>
			<div class="dukkan-loyalty-card">
				<div class="dukkan-loyalty-card__head dukkan-cf__head">
					<div>
						<h2><?php echo esc_html( ucfirst( $section ) . ' ' . __( 'fields', 'dukkan-plugin' ) ); ?></h2>
						<p><?php esc_html_e( 'Drag rows to change the order. Click a field name to edit it. Default fields can be turned off but not deleted.', 'dukkan-plugin' ); ?></p>
					</div>
					<button type="button" class="button button-secondary" data-cf-add data-prefix="<?php echo esc_attr( $prefix ); ?>"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add field', 'dukkan-plugin' ); ?></button>
				</div>

				<table class="widefat dukkan-cf__table" data-cf-table>
					<thead>
						<tr>
							<th class="dukkan-cf__drag"></th>
							<th><?php esc_html_e( 'Name', 'dukkan-plugin' ); ?></th>
							<th><?php esc_html_e( 'Type', 'dukkan-plugin' ); ?></th>
							<th><?php esc_html_e( 'Label', 'dukkan-plugin' ); ?></th>
							<th><?php esc_html_e( 'Placeholder', 'dukkan-plugin' ); ?></th>
							<th><?php esc_html_e( 'Width', 'dukkan-plugin' ); ?></th>
							<th class="dukkan-cf__flag"><?php esc_html_e( 'Required', 'dukkan-plugin' ); ?></th>
							<th class="dukkan-cf__flag"><?php esc_html_e( 'Enabled', 'dukkan-plugin' ); ?></th>
							<th class="dukkan-cf__actions"></th>
						</tr>
					</thead>
					<?php
					$i = 0;
					foreach ( $fields as $name => $f ) {
						$this->row( (string) $i++, (string) $name, (array) $f );
					}
					?>
				</table>
				<script type="text/html" id="tmpl-dukkan-cf-row"><?php $this->row( '__i__', '', array( 'custom' => 1, 'enabled' => 1, 'type' => 'text', 'show_in_email' => 1, 'show_in_order' => 1 ), true ); ?></script>
			</div>
			<p class="submit dukkan-cf__submit">
				<?php submit_button( __( 'Save changes', 'dukkan-plugin' ), 'primary', 'submit', false ); ?>
				<button type="submit" name="reset" value="1" class="button button-link-delete" data-cf-reset><?php esc_html_e( 'Reset to default fields', 'dukkan-plugin' ); ?></button>
			</p>
		</form>
		<?php
	}

	private function row( string $i, string $name, array $f, bool $is_template = false ): void {
		$custom   = ! empty( $f['custom'] );
		$type     = $f['type'] ?? 'text';
		$enabled  = ! ( isset( $f['enabled'] ) && ! $f['enabled'] );
		$n        = 'fields[' . $i . ']';
		$validate = isset( $f['validate'] ) && is_array( $f['validate'] ) ? $f['validate'] : array();
		$width    = self::width( $f['class'] ?? array() );
		?>
		<tbody class="dukkan-cf__field" data-cf-field>
		<tr class="dukkan-cf__row<?php echo $enabled ? '' : ' is-disabled'; ?><?php echo $is_template ? ' is-new is-open' : ''; ?>" data-cf-row>
			<td class="dukkan-cf__drag"><span class="dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'dukkan-plugin' ); ?>"></span></td>
			<td>
				<?php if ( $is_template ) : ?>
					<input type="text" class="regular-text dukkan-cf__name" name="<?php echo esc_attr( $n ); ?>[name]" value="" placeholder="<?php esc_attr_e( 'field_name', 'dukkan-plugin' ); ?>" data-cf-name required>
					<input type="hidden" name="<?php echo esc_attr( $n ); ?>[custom]" value="1">
				<?php else : ?>
					<a href="#" class="dukkan-cf__toggle" data-cf-toggle><code><?php echo esc_html( $name ); ?></code></a>
					<input type="hidden" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $name ); ?>" data-cf-name>
					<?php if ( $custom ) : ?><span class="dukkan-cf__badge"><?php esc_html_e( 'Custom', 'dukkan-plugin' ); ?></span><?php endif; ?>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $custom ) : ?>
					<select name="<?php echo esc_attr( $n ); ?>[type]" data-cf-type>
						<?php foreach ( self::TYPES as $t => $label ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<?php echo esc_html( $type ); ?>
				<?php endif; ?>
			</td>
			<td><input type="text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $f['label'] ?? '' ); ?>"></td>
			<td><input type="text" name="<?php echo esc_attr( $n ); ?>[placeholder]" value="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>"></td>
			<td>
				<select name="<?php echo esc_attr( $n ); ?>[width]">
					<option value="wide" <?php selected( $width, 'wide' ); ?>><?php esc_html_e( 'Full', 'dukkan-plugin' ); ?></option>
					<option value="first" <?php selected( $width, 'first' ); ?>><?php esc_html_e( 'Half (start)', 'dukkan-plugin' ); ?></option>
					<option value="last" <?php selected( $width, 'last' ); ?>><?php esc_html_e( 'Half (end)', 'dukkan-plugin' ); ?></option>
				</select>
			</td>
			<td class="dukkan-cf__flag"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[required]" value="1" <?php checked( ! empty( $f['required'] ) ); ?>></td>
			<td class="dukkan-cf__flag"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> data-cf-enabled></td>
			<td class="dukkan-cf__actions">
				<button type="button" class="button-link" data-cf-toggle title="<?php esc_attr_e( 'More settings', 'dukkan-plugin' ); ?>"><span class="dashicons dashicons-admin-generic"></span></button>
				<?php if ( $custom ) : ?>
					<button type="button" class="button-link button-link-delete" data-cf-delete title="<?php esc_attr_e( 'Delete', 'dukkan-plugin' ); ?>"><span class="dashicons dashicons-trash"></span></button>
				<?php endif; ?>
			</td>
		</tr>
		<tr class="dukkan-cf__more<?php echo $is_template ? ' is-open' : ''; ?>" data-cf-more>
			<td></td>
			<td colspan="8">
				<div class="dukkan-cf__more-grid">
					<label><?php esc_html_e( 'Default value', 'dukkan-plugin' ); ?>
						<input type="text" name="<?php echo esc_attr( $n ); ?>[default]" value="<?php echo esc_attr( $f['default'] ?? '' ); ?>">
					</label>
					<?php if ( $custom ) : ?>
						<label class="dukkan-cf__options<?php echo in_array( $type, array( 'select', 'radio' ), true ) ? '' : ' is-hidden'; ?>" data-cf-options><?php esc_html_e( 'Options (one per line, "value | Label" or just "Label")', 'dukkan-plugin' ); ?>
							<textarea name="<?php echo esc_attr( $n ); ?>[options]" rows="4"><?php echo esc_textarea( self::options_text( $f['options'] ?? array() ) ); ?></textarea>
						</label>
					<?php endif; ?>
					<fieldset>
						<legend><?php esc_html_e( 'Validation', 'dukkan-plugin' ); ?></legend>
						<?php foreach ( self::VALIDATORS as $v ) : ?>
							<label class="dukkan-cf__inline"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[validate][]" value="<?php echo esc_attr( $v ); ?>" <?php checked( in_array( $v, $validate, true ) ); ?>> <?php echo esc_html( ucfirst( $v ) ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<fieldset>
						<legend><?php esc_html_e( 'Show value', 'dukkan-plugin' ); ?></legend>
						<label class="dukkan-cf__inline"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_in_email]" value="1" <?php checked( ! empty( $f['show_in_email'] ) ); ?>> <?php esc_html_e( 'In order emails', 'dukkan-plugin' ); ?></label>
						<label class="dukkan-cf__inline"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_in_order]" value="1" <?php checked( ! empty( $f['show_in_order'] ) ); ?>> <?php esc_html_e( 'On the order page', 'dukkan-plugin' ); ?></label>
					</fieldset>
				</div>
			</td>
		</tr>
		</tbody>
		<?php
	}
}
