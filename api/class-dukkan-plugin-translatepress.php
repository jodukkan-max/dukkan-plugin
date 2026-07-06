<?php

/**
 * The translatepress-api functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 */

/**
 * The translatepress-api functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the translatepress-api stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Translatepress {

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
		add_action('rest_api_init', array($this, 'dukkan_plugin_translatepress_api'));

	}

    public function dukkan_plugin_translatepress_api(){
		register_rest_route('dukkan-translation-translatepress/v1', '/languages-list', array(
            'methods' => 'GET',
            'callback' => array($this, 'dukkan_plugin_get_translatepress_languages_list'),
            'permission_callback' => '__return_true'
        ));

		register_rest_route('dukkan-translation-translatepress/v1', '/translate', array(
			'methods' => 'POST',
			'callback' => array($this, 'dukkan_plugin_save_translation'),
			'permission_callback' => '__return_true'
		));
		register_rest_route('dukkan-translation-translatepress/v1', '/get-translations', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translations'),
			'permission_callback' => '__return_true'
		));

        register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'dukkan_plugin_get_translatepress_settings'),
            'permission_callback' => '__return_true'
        ));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-save-settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'dukkan_plugin_get_translatepress_save_settings'),
            'permission_callback' => '__return_true'
        ));

		// gettext string translation
		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-get-text-domains', array(
            'methods' => 'GET',
            'callback' => array($this, 'dukkan_plugin_get_translatepress_text_domains'),
            'permission_callback' => '__return_true'
        ));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-gettext-translations', array(
            'methods' => 'GET',
            'callback' => array($this, 'dukkan_plugin_get_translatepress_gettext_translations'),
            'permission_callback' => '__return_true'
        ));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-gettext-translate', array(
            'methods' => 'POST',
            'callback' => array($this, 'dukkan_plugin_save_translatepress_gettext_translations'),
            'permission_callback' => '__return_true'
        ));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-gettext-original-strings', array(
            'methods' => 'GET',
            'callback' => array($this, 'dukkan_plugin_get_translatepress_gettext_original_strings'),
            'permission_callback' => '__return_true'
        ));
	}

	public function dukkan_plugin_trp_format_string($string){
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

	public function dukkan_plugin_trp_unformat_string($string) {
		$replace = [
			'&#8211;' => '–',
			'&#8212;' => '—',
			'&#8217;' => '’',
			'&#8216;' => '‘',
			'&#8220;' => '“',
			'&#8221;' => '”',
		];

		// Replace entities back to characters
		$string = str_replace(array_keys($replace), array_values($replace), $string);

		// Decode any remaining HTML entities
		$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

		return $string;
	}

	public function dukkan_plugin_get_translatepress_text_domains(){
		global $wpdb;

		$table = $wpdb->prefix . 'trp_gettext_original_strings';

		// Check table exists
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
			return new WP_Error('table_missing','Gettext table not found',['status'=>404]);
		}

		// Get distinct domains
		$domains = $wpdb->get_col("
			SELECT DISTINCT domain 
			FROM $table 
			WHERE domain != '' 
			ORDER BY domain ASC
		");

		return [
			'status' => 'success',
			'domains' => $domains
		];
	}

	public function dukkan_plugin_get_translatepress_gettext_original_strings( $request ){
		
		global $wpdb;

    	$domain      = $request->get_param('domain'); // optional
		$page     = max(1, (int)$request->get_param('page'));
		$per_page = max(10, (int)$request->get_param('per_page'));
		$offset   = ($page - 1) * $per_page;

		$table = $wpdb->prefix . 'trp_gettext_original_strings';

		// Check table exists
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
			return new WP_Error('table_missing','Gettext table not found',['status'=>404]);
		}

		// Build query
		if(!empty($domain)){
			$query = $wpdb->prepare(
				"SELECT id, original, domain 
				FROM $table 
				WHERE domain = %s 
				ORDER BY id DESC LIMIT $per_page OFFSET $offset",
				$domain
			);
		} else {
			$query = "SELECT id, original, domain 
					FROM $table 
					ORDER BY id DESC LIMIT $per_page OFFSET $offset";
		}

		$rows = $wpdb->get_results($query);

    	$results = [];

		foreach($rows as $row){

			$item = [
				'id'       => $row->id,
				'original' => $row->original,
				'domain'   => $row->domain
			];

			$translations = [];

			$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}trp_gettext_%'");

			foreach($tables as $t){

				if(strpos($t, 'original_strings') !== false){
					continue;
				}
				else if(strpos($t, 'original_meta') !== false){
					continue;
				}

				$lang = str_replace("{$wpdb->prefix}trp_gettext_", '', $t);

				$row_tr = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT translated FROM $t WHERE original_id = %d LIMIT 1",
						$row->id
					)
				);

				$translations[$lang] = [
					'translated' => $row_tr ? $this->dukkan_plugin_trp_unformat_string($row_tr->translated) : '',
					'status'     => $row_tr ? 'translated' : 'missing'
				];
			}

			$item['translations'] = $translations;

			$results[] = $item;
		}

		return [
			'status' => 'success',
			'count'  => count($results),
			'data'   => $results
		];
	}

	public function dukkan_plugin_get_translatepress_gettext_translations( $request ){
		
		global $wpdb;

    	$original = $request['original']; //$this->dukkan_plugin_trp_format_string($request['original']);
		$domain   = $request['domain'] ?? '';

		$original_table = $wpdb->prefix . 'trp_gettext_original_strings';

		$original_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $original_table WHERE original = %s AND domain = %s LIMIT 1",
				$original,
				$domain
			)
		);

		if(!$original_row){
			return [
				'status' => 'not_found'
			];
		}

		$original_id = $original_row->id;
		$translations = [];

		// Get all gettext tables
		$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}trp_gettext_%'");

		foreach($tables as $table){

			if(strpos($table, 'original_strings') !== false){
				continue;
			}
			else if(strpos($table, 'original_meta') !== false){
				continue;
			}

			$lang = str_replace("{$wpdb->prefix}trp_gettext_", '', $table);

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT translated FROM $table WHERE original_id = %d LIMIT 1",
					$original_id
				)
			);

			if($row){
				$translations[$lang] = [
					'translated' => $this->dukkan_plugin_trp_unformat_string($row->translated),
					'status' => 'translated'
				];
			} else {
				$translations[$lang] = [
					'translated' => '',
					'status' => 'missing'
				];
			}
		}

		return [
			'status' => 'success',
			'original' => $this->dukkan_plugin_trp_unformat_string($original),
			'translations' => $translations
		];
	}

	public function dukkan_plugin_save_translatepress_gettext_translations($request){

		if( !class_exists('TRP_Translate_Press') ){
			return new WP_Error('tp_missing','TranslatePress not active',['status'=>400]);
		}

		global $wpdb;

		$params = $request->get_json_params();

		$original     = trim($params['original']);
		$domain       = $params['domain'] ?? '';
		$context      = $params['context'] ?? '';
		$translations = $params['translations'];

		if(empty($original) || empty($translations)){
			return new WP_Error('invalid_data','Missing data',['status'=>400]);
		}

		$trp = TRP_Translate_Press::get_trp_instance();
		$trp_query = $trp->get_component('query');
		$gettext_insert_update = $trp_query->get_query_component('gettext_insert_update');

		$original_table = $wpdb->prefix . 'trp_gettext_original_strings';

		// Get original_id
		$original_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $original_table 
				WHERE original = %s AND domain = %s 
				LIMIT 1",
				$original,
				$domain
			)
		);

		if(!$original_id){
			// Insert original manually (TP doesn't expose public method)
			$wpdb->insert($original_table, [
				'original' => $original,
				'domain'   => $domain,
				'context'  => $context
			]);
			$original_id = $wpdb->insert_id;
		}

		$results = [];

		foreach($translations as $lang => $translated){

			$translated = trim($translated);

			$table = $wpdb->prefix . "trp_gettext_{$lang}";

			if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
				$results[] = [
					'language' => $lang,
					'status'   => 'table_not_found'
				];
				continue;
			}

			// 🔍 Check if exists
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE original_id = %d LIMIT 1",
					$original_id
				)
			);

			if($existing){

				// UPDATE using TP core
				$update_data = [
					[
						'id'         => $existing->id,
						'translated' => $translated,
						'status'     => 2
					]
				];

				$gettext_insert_update->update_gettext_strings(
					$update_data,
					$lang,
					['translated','id','status']
				);

				$results[] = [
					'language' => $lang,
					'status'   => 'updated'
				];

			} else {

				// INSERT using TP core
				$insert_data = [
					[
						'original_id' => $original_id,
						'original'    => $original,
						'translated'  => $translated,
						'domain'      => $domain,
						'context'     => $context,
						'status'      => 2,
						'plural_form' => 0
					]
				];

				$gettext_insert_update->insert_gettext_strings($insert_data, $lang);

				$results[] = [
					'language' => $lang,
					'status'   => 'inserted'
				];
			}
		}

		// Clear cache
		$translation_manager = $trp->get_component('translation_manager');
		if(method_exists($translation_manager, 'delete_cache')){
			$translation_manager->delete_cache();
		}

		return [
			'status'      => 'success',
			'original_id' => (int)$original_id,
			'results'     => $results
		];
	}

	public function dukkan_plugin_save_translatepress_gettext_translations__stop( $request ){
		
		global $wpdb;

		$params = $request->get_json_params();

		$original = $this->dukkan_plugin_trp_format_string($params['original']);
		$domain   = $params['domain'] ?? '';
		//$context  = $params['context'] ?? '';
		$translations = $params['translations'];

		if(empty($original) || empty($translations)){
			return new WP_Error('invalid_data','Missing data',['status'=>400]);
		}

		 $original_table = $wpdb->prefix . 'trp_gettext_original_strings';

		// Check if original string exists
		$original_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $original_table WHERE original = %s AND domain = %s LIMIT 1",
				$original,
				$domain
			)
		);

		if($original_row){
			$original_id = $original_row->id;
		} else {

			// Insert original string
			$wpdb->insert($original_table, [
				'original' => $original,
				'domain'   => $domain
			]);

			$original_id = $wpdb->insert_id;
		}

		$results = [];

		// Loop languages
		foreach($translations as $lang => $translated){

			$translated = $this->dukkan_plugin_trp_format_string($translated);

			$table = $wpdb->prefix . "trp_gettext_{$lang}";

			if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
				$results[] = [
					'language' => $lang,
					'status' => 'table_not_found'
				];
				continue;
			}

			// Check if translation exists
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE original_id = %d LIMIT 1",
					$original_id
				)
			);

			if($row){

				$wpdb->update($table, [
					'translated' => $translated,
					'status' => 2
				], [
					'id' => $row->id
				]);

				$results[] = [
					'language' => $lang,
					'status' => 'updated'
				];

			} else {

				$wpdb->insert($table, [
					'original'    => $original,
					'translated'  => $translated,
					'domain'      => $domain,
					'status'      => 2,
					'original_id' => $original_id
				]);

				$results[] = [
					'language' => $lang,
					'status' => 'inserted'
				];
			}
		}

		return [
			'status' => 'success',
			'original_id' => $original_id,
			'results' => $results
		];
	}

	public function dukkan_plugin_get_translatepress_languages_list(){
		if( !class_exists('TRP_Translate_Press') ){
			return new WP_Error('tp_missing','TranslatePress not active',['status'=>400]);
		}
		$trp = TRP_Translate_Press::get_trp_instance();
		$trp_languages = $trp->get_component('languages');
		$wp_languages = $trp_languages->get_wp_languages();

        if (empty($wp_languages)) {
            return new WP_Error(
                'tp_languages_not_found',
                'TranslatePress Languages not found',
                ['status' => 404]
            );
        }

        return [
            'status' => 'success',
            'available_languages' => $wp_languages
        ];
	}

	public function dukkan_plugin_get_translatepress_save_settings($request){
		if( !class_exists('TRP_Translate_Press') ){
			return new WP_Error('tp_missing','TranslatePress not active',['status'=>400]);
		}

		$params = $request->get_json_params();

		if(empty($params)){
			return new WP_Error('invalid_data','No data provided',['status'=>400]);
		}

		// Get TranslatePress instance
		$trp = TRP_Translate_Press::get_trp_instance();
		$settings_obj = $trp->get_component('settings');

		// Get current settings
		$settings = get_option('trp_settings');

		if(empty($settings)){
			return new WP_Error('tp_settings_missing','Settings not found',['status'=>404]);
		}

		/**
		 * IMPORTANT PART
		 * Sanitize settings using TranslatePress internal method
		 */
		if(method_exists($settings_obj, 'sanitize_settings')){
			$settings_new = $settings_obj->sanitize_settings($params);
		}

		// Save settings
    	update_option('trp_settings', $settings_new);

		return [
            'status' => 'success',
            'settings' => $settings_new
        ];
	}

    public function dukkan_plugin_get_translatepress_settings() {

        $settings = get_option('trp_settings');
		// $settings_obj = new TRP_Settings();
		// $settings     = $settings_obj->get_settings();

        if (empty($settings)) {
            return new WP_Error(
                'tp_settings_not_found',
                'TranslatePress settings not found',
                ['status' => 404]
            );
        }

        return [
            'status' => 'success',
            'settings' => $settings
        ];
    }

    public function dukkan_plugin_get_translations($request){

        global $wpdb;

        $original = $request['original']; //$this->dukkan_plugin_trp_format_string($request['original']);
        $source_lang = strtolower($request['source_lang']);

        if(empty($original) || empty($source_lang)){
            return new WP_Error('invalid_data','Missing parameters',['status'=>400]);
        }

        $translations = [];

        // Get all dictionary tables
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}trp_dictionary_{$source_lang}_%'");

        if(!$tables){
            return [
                'status' => 'no_languages_found'
            ];
        }

        foreach($tables as $table){

            // extract target language from table name
            $target_lang = str_replace("{$wpdb->prefix}trp_dictionary_{$source_lang}_", '', $table);

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT translated FROM $table WHERE original = %s LIMIT 1",
                    $original
                )
            );

            if($row && $row->translated != ''){

                $translations[$target_lang] = [
                    'translated' => $this->dukkan_plugin_trp_unformat_string($row->translated),
                    'status' => 'translated'
                ];

            }else{

                $translations[$target_lang] = [
                    'translated' => '',
                    'status' => 'missing'
                ];
            }
        }

        return [
            'status' => 'success',
            'original' => $this->dukkan_plugin_trp_unformat_string($original),
            'translations' => $translations
        ];
    }

	public function dukkan_plugin_save_translation($request){

		global $wpdb;

		if( !class_exists('TRP_Translate_Press') ){
			return new WP_Error('tp_missing','TranslatePress not active',['status'=>400]);
		}

		$params = $request->get_json_params();

		$original     = $params['original'];
		$source_lang  = $params['source_lang']; // en_US
		$translations = $params['translations'];

		if(empty($original) || empty($translations)){
			return new WP_Error('invalid_data','Missing data',['status'=>400]);
		}

		$trp = TRP_Translate_Press::get_trp_instance();
		$trp_query = $trp->get_component('query');

		// STEP 1: Insert original string (only once)
		$trp_query->insert_strings([$original], $source_lang);

		// STEP 2: Get original_id (only once)
		$original_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}trp_original_strings WHERE original = %s LIMIT 1",
				$original
			)
		);

		if(!$original_id){
			return new WP_Error('original_not_found','Original string not found',['status'=>400]);
		}

		$results = [];

		// STEP 3: Loop translations
		foreach($translations as $target_lang => $translated){

			$table = $wpdb->prefix . "trp_dictionary_{$source_lang}_{$target_lang}";

			if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
				$results[] = [
					'language' => $target_lang,
					'status' => 'table_not_found'
				];
				continue;
			}

			// Check if translation exists
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE original_id = %d LIMIT 1",
					$original_id
				)
			);

			if($row){

				$wpdb->update(
					$table,
					[
						'translated' => $translated,
						'status' => 2
					],
					['id' => $row->id]
				);

				$results[] = [
					'language' => $target_lang,
					'status' => 'updated'
				];

			} else {

				$wpdb->insert(
					$table,
					[
						'original'   => $original,
						'translated' => $translated,
						'status'     => 2,
						'original_id'=> $original_id
					]
				);

				$results[] = [
					'language' => $target_lang,
					'status' => 'inserted'
				];
			}
		}

		return [
			'status' => 'success',
			'results' => $results
		];
	}

	// not in use

	public function dukkan_plugin_save_translation___old($request) {

		global $wpdb;

		$original_text = $this->dukkan_plugin_trp_format_string($request['original']);
		// $trp = get_option('trp_settings');
		// $source_lang = strtolower($trp['default-language']);
		$source_lang   = strtolower($request['source_lang']);
		$translations  = $request['translations'];

        $results = [];
		if(empty($original_text) || empty($translations) || empty($source_lang)){
			return new WP_Error('invalid_data','Missing data', ['status'=>400]);
		}

		// original strings
		$original_strings_table = $wpdb->prefix . "trp_original_strings";
		// Check if original strings table exists
		if($wpdb->get_var("SHOW TABLES LIKE '$original_strings_table'") != $original_strings_table){
			return new WP_Error('table_not_found','Original strings table not found', ['status'=>400]);
		}

		// Check if original string exists
		$original_string_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $original_strings_table WHERE original = %s LIMIT 1",
				$original_text
			)
		);
		if($original_string_row){
			$original_string_id = $original_string_row->id;
		} else {
			// Insert original string
			$wpdb->insert(
				$original_strings_table,
				[
					'original' => $original_text
				],
				['%s']
			);
			$original_string_id = $wpdb->insert_id;
		}

		foreach($translations as $target_lang => $translated_text){

			$target_lang = strtolower($target_lang);
			$translated_text = $this->dukkan_plugin_trp_format_string($translated_text);

			// Build TranslatePress table name
			$table = $wpdb->prefix . "trp_dictionary_{$source_lang}_{$target_lang}";

			// Check if table exists
			if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
                $results[] = [
                    'language' => $target_lang,
                    'status'   => 'table_not_found'
                ];
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

                $results[] = [
                    'language' => $target_lang,
                    'status'   => 'updated'
                ];

			} else {

				// Insert new translation
				$wpdb->insert(
					$table,
					[
						'original'   => $original_text,
						'translated' => $translated_text,
						'status'     => 2,
						'original_id' => $original_string_id
					],
					['%s','%s','%d','%d']
				);

				$insert_id = $wpdb->insert_id;

				if ($insert_id) {

                    $results[] = [
                        'language' => $target_lang,
                        'status'   => 'inserted'
                    ];
				}
                else {

                    $results[] = [
                        'language' => $target_lang,
                        'status'   => 'failed'
                    ];
                }
			}
		}

		return [
            'status' => 'success',
            'results' => $results
        ];
	}

}