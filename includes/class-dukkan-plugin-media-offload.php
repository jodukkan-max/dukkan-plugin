<?php
/**
 * Dukkan Media Offload: keeps a store's media on Cloudflare R2 and serves it
 * from the store's CDN domain (cdn.<domain>).
 *
 * Drop-in replacement for the "Advanced Media Offloader" plugin as Dukkan uses
 * it. It deliberately keeps that plugin's storage contract so a store can
 * switch with no migration:
 *   - credentials: the five ADVMO_CLOUDFLARE_R2_* constants in wp-config.php
 *   - settings:    the `advmo_settings` option (retention_policy, path_prefix,
 *                  path_prefix_active, object_versioning, mirror_delete,
 *                  auto_offload_uploads)
 *   - per file:    the advmo_* post meta (advmo_offloaded, advmo_path,
 *                  advmo_bucket, advmo_provider, advmo_offloaded_at,
 *                  advmo_retention_policy, advmo_object_version)
 *
 * It stays idle while the old plugin is active, so the switch is simply:
 * deactivate Advanced Media Offloader. Kill switch: define
 * DUKKAN_MEDIA_OFFLOAD as false in wp-config.php.
 *
 * Delayed offload: a new upload stays on this server for a few seconds
 * (option `dukkan_media_offload_delay`, default 10, 0 = at once; wp-config
 * constant DUKKAN_MEDIA_OFFLOAD_DELAY overrides it). Meanwhile it is served
 * from this server. A detached background process (`wp dukkan-media run-due`)
 * sends it when due, so no PHP-FPM worker waits; WP-Cron is the safety net.
 *
 * Retention policy (same values as the old plugin):
 *   0 = keep local copies, 1 = delete local thumbnails only,
 *   2 = delete every local copy (what Dukkan stores use).
 *
 * @package Dukkan_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal Cloudflare R2 (S3 API) client: AWS Signature V4, path-style URLs,
 * PHP cURL. Streams uploads and downloads from/to disk.
 */
final class Dukkan_R2_Client {

	private $endpoint;
	private $host;
	private $bucket;
	private $key;
	private $secret;

	public function __construct( string $endpoint, string $bucket, string $key, string $secret ) {
		$this->endpoint = rtrim( $endpoint, '/' );
		$this->host     = (string) wp_parse_url( $this->endpoint, PHP_URL_HOST );
		$this->bucket   = $bucket;
		$this->key      = $key;
		$this->secret   = $secret;
	}

	/** Uploads a local file to $object_key. */
	public function put_file( string $file, string $object_key, string $content_type ): bool {
		$fh = @fopen( $file, 'rb' );
		if ( ! $fh ) {
			return false;
		}
		$size = filesize( $file );
		$res  = $this->request( 'PUT', $object_key, array(
			'headers' => array(
				'Content-Type'  => $content_type,
				'Cache-Control' => 'public, max-age=2592000',
			),
			'infile'  => $fh,
			'size'    => $size,
		) );
		fclose( $fh );
		return 200 === $res['status'];
	}

	/** HTTP status of a HEAD request: 200 exists, 404 missing, other = error. */
	public function head( string $object_key ): int {
		return $this->request( 'HEAD', $object_key )['status'];
	}

	public function delete( string $object_key ): bool {
		$s = $this->request( 'DELETE', $object_key )['status'];
		return 204 === $s || 200 === $s || 404 === $s;
	}

	/** Downloads $object_key to $dest atomically (temp file + rename). */
	public function get_to_file( string $object_key, string $dest ): bool {
		wp_mkdir_p( dirname( $dest ) );
		$tmp = $dest . '.dukkan-' . wp_generate_password( 8, false ) . '.part';
		$fh  = @fopen( $tmp, 'wb' );
		if ( ! $fh ) {
			return false;
		}
		$res = $this->request( 'GET', $object_key, array( 'outfile' => $fh ) );
		fclose( $fh );
		if ( 200 !== $res['status'] || 0 === (int) @filesize( $tmp ) ) {
			@unlink( $tmp );
			return false;
		}
		return @rename( $tmp, $dest );
	}

	private function canonical_uri( string $object_key ): string {
		$parts = array_map( 'rawurlencode', explode( '/', ltrim( $object_key, '/' ) ) );
		return '/' . rawurlencode( $this->bucket ) . '/' . implode( '/', $parts );
	}

