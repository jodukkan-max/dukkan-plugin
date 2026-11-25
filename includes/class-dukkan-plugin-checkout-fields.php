<?php
/**
 * Checkout fields: shapes the classic WooCommerce checkout and address forms.
 *
 * Replaces the "Checkout Field Editor for WooCommerce" plugin (ThemeHigh) and
 * reads the same saved settings, so a store keeps its form when it switches:
 *   - wc_fields_billing / wc_fields_shipping / wc_fields_additional
 *   - thwcfd_advanced_settings (locale overrides)
 * Field wrappers keep the thwcfd-* CSS classes so themes and TranslatePress
 * strings see the same HTML.
 *
 * Idle while the old plugin is active, when WooCommerce is missing, or when
 * DUKKAN_CHECKOUT_FIELDS is defined as false in wp-config.php.
 *
 * WP-CLI: wp dukkan-checkout status | list [<section>] | set <field> --label=… | reset
 *
 * @since 1.0.70
 */

defined( 'ABSPATH' ) || exit;

class Dukkan_Checkout_Fields {

	const OLD_PLUGIN = 'woo-checkout-field-editor-pro/checkout-form-designer.php';
	const SECTIONS   = array( 'billing', 'shipping', 'additional' );
	const PRIORITY   = 1000;

	/** @var self|null */
	private static $instance = null;

	/** @var array|null */
	private $advanced = null;

	public static function boot(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_start' ), 20 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'dukkan-checkout', 'Dukkan_Checkout_Fields_CLI' );
		}
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	/** Why the module is idle, or '' when it runs. */
	public static function idle_reason(): string {
		if ( defined( 'DUKKAN_CHECKOUT_FIELDS' ) && ! DUKKAN_CHECKOUT_FIELDS ) {
			return 'switched off by DUKKAN_CHECKOUT_FIELDS';
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 'WooCommerce is not active';
		}
		if ( in_array( self::OLD_PLUGIN, (array) get_option( 'active_plugins', array() ), true ) ) {
			return 'Checkout Field Editor for WooCommerce is still active';
		}
		return '';
	}

