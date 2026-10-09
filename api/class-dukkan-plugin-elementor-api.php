<?php

/**
 * Elementor widget settings REST API.
 *
 * Elementor has no REST API for widget settings, so the mobile website
 * builder reads and writes them through these routes. The control schema
 * comes from Elementor itself (`Widget_Base::get_controls()`), which includes
 * theme injections such as Rey's "Special Styles", so labels, options,
 * defaults and conditions always match the installed versions.
 *
 * Only the Content and Style tabs are exposed. Writes are validated against
 * the widget's own controls and saved through Elementor's document API, which
 * stores a revision and regenerates the page CSS.
 *
 * Routes (namespace `dukkan-elementor/v1`, WooCommerce-key authenticated):
 *   GET   /pages                                    Pages built with Elementor
 *   GET   /pages/{id}                               A page's element tree (read-only)
 *   GET   /widgets/heading/controls                 Content + Style schema
 *   GET   /pages/{id}/headings                      Headings on a page
 *   GET   /pages/{id}/headings/{element_id}         One heading's settings
 *   PATCH /pages/{id}/headings/{element_id}         Update settings (null resets)
 *
 * @link       https://dukkanjo.com
 * @since      1.0.50
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_Elementor_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'dukkan-elementor/v1';

	/**
	 * Exposed widgets: route slug => Elementor widget type.
	 */
	const WIDGETS = array(
		'headings'     => 'heading',
		'text-editors' => 'text-editor',
		'images'       => 'image',
		'buttons'      => 'button',
		'rey-buttons'  => 'reycore-button-skew',
		'icon-boxes'   => 'icon-box',
		'rey-marquees' => 'reycore-marquee',
		'rey-logos'    => 'reycore-header-logo',
		'rey-menus'    => 'reycore-menu',
		'rey-navs'     => 'reycore-header-navigation',
		'rey-searches' => 'reycore-header-search',
		'rey-carts'    => 'reycore-header-cart',
		'rey-accounts' => 'reycore-header-account',
		'rey-scrollers' => 'reycore-text-scroller',
		'containers'   => 'container',
		'rey-sliders'  => 'reycore-basic-slider',
		'rey-grids'    => 'reycore-product-grid',
		'rey-carousels' => 'reycore-carousel',
	);

	/**
	 * Tabs whose settings can be read and written. Containers put their
	 * Content controls in the `layout` tab (not `content`).
	 */
	const TABS = array( 'content', 'style', 'advanced', 'layout' );

	/**
	 * Control types that only structure the panel and hold no value.
	 */
	const UI_ONLY_TYPES = array( 'section', 'tabs', 'tab', 'heading', 'divider', 'raw_html', 'alert', 'notice', 'deprecated_notice', 'button' );

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST routes. Skipped when Elementor isn't active.
	 */
	public function register_routes() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		register_rest_route( self::NAMESPACE, '/pages', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_pages' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_page' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( self::NAMESPACE, '/sections', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_sections' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/elements', array(
			'methods'             => 'PUT, POST',
			'callback'            => array( $this, 'replace_elements' ),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
			'args'                => array(
				'elements' => array(
					'required' => true,
					'type'     => 'array',
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/catalog', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_catalog' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'type' => array(
					'required' => false,
					'type'     => 'string',
					'enum'     => array( 'categories', 'tags', 'attributes', 'attribute_terms', 'loop_skins', 'pages', 'menus', 'menu_items' ),
				),
				'attribute_id' => array(
					'required' => false,
					'type'     => 'integer',
				),
				'attribute' => array(
					'required' => false,
					'type'     => 'string',
				),
				'menu' => array(
					'required' => false,
					'type'     => 'string',
				),
				'page' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 1,
				),
				'per_page' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 100,
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/globals', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_globals' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( self::NAMESPACE, '/site-logo', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_site_logo' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		foreach ( self::WIDGETS as $slug => $widget_type ) {
			register_rest_route( self::NAMESPACE, '/widgets/' . $widget_type . '/controls', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) use ( $widget_type ) {
					return $this->get_controls_schema( $request, $widget_type );
				},
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'tab' => array(
						'required' => false,
						'type'     => 'string',
						'enum'     => self::TABS,
					),
				),
			) );

			register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/' . $slug, array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) use ( $widget_type ) {
					return $this->list_widgets( $request, $widget_type );
				},
				'permission_callback' => array( $this, 'check_permissions' ),
			) );

			register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/' . $slug . '/(?P<element_id>[a-zA-Z0-9]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( WP_REST_Request $request ) use ( $widget_type ) {
						return $this->get_widget( $request, $widget_type );
					},
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => 'PATCH, PUT, POST',
					'callback'            => function ( WP_REST_Request $request ) use ( $widget_type ) {
						return $this->update_widget( $request, $widget_type );
					},
					'permission_callback' => array( $this, 'check_edit_permissions' ),
					'args'                => array(
						'settings' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			) );
		}
	}

	/**
	 * Permission callback — requires WooCommerce API read access.
	 *
	 * @param WP_REST_Request $request
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
	 * Permission callback for writes — WooCommerce edit access plus the
	 * right to edit this specific post.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_edit_permissions( WP_REST_Request $request ) {
		if ( ! wc_rest_check_manager_permissions( 'settings', 'edit' ) || ! current_user_can( 'edit_post', (int) $request['id'] ) ) {
			return new WP_Error(
				'woocommerce_rest_cannot_edit',
				__( 'Sorry, you cannot edit this resource.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * GET /pages — pages built with Elementor, front page first.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function list_pages( WP_REST_Request $request ) {
		$posts = get_posts( array(
			'post_type'   => 'page',
			'post_status' => array( 'publish', 'draft', 'private', 'future' ),
			'numberposts' => 200,
			'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'meta_key'    => '_elementor_edit_mode',
			'meta_value'  => 'builder',
		) );

		$front = (int) get_option( 'page_on_front' );
		$items = array();
		foreach ( $posts as $post ) {
			$items[] = $this->format_page( $post, $front );
		}
		usort( $items, function ( $a, $b ) {
			return (int) $b['is_front_page'] - (int) $a['is_front_page'];
		} );
		return rest_ensure_response( $items );
	}

	/**
	 * GET /sections — every Elementor-built template that isn't a regular page:
	 * headers, footers, global sections, etc. Each item can be opened with the
	 * same `GET /pages/{id}` + widget routes (its `_elementor_data` tree).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function list_sections( WP_REST_Request $request ) {
		$items = array();

		// Rey global sections (header, footer, cover, generic, megamenu, card).
		if ( post_type_exists( 'rey-global-sections' ) ) {
			$posts = get_posts( array(
				'post_type'   => 'rey-global-sections',
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			) );
			foreach ( $posts as $post ) {
				$items[] = $this->format_section( $post, 'gs', (string) get_post_meta( $post->ID, 'gs_type', true ) );
			}
		}

		// Elementor templates (elementor_library) that aren't the imported kit.
		if ( post_type_exists( 'elementor_library' ) ) {
			$posts = get_posts( array(
				'post_type'   => 'elementor_library',
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			) );
			foreach ( $posts as $post ) {
				$template_type = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
				if ( 'kit' === $template_type ) {
					continue; // the imported kit isn't an editable section.
				}
				$items[] = $this->format_section( $post, 'template', $template_type );
			}
		}

		return rest_ensure_response( $items );
	}

	/**
	 * @param WP_Post $post
	 * @param string  $kind  `gs` (Rey global section) or `template` (Elementor library).
	 * @param string  $subtype
	 * @return array
	 */
	private function format_section( WP_Post $post, $kind, $subtype ) {
		return array(
			'id'       => $post->ID,
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status'   => $post->post_status,
			'kind'     => $kind,
			'subtype'  => $subtype,
			'modified' => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * GET /catalog[?type=categories|tags|attributes|attribute_terms|loop_skins]
	 *
	 * Lists WooCommerce taxonomy terms / attributes and Rey loop skins, so the
	 * app can populate its `rey-query`/`rey-ajax-list` pickers with the real
	 * store data. `attribute_terms` requires `attribute_id`.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_catalog( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		if ( empty( $type ) ) {
			$type = 'categories';
		}
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );

		switch ( $type ) {
			case 'tags':
				$terms = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false, 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) );
				break;
			case 'attributes':
				$terms = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
				break;
			case 'attribute_terms':
				$attribute_slug = $request->get_param( 'attribute' );
				$attribute_id   = (int) $request->get_param( 'attribute_id' );
				$taxonomy       = '';
				if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
					foreach ( wc_get_attribute_taxonomies() as $a ) {
						$tax = wc_attribute_taxonomy_name( $a->attribute_name );
						if ( ( $attribute_slug && $tax === $attribute_slug ) || ( ! $attribute_slug && (int) $a->attribute_id === $attribute_id ) ) {
							$taxonomy = $tax;
							break;
						}
					}
				}
				$terms = $taxonomy ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) ) : array();
				break;
			case 'loop_skins':
				$skins = array();
				if ( class_exists( '\ReyCore\Plugin' ) && \ReyCore\Plugin::instance()->woocommerce_loop ) {
					$skins = \ReyCore\Plugin::instance()->woocommerce_loop->get_skins_list();
				}
				return rest_ensure_response( array_map( function ( $k, $v ) {
					return array( 'id' => $k, 'label' => $v );
				}, array_keys( $skins ), array_values( $skins ) ) );
			case 'pages':
				$posts = get_posts( array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'numberposts' => $per_page,
					'offset'      => ( $page - 1 ) * $per_page,
					'orderby'     => 'title',
					'order'       => 'ASC',
				) );
				return rest_ensure_response( array_values( array_map( function ( $p ) {
					return array( 'id' => (int) $p->ID, 'label' => $p->post_title );
				}, $posts ) ) );
			case 'menus':
				$menus = wp_get_nav_menus();
				if ( is_wp_error( $menus ) ) {
					return rest_ensure_response( array() );
				}
				// Rey's menu picker stores the menu SLUG (not the term id), so
				// `id` here is the slug to match `get_nav_menus_options`.
				return rest_ensure_response( array_values( array_map( function ( $m ) {
					return array( 'id' => $m->slug, 'term_id' => (int) $m->term_id, 'label' => $m->name );
				}, $menus ) ) );
			case 'menu_items':
				// Accepts a numeric term id OR a menu slug/name.
				$menu  = $request->get_param( 'menu' );
				$items = $menu ? wp_get_nav_menu_items( $menu ) : array();
				if ( is_wp_error( $items ) || ! is_array( $items ) ) {
					return rest_ensure_response( array() );
				}
				return rest_ensure_response( array_values( array_map( function ( $i ) {
					return array(
						'id'     => (int) $i->ID,
						'title'  => html_entity_decode( $i->title, ENT_QUOTES, 'UTF-8' ),
						'parent' => (int) $i->menu_item_parent,
						'url'    => $i->url,
					);
				}, $items ) ) );
			case 'categories':
			default:
				$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) );
				break;
		}

		if ( is_wp_error( $terms ) ) {
			return rest_ensure_response( array() );
		}

		if ( 'attributes' === $type ) {
			return rest_ensure_response( array_values( array_map( function ( $a ) {
				$slug = function_exists( 'wc_attribute_taxonomy_name' ) ? wc_attribute_taxonomy_name( $a->attribute_name ) : $a->attribute_name;
				return array( 'id' => isset( $a->attribute_id ) ? (int) $a->attribute_id : 0, 'label' => $a->attribute_label, 'slug' => $slug );
			}, $terms ) ) );
		}

		return rest_ensure_response( array_values( array_map( function ( $t ) {
			$image = '';
			$thumb = get_term_meta( $t->term_id, 'thumbnail_id', true );
			if ( $thumb && function_exists( 'wp_get_attachment_image_url' ) ) {
				$image = (string) wp_get_attachment_image_url( $thumb, 'woocommerce_thumbnail' );
			}
			return array( 'id' => (int) $t->term_id, 'label' => $t->name, 'image' => $image );
		}, $terms ) ) );
	}

	/**
	 * GET /globals — Elementor kit global colors + typography (fonts).
	 *
	 * Reads the active kit's `system_colors`/`custom_colors` and
	 * `system_typography`/`custom_typography` so the app can show (and let the
	 * merchant reference) the site's global styles. Read-only.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_globals( WP_REST_Request $request ) {
		$settings = array();
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->kits_manager ) {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
			if ( $kit ) {
				$settings = $kit->get_settings();
			}
		}

		$colors = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $group ) {
			if ( empty( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
				continue;
			}
			foreach ( $settings[ $group ] as $c ) {
				if ( empty( $c['title'] ) || empty( $c['color'] ) ) {
					continue;
				}
				$colors[] = array(
					'id'    => isset( $c['_id'] ) ? $c['_id'] : '',
					'title' => $c['title'],
					'color' => $c['color'],
					'system' => 'system_colors' === $group,
				);
			}
		}

		$typography = array();
		foreach ( array( 'system_typography', 'custom_typography' ) as $group ) {
			if ( empty( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
				continue;
			}
			foreach ( $settings[ $group ] as $t ) {
				if ( empty( $t['title'] ) ) {
					continue;
				}
				$size = '';
				if ( isset( $t['typography_font_size'] ) && is_array( $t['typography_font_size'] ) && ! empty( $t['typography_font_size']['size'] ) ) {
					$size = $t['typography_font_size']['size'] . ( isset( $t['typography_font_size']['unit'] ) ? $t['typography_font_size']['unit'] : '' );
				}
				$typography[] = array(
					'id'     => isset( $t['_id'] ) ? $t['_id'] : '',
					'title'  => $t['title'],
					'family' => isset( $t['typography_font_family'] ) ? $t['typography_font_family'] : '',
					'weight' => isset( $t['typography_font_weight'] ) ? $t['typography_font_weight'] : '',
					'size'   => $size,
					'system' => 'system_typography' === $group,
				);
			}
		}

		return rest_ensure_response( array(
			'colors'     => $colors,
			'typography' => $typography,
		) );
	}

	/**
	 * GET /site-logo — the site's global header logo (Customizer > Header > Logo),
	 * used by the `reycore-header-logo` widget when it doesn't override the global
	 * settings. Mirrors `rey__header_logo_params()` (themes/rey/inc/tags/header.php):
	 * the image is the `custom_logo` attachment, the mobile image is `logo_mobile`.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_site_logo( WP_REST_Request $request ) {
		$custom_logo_id = get_theme_mod( 'custom_logo', '' );
		$logo_mobile_id = get_theme_mod( 'logo_mobile', '' );

		$logo_url        = $custom_logo_id ? wp_get_attachment_image_url( (int) $custom_logo_id, 'full' ) : '';
		$logo_mobile_url = $logo_mobile_id ? wp_get_attachment_image_url( (int) $logo_mobile_id, 'full' ) : '';

		return rest_ensure_response( array(
			'blog_name'        => get_bloginfo( 'name', 'display' ),
			'blog_description' => get_bloginfo( 'description', 'display' ),
			'logo'             => $logo_url ? $logo_url : '',
			'logo_mobile'      => $logo_mobile_url ? $logo_mobile_url : '',
		) );
	}

	/**
	 * GET /pages/{id} — the page's full Elementor element tree (read-only).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_page( WP_REST_Request $request ) {
		$post_id  = (int) $request['id'];
		$document = $this->get_document( $post_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$page             = $this->format_page( get_post( $post_id ), (int) get_option( 'page_on_front' ) );
		$page['elements'] = $document->get_elements_data();
		return rest_ensure_response( $page );
	}

	/**
	 * @param WP_Post $post
	 * @param int     $front_page_id
	 * @return array
	 */
	private function format_page( WP_Post $post, $front_page_id ) {
		return array(
			'id'            => $post->ID,
			'title'         => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status'        => $post->post_status,
			'link'          => get_permalink( $post ),
			'is_front_page' => $post->ID === $front_page_id,
			'modified'      => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * PUT|POST /pages/{id}/elements — replace the page's whole element tree.
	 *
	 * Used for structure edits (add / remove / move / duplicate sections and
	 * widgets). The app sends the full `_elementor_data` tree it loaded
	 * (losslessly round-tripped), so this must preserve every element field.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function replace_elements( WP_REST_Request $request ) {
		$post_id  = (int) $request['id'];
		$document = $this->get_document( $post_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$elements = $request->get_param( 'elements' );
		$errors   = $this->validate_elements( $elements );
		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'dukkan_elementor_invalid_elements',
				__( 'The element structure is invalid. Nothing was saved.', 'dukkan-plugin' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		$saved = $document->save( array( 'elements' => $elements ) );
		if ( ! $saved ) {
			return new WP_Error( 'dukkan_elementor_save_failed', __( 'Elementor could not save the page.', 'dukkan-plugin' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success'  => true,
			'elements' => count( $elements ),
		) );
	}

	/**
	 * Structural validation of an element tree. Returns a map of element id →
	 * reason, or an empty array when valid. Empty is allowed (clearing a page).
	 *
	 * @param mixed $elements
	 * @return array
	 */
	private function validate_elements( $elements ) {
		$errors = array();
		$seen   = array();
		$walk   = function ( $list, $path ) use ( &$walk, &$errors, &$seen ) {
			if ( ! is_array( $list ) ) {
				$errors[ $path ] = 'expected a list of elements';
				return;
			}
			foreach ( $list as $index => $element ) {
				$where = $path . '[' . $index . ']';
				if ( ! is_array( $element ) ) {
					$errors[ $where ] = 'element must be an object';
					continue;
				}
				$el_type = isset( $element['elType'] ) ? $element['elType'] : '';
				if ( ! in_array( $el_type, array( 'container', 'section', 'column', 'widget' ), true ) ) {
					$errors[ $where ] = 'invalid elType "' . $el_type . '"';
					continue;
				}
				$id = isset( $element['id'] ) ? (string) $element['id'] : '';
				if ( '' === $id || ! preg_match( '/^[a-zA-Z0-9]+$/', $id ) ) {
					$errors[ $where ] = 'missing or invalid id';
				} elseif ( isset( $seen[ $id ] ) ) {
					$errors[ $where ] = 'duplicate id "' . $id . '"';
				} else {
					$seen[ $id ] = true;
				}
				if ( 'widget' === $el_type && ( ! isset( $element['widgetType'] ) || '' === (string) $element['widgetType'] ) ) {
					$errors[ $where ] = 'widget is missing widgetType';
				}
				if ( isset( $element['settings'] ) && ! is_array( $element['settings'] ) ) {
					$errors[ $where ] = 'settings must be an object';
				}
				if ( isset( $element['elements'] ) ) {
					$walk( $element['elements'], $where . '.elements' );
				}
			}
		};
		$walk( $elements, 'elements' );
		return $errors;
	}

	/**
	 * GET /widgets/{type}/controls — Content + Style control schema.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $widget_type
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_controls_schema( WP_REST_Request $request, $widget_type ) {
		$controls = $this->get_widget_controls( $widget_type );
		if ( is_wp_error( $controls ) ) {
			return $controls;
		}

		$only_tab = $request->get_param( 'tab' );
		$sections = array();
		$items    = array();

		foreach ( $controls as $key => $control ) {
			$tab = isset( $control['tab'] ) ? $control['tab'] : '';
			if ( ! in_array( $tab, self::TABS, true ) || ( $only_tab && $only_tab !== $tab ) ) {
				continue;
			}
			if ( 'section' === $control['type'] ) {
				$sections[] = array(
					'key'   => $key,
					'label' => isset( $control['label'] ) ? $this->plain_label( $control['label'] ) : $key,
					'tab'   => $tab,
				);
				continue;
			}
			$items[] = $this->describe_control( $key, $control );
		}

		return rest_ensure_response( array(
			'widget'            => $widget_type,
			'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
			'sections'          => $sections,
			'controls'          => $items,
		) );
	}

	/**
	 * GET /pages/{id}/{widgets} — every widget of this type on the page.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $widget_type
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_widgets( WP_REST_Request $request, $widget_type ) {
		$document = $this->get_document( (int) $request['id'] );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$controls = $this->get_widget_controls( $widget_type );
		if ( is_wp_error( $controls ) ) {
			return $controls;
		}

		$found = array();
		$this->collect_widgets( $document->get_elements_data(), $widget_type, $found );

		$items = array();
		foreach ( $found as $element ) {
			$items[] = $this->format_widget( $element, $controls );
		}
		return rest_ensure_response( $items );
	}

	/**
	 * GET /pages/{id}/{widgets}/{element_id} — one widget's settings.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $widget_type
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_widget( WP_REST_Request $request, $widget_type ) {
		$document = $this->get_document( (int) $request['id'] );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$controls = $this->get_widget_controls( $widget_type );
		if ( is_wp_error( $controls ) ) {
			return $controls;
		}

		$elements = $document->get_elements_data();
		$element  = $this->find_widget( $elements, (string) $request['element_id'], $widget_type );
		if ( null === $element ) {
			return $this->not_found( $widget_type );
		}
		return rest_ensure_response( $this->format_widget( $element, $controls ) );
	}

	/**
	 * PATCH /pages/{id}/{widgets}/{element_id} — merge settings.
	 *
	 * Body: `{ "settings": { "title": "Hi", "title_color": "#111111", "align_mobile": null } }`.
	 * A `null` value removes the key so Elementor falls back to its default.
	 * The whole request is rejected if any key is unknown, outside the
	 * Content/Style tabs, or has an invalid value.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $widget_type
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_widget( WP_REST_Request $request, $widget_type ) {
		$post_id  = (int) $request['id'];
		$document = $this->get_document( $post_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$controls = $this->get_widget_controls( $widget_type );
		if ( is_wp_error( $controls ) ) {
			return $controls;
		}

		$incoming = $request->get_param( 'settings' );
		if ( ! is_array( $incoming ) || empty( $incoming ) ) {
			return new WP_Error( 'dukkan_elementor_empty_settings', __( 'Provide at least one setting.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$clean  = array();
		$errors = array();
		foreach ( $incoming as $key => $value ) {
			$key = (string) $key;
			if ( '__dynamic__' === $key || '__globals__' === $key ) {
				// Elementor special maps (dynamic tags / global style links):
				// control_key => value. `null` clears the whole map; otherwise
				// it must be an object.
				if ( null === $value ) {
					$clean[ $key ] = null;
				} elseif ( is_array( $value ) ) {
					$clean[ $key ] = $value;
				} else {
					$errors[ $key ] = 'must_be_an_object';
				}
				continue;
			}
			if ( ! isset( $controls[ $key ] ) ) {
				$errors[ $key ] = 'unknown_setting';
				continue;
			}
			$control = $controls[ $key ];
			if ( ! in_array( isset( $control['tab'] ) ? $control['tab'] : '', self::TABS, true ) ) {
				$errors[ $key ] = 'not_editable_in_content_or_style';
				continue;
			}
			if ( in_array( $control['type'], self::UI_ONLY_TYPES, true ) ) {
				$errors[ $key ] = 'not_a_value_control';
				continue;
			}
			if ( null === $value ) {
				$clean[ $key ] = null;
				continue;
			}
			$sanitized = $this->sanitize_value( $value, $control );
			if ( is_wp_error( $sanitized ) ) {
				$errors[ $key ] = $sanitized->get_error_message();
				continue;
			}
			$clean[ $key ] = $sanitized;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'dukkan_elementor_invalid_settings',
				__( 'Some settings were rejected. Nothing was saved.', 'dukkan-plugin' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		$elements = $document->get_elements_data();
		$updated  = null;
		$changed  = $this->apply_settings( $elements, (string) $request['element_id'], $widget_type, $clean, $controls, $updated );
		if ( ! $changed ) {
			return $this->not_found( $widget_type );
		}

		$saved = $document->save( array( 'elements' => $elements ) );
		if ( ! $saved ) {
			return new WP_Error( 'dukkan_elementor_save_failed', __( 'Elementor could not save the page.', 'dukkan-plugin' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $this->format_widget( $updated, $controls ) );
	}

	// -------------------------------------------------------------------------
	// Elementor helpers
	// -------------------------------------------------------------------------

	/**
	 * The widget's full control stack (all tabs, incl. responsive variants).
	 *
	 * @param string $widget_type
	 * @return array|WP_Error
	 */
	private function get_widget_controls( $widget_type ) {
		if ( 'container' === $widget_type ) {
			$element = \Elementor\Plugin::$instance->elements_manager->get_element_types( 'container' );
			if ( ! $element ) {
				return new WP_Error( 'dukkan_elementor_unknown_widget', __( 'The Container element is not available on the site.', 'dukkan-plugin' ), array( 'status' => 404 ) );
			}
			return $element->get_controls();
		}
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		if ( ! $widget ) {
			return new WP_Error( 'dukkan_elementor_unknown_widget', __( 'This Elementor widget is not available on the site.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}
		return $widget->get_controls();
	}

	/**
	 * Elementor document for a post built with Elementor.
	 *
	 * @param int $post_id
	 * @return \Elementor\Core\Base\Document|WP_Error
	 */
	private function get_document( $post_id ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $document || ! $document->is_built_with_elementor() ) {
			return new WP_Error( 'dukkan_elementor_not_elementor_page', __( 'This page is not built with Elementor.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}
		return $document;
	}

	/**
	 * @param string $widget_type
	 * @return WP_Error
	 */
	private function not_found( $widget_type ) {
		return new WP_Error(
			'dukkan_elementor_widget_not_found',
			/* translators: %s: Elementor widget type. */
			sprintf( __( 'No %s widget with this ID on the page.', 'dukkan-plugin' ), $widget_type ),
			array( 'status' => 404 )
		);
	}

	/**
	 * @param array  $elements
	 * @param string $widget_type
	 * @param array  $found
	 */
	private function collect_widgets( array $elements, $widget_type, array &$found ) {
		foreach ( $elements as $element ) {
			$is_container = 'container' === $widget_type && isset( $element['elType'] ) && 'container' === $element['elType'];
			$is_widget    = isset( $element['elType'], $element['widgetType'] ) && 'widget' === $element['elType'] && $widget_type === $element['widgetType'];
			if ( $is_container || $is_widget ) {
				$found[] = $element;
			}
			if ( ! empty( $element['elements'] ) ) {
				$this->collect_widgets( $element['elements'], $widget_type, $found );
			}
		}
	}

	/**
	 * @param array  $elements
	 * @param string $element_id
	 * @param string $widget_type
	 * @return array|null
	 */
	private function find_widget( array $elements, $element_id, $widget_type ) {
		$found = array();
		$this->collect_widgets( $elements, $widget_type, $found );
		foreach ( $found as $element ) {
			if ( isset( $element['id'] ) && $element_id === $element['id'] ) {
				return $element;
			}
		}
		return null;
	}

	/**
	 * Per-device keys of a responsive control (`align` → `align_tablet`,
	 * `align_mobile`, … for the site's active breakpoints).
	 *
	 * @param string $key
	 * @param array  $controls
	 * @return string[]
	 */
	private function device_variants( $key, array $controls ) {
		static $devices = null;
		if ( null === $devices ) {
			$devices = array( 'tablet', 'mobile' );
			$breakpoints = isset( \Elementor\Plugin::$instance->breakpoints ) ? \Elementor\Plugin::$instance->breakpoints : null;
			if ( $breakpoints && method_exists( $breakpoints, 'get_active_breakpoints' ) ) {
				$devices = array_keys( $breakpoints->get_active_breakpoints() );
			}
		}
		$variants = array();
		foreach ( $devices as $device ) {
			$variant = $key . '_' . $device;
			if ( isset( $controls[ $variant ] ) && ! empty( $controls[ $variant ]['responsive'] ) ) {
				$variants[] = $variant;
			}
		}
		return $variants;
	}

	/**
	 * Merge [$clean] into the matching widget inside [$elements] (by reference).
	 *
	 * Writing a value also drops the matching `__globals__` link (and the group
	 * toggle's link for typography/background groups); otherwise Elementor keeps
	 * rendering the global kit value instead of the explicit one.
	 *
	 * Writing (or resetting) a base key applies it to all devices: its tablet/
	 * mobile/… overrides are removed unless the same request sets them.
	 *
	 * @param array  $elements
	 * @param string $element_id
	 * @param string $widget_type
	 * @param array  $clean
	 * @param array  $controls
	 * @param array  $updated Receives the updated element.
	 * @return bool
	 */
	private function apply_settings( array &$elements, $element_id, $widget_type, array $clean, array $controls, &$updated ) {
		foreach ( $elements as &$element ) {
			$is_target = 'container' === $widget_type
				? ( isset( $element['id'], $element['elType'] ) && $element_id === $element['id'] && 'container' === $element['elType'] )
				: ( isset( $element['id'], $element['elType'], $element['widgetType'] ) && $element_id === $element['id'] && 'widget' === $element['elType'] && $widget_type === $element['widgetType'] );

			if ( $is_target ) {
				$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
				$globals  = isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ? $settings['__globals__'] : array();

				foreach ( $clean as $key => $value ) {
					if ( '__dynamic__' === $key || '__globals__' === $key ) {
						continue; // special maps, merged below.
					}
					foreach ( $this->device_variants( $key, $controls ) as $variant ) {
						if ( ! array_key_exists( $variant, $clean ) ) {
							unset( $settings[ $variant ], $globals[ $variant ] );
						}
					}
					if ( null === $value ) {
						unset( $settings[ $key ] );
					} else {
						$settings[ $key ] = $value;
						unset( $globals[ $key ] );
						if ( ! empty( $controls[ $key ]['groupPrefix'] ) && ! empty( $controls[ $key ]['groupType'] ) ) {
							unset( $globals[ $controls[ $key ]['groupPrefix'] . $controls[ $key ]['groupType'] ] );
						}
					}
				}

				// Merge the special maps (`__dynamic__`, `__globals__`) when present.
				foreach ( array( '__dynamic__', '__globals__' ) as $map_key ) {
					if ( ! array_key_exists( $map_key, $clean ) ) {
						continue;
					}
					if ( null === $clean[ $map_key ] ) {
						unset( $settings[ $map_key ] );
					} elseif ( is_array( $clean[ $map_key ] ) ) {
						if ( '__globals__' === $map_key ) {
							$globals = array_merge( $globals, $clean[ $map_key ] );
						} else {
							$settings[ $map_key ] = $clean[ $map_key ];
						}
					}
				}

				if ( empty( $globals ) ) {
					unset( $settings['__globals__'] );
				} else {
					$settings['__globals__'] = $globals;
				}
				$element['settings'] = $settings;
				$updated             = $element;
				return true;
			}

			if ( ! empty( $element['elements'] ) && $this->apply_settings( $element['elements'], $element_id, $widget_type, $clean, $controls, $updated ) ) {
				return true;
			}
		}
		unset( $element );
		return false;
	}

	/**
	 * Response shape for one widget: only Content/Style settings, plus any
	 * global (kit) links on those keys.
	 *
	 * @param array $element
	 * @param array $controls
	 * @return array
	 */
	private function format_widget( array $element, array $controls ) {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$out      = array();
		$globals  = array();

		foreach ( $settings as $key => $value ) {
			if ( '__globals__' === $key ) {
				continue;
			}
			if ( '__dynamic__' === $key && is_array( $value ) ) {
				$out[ $key ] = $value;
				continue;
			}
			if ( isset( $controls[ $key ]['tab'] ) && in_array( $controls[ $key ]['tab'], self::TABS, true ) ) {
				$out[ $key ] = $value;
			}
		}
		if ( ! empty( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) {
			foreach ( $settings['__globals__'] as $key => $ref ) {
				if ( '' !== $ref && isset( $controls[ $key ]['tab'] ) && in_array( $controls[ $key ]['tab'], self::TABS, true ) ) {
					$globals[ $key ] = $ref;
				}
			}
		}

		return array(
			'id'          => $element['id'],
			'widget_type' => isset( $element['widgetType'] ) ? $element['widgetType'] : ( isset( $element['elType'] ) ? $element['elType'] : '' ),
			'settings'    => (object) $out,
			'globals'     => (object) $globals,
		);
	}

	/**
	 * Schema entry for one control.
	 *
	 * @param string $key
	 * @param array  $control
	 * @return array
	 */
	private function describe_control( $key, array $control ) {
		$item = array(
			'key'     => $key,
			'type'    => $control['type'],
			'label'   => isset( $control['label'] ) ? $this->plain_label( $control['label'] ) : '',
			'tab'     => $control['tab'],
			'section' => isset( $control['section'] ) ? $control['section'] : '',
			'ui_only' => in_array( $control['type'], self::UI_ONLY_TYPES, true ),
		);

		if ( 'raw_html' === $control['type'] && isset( $control['raw'] ) ) {
			$item['text'] = wp_strip_all_tags( $control['raw'] );
		}
		if ( array_key_exists( 'default', $control ) ) {
			$item['default'] = $control['default'];
		}
		if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
			$options = array();
			foreach ( $control['options'] as $value => $option ) {
				$options[ (string) $value ] = is_array( $option ) ? ( isset( $option['title'] ) ? $option['title'] : (string) $value ) : wp_strip_all_tags( (string) $option );
			}
			$item['options'] = (object) $options;
		}
		foreach ( array( 'condition', 'conditions', 'size_units', 'range', 'return_value', 'min', 'max', 'step', 'placeholder', 'description' ) as $field ) {
			if ( isset( $control[ $field ] ) && '' !== $control[ $field ] ) {
				$item[ $field ] = $control[ $field ];
			}
		}
		if ( ! empty( $control['responsive']['max'] ) || ! empty( $control['responsive']['min'] ) ) {
			$item['device'] = ! empty( $control['responsive']['max'] ) ? $control['responsive']['max'] : $control['responsive']['min'];
		}
		if ( ! empty( $control['groupType'] ) ) {
			$item['group'] = $control['groupType'];
		}
		if ( 'popover_toggle' === $control['type'] ) {
			$item['group_toggle'] = true;
		}
		if ( 'font' === $control['type'] && class_exists( '\Elementor\Fonts' ) ) {
			$item['options'] = (object) array_combine( array_keys( \Elementor\Fonts::get_fonts() ), array_keys( \Elementor\Fonts::get_fonts() ) );
		}
		return $item;
	}

	/**
	 * Label text without inline badges (e.g. Rey's `<span class="rey-badge">rey</span>`).
	 *
	 * @param string $label
	 * @return string
	 */
	private function plain_label( $label ) {
		$label = preg_replace( '#<(span|sup|small|i|em)\b[^>]*>.*?</\1>#is', '', (string) $label );
		return trim( wp_strip_all_tags( $label ) );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate and normalize a value for its Elementor control type.
	 *
	 * @param mixed $value
	 * @param array $control
	 * @return mixed|WP_Error
	 */
	private function sanitize_value( $value, array $control ) {
		$type = $control['type'];

		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'wysiwyg':
				if ( ! is_scalar( $value ) ) {
					return $this->invalid( 'expected a string' );
				}
				return wp_kses_post( (string) $value );

			case 'number':
				if ( '' === $value ) {
					return '';
				}
				if ( ! is_numeric( $value ) ) {
					return $this->invalid( 'expected a number' );
				}
				$number = 0 + $value;
				if ( isset( $control['min'] ) && '' !== $control['min'] && $number < $control['min'] ) {
					return $this->invalid( 'below minimum ' . $control['min'] );
				}
				if ( isset( $control['max'] ) && '' !== $control['max'] && $number > $control['max'] ) {
					return $this->invalid( 'above maximum ' . $control['max'] );
				}
				return $number;

			case 'select':
			case 'choose':
				if ( ! is_scalar( $value ) ) {
					return $this->invalid( 'expected a string' );
				}
				$value = (string) $value;
				if ( '' === $value || empty( $control['options'] ) ) {
					return sanitize_text_field( $value );
				}
				if ( ! array_key_exists( $value, $control['options'] ) ) {
					return $this->invalid( 'not one of: ' . implode( ', ', array_map( 'strval', array_keys( $control['options'] ) ) ) );
				}
				return $value;

			case 'switcher':
			case 'popover_toggle':
				$on = isset( $control['return_value'] ) ? (string) $control['return_value'] : 'yes';
				if ( true === $value ) {
					return $on;
				}
				if ( false === $value || '' === $value ) {
					return '';
				}
				if ( (string) $value !== $on ) {
					return $this->invalid( "expected '' or '{$on}'" );
				}
				return $on;

			case 'color':
				return $this->sanitize_color( $value );

			case 'font':
				// Not checked against Elementor's list: sites that turn off
				// Elementor's Google Fonts (Rey loads its own) only list system fonts.
				if ( ! is_string( $value ) || ! preg_match( '/^[\p{L}\p{N} \-_\']{0,80}$/u', $value ) ) {
					return $this->invalid( 'expected a font family name' );
				}
				return $value;

			case 'animation':
			case 'hover_animation':
				// An animate.css-style class slug (e.g. `fadeInUp`, `grow`); empty = none.
				if ( ! is_string( $value ) ) {
					return $this->invalid( 'expected an animation name' );
				}
				if ( '' === $value ) {
					return '';
				}
				if ( ! preg_match( '/^[a-zA-Z0-9_-]{0,80}$/', $value ) ) {
					return $this->invalid( 'invalid animation name' );
				}
				return $value;

			case 'slider':
				return $this->sanitize_slider( $value, $control );

			case 'dimensions':
				return $this->sanitize_dimensions( $value, $control );

			case 'gaps':
				// Flex/Grid gap: `{column, row, isLinked, unit}` (unit = px default).
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected {column, row, isLinked, unit}' );
				}
				$unit = isset( $value['unit'] ) ? (string) $value['unit'] : 'px';
				if ( ! empty( $control['size_units'] ) && ! in_array( $unit, $control['size_units'], true ) ) {
					return $this->invalid( 'unit must be one of: ' . implode( ', ', $control['size_units'] ) );
				}
				$out = array( 'unit' => $unit );
				foreach ( array( 'column', 'row' ) as $axis ) {
					$n = isset( $value[ $axis ] ) ? $value[ $axis ] : '';
					if ( '' !== $n && ! is_numeric( $n ) ) {
						return $this->invalid( "{$axis} must be a number" );
					}
					$out[ $axis ] = (string) $n;
				}
				$out['isLinked'] = ! empty( $value['isLinked'] );
				return $out;

			case 'url':
				if ( is_string( $value ) ) {
					$value = array( 'url' => $value );
				}
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected {url, is_external, nofollow, custom_attributes}' );
				}
				return array(
					'url'               => isset( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '',
					'is_external'       => ! empty( $value['is_external'] ) ? 'on' : '',
					'nofollow'          => ! empty( $value['nofollow'] ) ? 'on' : '',
					'custom_attributes' => isset( $value['custom_attributes'] ) ? sanitize_text_field( (string) $value['custom_attributes'] ) : '',
				);

			case 'media':
				if ( is_string( $value ) ) {
					$value = array( 'url' => $value );
				}
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected {url, id}' );
				}
				return array(
					'url' => isset( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '',
					'id'  => isset( $value['id'] ) && '' !== $value['id'] ? absint( $value['id'] ) : '',
				);

			case 'icons':
				// Icon picker value: `{value: 'fas fa-star', library: 'fa-solid'}`.
				if ( is_string( $value ) ) {
					$value = array( 'value' => $value, 'library' => '' );
				}
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected {value, library}' );
				}
				return array(
					'value'   => isset( $value['value'] ) ? sanitize_text_field( (string) $value['value'] ) : '',
					'library' => isset( $value['library'] ) ? sanitize_text_field( (string) $value['library'] ) : '',
				);

			case 'text_shadow':
			case 'box_shadow':
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected {horizontal, vertical, blur, color}' );
				}
				$fields = 'box_shadow' === $type ? array( 'horizontal', 'vertical', 'blur', 'spread' ) : array( 'horizontal', 'vertical', 'blur' );
				$out    = array();
				foreach ( $fields as $field ) {
					$n = isset( $value[ $field ] ) ? $value[ $field ] : 0;
					if ( ! is_numeric( $n ) ) {
						return $this->invalid( "{$field} must be a number" );
					}
					$out[ $field ] = 0 + $n;
				}
				$color = $this->sanitize_color( isset( $value['color'] ) ? $value['color'] : 'rgba(0,0,0,0.3)' );
				if ( is_wp_error( $color ) ) {
					return $color;
				}
				$out['color'] = $color;
				return $out;

			case 'gallery':
				// Array of media items `{id}` / `{url}`.
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected an array of images' );
				}
				$out = array();
				foreach ( $value as $item ) {
					if ( is_numeric( $item ) ) {
						$out[] = array( 'id' => absint( $item ), 'url' => '' );
					} elseif ( is_array( $item ) ) {
						$out[] = array(
							'id'  => isset( $item['id'] ) ? absint( $item['id'] ) : '',
							'url' => isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '',
						);
					} else {
						return $this->invalid( 'gallery item must be an id or {id, url}' );
					}
				}
				return $out;

			case 'select2':
			case 'rey-query':
			case 'rey-ajax-list':
				// Select2-style multi-select: an array of IDs (ints or strings),
				// or a single ID. Empty array/'' = clear.
				if ( '' === $value || null === $value ) {
					return array();
				}
				if ( is_array( $value ) ) {
					$out = array();
					foreach ( $value as $item ) {
						$out[] = is_numeric( $item ) ? (int) $item : sanitize_text_field( (string) $item );
					}
					return $out;
				}
				if ( is_scalar( $value ) ) {
					return is_numeric( $value ) ? (int) $value : sanitize_text_field( (string) $value );
				}
				return $this->invalid( 'expected an array of ids or a single id' );

			case 'repeater':
				// List of row objects; each row validated against `fields` (when present).
				if ( ! is_array( $value ) ) {
					return $this->invalid( 'expected an array of rows' );
				}
				$fields = isset( $control['fields'] ) && is_array( $control['fields'] ) ? $control['fields'] : array();
				// Structural repeater field types that carry no user-editable value.
				$structural = array( 'hidden', 'tabs', 'tab', 'rey-query', 'rey-ajax-list', 'select2', 'image_dimensions', 'visual_choice', 'code' );
				$out    = array();
				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						return $this->invalid( 'repeater row must be an object' );
					}
					$clean_row = array();
					foreach ( $row as $k => $v ) {
						if ( isset( $fields[ $k ] ) ) {
							$ftype = isset( $fields[ $k ]['type'] ) ? $fields[ $k ]['type'] : '';
							if ( in_array( $ftype, $structural, true ) ) {
								// Pass structural fields through leniently.
								$clean_row[ $k ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
								continue;
							}
							$sanitized = $this->sanitize_value( $v, $fields[ $k ] );
							if ( is_wp_error( $sanitized ) ) {
								return $sanitized;
							}
							$clean_row[ $k ] = $sanitized;
						} else {
							// Keep unknown keys (e.g. `_id`) as-is, text-sanitized.
							$clean_row[ $k ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
						}
					}
					$out[] = $clean_row;
				}
				return $out;
		}

		return $this->invalid( "control type '{$type}' is not supported by this API" );
	}

	/**
	 * Hex (#rgb, #rgba, #rrggbb, #rrggbbaa), rgb(a)/hsl(a) or empty.
	 *
	 * @param mixed $value
	 * @return string|WP_Error
	 */
	private function sanitize_color( $value ) {
		if ( ! is_string( $value ) ) {
			return $this->invalid( 'expected a color string' );
		}
		$value = trim( $value );
		if ( '' === $value || 'transparent' === $value ) {
			return $value;
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(rgb|hsl)a?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+\s*)?\)$/', $value ) ) {
			return $value;
		}
		return $this->invalid( 'expected #hex, rgb(a) or hsl(a)' );
	}

	/**
	 * SLIDER: `{unit, size, sizes}`; a bare number uses the first unit.
	 *
	 * @param mixed $value
	 * @param array $control
	 * @return array|WP_Error
	 */
	private function sanitize_slider( $value, array $control ) {
		$units = ! empty( $control['size_units'] ) ? $control['size_units'] : array( 'px' );
		if ( is_numeric( $value ) || '' === $value ) {
			$value = array( 'size' => $value );
		}
		if ( ! is_array( $value ) ) {
			return $this->invalid( 'expected {unit, size}' );
		}
		$unit = isset( $value['unit'] ) ? (string) $value['unit'] : ( isset( $control['default']['unit'] ) ? $control['default']['unit'] : reset( $units ) );
		if ( ! in_array( $unit, $units, true ) ) {
			return $this->invalid( 'unit must be one of: ' . implode( ', ', $units ) );
		}
		$size = isset( $value['size'] ) ? $value['size'] : '';
		if ( '' !== $size && ! is_numeric( $size ) ) {
			return $this->invalid( 'size must be a number' );
		}
		return array(
			'unit'  => $unit,
			'size'  => '' === $size ? '' : 0 + $size,
			'sizes' => array(),
		);
	}

	/**
	 * DIMENSIONS: `{unit, top, right, bottom, left, isLinked}` with string sides.
	 *
	 * @param mixed $value
	 * @param array $control
	 * @return array|WP_Error
	 */
	private function sanitize_dimensions( $value, array $control ) {
		$units = ! empty( $control['size_units'] ) ? $control['size_units'] : array( 'px' );
		if ( ! is_array( $value ) ) {
			return $this->invalid( 'expected {unit, top, right, bottom, left, isLinked}' );
		}
		$unit = isset( $value['unit'] ) ? (string) $value['unit'] : reset( $units );
		if ( ! in_array( $unit, $units, true ) ) {
			return $this->invalid( 'unit must be one of: ' . implode( ', ', $units ) );
		}
		$out = array( 'unit' => $unit );
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$n = isset( $value[ $side ] ) ? $value[ $side ] : '';
			if ( '' !== $n && ! is_numeric( $n ) ) {
				return $this->invalid( "{$side} must be a number" );
			}
			$out[ $side ] = (string) $n;
		}
		$out['isLinked'] = ! empty( $value['isLinked'] );
		return $out;
	}

	/**
	 * @param string $message
	 * @return WP_Error
	 */
	private function invalid( $message ) {
		return new WP_Error( 'dukkan_elementor_invalid_value', $message );
	}
}