	/** A signed cURL handle (not yet run). */
	private function handle( string $method, string $object_key, array $opt = array() ) {
		$uri          = $this->canonical_uri( $object_key );
		$amz_date     = gmdate( 'Ymd\THis\Z' );
		$date         = substr( $amz_date, 0, 8 );
		$payload_hash = 'PUT' === $method ? 'UNSIGNED-PAYLOAD' : hash( 'sha256', '' );
		$scope        = "$date/auto/s3/aws4_request";

		$canonical_headers = "host:{$this->host}\nx-amz-content-sha256:$payload_hash\nx-amz-date:$amz_date\n";
		$signed_headers    = 'host;x-amz-content-sha256;x-amz-date';
		$canonical_request = "$method\n$uri\n\n$canonical_headers\n$signed_headers\n$payload_hash";
		$string_to_sign    = "AWS4-HMAC-SHA256\n$amz_date\n$scope\n" . hash( 'sha256', $canonical_request );

		$k = hash_hmac( 'sha256', $date, 'AWS4' . $this->secret, true );
		$k = hash_hmac( 'sha256', 'auto', $k, true );
		$k = hash_hmac( 'sha256', 's3', $k, true );
		$k = hash_hmac( 'sha256', 'aws4_request', $k, true );
		$signature = hash_hmac( 'sha256', $string_to_sign, $k );

		$headers = array(
			'Authorization: AWS4-HMAC-SHA256 Credential=' . $this->key . "/$scope, SignedHeaders=$signed_headers, Signature=$signature",
			"x-amz-content-sha256: $payload_hash",
			"x-amz-date: $amz_date",
			'Expect:',
		);
		foreach ( ( $opt['headers'] ?? array() ) as $name => $value ) {
			$headers[] = "$name: $value";
		}

		$ch = curl_init( $this->endpoint . $uri );
		if ( 'HEAD' === $method ) {
			// NOBODY alone: a custom "HEAD" verb makes cURL wait for a body
			// of Content-Length bytes that never comes.
			curl_setopt( $ch, CURLOPT_NOBODY, true );
		} else {
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		}
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 'HEAD' === $method ? 20 : 300 );
		if ( isset( $opt['infile'] ) ) {
			curl_setopt( $ch, CURLOPT_UPLOAD, true );
			curl_setopt( $ch, CURLOPT_INFILE, $opt['infile'] );
			curl_setopt( $ch, CURLOPT_INFILESIZE, (int) $opt['size'] );
		}
		if ( isset( $opt['outfile'] ) ) {
			curl_setopt( $ch, CURLOPT_FILE, $opt['outfile'] );
		} else {
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		}
		return $ch;
	}

	/** Status of a finished handle; logs failures. */
	private function status( $ch, string $method, string $object_key, $body ): int {
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$error  = curl_error( $ch );
		if ( $error ) {
			error_log( "Dukkan media offload: R2 $method $object_key failed: $error" );
		} elseif ( $status >= 300 && 404 !== $status ) {
			error_log( "Dukkan media offload: R2 $method $object_key -> HTTP $status " . ( is_string( $body ) ? substr( $body, 0, 300 ) : '' ) );
		}
		return $status;
	}

	private function request( string $method, string $object_key, array $opt = array() ): array {
		if ( ! function_exists( 'curl_init' ) ) {
			return array( 'status' => 0 );
		}
		$ch     = $this->handle( $method, $object_key, $opt );
		$body   = curl_exec( $ch );
		$status = $this->status( $ch, $method, $object_key, $body );
		curl_close( $ch );
		return array( 'status' => $status );
	}

	/**
	 * Uploads several files at once (up to 8 in parallel).
	 *
	 * @param array $items list of [ local path, object key, content type ]
	 * @return bool true when every file was stored
	 */
	public function put_files( array $items ): bool {
		if ( ! function_exists( 'curl_multi_init' ) ) {
			foreach ( $items as $it ) {
				if ( ! $this->put_file( $it[0], $it[1], $it[2] ) ) {
					return false;
				}
			}
			return true;
		}
		$ok = true;
		foreach ( array_chunk( $items, 8 ) as $chunk ) {
			$mh      = curl_multi_init();
			$running = array();
			foreach ( $chunk as $it ) {
				$fh = @fopen( $it[0], 'rb' );
				if ( ! $fh ) {
					$ok = false;
					continue;
				}
				$ch = $this->handle( 'PUT', $it[1], array(
					'headers' => array( 'Content-Type' => $it[2], 'Cache-Control' => 'public, max-age=2592000' ),
					'infile'  => $fh,
					'size'    => filesize( $it[0] ),
				) );
				curl_multi_add_handle( $mh, $ch );
				$running[] = array( $ch, $fh, $it[1] );
			}
			do {
				$st = curl_multi_exec( $mh, $active );
				if ( $active ) {
					curl_multi_select( $mh, 1.0 );
				}
			} while ( $active && CURLM_OK === $st );
			foreach ( $running as list( $ch, $fh, $key ) ) {
				if ( 200 !== $this->status( $ch, 'PUT', $key, curl_multi_getcontent( $ch ) ) ) {
					$ok = false;
				}
				curl_multi_remove_handle( $mh, $ch );
				curl_close( $ch );
				fclose( $fh );
			}
			curl_multi_close( $mh );
			if ( ! $ok ) {
				break;
			}
		}
		return $ok;
	}
}

final class Dukkan_Media_Offload {

	const OLD_PLUGIN   = 'advanced-media-offloader/advanced-media-offloader.php';
	const PROVIDER     = 'Cloudflare R2';
	const ERROR_META   = 'dukkan_offload_error';
	const CACHE_FOLDER = 'dukkan-offload-cache';

	/** Delayed offload: seconds a new upload stays local first (option). */
	const DELAY_OPTION   = 'dukkan_media_offload_delay';
	const DEFAULT_DELAY  = 10;
	const DUE_META       = 'dukkan_offload_due';
	const ATTEMPTS_META  = 'dukkan_offload_attempts';
	const RUNNER_OPTION  = 'dukkan_media_offload_runner_at';
	const SWEEP_HOOK     = 'dukkan_media_offload_sweep';
	const MAX_ATTEMPTS   = 5;

	/** Latest due time queued in this request (0 = nothing queued). */
	private $queued_due = 0;

	/** @var Dukkan_Media_Offload|null */
	private static $instance = null;

	/** @var Dukkan_R2_Client */
	private $r2;
	private $domain;
	private $bucket;

	/** Local files already uploaded in this request (absolute path => true). */
	private $uploaded = array();

	/** Local files to delete when the request ends (absolute path => true). */
	private $cleanup = array();