	public static function maybe_start(): void {
		if ( '' !== self::idle_reason() ) {
			return;
		}
		self::$instance = new self();
		add_action( 'init', array( self::$instance, 'register' ), 20 );
		add_action( 'wp_enqueue_scripts', array( self::$instance, 'enqueue' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * The form Dukkan stores ship with; used for billing when nothing is saved.
	 */
	public static function default_billing(): array {
		$f = function ( $label, $type, $class, $priority, $required, $enabled, $extra = array() ) {
			return array_merge(
				array(
					'type'          => $type,
					'label'         => $label,
					'default'       => '',
					'placeholder'   => '',
					'class'         => $class,
					'validate'      => '',
					'required'      => $required,
					'enabled'       => $enabled,
					'priority'      => $priority,
					'custom'        => 0,
					'show_in_email' => 0,
					'show_in_order' => 0,
				),
				$extra
			);
		};
		return array(
			'billing_first_name' => $f( 'First name', 'text', array( 'form-row-first' ), 10, 1, 1, array( 'show_in_email' => 1, 'show_in_order' => 1 ) ),
			'billing_last_name'  => $f( 'Last name', 'text', array( 'form-row-last' ), 20, 1, 1, array( 'show_in_email' => 1, 'show_in_order' => 1 ) ),
			'billing_email'      => $f( 'Email address', 'email', array( 'form-row-first' ), 30, 1, 1, array( 'validate' => array( 'email' ) ) ),
			'billing_phone'      => $f( 'Phone', 'tel', array( 'form-row-last' ), 40, 1, 1, array( 'validate' => array( 'phone' ) ) ),
			'billing_country'    => $f( 'Country / Region', 'country', array( 'form-row-first', 'address-field', 'update_totals_on_change' ), 50, 1, 1 ),
			'billing_state'      => $f( 'City', 'state', array( 'form-row-last', 'address-field' ), 60, 1, 1, array( 'validate' => array( 'state' ) ) ),
			'billing_address_1'  => $f( 'Full address', 'text', array( 'form-row-wide', 'address-field' ), 70, 1, 1, array( 'placeholder' => 'Area Name, Street, etc..' ) ),
			'billing_company'    => $f( 'Company name', 'text', array( 'form-row-wide' ), 80, 0, 0 ),
			'billing_address_2'  => $f( 'Apartment, suite, unit, etc.', 'text', array( 'form-row-wide', 'address-field' ), 90, 0, 0, array( 'placeholder' => 'Apartment, suite, unit, etc. (optional)' ) ),
			'billing_city'       => $f( 'Town / City', 'text', array( 'form-row-wide', 'address-field' ), 100, 0, 0 ),
			'billing_postcode'   => $f( 'Postcode / ZIP', 'text', array( 'form-row-wide', 'address-field' ), 110, 0, 0, array( 'default' => '00000', 'validate' => array( 'postcode' ) ) ),
		);
	}

	/** Saved fields of a section; billing falls back to the Dukkan form. */
	public static function saved( string $section ) {
		$fields = get_option( 'wc_fields_' . $section );
		if ( is_array( $fields ) && array() !== array_filter( $fields ) ) {
			return array_filter( $fields );
		}
		return 'billing' === $section ? self::default_billing() : false;
	}

	private function advanced( string $key, bool $default ): bool {
		if ( null === $this->advanced ) {
			$a              = get_option( 'thwcfd_advanced_settings' );
			$this->advanced = is_array( $a ) ? $a : array();
		}
		$v = array_key_exists( $key, $this->advanced ) ? $this->advanced[ $key ] : $default;
		return ( 'undefined' === $v ) ? $default : (bool) $v;
	}

	private function override_label(): bool {
		return (bool) apply_filters( 'thwcfd_address_field_override_label', $this->advanced( 'enable_label_override', true ) );
	}

	private function override_placeholder(): bool {
		return (bool) apply_filters( 'thwcfd_address_field_override_placeholder', $this->advanced( 'enable_placeholder_override', true ) );
	}

	private function override_class(): bool {
		return (bool) apply_filters( 'thwcfd_address_field_override_class', $this->advanced( 'enable_class_override', false ) );
	}

	private function override_priority(): bool {
		return (bool) apply_filters( 'thwcfd_address_field_override_priority', $this->advanced( 'enable_priority_override', true ) );
	}

	public function override_required(): bool {
		return (bool) apply_filters( 'thwcfd_address_field_override_required', $this->advanced( 'enable_required_override', false ) );
	}

	private static function enabled( $field ): bool {
		return is_array( $field ) && ! ( isset( $field['enabled'] ) && false == $field['enabled'] ); // phpcs:ignore WordPress.PHP.StrictComparisons
	}

	private static function custom( $field ): bool {
		if ( ! self::enabled( $field ) || empty( $field['custom'] ) ) {
			return false;
		}
		// WooCommerce stores shipping_phone itself.
		return ! ( isset( $field['name'] ) && 'shipping_phone' === $field['name'] );
	}

	private static function options( $options ): array {
		if ( is_string( $options ) ) {
			$options = array_map( 'trim', explode( '|', $options ) );
		}
		return is_array( $options ) ? $options : array();
	}

	/* ------------------------------------------------------------------ */
	/* Hooks                                                               */
	/* ------------------------------------------------------------------ */

	public function register(): void {
		add_filter( 'woocommerce_enable_order_notes_field', array( $this, 'order_notes_enabled' ), self::PRIORITY );

		add_filter( 'woocommerce_get_country_locale_default', array( $this, 'strip_locale' ) );
		add_filter( 'woocommerce_get_country_locale_base', array( $this, 'strip_locale' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'country_locale' ) );

		add_filter( 'woocommerce_billing_fields', array( $this, 'billing_fields' ), self::PRIORITY, 2 );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'shipping_fields' ), self::PRIORITY, 2 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ), self::PRIORITY );

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom' ), 10, 2 );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_fields' ), self::PRIORITY, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_details' ), 20, 1 );

