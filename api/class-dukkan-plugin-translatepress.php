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
 * Defines the plugin name, version, and REST routes for reading and writing
 * TranslatePress translations (regular + gettext) from the Dukkan mobile app.
 *
 * All routes require WooCommerce manager permissions (same convention as the
 * product-addon, badge and loyalty APIs): read routes use `check_permissions()`
 * and write routes use `check_edit_permissions()`.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Dukkan Ecommerce LLC
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
		add_action( 'rest_api_init', array( $this, 'dukkan_plugin_translatepress_api' ) );

	}

	/**
	 * Permission callback for reads — requires WooCommerce REST read access.
	 *
	 * @since 1.0.35
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ) {
		if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
			return new WP_Error(
				'woocommerce_rest_cannot_view',
				__( 'Sorry, you cannot view this resource.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Permission callback for mutations — requires WooCommerce REST edit access.
	 *
	 * @since 1.0.35
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_edit_permissions( WP_REST_Request $request ) {
		if ( ! wc_rest_check_manager_permissions( 'settings', 'edit' ) ) {
			return new WP_Error(
				'woocommerce_rest_cannot_edit',
				__( 'Sorry, you cannot edit this resource.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function dukkan_plugin_translatepress_api(){
		register_rest_route('dukkan-translation-translatepress/v1', '/languages-list', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translatepress_languages_list'),
			'permission_callback' => array( $this, 'check_permissions' ),
		));

		register_rest_route('dukkan-translation-translatepress/v1', '/translate', array(
			'methods' => 'POST',
			'callback' => array($this, 'dukkan_plugin_save_translation'),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
		));
		register_rest_route('dukkan-translation-translatepress/v1', '/translate-block', array(
			'methods' => 'POST',
			'callback' => array($this, 'dukkan_plugin_save_translation_block'),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
		));
		register_rest_route('dukkan-translation-translatepress/v1', '/get-translations', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translations'),
			'permission_callback' => array( $this, 'check_permissions' ),
		));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-settings', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translatepress_settings'),
			'permission_callback' => array( $this, 'check_permissions' ),
		));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-save-settings', array(
			'methods' => 'POST',
			'callback' => array($this, 'dukkan_plugin_save_translatepress_settings'),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
		));

		// gettext string translation
		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-get-text-domains', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translatepress_text_domains'),
			'permission_callback' => array( $this, 'check_permissions' ),
		));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-gettext-translations', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translatepress_gettext_translations'),
			'permission_callback' => array( $this, 'check_permissions' ),
		));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-gettext-translate', array(
			'methods' => 'POST',
			'callback' => array($this, 'dukkan_plugin_save_translatepress_gettext_translations'),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
		));

		register_rest_route('dukkan-translation-translatepress/v1', '/translatepress-gettext-original-strings', array(
			'methods' => 'GET',
			'callback' => array($this, 'dukkan_plugin_get_translatepress_gettext_original_strings'),
			'permission_callback' => array( $this, 'check_permissions' ),
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

	/**
	 * Normalize an "original" string to the exact form TranslatePress stores in
	 * its dictionary tables, so lookups and writes match regardless of the
	 * punctuation encoding the caller (mobile app) sent.
	 *
	 * TranslatePress extracts strings from the rendered page with
	 * `html_entity_decode()` (see `class-translation-render.php`) and matches them
	 * EXACTLY against the dictionary `original` column. That column therefore
	 * holds DECODED raw Unicode (e.g. `you’re`), NOT numeric entities
	 * (`you&#8217;re`).
	 *
	 * We canonicalize by: (1) decoding any entities to raw UTF-8, (2) running
	 * `wptexturize()` so straight quotes/dashes become curly, and (3) decoding a
	 * final time in case `wptexturize()` produced numeric entities.
	 *
	 * NOTE: We must NOT re-encode curly punctuation back to numeric entities —
	 * that was the previous bug which produced keys the front-end never matches,
	 * leaving descriptions with apostrophes/quotes/dashes untranslated.
	 *
	 * @since 1.0.35
	 * @param string $string Raw original string.
	 * @return string Canonicalized original string (decoded raw UTF-8).
	 */
	public function dukkan_plugin_canonicalize_original( $string ) {
		if ( ! is_string( $string ) ) {
			return $string;
		}

		// Decode numeric + named entities to raw UTF-8.
		$string = html_entity_decode( $string, ENT_QUOTES, 'UTF-8' );

		// Straight quotes/dashes -> curly (may produce numeric entities).
		if ( function_exists( 'wptexturize' ) ) {
			$string = wptexturize( $string );
		}

		// Final decode so the key matches TranslatePress's decoded lookup form.
		return html_entity_decode( $string, ENT_QUOTES, 'UTF-8' );
	}

	public function dukkan_plugin_get_translatepress_text_domains(){
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return new WP_Error( 'tp_missing', 'TranslatePress not active', array( 'status' => 400 ) );
		}

		global $wpdb;

		$table = $wpdb->prefix . 'trp_gettext_original_strings';

		// Check table exists
		if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) != $table){
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
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return new WP_Error( 'tp_missing', 'TranslatePress not active', array( 'status' => 400 ) );
		}

		global $wpdb;

		$domain   = $request->get_param('domain'); // optional
		$page     = max(1, (int)$request->get_param('page'));
		$per_page = min( 100, max( 10, (int) $request->get_param( 'per_page' ) ) ); // cap to prevent abuse
		$offset   = ($page - 1) * $per_page;

		$table = $wpdb->prefix . 'trp_gettext_original_strings';

		// Check table exists
		if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) != $table){
			return new WP_Error('table_missing','Gettext table not found',['status'=>404]);
		}

		// Build query
		if(!empty($domain)){
			$query = $wpdb->prepare(
				"SELECT id, original, domain 
				FROM $table 
				WHERE domain = %s 
				ORDER BY id DESC LIMIT %d OFFSET %d",
				$domain,
				$per_page,
				$offset
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT id, original, domain 
				FROM $table 
				ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$rows = $wpdb->get_results($query);

		$results = [];

		if ( empty( $rows ) ) {
			return [
				'status' => 'success',
				'count'  => 0,
				'data'   => $results,
			];
		}

		$ids = wp_list_pluck( $rows, 'id' );

		// Collect the language tables once.
		$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}trp_gettext_%'");

		$lang_tables = [];
		foreach ( $tables as $t ) {
			if ( strpos( $t, 'original_strings' ) !== false || strpos( $t, 'original_meta' ) !== false ) {
				continue;
			}
			$lang_tables[ str_replace( "{$wpdb->prefix}trp_gettext_", '', $t ) ] = $t;
		}

		// Batch-load translations for all requested original ids per language,
		// avoiding an N+1 query storm.
		$translations_by_id = [];
		foreach ( $rows as $row ) {
			$translations_by_id[ $row->id ] = [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		foreach ( $lang_tables as $lang => $t ) {
			$id_lookup = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT original_id, translated FROM {$t} WHERE original_id IN ( {$placeholders} )",
					$ids
				),
				ARRAY_A
			);

			foreach ( $id_lookup as $tr ) {
				$translations_by_id[ (int) $tr['original_id'] ][ $lang ] = $tr['translated'];
			}
		}

		foreach ( $rows as $row ) {

			$item = [
				'id'       => $row->id,
				'original' => $row->original,
				'domain'   => $row->domain
			];

			$translations = [];

			foreach ( $lang_tables as $lang => $t ) {
				if ( isset( $translations_by_id[ $row->id ][ $lang ] ) ) {
					$translations[ $lang ] = [
						'translated' => $this->dukkan_plugin_trp_unformat_string( $translations_by_id[ $row->id ][ $lang ] ),
						'status'     => 'translated'
					];
				} else {
					$translations[ $lang ] = [
						'translated' => '',
						'status'     => 'missing'
					];
				}
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
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return new WP_Error( 'tp_missing', 'TranslatePress not active', array( 'status' => 400 ) );
		}

		global $wpdb;

		$original = $request->get_param( 'original' );
		$domain   = $request->get_param( 'domain' );
		$domain   = is_string( $domain ) ? $domain : '';

		if ( empty( $original ) ) {
			return new WP_Error( 'invalid_data', 'Missing original parameter', array( 'status' => 400 ) );
		}

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

		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_data', 'Invalid or missing JSON body', array( 'status' => 400 ) );
		}

		$original     = isset( $params['original'] ) ? trim( (string) $params['original'] ) : '';
		$domain       = isset( $params['domain'] ) ? sanitize_text_field( (string) $params['domain'] ) : '';
		$context      = isset( $params['context'] ) ? sanitize_text_field( (string) $params['context'] ) : '';
		$translations = isset( $params['translations'] ) && is_array( $params['translations'] ) ? $params['translations'] : [];

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

		foreach($translations as $lang_raw => $translated){

			$lang = sanitize_key( (string) $lang_raw );
			if ( '' === $lang ) {
				$results[] = [
					'language' => $lang_raw,
					'status'   => 'invalid_language'
				];
				continue;
			}

			$translated = trim( (string) $translated );

			$table = $wpdb->prefix . "trp_gettext_{$lang}";

			if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) != $table){
				$results[] = [
					'language' => $lang,
					'status'   => 'table_not_found'
				];
				continue;
			}

			// Check if exists
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

	public function dukkan_plugin_save_translatepress_settings($request){
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

		// Sanitize settings using TranslatePress internal method.
		if( ! method_exists( $settings_obj, 'sanitize_settings' ) ){
			return new WP_Error( 'tp_sanitize_missing', 'TranslatePress sanitize_settings() unavailable', array( 'status' => 500 ) );
		}

		$settings_new = $settings_obj->sanitize_settings( $params );

		if ( ! is_array( $settings_new ) ) {
			return new WP_Error( 'tp_sanitize_failed', 'Failed to sanitize settings', array( 'status' => 500 ) );
		}

		// Save settings
		update_option('trp_settings', $settings_new);

		return [
			'status' => 'success',
			'settings' => $settings_new
		];
	}

	public function dukkan_plugin_get_translatepress_settings() {

		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return new WP_Error( 'tp_missing', 'TranslatePress not active', array( 'status' => 400 ) );
		}

		$settings = get_option('trp_settings');

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

		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return new WP_Error( 'tp_missing', 'TranslatePress not active', array( 'status' => 400 ) );
		}

		global $wpdb;

		$original = $request->get_param( 'original' );
		$source_lang = sanitize_key( (string) $request->get_param( 'source_lang' ) );

		if(empty($original) || empty($source_lang)){
			return new WP_Error('invalid_data','Missing parameters',['status'=>400]);
		}

		// Canonicalize so punctuation (apostrophes, quotes, dashes) matches the
		// entity-encoded form TranslatePress stores in the dictionary.
		$original = $this->dukkan_plugin_canonicalize_original( $original );

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

		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_data', 'Invalid or missing JSON body', array( 'status' => 400 ) );
		}

		$original     = isset( $params['original'] ) ? (string) $params['original'] : '';
		$source_lang  = isset( $params['source_lang'] ) ? sanitize_key( (string) $params['source_lang'] ) : '';
		$translations = isset( $params['translations'] ) && is_array( $params['translations'] ) ? $params['translations'] : [];

		if(empty($original) || empty($translations) || empty($source_lang)){
			return new WP_Error('invalid_data','Missing data',['status'=>400]);
		}

		// Canonicalize so punctuation (apostrophes, quotes, dashes) matches the
		// entity-encoded form TranslatePress stores in the dictionary.
		$original = $this->dukkan_plugin_canonicalize_original( $original );

		$trp = TRP_Translate_Press::get_trp_instance();
		$trp_query = $trp->get_component('query');

		$results = [];
		foreach($translations as $target_lang_raw => $translated){

			$target_lang = sanitize_key( (string) $target_lang_raw );
			if ( '' === $target_lang ) {
				$results[] = [
					'language' => $target_lang_raw,
					'status' => 'invalid_language'
				];
				continue;
			}

			$table = $trp_query->get_table_name( $target_lang );

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) != $table ) {
				$results[] = [
					'language' => $target_lang,
					'status' => 'table_not_found'
				];
				continue;
			}

			$translated = trim( (string) $translated );

			// Find an existing dictionary row using TranslatePress's own query method.
			$existing_rows = $trp_query->get_string_ids( array( $original ), $target_lang );
			$row_id        = isset( $existing_rows[ $original ] ) ? (int) $existing_rows[ $original ]->id : 0;

			if ( ! $row_id ) {
				// Brand-new string: create the dictionary row (this also syncs the
				// original into the originals table with the correct original_id).
				$trp_query->insert_strings( array( $original ), $target_lang );
				$existing_rows = $trp_query->get_string_ids( array( $original ), $target_lang );
				$row_id        = isset( $existing_rows[ $original ] ) ? (int) $existing_rows[ $original ]->id : 0;
			}

			if ( ! $row_id ) {
				$results[] = [
					'language' => $target_lang,
					'status' => 'error'
				];
				continue;
			}

			// Upsert the translation through TranslatePress's own query method.
			$trp_query->update_strings(
				array(
					array(
						'id'         => $row_id,
						'original'   => $original,
						'translated' => $translated,
						'status'     => TRP_Query::HUMAN_REVIEWED,
						'block_type' => TRP_Query::BLOCK_TYPE_REGULAR_STRING,
					)
				),
				$target_lang,
				array( 'id', 'original', 'translated', 'status', 'block_type' )
			);

			$results[] = [
				'language' => $target_lang,
				'status' => 'saved'
			];
		}

		// Flush the object cache so any cached translation data (e.g. the
		// TranslatePress "trp" group) is refreshed. Regular translations are read
		// live from the DB, but this keeps derived caches consistent after a save.
		wp_cache_flush();

		return [
			'status' => 'success',
			'results' => $results
		];
	}

	/**
	 * Create or update a TranslatePress "translation block" (merged string).
	 *
	 * A translation block is the editor's "merge into one block" feature: several
	 * adjacent DOM strings are treated as a single unit. TranslatePress stores the
	 * merged text as one dictionary row with `block_type = 1` (ACTIVE), and the
	 * renderer replaces the whole parent element's inner HTML with that single
	 * original before looking up the translation.
	 *
	 * This endpoint exposes that same capability over REST so the mobile app can
	 * translate a multi-element description (e.g. a page builder that wraps each
	 * sentence in its own <span>/<div>) as one block instead of many strings.
	 *
	 * Request body:
	 *   {
	 *     "source_lang": "en_US",
	 *     "original": "Full merged text of the block",
	 *     "child_originals": ["sentence one", "sentence two"],  // optional
	 *     "translations": { "ar": "...", "fr": "..." }
	 *   }
	 *
	 * @since 1.0.36
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|array
	 */
	public function dukkan_plugin_save_translation_block( $request ) {
		global $wpdb;

		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return new WP_Error( 'tp_missing', 'TranslatePress not active', array( 'status' => 400 ) );
		}

		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_data', 'Invalid or missing JSON body', array( 'status' => 400 ) );
		}

		$original        = isset( $params['original'] ) ? (string) $params['original'] : '';
		$source_lang     = isset( $params['source_lang'] ) ? sanitize_key( (string) $params['source_lang'] ) : '';
		$child_originals = isset( $params['child_originals'] ) && is_array( $params['child_originals'] ) ? $params['child_originals'] : array();
		$translations    = isset( $params['translations'] ) && is_array( $params['translations'] ) ? $params['translations'] : array();

		if ( empty( $original ) || empty( $source_lang ) ) {
			return new WP_Error( 'invalid_data', 'Missing data', array( 'status' => 400 ) );
		}

		$trp       = TRP_Translate_Press::get_trp_instance();
		$trp_query = $trp->get_component( 'query' );

		$settings     = get_option( 'trp_settings' );
		$default_lang = isset( $settings['default-language'] ) ? $settings['default-language'] : $source_lang;

		// Canonicalize punctuation so it matches TranslatePress's stored entity form.
		$original = $this->dukkan_plugin_canonicalize_original( $original );

		$child_originals = array_map(
			array( $this, 'dukkan_plugin_canonicalize_original' ),
			array_map( 'strval', $child_originals )
		);

		$active_block_type     = TRP_Query::BLOCK_TYPE_ACTIVE;
		$deprecated_block_type = TRP_Query::BLOCK_TYPE_DEPRECATED;

		// Every non-default language configured in TranslatePress.
		$all_langs = isset( $settings['translation-languages'] ) && is_array( $settings['translation-languages'] )
			? $settings['translation-languages']
			: array();

		$target_langs = array();
		foreach ( $all_langs as $lang ) {
			$lang = sanitize_key( (string) $lang );
			if ( '' !== $lang && $lang !== $default_lang ) {
				$target_langs[] = $lang;
			}
		}

		$results = array();

		// 1. Upsert the block into every non-default language with block_type = ACTIVE,
		//    so the renderer can find and replace the block in whichever language is active.
		foreach ( $target_langs as $target_lang ) {
			$table = $trp_query->get_table_name( $target_lang );

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) != $table ) {
				$results[ $target_lang ] = array( 'status' => 'table_not_found' );
				continue;
			}

			$translated = isset( $translations[ $target_lang ] ) ? trim( (string) $translations[ $target_lang ] ) : '';

			// Ensure the merged original exists as a row in this language's dictionary.
			$existing = $trp_query->get_string_ids( array( $original ), $target_lang );
			$row_id   = isset( $existing[ $original ] ) ? (int) $existing[ $original ]->id : 0;

			if ( ! $row_id ) {
				$trp_query->insert_strings( array( $original ), $target_lang, $active_block_type );
				$existing = $trp_query->get_string_ids( array( $original ), $target_lang );
				$row_id   = isset( $existing[ $original ] ) ? (int) $existing[ $original ]->id : 0;
			}

			if ( ! $row_id ) {
				$results[ $target_lang ] = array( 'status' => 'error' );
				continue;
			}

			$trp_query->update_strings(
				array(
					array(
						'id'         => $row_id,
						'original'   => $original,
						'translated' => $translated,
						'status'     => ( '' !== $translated ) ? TRP_Query::HUMAN_REVIEWED : TRP_Query::NOT_TRANSLATED,
						'block_type' => $active_block_type,
					)
				),
				$target_lang,
				array( 'id', 'original', 'translated', 'status', 'block_type' )
			);

			$results[ $target_lang ] = array(
				'status'   => ( '' !== $translated ) ? 'saved' : 'block_created',
				'block_id' => $row_id,
			);
		}

		// 2. Mark the child strings as DEPRECATED so they no longer render individually
		//    (the parent block now owns their rendering).
		if ( ! empty( $child_originals ) ) {
			foreach ( $target_langs as $target_lang ) {
				$table = $trp_query->get_table_name( $target_lang );
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) != $table ) {
					continue;
				}

				$ids = $trp_query->get_string_ids( $child_originals, $target_lang );
				if ( empty( $ids ) ) {
					continue;
				}

				$update_rows = array();
				foreach ( $child_originals as $child ) {
					if ( isset( $ids[ $child ] ) ) {
						$update_rows[] = array(
							'id'         => (int) $ids[ $child ]->id,
							'block_type' => $deprecated_block_type,
						);
					}
				}

				if ( ! empty( $update_rows ) ) {
					// Only flip block_type (non-destructive: keeps the child's
					// translation intact in case the block is later split again).
					$trp_query->update_strings( $update_rows, $target_lang, array( 'id', 'block_type' ) );
				}
			}
		}

		// Flush caches so the block renders immediately.
		wp_cache_flush();

		return array(
			'status'   => 'success',
			'original' => $this->dukkan_plugin_trp_unformat_string( $original ),
			'results'  => $results,
		);
	}

}