	public static function boot(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_start' ), 1 );
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	/** Why the module is idle, or '' when it runs. */
	public static function idle_reason(): string {
		if ( defined( 'DUKKAN_MEDIA_OFFLOAD' ) && ! DUKKAN_MEDIA_OFFLOAD ) {
			return 'switched off by DUKKAN_MEDIA_OFFLOAD';
		}
		foreach ( array( 'KEY', 'SECRET', 'ENDPOINT', 'BUCKET', 'DOMAIN' ) as $c ) {
			if ( ! defined( "ADVMO_CLOUDFLARE_R2_$c" ) || '' === (string) constant( "ADVMO_CLOUDFLARE_R2_$c" ) ) {
				return "ADVMO_CLOUDFLARE_R2_$c is not set in wp-config.php";
			}
		}
		if ( in_array( self::OLD_PLUGIN, (array) get_option( 'active_plugins', array() ), true ) ) {
			return 'Advanced Media Offloader is still active';
		}
		return '';
	}

	public static function maybe_start(): void {
		if ( '' !== self::idle_reason() ) {
			return;
		}
		self::$instance = new self();
		self::$instance->register();
	}

	private function __construct() {
		$this->bucket = (string) ADVMO_CLOUDFLARE_R2_BUCKET;
		$this->domain = rtrim( (string) ADVMO_CLOUDFLARE_R2_DOMAIN, '/' );
		$this->r2     = new Dukkan_R2_Client(
			(string) ADVMO_CLOUDFLARE_R2_ENDPOINT,
			$this->bucket,
			(string) ADVMO_CLOUDFLARE_R2_KEY,
			(string) ADVMO_CLOUDFLARE_R2_SECRET
		);
	}

	private function register(): void {
		// Serving: point every offloaded file at the CDN domain.
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 10, 2 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_srcset' ), 10, 5 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'filter_srcset_meta' ), 1000, 4 );
		add_filter( 'wp_content_img_tag', array( $this, 'filter_content_img_tag' ), 10, 3 );
		add_filter( 'attachment_url_to_postid', array( $this, 'filter_url_to_postid' ), 10, 2 );

		// Uploading.
		add_filter( 'wp_unique_filename', array( $this, 'filter_unique_filename' ), 10, 3 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_generate_metadata' ), 99, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'on_update_metadata' ), 99, 2 );
		add_action( 'shutdown', array( $this, 'run_cleanup' ) );
		add_action( 'shutdown', array( $this, 'start_runner' ), 20 );
		add_action( self::SWEEP_HOOK, array( $this, 'run_due' ) );

		// Local copies that are gone: editing, SVG readers, missing sub-sizes.
		add_filter( 'load_image_to_edit_path', array( $this, 'filter_edit_path' ), 10, 3 );
		add_filter( 'get_attached_file', array( $this, 'filter_attached_file_svg' ), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'filter_missing_original' ), 10, 2 );

		// Deleting.
		add_action( 'delete_attachment', array( $this, 'on_delete_attachment' ), 10, 1 );
	}

	/* ------------------------------------------------------------------ */
	/* Settings and helpers                                               */
	/* ------------------------------------------------------------------ */

	private function settings(): array {
		$s = get_option( 'advmo_settings', array() );
		return is_array( $s ) ? $s : array();
	}

	public function retention(): int {
		return (int) ( $this->settings()['retention_policy'] ?? 0 );
	}

	/**
	 * Seconds a new upload stays on this server before it goes to R2.
	 * wp-config constant DUKKAN_MEDIA_OFFLOAD_DELAY wins over the option.
	 */
	public function delay(): int {
		if ( defined( 'DUKKAN_MEDIA_OFFLOAD_DELAY' ) ) {
			return max( 0, (int) DUKKAN_MEDIA_OFFLOAD_DELAY );
		}
		$v = get_option( self::DELAY_OPTION, null );
		return null === $v || '' === $v ? self::DEFAULT_DELAY : max( 0, (int) $v );
	}

	private function auto_offload(): bool {
		$s = $this->settings();
		return ! array_key_exists( 'auto_offload_uploads', $s ) || (bool) $s['auto_offload_uploads'];
	}

	private function path_prefix(): string {
		$s = $this->settings();
		if ( empty( $s['path_prefix_active'] ) || empty( $s['path_prefix'] ) ) {
			return '';
		}
		$p = trim( preg_replace( '#[^A-Za-z0-9/_\-.]#', '', str_replace( '\\', '/', (string) $s['path_prefix'] ) ), '/' );
		$p = preg_replace( '#/+#', '/', str_replace( '..', '', $p ) );
		return '' === $p ? '' : $p . '/';
	}

	public function is_offloaded( $id ): bool {
		return (bool) get_post_meta( (int) $id, 'advmo_offloaded', true );
	}

	/** Cloud folder of an offloaded attachment, e.g. "2026/11/" (may be ""). */
	private function cloud_dir( int $id ): string {
		return ltrim( (string) get_post_meta( $id, 'advmo_path', true ), '/' );
	}

	private function cdn_url( int $id, string $file_name ): string {
		return $this->domain . '/' . $this->cloud_dir( $id ) . $file_name;
	}

	/** Cloud folder for an attachment that is being offloaded now. */
	private function new_cloud_dir( int $id ): string {
		$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
		$dir = ltrim( dirname( $rel ), '/' );
		$dir = ( '.' === $dir || '' === $dir ) ? '' : trailingslashit( $dir );
		return $this->path_prefix() . $dir . $this->object_version( $id );
	}

	private function object_version( int $id ): string {
		$v = (string) get_post_meta( $id, 'advmo_object_version', true );
		if ( '' !== $v ) {
			return trailingslashit( $v );
		}
		if ( empty( $this->settings()['object_versioning'] ) ) {
			return '';
		}
		$v = get_option( 'uploads_use_yearmonth_folders' ) ? gmdate( 'dHis' ) : gmdate( 'YmdHis' );
		update_post_meta( $id, 'advmo_object_version', $v );
		return trailingslashit( $v );
	}

	private function content_type( string $file ): string {
		$t = wp_check_filetype( wp_basename( $file ) );
		return ! empty( $t['type'] ) ? $t['type'] : 'application/octet-stream';
	}

	/**
	 * Every file name of an attachment that lives next to its main file.
	 *
	 * @return array file name => true when it is the main/original (kept by retention 1)
	 */
	private function file_names( int $id, $metadata ): array {
		$main  = (string) get_attached_file( $id, true );
		$names = array();
		if ( '' !== $main ) {
			$names[ wp_basename( $main ) ] = true;
		}
		if ( is_array( $metadata ) ) {
			if ( ! empty( $metadata['original_image'] ) ) {
				$names[ wp_basename( $metadata['original_image'] ) ] = true;
			}
			foreach ( (array) ( $metadata['sources'] ?? array() ) as $src ) {
				if ( ! empty( $src['file'] ) ) {
					$names[ wp_basename( $src['file'] ) ] = $names[ wp_basename( $src['file'] ) ] ?? true;
				}
			}
			foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) {
				if ( ! is_array( $size ) ) {
					continue;
				}
				if ( ! empty( $size['file'] ) ) {
					$names[ wp_basename( $size['file'] ) ] = $names[ wp_basename( $size['file'] ) ] ?? false;
				}
				foreach ( (array) ( $size['sources'] ?? array() ) as $src ) {
					if ( ! empty( $src['file'] ) ) {
						$names[ wp_basename( $src['file'] ) ] = $names[ wp_basename( $src['file'] ) ] ?? false;
					}
				}
			}
		}
		$backups = get_post_meta( $id, '_wp_attachment_backup_sizes', true );
		if ( is_array( $backups ) ) {
			foreach ( $backups as $key => $b ) {
				if ( is_array( $b ) && ! empty( $b['file'] ) ) {
					$names[ wp_basename( $b['file'] ) ] = $names[ wp_basename( $b['file'] ) ] ?? ( 0 === strpos( (string) $key, 'full-' ) );
				}
			}
		}
		return $names;
	}

	/**
	 * Uploads the attachment's local files to $cloud_dir. Files already sent in
	 * this request are skipped; with $require_main the main file must exist.
	 *
	 * @return array|false uploaded absolute paths => is-main flag, or false on failure.
	 */
	private function upload_files( int $id, $metadata, string $cloud_dir, bool $require_main ) {
		$main = (string) get_attached_file( $id, true );
		if ( '' === $main ) {
			return false;
		}
		if ( $require_main && ! file_exists( $main ) ) {
			$this->log_error( $id, 'Local file missing, nothing to offload.' );
			return false;
		}
		$dir  = trailingslashit( dirname( $main ) );
		$done  = array();
		$items = array();
		foreach ( $this->file_names( $id, $metadata ) as $name => $is_main ) {
			$path = $dir . $name;
			if ( isset( $this->uploaded[ $path ] ) || ! is_file( $path ) ) {
				continue;
			}
			$items[]       = array( $path, $cloud_dir . $name, $this->content_type( $path ) );
			$done[ $path ] = $is_main;
		}
		if ( $items && ! $this->r2->put_files( $items ) ) {
			$this->log_error( $id, 'Upload to R2 failed; local files were kept.' );
			return false;
		}
		foreach ( array_keys( $done ) as $path ) {
			$this->uploaded[ $path ] = true;
		}
		delete_post_meta( $id, self::ERROR_META );
		return $done;
	}

	private function schedule_cleanup( array $uploaded ): void {
		$rule = $this->retention();
		foreach ( $uploaded as $path => $is_main ) {
			if ( 2 === $rule || ( 1 === $rule && ! $is_main ) ) {
				$this->cleanup[ $path ] = true;
			}
		}
	}