		if ( version_compare( WC()->version, '9.8.0', '>=' ) ) {
			add_filter( 'woocommerce_get_country_locale', array( $this, 'address_required_locales' ), 9 );
			add_filter( 'woocommerce_get_country_locale_default', array( $this, 'address_required_default' ), 9 );
		}
	}

	public function enqueue(): void {
		if ( ! ( is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) ) {
			return;
		}
		wp_register_style( 'dukkan-checkout-fields', false, array(), DUKKAN_PLUGIN_VERSION );
		wp_enqueue_style( 'dukkan-checkout-fields' );
		wp_add_inline_style( 'dukkan-checkout-fields', '.thwcfd-field-radio label.radio{display:inline-block;margin-right:15px}.thwcfd-optional .required{display:none}' );

		if ( ! $this->override_required() ) {
			return;
		}
		$after_address = 'yes' === get_option( 'woocommerce_shipping_cost_requires_address', 'no' );
		wp_register_script( 'dukkan-checkout-fields', false, array( 'jquery' ), DUKKAN_PLUGIN_VERSION, true );
		wp_enqueue_script( 'dukkan-checkout-fields' );
		wp_add_inline_script( 'dukkan-checkout-fields', 'var dukkanCheckoutAfterAddress=' . ( $after_address ? 'true' : 'false' ) . ';' . self::script() );
	}

	/**
	 * After a country change WooCommerce re-applies its own required markers;
	 * put back the ones the store chose (thwcfd-required / thwcfd-optional).
	 */
	private static function script(): string {
		return <<<'JS'
jQuery(function($){
	if(typeof wc_address_i18n_params==='undefined'){return;}
	function clean(f){var l=f.find('label').html();if(l){f.find('label').html(l.replace(/(?:^(?:&nbsp;)+)|(?:(?:&nbsp;)+$)/g,'').trim());}}
	function mark(f,req){
		if(req){
			f.find('label .optional').remove();f.addClass('validate-required');clean(f);
			if(!f.find('label .required[aria-hidden="true"]').length){f.find('label').append('&nbsp;<span class="required" aria-hidden="true">*</span>');}
		}else{
			f.find('label .required').remove();f.removeClass('validate-required woocommerce-invalid woocommerce-invalid-required-field');clean(f);
			if(!f.find('label .optional').length){f.find('label').append('&nbsp;<span class="optional">('+wc_address_i18n_params.i18n_optional_text+')</span>');}
		}
	}
	function apply(country){
		var form=$('.woocommerce-checkout'),ids=JSON.parse(wc_address_i18n_params.locale_fields),loc=null;
		if(dukkanCheckoutAfterAddress&&country){var all=JSON.parse(wc_address_i18n_params.locale.replace(/&quot;/g,'"'));loc=all[country]||all['default'];}
		$.each(ids||{},function(key,val){$.each(val.split(','),function(i,id){
			var f=form.find(id.trim()),set=loc?loc[key]:null;
			if(set&&typeof set==='object'&&'hidden' in set){return;}
			if(f.hasClass('thwcfd-required')){mark(f,true);}else if(f.hasClass('thwcfd-optional')){mark(f,false);}
		});});
	}
	$(document.body).on('country_to_state_changing',function(e,country){setTimeout(function(){apply(country);},500);});
});
JS;
	}

	/** Hide the "Additional information" box when every extra field is off. */
	public function order_notes_enabled( $enabled ) {
		$extra = get_option( 'wc_fields_additional' );
		if ( ! is_array( $extra ) ) {
			return true;
		}
		foreach ( $extra as $field ) {
			if ( ! empty( $field['enabled'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Drop per-country labels etc. so the store's own values win. */
	public function strip_locale( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		$drop = array_keys(
			array_filter(
				array(
					'label'       => $this->override_label(),
					'placeholder' => $this->override_placeholder(),
					'class'       => $this->override_class(),
					'priority'    => $this->override_priority(),
				)
			)
		);
		foreach ( $fields as $key => $props ) {
			foreach ( $drop as $prop ) {
				if ( is_array( $props ) && isset( $props[ $prop ] ) ) {
					unset( $fields[ $key ][ $prop ] );
				}
			}
		}
		return $fields;
	}

	public function country_locale( $locale ) {
		if ( ! is_array( $locale ) ) {
			return $locale;
		}
		$countries = array_keys( array_merge( WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries() ) );
		foreach ( $countries as $country ) {
			if ( isset( $locale[ $country ] ) ) {
				$locale[ $country ] = $this->strip_locale( $locale[ $country ] );
			}
		}
		return $locale;
	}

	private function posted( string $key ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		if ( '' === $value && isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			parse_str( wp_unslash( $_POST['post_data'] ), $data );
			$value = $data[ $key ] ?? '';
		}
		// phpcs:enable
		return $value;
	}

	private function address_fieldset(): array {
		$shipping = (bool) $this->posted( 'ship_to_different_address' );
		$fields   = self::saved( $shipping ? 'shipping' : 'billing' );
		return array( $shipping ? 'shipping_' : 'billing_', is_array( $fields ) ? $fields : null );
	}

	private function address_rule_applies(): bool {
		return is_checkout()
			&& wc_string_to_bool( get_option( 'woocommerce_shipping_cost_requires_address', 'no' ) )
			&& $this->override_required();
	}

	/** WooCommerce 9.8+ "hide shipping costs until an address is entered". */
	public function address_required_locales( $locales ) {
		if ( ! $this->address_rule_applies() ) {
			return $locales;
		}
		list( $prefix, $set ) = $this->address_fieldset();
		if ( null === $set ) {
			return $locales;
		}
		foreach ( $locales as $country => &$locale ) {
			foreach ( array( 'address_1', 'postcode', 'city', 'state' ) as $f ) {
				if ( ! empty( $locale[ $f ]['hidden'] ) ) {
					$locale[ $f ]['required'] = false;
					continue;
				}
				$locale[ $f ]['required'] = isset( $set[ $prefix . $f ]['required'] ) ? (bool) $set[ $prefix . $f ]['required'] : true;
			}
		}
		return $locales;
	}

	public function address_required_default( $locale ) {
		if ( ! $this->address_rule_applies() ) {
			return $locale;
		}
		list( $prefix, $set ) = $this->address_fieldset();
		if ( null === $set ) {
			return $locale;
		}
		foreach ( array( 'address_1', 'postcode', 'city', 'state' ) as $f ) {
			if ( isset( $set[ $prefix . $f ]['required'] ) ) {
				$locale[ $f ]['required'] = (bool) $set[ $prefix . $f ]['required'];
			}
		}
		return $locale;
	}

	public function billing_fields( $fields, $country ) {
		return $this->address_fields( 'billing', $fields, $country );
	}

	public function shipping_fields( $fields, $country ) {
		return $this->address_fields( 'shipping', $fields, $country );
	}

	private function address_fields( string $section, $fields, $country ) {
		$fields = $this->prepare_address( self::saved( $section ), $country, $fields, $section );
		if ( ! is_wc_endpoint_url( 'edit-address' ) || ! is_array( $fields ) ) {
			return $fields;
		}
		// My Account > Addresses: show the customer's saved values.
		$user = get_current_user_id();
		foreach ( $fields as $key => $field ) {
			$value = get_user_meta( $user, $key, true );
			if ( ! empty( $value ) ) {
				$field['value'] = $value;
			} else {
				$field['value'] = $field['default'] ?? '';
			}
			if ( isset( $field['type'] ) && 'checkbox' === $field['type'] ) {
				$field['checked'] = $value;
			}
			$fields[ $key ] = $field;
			if ( ! empty( $field['custom'] ) && apply_filters( 'thwcfd_show_custom_field_my_account', false ) ) {
				unset( $fields[ $key ] );
			}
		}
		return $fields;
	}

	public function checkout_fields( $fields ) {
		$extra = get_option( 'wc_fields_additional' );
		if ( is_array( $extra ) ) {
			if ( isset( $fields['order'] ) && is_array( $fields['order'] ) ) {
				$fields['order'] = $extra + $fields['order'];
			}
			if ( isset( $extra['order_comments']['enabled'] ) && ! $extra['order_comments']['enabled'] ) {
				unset( $fields['order']['order_comments'] );
			}
		}
		if ( isset( $fields['order'] ) && is_array( $fields['order'] ) ) {
			$fields['order'] = $this->prepare( $fields['order'], false );
		}
		if ( isset( $fields['order'] ) && ! is_array( $fields['order'] ) ) {
			unset( $fields['order'] );
		}
		return $fields;
	}

	private function prepare_address( $set, $country, $original, string $section ) {
		if ( ! is_array( $set ) || empty( $set ) ) {
			return $original;
		}
		$locale = WC()->countries->get_country_locale();
		if ( isset( $locale[ $country ] ) && is_array( $locale[ $country ] ) ) {
			$override_required = $this->override_required();
			$states            = WC()->countries->get_states( $country );
			foreach ( $locale[ $country ] as $key => $value ) {
				$name = $section . '_' . $key;
				if ( ! is_array( $value ) || ! isset( $set[ $name ] ) ) {
					continue;
				}
				if ( ! $override_required && isset( $value['required'] ) ) {
					$set[ $name ]['required'] = $value['required'];
				}
				if ( 'state' === $key ) {
					if ( is_array( $states ) && empty( $states ) ) {
						$set[ $name ]['hidden'] = true;
					}
				} elseif ( isset( $value['hidden'] ) ) {
					$set[ $name ]['hidden'] = $value['hidden'];
				}
			}
		}
		return $this->prepare( $set, $original );
	}

	/** Merge the store's field settings over WooCommerce's fields. */
	private function prepare( $set, $original ) {
		if ( ! is_array( $set ) || empty( $set ) ) {
			return $original;
		}
		$override_required = $this->override_required();
		foreach ( $set as $name => $field ) {
			if ( ! self::enabled( $field ) ) {
				unset( $set[ $name ] );
				continue;
			}
			if ( $original && isset( $original[ $name ] ) && ! apply_filters( 'thwcfd_allow_default_field_override_' . $name, false ) ) {
				$new      = $original[ $name ];
				$class    = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : array();
				$required = $field['required'] ?? 0;
				if ( ! empty( $field['hidden'] ) ) {
					$new['hidden']   = $field['hidden'];
					$new['required'] = false;
				} elseif ( $override_required ) {
					$new['required'] = $required;
				}
				if ( $override_required ) {
					$class[] = $required ? 'thwcfd-required' : 'thwcfd-optional';
				}
				$new['label']       = $field['label'] ?? '';
				$new['default']     = $field['default'] ?? '';
				$new['placeholder'] = $field['placeholder'] ?? '';
				$new['class']       = $class;
				$new['label_class'] = isset( $field['label_class'] ) && is_array( $field['label_class'] ) ? $field['label_class'] : array();
				$new['validate']    = isset( $field['validate'] ) && is_array( $field['validate'] ) ? $field['validate'] : array();
				$new['priority']    = $field['priority'] ?? '';
			} else {
				$new = $field;
			}

			$type           = $new['type'] ?? 'text';
			$new['class'][] = 'thwcfd-field-wrapper';
			$new['class'][] = 'thwcfd-field-' . $type;

			if ( ( 'select' === $type || 'radio' === $type ) && isset( $new['options'] ) ) {
				$opts = array();
				foreach ( self::options( $new['options'] ) as $k => $v ) {
					$opts[ $k ] = $this->translate( $v, 'option' );
				}
				$new['options'] = $opts;
			}
			if ( isset( $new['label'] ) ) {
				$new['label'] = $this->translate( $new['label'], 'label' );
			}
			if ( isset( $new['placeholder'] ) ) {
				$new['placeholder'] = $this->translate( $new['placeholder'], 'placeholder' );
			}
			$set[ $name ] = $new;
		}
		return $set;
	}

	private function translate( $text, string $kind ) {
		if ( empty( $text ) ) {
			return '';
		}
		$prefix = array(
			'label'       => 'Field label - ',
			'placeholder' => 'Field placeholder - ',
			'option'      => 'Field option text - ',
		);
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$t = apply_filters( 'wpml_translate_single_string', $text, 'woo-checkout-field-editor-pro', $prefix[ $kind ] . $text );
			if ( $t !== $text ) {
				return $t;
			}
		}
		// TranslatePress wraps gettext results in #!trpst#…#!trpen# markers, so
		// compare the plain text; otherwise the untranslated English looks
		// "translated" and WooCommerce's own Arabic is never reached.
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.TextDomainMismatch
		$t = __( $text, 'woo-checkout-field-editor-pro' );
		if ( self::plain( $t ) !== $text ) {
			return $t;
		}
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.TextDomainMismatch
		$t = __( $text, 'woocommerce' );
		if ( self::plain( $t ) !== $text ) {
			return $t;
		}
		$own = self::own_translation( $text );
		return null !== $own ? $own : $t;
	}

	private static function plain( $text ): string {
		return (string) preg_replace( '/#!trpst#.*?#!trpen#/s', '', (string) $text );
	}

	/** Arabic for the Dukkan form's own wording, which WooCommerce does not ship. */
	private static function own_translation( string $text ): ?string {
		if ( 0 !== strpos( determine_locale(), 'ar' ) ) {
			return null;
		}
		$ar = array(
			'Full Name'                => 'الاسم الكامل',
			'Full name'                => 'الاسم الكامل',
			'Full address'             => 'العنوان الكامل',
			'Area Name, Street, etc..' => 'المنطقة، الشارع، إلخ..',
			'City'                     => 'المدينة',
		);
		return $ar[ $text ] ?? null;
	}

	/* ------------------------------------------------------------------ */
	/* Custom fields: validate, save, show                                 */
	/* ------------------------------------------------------------------ */

	private static function skip_section( string $section, $data ): bool {
		if ( 'shipping' !== $section ) {
			return false;
		}
		$different = ! empty( $data['ship_to_different_address'] );
		if ( ! $different || ( WC()->cart && ! WC()->cart->needs_shipping_address() ) ) {
			return 'billing_only' !== get_option( 'woocommerce_ship_to_destination' );
		}
		return false;
	}

	public function validate( $posted, $errors ): void {
		$checkout = WC()->checkout();
		foreach ( (array) $checkout->get_checkout_fields() as $section => $set ) {
			if ( self::skip_section( (string) $section, $posted ) || ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $key => $field ) {
				$value = $posted[ $key ] ?? '';
				if ( is_array( $value ) || '' === trim( (string) $value ) || empty( $field['validate'] ) || ! is_array( $field['validate'] ) ) {
					continue;
				}
				$label = $field['label'] ?? $key;
				foreach ( $field['validate'] as $rule ) {
					if ( 'number' === $rule && ! is_numeric( $value ) ) {
						/* translators: %s: field label */
						$errors->add( 'validation', sprintf( __( '<strong>%s</strong> is not a valid number.', 'woo-checkout-field-editor-pro' ), $label ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					} elseif ( 'url' === $rule && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
						/* translators: %s: field label */
						$errors->add( 'validation', sprintf( __( '<strong>%s</strong> is not a valid url.', 'woo-checkout-field-editor-pro' ), $label ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					}
				}
			}
		}
	}

	public function save_custom( $order_id, $posted ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$changed = false;
		foreach ( self::SECTIONS as $section ) {
			if ( self::skip_section( $section, $posted ) ) {
				continue;
			}
			$set = self::saved( $section );
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $name => $field ) {
				if ( ! self::custom( $field ) || ! isset( $posted[ $name ] ) ) {
					continue;
				}
				$raw  = $posted[ $name ];
				$type = $field['type'] ?? 'text';
				$opts = isset( $field['options'] ) ? self::options( $field['options'] ) : array();
				switch ( $type ) {
					case 'textarea':
						$value = sanitize_textarea_field( (string) $raw );
						break;
					case 'email':
						$value = sanitize_email( (string) $raw );
						break;
					case 'select':
					case 'radio':
						$value = sanitize_text_field( (string) $raw );
						$value = array_key_exists( $value, $opts ) ? $value : '';
						break;
					case 'checkboxgroup':
					case 'multiselect':
						$picked = is_array( $raw ) ? $raw : explode( ', ', (string) $raw );
						$picked = array_intersect( array_map( 'strval', $picked ), array_map( 'strval', array_keys( $opts ) ) );
						$value  = implode( ',', $picked );
						break;
					case 'checkbox':
						$value = sanitize_text_field( (string) $raw );
						$value = $value ? ( ! empty( $field['default'] ) ? $field['default'] : $value ) : apply_filters( 'thwcfd_checkbox_field_off_value', $value, $name );
						break;
					default:
						$value = sanitize_text_field( (string) $raw );
				}
				if ( $value || ( '' !== $value && 0 == $value && apply_filters( 'thwcfe_accept_value_zero', false ) ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons
					$order->update_meta_data( $name, $value );
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			$order->save();
		}
	}

	private static function all_fields( $order = null ): array {
		$sections = array( 'billing', 'shipping', 'additional' );
		if ( $order && ( wc_ship_to_billing_address_only() || ! $order->needs_shipping_address() ) ) {
			$sections = array( 'billing', 'additional' );
		}
		$all = array();
		foreach ( $sections as $s ) {
			$set = self::saved( $s );
			if ( is_array( $set ) ) {
				$all = array_merge( $all, $set );
			}
		}
		return $all;
	}

	private static function option_text( $field, $value ) {
		$type = $field['type'] ?? 'text';
		if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
			$opts = self::options( $field['options'] ?? array() );
			return $opts[ $value ] ?? $value;
		}
		return $value;
	}

	/** Lines under "Order details" in WooCommerce emails. */
	public function email_fields( $lines, $sent_to_admin, $order ) {
		$lines = is_array( $lines ) ? $lines : array();
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return $lines;
		}
		foreach ( self::all_fields() as $key => $field ) {
			if ( empty( $field['show_in_email'] ) || ( isset( $field['name'] ) && 'shipping_phone' === $field['name'] ) ) {
				continue;
			}
			$value = $order->get_meta( $key, true );
			if ( ! $value && ! ( '' !== $value && 0 == $value && apply_filters( 'thwcfe_accept_value_zero', false ) ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons
				continue;
			}
			$label = ! empty( $field['label'] ) ? $field['label'] : $key;
			$value = esc_html( $this->translate( self::option_text( $field, $value ), 'option' ) );
			if ( 'textarea' === ( $field['type'] ?? '' ) ) {
				$value = nl2br( $value );
			}
			$lines[ $key ] = array(
				'label' => wp_kses_post( $this->translate( $label, 'label' ) ),
				'value' => $value,
			);
		}
		return $lines;
	}

	/** Custom field values on the thank-you and My Account order pages. */
	public function order_details( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$rows = '';
		foreach ( self::all_fields( $order ) as $key => $field ) {
			if ( ! self::custom( $field ) || empty( $field['show_in_order'] ) ) {
				continue;
			}
			$value = $order->get_meta( $key, true );
			if ( ! $value && ! ( '' !== $value && 0 == $value && apply_filters( 'thwcfe_accept_value_zero', false ) ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons
				continue;
			}
			$label = wp_kses_post( $this->translate( ! empty( $field['label'] ) ? $field['label'] : $key, 'label' ) );
			$value = esc_html( $this->translate( self::option_text( $field, $value ), 'option' ) );
			if ( 'textarea' === ( $field['type'] ?? '' ) ) {
				$value = nl2br( $value );
			}
			$rows .= '<tr><th>' . $label . ':</th><td>' . wptexturize( $value ) . '</td></tr>';
		}
		if ( '' !== $rows ) {
			echo '<table class="woocommerce-table woocommerce-table--custom-fields shop_table custom-fields">' . $rows . '</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/**
 * Checkout form fields.
 */
class Dukkan_Checkout_Fields_CLI {

	/**
	 * Show whether the module runs and why not.
	 *
	 * @subcommand status
	 */
	public function status() {
		$why = Dukkan_Checkout_Fields::idle_reason();
		WP_CLI::line( 'Running: ' . ( '' === $why ? 'yes' : 'no (' . $why . ')' ) );
		foreach ( Dukkan_Checkout_Fields::SECTIONS as $s ) {
			$saved = get_option( 'wc_fields_' . $s );
			WP_CLI::line( sprintf( '%-10s %s', $s . ':', is_array( $saved ) ? count( $saved ) . ' saved fields' : ( 'billing' === $s ? 'Dukkan default form' : 'WooCommerce default' ) ) );
		}
	}

	/**
	 * List the fields of a section.
	 *
	 * ## OPTIONS
	 *
	 * [<section>]
	 * : billing, shipping or additional.
	 * ---
	 * default: billing
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json or csv.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc ) {
		$section = $this->section( $args[0] ?? 'billing' );
		$set     = Dukkan_Checkout_Fields::saved( $section );
		if ( ! is_array( $set ) ) {
			WP_CLI::line( "No saved $section fields; WooCommerce's own fields are used." );
			return;
		}
		$rows = array();
		foreach ( $set as $name => $f ) {
			$rows[] = array(
				'field'       => $name,
				'type'        => $f['type'] ?? 'text',
				'label'       => $f['label'] ?? '',
				'placeholder' => $f['placeholder'] ?? '',
				'required'    => empty( $f['required'] ) ? 'no' : 'yes',
				'enabled'     => ( isset( $f['enabled'] ) && ! $f['enabled'] ) ? 'no' : 'yes',
				'priority'    => $f['priority'] ?? '',
				'width'       => $this->width( $f['class'] ?? array() ),
			);
		}
		WP_CLI\Utils\format_items( $assoc['format'], $rows, array_keys( $rows[0] ) );
	}

	/**
	 * Change one field.
	 *
	 * ## OPTIONS
	 *
	 * <field>
	 * : Field name, e.g. billing_state.
	 *
	 * [--label=<label>]
	 * : Field label.
	 *
	 * [--placeholder=<text>]
	 * : Placeholder text.
	 *
	 * [--required=<value>]
	 * : yes or no.
	 *
	 * [--enabled=<value>]
	 * : yes or no.
	 *
	 * [--priority=<n>]
	 * : Position; lower comes first.
	 *
	 * [--width=<value>]
	 * : first (left half), last (right half) or wide.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dukkan-checkout set billing_company --enabled=yes --required=no
	 */
	public function set( $args, $assoc ) {
		$name    = $args[0];
		$section = 0 === strpos( $name, 'billing_' ) ? 'billing' : ( 0 === strpos( $name, 'shipping_' ) ? 'shipping' : 'additional' );
		$set     = Dukkan_Checkout_Fields::saved( $section );
		if ( ! is_array( $set ) || ! isset( $set[ $name ] ) ) {
			WP_CLI::error( "No saved field $name in $section." );
		}
		$f = $set[ $name ];
		foreach ( array( 'label', 'placeholder' ) as $k ) {
			if ( isset( $assoc[ $k ] ) ) {
				$f[ $k ] = sanitize_text_field( $assoc[ $k ] );
			}
		}
		foreach ( array( 'required', 'enabled' ) as $k ) {
			if ( isset( $assoc[ $k ] ) ) {
				$f[ $k ] = in_array( strtolower( $assoc[ $k ] ), array( 'yes', '1', 'true' ), true ) ? 1 : 0;
			}
		}
		if ( isset( $assoc['priority'] ) ) {
			$f['priority'] = (int) $assoc['priority'];
		}
		if ( isset( $assoc['width'] ) ) {
			if ( ! in_array( $assoc['width'], array( 'first', 'last', 'wide' ), true ) ) {
				WP_CLI::error( 'Width must be first, last or wide.' );
			}
			$class   = array_values( array_diff( (array) ( $f['class'] ?? array() ), array( 'form-row-first', 'form-row-last', 'form-row-wide' ) ) );
			array_unshift( $class, 'form-row-' . $assoc['width'] );
			$f['class'] = $class;
		}
		$set[ $name ] = $f;
		update_option( 'wc_fields_' . $section, $set, false );
		WP_CLI::success( "Saved $name." );
	}

	/**
	 * Put a section back to the Dukkan form (billing) or WooCommerce's own fields.
	 *
	 * ## OPTIONS
	 *
	 * <section>
	 * : billing, shipping or additional.
	 *
	 * [--yes]
	 * : Skip the question.
	 */
	public function reset( $args, $assoc ) {
		$section = $this->section( $args[0] );
		WP_CLI::confirm( "Reset the $section fields?", $assoc );
		if ( 'billing' === $section ) {
			update_option( 'wc_fields_billing', Dukkan_Checkout_Fields::default_billing(), false );
		} else {
			delete_option( 'wc_fields_' . $section );
		}
		WP_CLI::success( "Reset $section." );
	}

	private function section( string $s ): string {
		if ( ! in_array( $s, Dukkan_Checkout_Fields::SECTIONS, true ) ) {
			WP_CLI::error( 'Section must be billing, shipping or additional.' );
		}
		return $s;
	}

	private function width( $class ): string {
		foreach ( array( 'first', 'last', 'wide' ) as $w ) {
			if ( in_array( 'form-row-' . $w, (array) $class, true ) ) {
				return $w;
			}
		}
		return '';
	}
}
