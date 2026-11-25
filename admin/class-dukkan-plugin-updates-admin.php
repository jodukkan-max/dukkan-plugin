<?php
/**
 * Dukkan > Updates tab: update rules for WordPress, plugins, themes and
 * translations, and the update log.
 *
 * Saves to the same settings the updates module reads (site option MPSUM).
 *
 * @since 1.0.71
 */

defined( 'ABSPATH' ) || exit;

class Dukkan_Updates_Admin {

	const TAB    = 'updates';
	const ACTION = 'dukkan_updates_save';

	public function __construct() {
		add_filter( 'dukkan_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'dukkan_settings_tab_content_' . self::TAB, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_tab( $tabs ) {
		$tabs[ self::TAB ] = array(
			'title' => __( 'Updates', 'dukkan-plugin' ),
			'icon'  => 'dashicons-update',
		);
		return $tabs;
	}

	public function enqueue( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_dukkan-settings' !== $hook || ! isset( $_GET['tab'] ) || self::TAB !== $_GET['tab'] ) {
			return;
		}
		wp_register_style( 'dukkan-updates-admin', false, array(), DUKKAN_PLUGIN_VERSION );
		wp_enqueue_style( 'dukkan-updates-admin' );
		wp_add_inline_style(
			'dukkan-updates-admin',
			'.dukkan-upd__sections{margin:12px 0 8px}.dukkan-upd__table td,.dukkan-upd__table th{vertical-align:middle}.dukkan-upd__table .dukkan-upd__rule{white-space:nowrap}.dukkan-upd__table .dukkan-upd__rule label{margin-inline-end:14px}.dukkan-upd__muted{color:#646970}.dukkan-upd__ok{color:#008a20}.dukkan-upd__fail{color:#b32d2e}.dukkan-upd__actions{display:flex;gap:12px;align-items:center}'
		);
	}