	private function mark_offloaded( int $id, string $cloud_dir ): void {
		update_post_meta( $id, 'advmo_path', $cloud_dir );
		update_post_meta( $id, 'advmo_provider', self::PROVIDER );
		update_post_meta( $id, 'advmo_bucket', $this->bucket );
		update_post_meta( $id, 'advmo_offloaded_at', time() );
		update_post_meta( $id, 'advmo_retention_policy', $this->retention() );
		update_post_meta( $id, 'advmo_offloaded', 1 );
	}

	private function log_error( int $id, string $message ): void {
		update_post_meta( $id, self::ERROR_META, $message );
		error_log( "Dukkan media offload: attachment $id: $message" );
	}

	/**
	 * Offloads one attachment now (used by uploads and by WP-CLI).
	 *
	 * @param bool $cleanup_now delete local copies immediately (CLI) instead of at shutdown.
	 * @param bool $rewrite     point saved local addresses (Elementor, post content) at the CDN.
	 */
	public function offload( int $id, $metadata = null, bool $cleanup_now = false, bool $rewrite = true ): bool {
		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $id, true );
		}
		$cloud_dir = $this->new_cloud_dir( $id );
		$uploaded  = $this->upload_files( $id, $metadata, $cloud_dir, true );
		if ( false === $uploaded ) {
			return false;
		}
		$this->mark_offloaded( $id, $cloud_dir );
		delete_post_meta( $id, self::DUE_META );
		if ( $rewrite ) {
			// Before the local copies go: pages that saved the local address
			// (Elementor image/background, post content) now use the CDN one.
			$this->rewrite_references( $id, array_keys( $this->file_names( $id, $metadata ) ), $cloud_dir );
			// Pages cached while the photo was local (product page, shop,
			// home, its categories) hold the local address: rebuild them.
			list( $posts, $paths ) = $this->pages_showing( $id );
			if ( $posts || $paths ) {
				$this->queue_purge( $posts, $paths, false );
			}
		}
		$this->schedule_cleanup( $uploaded );
		if ( $cleanup_now ) {
			$this->run_cleanup();
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Saved addresses                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Replaces the attachment's local addresses with its CDN addresses where
	 * pages store full URLs: Elementor data and page settings, and post
	 * content. Rebuilds Elementor CSS and clears the page cache for every page
	 * it changed.
	 *
	 * @return int number of posts changed
	 */
	public function rewrite_references( int $id, array $names, string $cloud_dir ): int {
		global $wpdb;
		if ( ! $names ) {
			return 0;
		}
		$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
		$sub = ltrim( dirname( $rel ), '/' );
		$sub = ( '.' === $sub || '' === $sub ) ? '' : $sub . '/';

		// Every way the local folder may have been written.
		$base  = trailingslashit( (string) wp_get_upload_dir()['baseurl'] ) . $sub;
		$host  = (string) wp_parse_url( $base, PHP_URL_HOST );
		$alt   = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : 'www.' . $host;
		$bases = array();
		foreach ( array( $base, str_replace( "//$host/", "//$alt/", $base ) ) as $b ) {
			$bases[] = set_url_scheme( $b, 'https' );
			$bases[] = set_url_scheme( $b, 'http' );
		}

		// Plain and JSON-escaped forms (Elementor data is JSON: "\/" and "\uXXXX").
		$plain = array();
		$json  = array();
		foreach ( $names as $name ) {
			$new = $this->domain . '/' . $cloud_dir . $name;
			foreach ( array_unique( $bases ) as $b ) {
				$plain[ $b . $name ] = $new;
				$json[ substr( wp_json_encode( $b . $name ), 1, -1 ) ] = substr( wp_json_encode( $new ), 1, -1 );
			}
		}

		// Narrow the search with the part all the file names share.
		$prefix = $names[0];
		foreach ( $names as $n ) {
			while ( '' !== $prefix && 0 !== strpos( $n, $prefix ) ) {
				$prefix = substr( $prefix, 0, -1 );
			}
		}
		$prefix = pathinfo( $prefix, PATHINFO_FILENAME );
		if ( strlen( $prefix ) < 3 ) {
			$prefix = pathinfo( $names[0], PATHINFO_FILENAME );
		}
		$like      = '%' . $wpdb->esc_like( $prefix ) . '%';
		$like_json = '%' . $wpdb->esc_like( substr( wp_json_encode( $prefix ), 1, -1 ) ) . '%';

		$changed = array();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			WHERE meta_key IN ('_elementor_data', '_elementor_page_settings') AND (meta_value LIKE %s OR meta_value LIKE %s)",
			$like,
			$like_json
		) );
		foreach ( (array) $rows as $row ) {
			if ( '_elementor_data' === $row->meta_key ) {
				$new = strtr( strtr( $row->meta_value, $json ), $plain );
				if ( $new !== $row->meta_value ) {
					$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_id' => $row->meta_id ) );
					$changed[ (int) $row->post_id ] = true;
				}
				continue;
			}
			$value = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $value ) ) {
				continue;
			}
			$before = $value;
			array_walk_recursive( $value, function ( &$v ) use ( $plain ) {
				if ( is_string( $v ) ) {
					$v = strtr( $v, $plain );
				}
			} );
			if ( $value !== $before ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $value ) ), array( 'meta_id' => $row->meta_id ) );
				$changed[ (int) $row->post_id ] = true;
			}
		}

		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_type NOT IN ('revision', 'attachment') AND post_content LIKE %s",
			$like
		) );
		foreach ( (array) $posts as $p ) {
			$new = strtr( $p->post_content, $plain );
			if ( $new !== $p->post_content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $p->ID ) );
				$changed[ (int) $p->ID ] = true;
			}
		}

		if ( $changed ) {
			$this->refresh_pages( array_keys( $changed ) );
		}
		return count( $changed );
	}

	/** Rebuilds Elementor CSS and clears cached copies of the changed pages. */
	private function refresh_pages( array $post_ids ): void {
		$kit  = (int) get_option( 'elementor_active_kit' );
		$wide = false;
		foreach ( $post_ids as $pid ) {
			wp_cache_delete( $pid, 'post_meta' );
			clean_post_cache( $pid );
			delete_post_meta( $pid, '_elementor_element_cache' );
			if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				\Elementor\Core\Files\CSS\Post::create( $pid )->delete();
			} else {
				delete_post_meta( $pid, '_elementor_css' );
			}
			// Header/footer templates and the site kit show on every page.
			if ( $pid === $kit || 'elementor_library' === get_post_type( $pid ) ) {
				$wide = true;
			}
		}
		$this->queue_purge( $post_ids, array(), $wide );
	}

	/**
	 * Pages whose cached copy shows the attachment: the product (or the
	 * variation's product) using it as image or in its gallery, the post it
	 * was uploaded to, and categories using it as their image.
	 *
	 * @return array{0:int[],1:string[]} post IDs, extra paths
	 */
	private function pages_showing( int $id ): array {
		global $wpdb;
		$posts  = array();
		$parent = (int) wp_get_post_parent_id( $id );
		if ( $parent ) {
			$posts[] = $parent;
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE (meta_key = '_thumbnail_id' AND meta_value = %s)
			OR (meta_key = '_product_image_gallery' AND FIND_IN_SET(%s, meta_value))",
			(string) $id,
			(string) $id
		) );
		foreach ( array_map( 'intval', $ids ) as $pid ) {
			$posts[] = 'product_variation' === get_post_type( $pid ) ? (int) wp_get_post_parent_id( $pid ) : $pid;
		}
		$paths = array();
		$terms = $wpdb->get_col( $wpdb->prepare(
			"SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id' AND meta_value = %s",
			(string) $id
		) );
		foreach ( array_map( 'intval', $terms ) as $tid ) {
			$term = get_term( $tid );
			$link = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : '';
			$path = is_string( $link ) ? wp_parse_url( $link, PHP_URL_PATH ) : '';
			if ( $path ) {
				$paths[] = $path;
			}
		}
		return array( array_values( array_unique( array_filter( $posts ) ) ), $paths );
	}

	/** Purges collected during this request; sent once at the end. */
	private $purge_posts = array();
	private $purge_paths = array();
	private $purge_wide  = false;
	private $purge_hooked = false;

	private function queue_purge( array $posts, array $paths, bool $wide ): void {
		foreach ( $posts as $p ) {
			$this->purge_posts[ (int) $p ] = true;
		}
		$this->purge_paths = array_merge( $this->purge_paths, $paths );
		$this->purge_wide  = $this->purge_wide || $wide;
		if ( ! $this->purge_hooked ) {
			$this->purge_hooked = true;
			// Before the cache plugin's own shutdown work (priority 1000),
			// which re-renders the pages we hand it.
			add_action( 'shutdown', array( $this, 'flush_purges' ), 5 );
		}
	}

	/**
	 * One purge for everything this request changed, through the stores'
	 * cache plugin (dukkan-auto-purge mu-plugin): page cache, Cloudflare
	 * edge, and an in-place re-render. Without that plugin, nothing to do.
	 */
	public function flush_purges(): void {
		$posts = array_keys( $this->purge_posts );
		$paths = array_values( array_unique( $this->purge_paths ) );
		$wide  = $this->purge_wide;
		$this->purge_posts = array();
		$this->purge_paths = array();
		$this->purge_wide  = false;
		if ( $wide && function_exists( 'dukkan_auto_purge_full_site' ) ) {
			dukkan_auto_purge_full_site();
			return;
		}
		if ( function_exists( 'dukkan_auto_purge_run' ) ) {
			foreach ( $posts as $pid ) {
				dukkan_auto_purge_run( $pid );
			}
		}
		if ( $paths && function_exists( 'dukkan_refresh_defer' ) ) {
			if ( function_exists( 'dukkan_purge_nginx_path' ) ) {
				foreach ( $paths as $p ) {
					dukkan_purge_nginx_path( $p );
				}
			}
			dukkan_refresh_defer( $paths );
			if ( function_exists( 'dukkan_purge_cloudflare_cache' ) && function_exists( 'dukkan_full_urls' ) ) {
				dukkan_purge_cloudflare_cache( dukkan_full_urls( $paths ) );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Upload hooks                                                       */
	/* ------------------------------------------------------------------ */

	public function on_generate_metadata( $metadata, $id ) {
		$id = (int) $id;
		if ( ! $this->auto_offload() || 'attachment' !== get_post_type( $id ) ) {
			return $metadata;
		}
		if ( $this->is_offloaded( $id ) ) {
			// Sub-sizes regenerated for an offloaded file: send the new ones.
			$uploaded = $this->upload_files( $id, $metadata, $this->cloud_dir( $id ), false );
			if ( is_array( $uploaded ) ) {
				$this->schedule_cleanup( $uploaded );
			}
			return $metadata;
		}
		$delay = $this->delay();
		if ( $delay > 0 ) {
			// Keep it local for now; the runner sends it when it is due.
			// Counted from when the upload arrived, not from when WordPress
			// finished making its thumbnails.
			$arrived = isset( $_SERVER['REQUEST_TIME'] ) ? min( time(), (int) $_SERVER['REQUEST_TIME'] ) : time();
			$this->queue( $id, $arrived + $delay );
			return $metadata;
		}
		// Sent during the upload itself: no page can hold its local address yet.
		$this->offload( $id, $metadata, false, false );
		return $metadata;
	}

	/* ------------------------------------------------------------------ */
	/* Delayed offload                                                    */
	/* ------------------------------------------------------------------ */

	private function queue( int $id, int $due ): void {
		update_post_meta( $id, self::DUE_META, $due );
		$this->queued_due = max( $this->queued_due, $due );
	}

	/**
	 * End of the request: start one background process that waits until the
	 * uploads are due and sends them. It runs outside PHP-FPM, so no web
	 * worker is held while waiting. A WP-Cron sweep is the safety net.
	 */
	public function start_runner(): void {
		if ( ! $this->queued_due ) {
			return;
		}
		$due = $this->queued_due;
		$this->queued_due = 0;
		wp_schedule_single_event( $due + 120, self::SWEEP_HOOK );

		// One runner already waiting for this time or later covers us too.
		$runner_at = (int) get_option( self::RUNNER_OPTION, 0 );
		if ( $runner_at >= $due && $runner_at > time() - 300 ) {
			return;
		}
		update_option( self::RUNNER_OPTION, $due, false );

		$wp = defined( 'DUKKAN_WP_CLI_BIN' ) ? DUKKAN_WP_CLI_BIN : '/usr/local/bin/wp';
		if ( ! function_exists( 'exec' ) || ! is_executable( $wp ) || ! is_executable( '/usr/bin/flock' ) ) {
			return; // WP-Cron sweep sends them instead.
		}
		// WP-CLI starts now (it takes a few seconds to load) and waits inside
		// until the uploads are due, so they leave on time.
		$lock = trailingslashit( get_temp_dir() ) . 'dukkan-offload-' . md5( ABSPATH ) . '.lock';
		$cmd  = sprintf(
			'HOME=/tmp /usr/bin/flock -w 600 %s %s --path=%s dukkan-media run-due --until=%d --quiet',
			escapeshellarg( $lock ),
			escapeshellarg( $wp ),
			escapeshellarg( untrailingslashit( ABSPATH ) ),
			$due
		);
		$setsid = is_executable( '/usr/bin/setsid' ) ? '/usr/bin/setsid ' : '';
		exec( $setsid . 'sh -c ' . escapeshellarg( $cmd ) . ' > /dev/null 2>&1 &' );
	}

	/**
	 * Sends every upload whose time has come. Keeps going while more uploads
	 * fall due within the next minute, so one runner covers a burst.
	 *
	 * @return array{0:int,1:int} sent, failed
	 */
	public function run_due(): array {
		global $wpdb;
		$sent   = 0;
		$failed = 0;
		$until  = time() + 300;
		while ( time() < $until ) {
			$now = time();
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) <= %d ORDER BY post_id LIMIT 50",
				self::DUE_META,
				$now
			) ) );
			foreach ( $ids as $id ) {
				// Claim it: only the process that deletes the row sends it.
				$claimed = $wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => self::DUE_META ) );
				wp_cache_delete( $id, 'post_meta' );
				if ( ! $claimed || 'attachment' !== get_post_type( $id ) || $this->is_offloaded( $id ) ) {
					continue;
				}
				clean_post_cache( $id );
				if ( $this->offload( $id, null, true ) ) {
					delete_post_meta( $id, self::ATTEMPTS_META );
					$sent++;
					continue;
				}
				$failed++;
				$attempts = (int) get_post_meta( $id, self::ATTEMPTS_META, true ) + 1;
				update_post_meta( $id, self::ATTEMPTS_META, $attempts );
				if ( $attempts < self::MAX_ATTEMPTS ) {
					// Retry later (2, 4, 8, 16 minutes); the WP-Cron sweep picks it up.
					$retry = time() + 60 * ( 2 ** $attempts );
					update_post_meta( $id, self::DUE_META, $retry );
					wp_schedule_single_event( $retry + 60, self::SWEEP_HOOK );
				}
			}
			if ( count( $ids ) >= 50 ) {
				continue;
			}
			$next = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT MIN(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::DUE_META
			) );
			if ( ! $next || $next > time() + 60 ) {
				break;
			}
			sleep( max( 1, $next - time() ) );
		}
		return array( $sent, $failed );
	}

	/** Image edits (crop/rotate/scale) write new files for an offloaded attachment. */
	public function on_update_metadata( $metadata, $id ) {
		$id = (int) $id;
		if ( ! $this->is_offloaded( $id ) ) {
			return $metadata;
		}
		$uploaded = $this->upload_files( $id, $metadata, $this->cloud_dir( $id ), false );
		if ( is_array( $uploaded ) ) {
			$this->schedule_cleanup( $uploaded );
		}
		return $metadata;
	}

	public function run_cleanup(): void {
		$uploads = wp_get_upload_dir();
		$base    = trailingslashit( wp_normalize_path( (string) $uploads['basedir'] ) );
		foreach ( array_keys( $this->cleanup ) as $path ) {
			if ( 0 === strpos( wp_normalize_path( $path ), $base ) && is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$this->cleanup = array();
	}

	/**
	 * With every local copy deleted, WordPress cannot see name clashes, so the
	 * bucket is checked instead (only when local copies are deleted).
	 */
	public function filter_unique_filename( $filename, $ext, $dir ) {
		if ( 2 !== $this->retention() ) {
			return $filename;
		}
		$uploads = wp_get_upload_dir();
		$base    = trailingslashit( wp_normalize_path( (string) $uploads['basedir'] ) );
		$d       = trailingslashit( wp_normalize_path( (string) $dir ) );
		if ( 0 !== strpos( $d, $base ) ) {
			return $filename;
		}
		$cloud = $this->path_prefix() . substr( $d, strlen( $base ) );
		$name  = pathinfo( $filename, PATHINFO_FILENAME );
		$extn  = pathinfo( $filename, PATHINFO_EXTENSION );
		$extn  = '' === $extn ? '' : '.' . $extn;
		$try   = $filename;
		for ( $i = 1; $i <= 10; $i++ ) {
			$status = $this->r2->head( $cloud . $try );
			if ( 404 === $status ) {
				return $try;
			}
			if ( 200 !== $status ) {
				// R2 not reachable: pick a name that cannot clash.
				return $name . '-' . substr( md5( uniqid( '', true ) ), 0, 8 ) . $extn;
			}
			$try = $name . '-' . $i . $extn;
		}
		return $name . '-' . time() . $extn;
	}

	/* ------------------------------------------------------------------ */
	/* Serving                                                            */
	/* ------------------------------------------------------------------ */

	public function filter_attachment_url( $url, $id ) {
		if ( ! is_string( $url ) || ! $this->is_offloaded( $id ) ) {
			return $url;
		}
		return $this->cdn_url( (int) $id, wp_basename( $url ) );
	}

	public function filter_srcset( $sources, $size_array, $image_src, $image_meta, $id ) {
		if ( ! is_array( $sources ) || ! $this->is_offloaded( $id ) ) {
			return $sources;
		}
		foreach ( $sources as $w => $source ) {
			if ( isset( $source['url'] ) ) {
				$sources[ $w ]['url'] = $this->cdn_url( (int) $id, wp_basename( $source['url'] ) );
			}
		}
		return $sources;
	}

	public function filter_srcset_meta( $image_meta, $size_array, $image_src, $id ) {
		if ( ! is_array( $image_meta ) || ! $this->is_offloaded( $id ) ) {
			return $image_meta;
		}
		$version = (string) get_post_meta( (int) $id, 'advmo_object_version', true );
		if ( '' === $version || empty( $image_meta['sizes'] ) || ! is_array( $image_meta['sizes'] ) ) {
			return $image_meta;
		}
		foreach ( $image_meta['sizes'] as $k => $size ) {
			if ( is_array( $size ) && isset( $size['file'] ) ) {
				$image_meta['sizes'][ $k ]['file'] = trailingslashit( $version ) . $size['file'];
			}
		}
		return $image_meta;
	}

	public function filter_content_img_tag( $tag, $context, $id ) {
		if ( ! $id || ! $this->is_offloaded( $id ) || ! preg_match( '/src=[\'"]?([^\'" >]+)/i', $tag, $m ) ) {
			return $tag;
		}
		$src = $m[1];
		if ( 0 === strpos( $src, $this->domain ) || preg_match( '#^(data|blob):#i', $src ) ) {
			return $tag;
		}
		return str_replace( $src, $this->cdn_url( (int) $id, wp_basename( $src ) ), $tag );
	}

	public function filter_url_to_postid( $post_id, $url ) {
		if ( $post_id || ! is_string( $url ) ) {
			return $post_id;
		}
		$domain = preg_replace( '#^https?:#i', '', $this->domain ) . '/';
		$url    = preg_replace( '#^https?:#i', '', $url );
		if ( 0 !== stripos( $url, $domain ) ) {
			return $post_id;
		}
		$key  = substr( $url, strlen( $domain ) );
		$path = false !== strpos( $key, '/' ) ? dirname( $key ) . '/' : '';
		$file = wp_basename( $key );
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.post_id FROM {$wpdb->postmeta} p
			JOIN {$wpdb->postmeta} f ON f.post_id = p.post_id AND f.meta_key = '_wp_attached_file'
			WHERE p.meta_key = 'advmo_path' AND p.meta_value IN (%s, %s)
			AND (f.meta_value = %s OR f.meta_value LIKE %s) LIMIT 2",
			$path,
			'/' . $path,
			$file,
			'%/' . $wpdb->esc_like( $file )
		) );
		return 1 === count( $ids ) ? (int) $ids[0] : $post_id;
	}

	/* ------------------------------------------------------------------ */
	/* Files that only exist on R2                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * The image editor needs the file on disk: fetch it from R2 next to where
	 * it used to be, and delete it again at the end of the request.
	 */
	public function filter_edit_path( $path, $id, $size ) {
		$id = (int) $id;
		if ( ! $this->is_offloaded( $id ) || ( is_string( $path ) && is_file( $path ) ) ) {
			return $path;
		}
		$main = (string) get_attached_file( $id, true );
		if ( '' === $main ) {
			return $path;
		}
		$name = wp_basename( is_string( $path ) && '' !== $path ? wp_parse_url( $path, PHP_URL_PATH ) : $main );
		$dest = trailingslashit( dirname( $main ) ) . $name;
		if ( ! is_file( $dest ) && ! $this->r2->get_to_file( $this->cloud_dir( $id ) . $name, $dest ) ) {
			return $path;
		}
		if ( 0 !== $this->retention() ) {
			$this->cleanup[ $dest ] = true;
		}
		return $dest;
	}

	/** SVG readers (Elementor inline icons, sanitizers) open the file itself. */
	public function filter_attached_file_svg( $file, $id ) {
		if ( ! is_string( $file ) || '' === $file || is_file( $file ) ) {
			return $file;
		}
		$id = (int) $id;
		if ( 'image/svg+xml' !== get_post_mime_type( $id ) || ! $this->is_offloaded( $id ) ) {
			return $file;
		}
		$uploads = wp_get_upload_dir();
		$cached  = trailingslashit( $uploads['basedir'] ) . self::CACHE_FOLDER . '/' . $id . '/' . wp_basename( $file );
		if ( is_file( $cached ) || $this->r2->get_to_file( $this->cloud_dir( $id ) . wp_basename( $file ), $cached ) ) {
			return $cached;
		}
		return $file;
	}

	/** Keeps WordPress from re-making sub-sizes from an original that is on R2 only. */
	public function filter_missing_original( $data, $id ) {
		if ( ! is_array( $data ) || empty( $data['original_image'] ) || ! $this->is_offloaded( $id ) ) {
			return $data;
		}
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ); // phpcs:ignore
		foreach ( $trace as $frame ) {
			if ( 'wp_get_missing_image_subsizes' === ( $frame['function'] ?? '' ) && empty( $frame['class'] ) ) {
				$original = path_join( dirname( (string) get_attached_file( (int) $id, true ) ), $data['original_image'] );
				if ( ! file_exists( $original ) ) {
					unset( $data['original_image'] );
				}
				break;
			}
		}
		return $data;
	}

	/* ------------------------------------------------------------------ */
	/* Deleting                                                           */
	/* ------------------------------------------------------------------ */

	public function on_delete_attachment( $id ): void {
		$id = (int) $id;
		if ( ! $this->is_offloaded( $id ) ) {
			return;
		}
		$uploads = wp_get_upload_dir();
		$cache   = trailingslashit( $uploads['basedir'] ) . self::CACHE_FOLDER . '/' . $id;
		if ( is_dir( $cache ) ) {
			array_map( 'wp_delete_file', (array) glob( $cache . '/*' ) );
			@rmdir( $cache );
		}
		// Same rule as the old plugin: the cloud copy is removed only when
		// "mirror_delete" is on (it is off on every Dukkan store).
		if ( empty( $this->settings()['mirror_delete'] ) ) {
			return;
		}
		$dir = $this->cloud_dir( $id );
		foreach ( array_keys( $this->file_names( $id, wp_get_attachment_metadata( $id, true ) ) ) as $name ) {
			$this->r2->delete( $dir . $name );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Status (WP-CLI)                                                    */
	/* ------------------------------------------------------------------ */

	public function r2(): Dukkan_R2_Client {
		return $this->r2;
	}

	public function names_of( int $id ): array {
		return array_keys( $this->file_names( $id, wp_get_attachment_metadata( $id, true ) ) );
	}

	public function cloud_dir_of( int $id ): string {
		return $this->cloud_dir( $id );
	}

	public function cloud_key( int $id ): string {
		return $this->cloud_dir( $id ) . wp_basename( (string) get_attached_file( $id, true ) );
	}

	public function domain(): string {
		return $this->domain;
	}

	public function bucket(): string {
		return $this->bucket;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Dukkan media offload (Cloudflare R2).
	 */
	class Dukkan_Media_Offload_CLI {

		private function module(): Dukkan_Media_Offload {
			$m = Dukkan_Media_Offload::instance();
			if ( ! $m ) {
				WP_CLI::error( 'Media offload is idle: ' . Dukkan_Media_Offload::idle_reason() );
			}
			return $m;
		}

		/**
		 * Shows whether offloading runs, the R2 connection and the counts.
		 *
		 * ## EXAMPLES
		 *     wp dukkan-media status
		 */
		public function status() {
			$reason = Dukkan_Media_Offload::idle_reason();
			WP_CLI::log( 'Running: ' . ( '' === $reason ? 'yes' : 'no (' . $reason . ')' ) );
			global $wpdb;
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
			$off   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = 'advmo_offloaded' AND meta_value <> '' AND meta_value <> '0'" );
			$err   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s", Dukkan_Media_Offload::ERROR_META ) );
			WP_CLI::log( "Attachments: $total | on R2: $off | not on R2: " . ( $total - $off ) . " | with errors: $err" );
			$m = Dukkan_Media_Offload::instance();
			if ( $m ) {
				$probe = $m->r2()->head( 'dukkan-offload-probe-' . wp_generate_password( 10, false ) );
				WP_CLI::log( 'Bucket: ' . $m->bucket() . ' | CDN: ' . $m->domain() . ' | retention: ' . $m->retention() );
				$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", Dukkan_Media_Offload::DUE_META ) );
				WP_CLI::log( 'Delay: ' . $m->delay() . ' s' . ( defined( 'DUKKAN_MEDIA_OFFLOAD_DELAY' ) ? ' (set in wp-config.php)' : '' ) . " | waiting to be sent: $pending" );
				WP_CLI::log( 'R2 connection: ' . ( 404 === $probe ? 'OK' : "FAILED (HTTP $probe)" ) );
			}
		}

		/**
		 * Sends media that is not on R2 yet.
		 *
		 * ## OPTIONS
		 *
		 * [<id>...]
		 * : Attachment IDs. Without IDs, use --all.
		 *
		 * [--all]
		 * : Every attachment not on R2 yet.
		 *
		 * [--limit=<n>]
		 * : Stop after this many.
		 *
		 * [--dry-run]
		 * : Only list what would be sent.
		 *
		 * ## EXAMPLES
		 *     wp dukkan-media offload --all --limit=50
		 */
		public function offload( $args, $assoc ) {
			$m     = $this->module();
			$limit = isset( $assoc['limit'] ) ? max( 1, (int) $assoc['limit'] ) : PHP_INT_MAX;
			$ids   = array_map( 'intval', $args );
			if ( ! $ids ) {
				if ( empty( $assoc['all'] ) ) {
					WP_CLI::error( 'Give attachment IDs or --all.' );
				}
				global $wpdb;
				$ids = array_map( 'intval', $wpdb->get_col(
					"SELECT p.ID FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'advmo_offloaded'
					WHERE p.post_type = 'attachment' AND (m.meta_value IS NULL OR m.meta_value = '' OR m.meta_value = '0')
					ORDER BY p.ID"
				) );
			}
			$ok = 0;
			$failed = 0;
			$skipped = 0;
			foreach ( $ids as $id ) {
				if ( $ok + $failed >= $limit ) {
					break;
				}
				if ( $m->is_offloaded( $id ) || 'attachment' !== get_post_type( $id ) ) {
					$skipped++;
					continue;
				}
				$file = (string) get_attached_file( $id, true );
				if ( ! is_file( $file ) ) {
					WP_CLI::warning( "#$id: local file missing ($file), skipped" );
					$skipped++;
					continue;
				}
				if ( ! empty( $assoc['dry-run'] ) ) {
					WP_CLI::log( "#$id would be sent: " . wp_basename( $file ) );
					$ok++;
					continue;
				}
				if ( $m->offload( $id, null, true ) ) {
					WP_CLI::log( "#$id sent: " . wp_get_attachment_url( $id ) );
					$ok++;
				} else {
					WP_CLI::warning( "#$id failed: " . get_post_meta( $id, Dukkan_Media_Offload::ERROR_META, true ) );
					$failed++;
				}
			}
			WP_CLI::success( "sent $ok, failed $failed, skipped $skipped" );
		}

		/**
		 * Points saved local addresses of media already on R2 at the CDN
		 * (Elementor data, page settings, post content), then rebuilds the
		 * Elementor CSS and clears the cache of the pages it changed.
		 *
		 * ## OPTIONS
		 *
		 * [<id>...]
		 * : Attachment IDs. Without IDs, use --all.
		 *
		 * [--all]
		 * : Every attachment on R2.
		 *
		 * ## EXAMPLES
		 *     wp dukkan-media fix-links --all
		 *
		 * @subcommand fix-links
		 */
		public function fix_links( $args, $assoc ) {
			$m   = $this->module();
			$ids = array_map( 'intval', $args );
			if ( ! $ids ) {
				if ( empty( $assoc['all'] ) ) {
					WP_CLI::error( 'Give attachment IDs or --all.' );
				}
				global $wpdb;
				$ids = array_map( 'intval', $wpdb->get_col(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'advmo_offloaded' AND meta_value <> '' AND meta_value <> '0' ORDER BY post_id"
				) );
			}
			$posts = 0;
			$media = 0;
			foreach ( $ids as $id ) {
				if ( ! $m->is_offloaded( $id ) ) {
					continue;
				}
				$n = $m->rewrite_references( $id, $m->names_of( $id ), $m->cloud_dir_of( $id ) );
				if ( $n ) {
					$media++;
					$posts += $n;
					WP_CLI::log( "#$id: $n page(s) updated" );
				}
			}
			WP_CLI::success( count( $ids ) . " checked, $media had local addresses, $posts page updates" );
		}

		/**
		 * Shows or sets how many seconds a new upload stays local before it
		 * goes to R2 (default 10; 0 = at once).
		 *
		 * ## OPTIONS
		 *
		 * [<seconds>]
		 * : New delay in seconds (0-3600).
		 *
		 * ## EXAMPLES
		 *     wp dukkan-media delay
		 *     wp dukkan-media delay 10
		 */
		public function delay( $args ) {
			if ( ! isset( $args[0] ) ) {
				$m = Dukkan_Media_Offload::instance();
				$v = $m ? $m->delay() : (int) get_option( Dukkan_Media_Offload::DELAY_OPTION, Dukkan_Media_Offload::DEFAULT_DELAY );
				WP_CLI::log( "Delay: $v s" );
				return;
			}
			if ( ! is_numeric( $args[0] ) || (int) $args[0] < 0 || (int) $args[0] > 3600 ) {
				WP_CLI::error( 'Give a number of seconds from 0 to 3600.' );
			}
			update_option( Dukkan_Media_Offload::DELAY_OPTION, (int) $args[0], false );
			if ( defined( 'DUKKAN_MEDIA_OFFLOAD_DELAY' ) ) {
				WP_CLI::warning( 'DUKKAN_MEDIA_OFFLOAD_DELAY in wp-config.php overrides this option.' );
			}
			WP_CLI::success( 'Delay set to ' . (int) $args[0] . ' s' . ( 0 === (int) $args[0] ? ' (offload at once)' : '' ) );
		}

		/**
		 * Sends uploads whose delay has passed (started automatically).
		 *
		 * ## OPTIONS
		 *
		 * [--until=<timestamp>]
		 * : Wait until this Unix time before sending.
		 *
		 * [--quiet]
		 * : No output.
		 *
		 * @subcommand run-due
		 */
		public function run_due( $args, $assoc ) {
			$m = Dukkan_Media_Offload::instance();
			if ( ! $m ) {
				return;
			}
			$until = isset( $assoc['until'] ) ? (int) $assoc['until'] : 0;
			if ( $until > time() ) {
				sleep( min( 3600, $until - time() ) );
			}
			delete_option( Dukkan_Media_Offload::RUNNER_OPTION );
			wp_cache_delete( 'alloptions', 'options' );
			[ $sent, $failed ] = $m->run_due();
			if ( empty( $assoc['quiet'] ) ) {
				WP_CLI::success( "sent $sent, failed $failed" );
			}
		}

		/**
		 * Checks that offloaded files really are on R2 (HEAD on the main file).
		 *
		 * ## OPTIONS
		 *
		 * [--limit=<n>]
		 * : Check at most this many (newest first). Default 100.
		 *
		 * ## EXAMPLES
		 *     wp dukkan-media verify --limit=500
		 */
		public function verify( $args, $assoc ) {
			$m     = $this->module();
			$limit = isset( $assoc['limit'] ) ? max( 1, (int) $assoc['limit'] ) : 100;
			global $wpdb;
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'advmo_offloaded' AND meta_value <> '' AND meta_value <> '0' ORDER BY post_id DESC LIMIT %d",
				$limit
			) ) );
			$missing = 0;
			foreach ( $ids as $id ) {
				$status = $m->r2()->head( $m->cloud_key( $id ) );
				if ( 200 !== $status ) {
					$missing++;
					WP_CLI::warning( "#$id: " . $m->cloud_key( $id ) . " -> HTTP $status" );
				}
			}
			WP_CLI::success( count( $ids ) . " checked, $missing missing" );
		}
	}
	WP_CLI::add_command( 'dukkan-media', 'Dukkan_Media_Offload_CLI' );
}
