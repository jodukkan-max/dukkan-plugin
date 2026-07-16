<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		//add_action('rest_api_init', array($this, 'ag_testing_tp'));

	}

	public function ag_testing_tp(){
		register_rest_route('twb/v1', '/translate', array(
			'methods' => 'POST',
			'callback' => array($this, 'twb_save_translation'),
			'permission_callback' => '__return_true'
		));
		register_rest_route('twb/v1', '/get-translations', array(
			'methods' => 'GET',
			'callback' => array($this, 'twb_get_translations'),
			'permission_callback' => '__return_true'
		));
	}

	public function twb_trp_format_string($string){
		$replace = [
			'–' => '&#8211;',
			'—' => '&#8212;',
			'’' => '&#8217;',
			'‘' => '&#8216;',
			'“' => '&#8220;',
			'”' => '&#8221;',
		];

		return str_replace(array_keys($replace), array_values($replace), wptexturize($string));
	}

	public function twb_save_translation($request) {

		// {
		// "original": "Hello World",
		// "source_lang": "en_us",
		// "translations": {
		// 	"ar": "مرحبا بالعالم",
		// 	"es_es": "Hola Mundo",
		// 	"fr_fr": "Bonjour le monde"
		// }
		// }

		global $wpdb;

		// $post_id = $request['post_id'];
		// $lang = $request['lang'];
		$original_text = $this->twb_trp_format_string($request['original']);
		// $trp = get_option('trp_settings');
		// $source_lang = strtolower($trp['default-language']);
		$source_lang   = strtolower($request['source_lang']);
		$translations  = $request['translations'];

		if(empty($original_text) || empty($translations) || empty($source_lang)){
			return new WP_Error('invalid_data','Missing data', ['status'=>400]);
		}

		foreach($translations as $target_lang => $translated_text){

			$target_lang = strtolower($target_lang);
			$translated_text = $this->twb_trp_format_string($translated_text);

			// Build TranslatePress table name
			$table = $wpdb->prefix . "trp_dictionary_{$source_lang}_{$target_lang}";

			// Check if table exists
			if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
				continue;
			}

			// Check if original string exists
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE original = %s LIMIT 1",
					$original_text
				)
			);

			if ($row) {

				// Update translation
				$wpdb->update(
					$table,
					[
						'translated' => $translated_text,
						'status'     => 2
					],
					[
						'id' => $row->id
					],
					['%s','%d'],
					['%d']
				);

			} else {

				// Insert new translation
				$wpdb->insert(
					$table,
					[
						'original'   => $original_text,
						'translated' => $translated_text,
						'status'     => 2
					],
					['%s','%s','%d']
				);

				$insert_id = $wpdb->insert_id;

				if ($insert_id) {

					$wpdb->update(
						$table,
						[
							'original_id' => $insert_id
						],
						[
							'id' => $insert_id
						],
						['%d'],
						['%d']
					);
				}
			}
		}

		// $translated_text = $this->twb_trp_format_string($request['translated']);

		// $table = $wpdb->prefix . 'trp_dictionary_en_us_ar'; // example table

		// // $wpdb->insert($table, [
		// // 	'original' => $original_text,
		// // 	'translated' => $translated_text
		// // ]);
		// // Check if original string exists
		// $row = $wpdb->get_row(
		// 	$wpdb->prepare(
		// 		"SELECT id FROM $table WHERE original = %s LIMIT 1",
		// 		$original_text
		// 	)
		// );

		// if ($row) {

		// 	// Update existing row
		// 	$wpdb->update(
		// 		$table,
		// 		[
		// 			'translated' => $translated_text,
		// 			'status'     => 2
		// 		],
		// 		[
		// 			'id' => $row->id
		// 		],
		// 		['%s','%d'],
		// 		['%d']
		// 	);

		// 	$insert_id = $row->id;

		// } else {

		// 	// Insert new row
		// 	$wpdb->insert(
		// 		$table,
		// 		[
		// 			'original'   => $original_text,
		// 			'translated' => $translated_text,
		// 			'status'     => 2
		// 		],
		// 		['%s','%s','%d']
		// 	);

		// 	$insert_id = $wpdb->insert_id;

		// 	// Update original_id with inserted id
		// 	if ($insert_id) {
		// 		$wpdb->update(
		// 			$table,
		// 			[
		// 				'original_id' => $insert_id
		// 			],
		// 			[
		// 				'id' => $insert_id
		// 			],
		// 			['%d'],
		// 			['%d']
		// 		);
		// 	}
		// }

		return [
			'status' => 'success'
		];
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( ! is_product() ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dukkan_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dukkan_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/dukkan-plugin-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! is_product() ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dukkan_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dukkan_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/dukkan-plugin-public.js', array( 'jquery' ), $this->version, false );

	}

}