	private static function choices(): array {
		return array(
			'all_updates'         => array(
				'label' => __( 'All updates', 'dukkan-plugin' ),
				'help'  => __( 'Off stops every update check and install below.', 'dukkan-plugin' ),
				'opts'  => array(
					'on'  => __( 'On', 'dukkan-plugin' ),
					'off' => __( 'Off', 'dukkan-plugin' ),
				),
			),
			'core_updates'        => array(
				'label' => __( 'WordPress core', 'dukkan-plugin' ),
				'help'  => '',
				'opts'  => array(
					'off'             => __( 'Blocked', 'dukkan-plugin' ),
					'on'              => __( 'Manual updates', 'dukkan-plugin' ),
					'automatic_minor' => __( 'Auto-update minor releases', 'dukkan-plugin' ),
					'automatic'       => __( 'Auto-update all releases', 'dukkan-plugin' ),
				),
			),
			'plugin_updates'      => array(
				'label' => __( 'Plugins', 'dukkan-plugin' ),
				'help'  => __( '"Per plugin" uses the rules in the Plugins section.', 'dukkan-plugin' ),
				'opts'  => array(
					'off'           => __( 'Blocked', 'dukkan-plugin' ),
					'on'            => __( 'Manual updates', 'dukkan-plugin' ),
					'automatic'     => __( 'Auto-update all', 'dukkan-plugin' ),
					'individual'    => __( 'Per plugin', 'dukkan-plugin' ),
				),
			),
			'theme_updates'       => array(
				'label' => __( 'Themes', 'dukkan-plugin' ),
				'help'  => __( '"Per theme" uses the rules in the Themes section.', 'dukkan-plugin' ),
				'opts'  => array(
					'off'        => __( 'Blocked', 'dukkan-plugin' ),
					'on'         => __( 'Manual updates', 'dukkan-plugin' ),
					'automatic'  => __( 'Auto-update all', 'dukkan-plugin' ),
					'individual' => __( 'Per theme', 'dukkan-plugin' ),
				),
			),
			'translation_updates' => array(
				'label' => __( 'Translations', 'dukkan-plugin' ),
				'help'  => '',
				'opts'  => array(
					'off'       => __( 'Blocked', 'dukkan-plugin' ),
					'on'        => __( 'Manual updates', 'dukkan-plugin' ),
					'automatic' => __( 'Auto-update', 'dukkan-plugin' ),
				),
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dukkan-plugin' ) );
		}
		check_admin_referer( self::ACTION, 'dukkan_updates_nonce' );
		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : 'general';
		$back    = array(
			'page'    => 'dukkan-settings',
			'tab'     => self::TAB,
			'section' => $section,
		);

		if ( 'general' === $section ) {
			$core = Dukkan_Updates::options( 'core' );
			foreach ( self::choices() as $key => $c ) {
				$v = isset( $_POST['core'][ $key ] ) ? sanitize_key( wp_unslash( $_POST['core'][ $key ] ) ) : '';
				if ( isset( $c['opts'][ $v ] ) ) {
					$core[ $key ] = $v;
				}
			}
			$core['logs'] = 'on';
			Dukkan_Updates::save_section( 'core', $core );
		} elseif ( 'plugins' === $section || 'themes' === $section ) {
			$rules   = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$blocked = array();
			$auto    = array();
			$known   = 'plugins' === $section ? array_keys( self::plugins() ) : array_keys( wp_get_themes() );
			foreach ( $rules as $item => $rule ) {
				$item = (string) $item;
				if ( ! in_array( $item, $known, true ) ) {
					continue;
				}
				if ( 'blocked' === $rule ) {
					$blocked[] = $item;
				} elseif ( 'auto' === $rule ) {
					$auto[] = $item;
				}
			}
			// Keep rules for items that are not installed (any more).
			foreach ( Dukkan_Updates::options( $section ) as $i ) {
				if ( ! in_array( $i, $known, true ) ) {
					$blocked[] = $i;
				}
			}
			foreach ( Dukkan_Updates::options( $section . '_automatic' ) as $i ) {
				if ( ! in_array( $i, $known, true ) ) {
					$auto[] = $i;
				}
			}
			$o                           = Dukkan_Updates::options();
			$o[ $section ]               = array_values( array_unique( $blocked ) );
			$o[ $section . '_automatic' ] = array_values( array_unique( $auto ) );
			update_site_option( Dukkan_Updates::OPTION, $o );
		} elseif ( 'logs' === $section && ! empty( $_POST['clear_logs'] ) ) {
			Dukkan_Updates_Log::clear();
			wp_safe_redirect( add_query_arg( $back + array( 'cleared' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( $back + array( 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                              */
	/* ------------------------------------------------------------------ */

	public function render(): void {
		$sections = array(
			'general' => __( 'General', 'dukkan-plugin' ),
			'plugins' => __( 'Plugins', 'dukkan-plugin' ),
			'themes'  => __( 'Themes', 'dukkan-plugin' ),
			'logs'    => __( 'Log', 'dukkan-plugin' ),
		);
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general';
		$section = isset( $sections[ $section ] ) ? $section : 'general';
		// phpcs:enable
		$idle = Dukkan_Updates::idle_reason();
		?>
		<div class="dukkan-upd">
			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Update settings saved.', 'dukkan-plugin' ); ?></p></div>
			<?php elseif ( isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Update log cleared.', 'dukkan-plugin' ); ?></p></div>
			<?php endif; ?>
			<?php // phpcs:enable ?>
			<?php if ( '' !== $idle ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					/* translators: %s: reason */
					echo esc_html( sprintf( __( 'Dukkan update control is not running on this store: %s. Changes here are saved but take effect once it runs.', 'dukkan-plugin' ), $idle ) );
					?>
				</p></div>
			<?php endif; ?>

			<ul class="subsubsub dukkan-upd__sections">
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
			if ( 'plugins' === $section ) {
				$this->render_items( 'plugins' );
			} elseif ( 'themes' === $section ) {
				$this->render_items( 'themes' );
			} elseif ( 'logs' === $section ) {
				$this->render_logs();
			} else {
				$this->render_general();
			}
			?>
		</div>
		<?php
	}

	private function form_open( string $section ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
			<?php wp_nonce_field( self::ACTION, 'dukkan_updates_nonce' ); ?>
		<?php
	}

	private function render_general(): void {
		$this->form_open( 'general' );
		?>
			<div class="dukkan-loyalty-card">
				<div class="dukkan-loyalty-card__head">
					<h2><?php esc_html_e( 'Update rules', 'dukkan-plugin' ); ?></h2>
					<p><?php esc_html_e( 'Choose what this store may update. Blocked items never show an update; auto-updates install in the background.', 'dukkan-plugin' ); ?></p>
				</div>
				<table class="form-table" role="presentation"><tbody>
					<?php foreach ( self::choices() as $key => $c ) : ?>
						<?php
						$current = Dukkan_Updates::core( $key );
						if ( 'automatic_off' === $current ) {
							$current = 'on';
						}
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $c['label'] ); ?></th>
							<td>
								<?php foreach ( $c['opts'] as $v => $label ) : ?>
									<label style="margin-inline-end:16px"><input type="radio" name="core[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $v ); ?>" <?php checked( $current, $v ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
								<?php if ( $c['help'] ) : ?>
									<p class="description"><?php echo esc_html( $c['help'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody></table>
			</div>
			<?php submit_button( __( 'Save changes', 'dukkan-plugin' ) ); ?>
		</form>
		<?php
	}

	private function render_items( string $kind ): void {
		$blocked = Dukkan_Updates::options( $kind );
		$auto    = Dukkan_Updates::options( $kind . '_automatic' );
		$mode    = Dukkan_Updates::core( 'plugins' === $kind ? 'plugin_updates' : 'theme_updates' );
		$items   = array();
		if ( 'plugins' === $kind ) {
			foreach ( self::plugins() as $file => $p ) {
				$items[ $file ] = array( $p['Name'], $p['Version'] );
			}
		} else {
			foreach ( wp_get_themes() as $slug => $t ) {
				$items[ $slug ] = array( $t->get( 'Name' ), $t->get( 'Version' ) );
			}
		}
		uasort(
			$items,
			function ( $a, $b ) {
				return strcasecmp( $a[0], $b[0] );
			}
		);
		$this->form_open( $kind );
		?>
			<div class="dukkan-loyalty-card">
				<div class="dukkan-loyalty-card__head">
					<h2><?php echo esc_html( 'plugins' === $kind ? __( 'Plugin rules', 'dukkan-plugin' ) : __( 'Theme rules', 'dukkan-plugin' ) ); ?></h2>
					<p>
						<?php esc_html_e( 'Blocked: never shows an update. Auto-update: installs updates in the background. Allowed: updates are installed by hand.', 'dukkan-plugin' ); ?>
						<?php if ( 'individual' !== $mode ) : ?>
							<br><strong><?php esc_html_e( 'Auto-update choices here only apply when the General setting is "Per plugin" / "Per theme". Blocked always applies.', 'dukkan-plugin' ); ?></strong>
						<?php endif; ?>
					</p>
				</div>
				<table class="widefat striped dukkan-upd__table">
					<thead><tr>
						<th><?php esc_html_e( 'Name', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'Version', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'Rule', 'dukkan-plugin' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $items as $id => $it ) : ?>
							<?php $rule = in_array( $id, $blocked, true ) ? 'blocked' : ( in_array( $id, $auto, true ) ? 'auto' : 'allowed' ); ?>
							<tr>
								<td><strong><?php echo esc_html( $it[0] ); ?></strong><br><code class="dukkan-upd__muted"><?php echo esc_html( $id ); ?></code></td>
								<td><?php echo esc_html( $it[1] ); ?></td>
								<td class="dukkan-upd__rule">
									<?php
									foreach ( array(
										'allowed' => __( 'Allowed', 'dukkan-plugin' ),
										'auto'    => __( 'Auto-update', 'dukkan-plugin' ),
										'blocked' => __( 'Blocked', 'dukkan-plugin' ),
									) as $v => $label ) :
										?>
										<label><input type="radio" name="rule[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $v ); ?>" <?php checked( $rule, $v ); ?>> <?php echo esc_html( $label ); ?></label>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php submit_button( __( 'Save changes', 'dukkan-plugin' ) ); ?>
		</form>
		<?php
	}

	private function render_logs(): void {
		$rows  = Dukkan_Updates_Log::latest( 200 );
		$total = Dukkan_Updates_Log::count();
		?>
		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head dukkan-upd__actions" style="justify-content:space-between">
				<div>
					<h2><?php esc_html_e( 'Update log', 'dukkan-plugin' ); ?></h2>
					<p>
						<?php
						/* translators: 1: shown, 2: total */
						echo esc_html( sprintf( __( 'Showing the latest %1$d of %2$d updates.', 'dukkan-plugin' ), count( $rows ), $total ) );
						?>
					</p>
				</div>
				<?php if ( $total ) : ?>
					<?php $this->form_open( 'logs' ); ?>
						<button type="submit" name="clear_logs" value="1" class="button" onclick="return confirm('<?php echo esc_js( __( 'Delete the whole update log?', 'dukkan-plugin' ) ); ?>');"><?php esc_html_e( 'Clear log', 'dukkan-plugin' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<?php if ( ! $rows ) : ?>
				<p class="dukkan-upd__muted"><?php esc_html_e( 'No updates logged yet.', 'dukkan-plugin' ); ?></p>
			<?php else : ?>
				<table class="widefat striped dukkan-upd__table">
					<thead><tr>
						<th><?php esc_html_e( 'Date', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'Name', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'Type', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'From', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'To', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'How', 'dukkan-plugin' ); ?></th>
						<th><?php esc_html_e( 'Result', 'dukkan-plugin' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<td><?php echo esc_html( $r['date'] ); ?></td>
								<td><?php echo esc_html( $r['name'] ); ?></td>
								<td><?php echo esc_html( $r['type'] ); ?></td>
								<td><?php echo esc_html( $r['version_from'] ); ?></td>
								<td><?php echo esc_html( $r['version'] ); ?></td>
								<td><?php echo esc_html( 'automatic' === $r['action'] ? __( 'Automatic', 'dukkan-plugin' ) : __( 'Manual', 'dukkan-plugin' ) ); ?></td>
								<td><?php echo '1' === (string) $r['status'] ? '<span class="dukkan-upd__ok">' . esc_html__( 'Success', 'dukkan-plugin' ) . '</span>' : '<span class="dukkan-upd__fail">' . esc_html__( 'Failed', 'dukkan-plugin' ) . '</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
