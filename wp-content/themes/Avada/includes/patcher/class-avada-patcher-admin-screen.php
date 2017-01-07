<?php

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * The admin screen class for teh patcher.
 *
 * @since 4.0.0
 */
class Avada_Patcher_Admin_Screen {

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {

		// Call register settings function.
		add_action( 'admin_init', array( $this, 'settings' ) );
		// Add the patcher to the support screen.
		add_action( 'avada/admin_pages/support/after_list', array( $this, 'form' ) );

	}

	/**
	 * Register the settings.
	 *
	 * @access public
	 * @return void
	 */
	public function settings() {

		// Get the patches.
		$patches = Avada_Patcher_Client::get_patches();
		if ( ! empty( $patches ) ) {

			// Register settings for the patch contents.
			foreach ( $patches as $key => $value ) {
				register_setting( 'avada_patcher_' . $key, 'avada_patch_contents_' . $key );
			}
		}
	}

	/**
	 * The page contents.
	 *
	 * @access public
	 * @return void
	 */
	public function form() {

		// Make sure that any patches marked as manually applied
		// using the AVADA_MANUALLY_APPLIED_PATCHES constant are marked as complete.
		$this->manually_applied_patches();

		// Get the patches.
		$patches = Avada_Patcher_Client::get_patches();
		// Get the fusion-core plugin version.
		$fusion_core_version = ( class_exists( 'FusionCore_Plugin' ) ) ? FusionCore_Plugin::VERSION : false;
		// Get the fusion-builder plugin version.
		$fusion_builder_version = ( class_exists( 'FusionBuilder' ) ) ? FUSION_BUILDER_VERSION : false;
		// Get the avada theme version.
		$avada_version = Avada::get_theme_version();

		// Determine if there are available patches, and build an array of them.
		$available_patches = array();
		$context = array(
			'avada'          => false,
			'fusion-core'    => false,
			'fusion-builder' => false,
		);
		foreach ( $patches as $patch_id => $patch_args ) {
			if ( ! isset( $patch_args['patch'] ) ) {
				continue;
			}
			foreach ( $patch_args['patch'] as $key => $unique_patch_args ) {
				switch ( $unique_patch_args['context'] ) {
					case 'avada':
						if ( $avada_version == $unique_patch_args['version'] ) {
							$available_patches[] = $patch_id;
							$context['avada'] = true;
						}
						break;
					case 'fusion-core':
						if ( $fusion_core_version == $unique_patch_args['version'] ) {
							$available_patches[] = $patch_id;
							$context['fusion-core'] = true;
						}
						break;
					case 'fusion-builder':
						if ( $fusion_builder_version == $unique_patch_args['version'] ) {
							$available_patches[] = $patch_id;
							$context['fusion-builder'] = true;
						}
						break;
				}
			}
		}
		// Make sure we have a unique array.
		$available_patches = array_unique( $available_patches );
		// Sort the array by value and re-index the keys.
		sort( $available_patches );

		// Get an array of the already applied patches.
		$applied_patches = get_site_option( 'avada_applied_patches', array() );

		// Get an array of patches that failed to be applied.
		$failed_patches = get_site_option( 'avada_failed_patches', array() );

		// Get the array of messages to display.
		$messages = Avada_Patcher_Admin_Notices::get_messages();
		?>
		<div class="avada-important-notice avada-auto-patcher">

			<div class="avada-patcher-heading">
				<p class="description">
					<?php if ( empty( $available_patches ) ) : ?>
						<?php printf( esc_html__( 'Avada Patcher: Currently there are no patches available for Avada v%s', 'Avada' ), $avada_version ); ?>
					<?php else : ?>
						<?php printf( esc_html__( 'Avada Patcher: The following patches are available for Avada %s', 'Avada' ), $avada_version ); ?>
					<?php endif; ?>
					<span class="avada-auto-patcher learn-more"><a href="https://theme-fusion.com/avada-doc/avada-patcher/" target="_blank" rel="noopener noreferrer"><?php esc_attr_e( 'Learn More', 'Avada' ); ?></a></span>
				</p>
				<?php if ( ! empty( $available_patches ) ) : ?>
					<p class="sub-description">
						<?php esc_html_e( 'The status column displays if a patch was applied. However, a patch can be reapplied if necessary.', 'Avada' ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $messages ) ) : ?>
				<?php foreach ( $messages as $message_id => $message ) : ?>
					<?php if ( false !== strpos( $message_id, 'write-permissions-' ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<p class="avada-patcher-error"><?php echo wp_kses_post( $message ); ?></p>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if ( ! empty( $available_patches ) ) : // Only display the table if we have patches to apply. ?>
				<table class="avada-patcher-table">
					<tbody>
						<tr class="avada-patcher-headings">
							<th style="min-width:6em;"><?php esc_attr_e( 'Patch #', 'Avada' ); ?></th>
							<th><?php esc_attr_e( 'Issue Date', 'Avada' ); ?></th>
							<th><?php esc_attr_e( 'Description', 'Avada' ); ?></th>
							<th><?php esc_attr_e( 'Status', 'Avada' ); ?></th>
							<th></th>
						</tr>
						</tr>
						<?php foreach ( $available_patches as $key => $patch_id ) :

							// Do not allow applying the patch initially.
							// We'll have to check if they can later.
							$can_apply = false;

							// Make sure the patch exists.
							if ( ! array_key_exists( $patch_id, $patches ) ) {
								continue;
							}

							// Get the patch arguments.
							$patch_args = $patches[ $patch_id ];

							// Has the patch been applied?
							$patch_applied = ( in_array( $patch_id, $applied_patches ) );

							// Has the patch failed?
							$patch_failed = ( in_array( $patch_id, $failed_patches ) );

							// If there is no previous patch, we can apply it.
							if ( ! isset( $available_patches[ $key - 1 ] ) ) {
								$can_apply = true;
							}

							// If the previous patch exists and has already been applied,
							// then we can apply this one.
							if ( isset( $available_patches[ $key - 1 ] ) ) {
								if ( in_array( $available_patches[ $key - 1 ], $applied_patches ) ) {
									$can_apply = true;
								}
							}
							?>

							<tr class="avada-patcher-table-head">
								<td class="patch-id">#<?php echo intval( $patch_id ); ?></td>
								<td class="patch-date"><?php echo $patch_args['date'][0]; ?></td>
								<td class="patch-description">
									<?php if ( isset( $messages[ 'write-permissions-' . $patch_id ] ) ) : ?>
										<div class="avada-patcher-error" style="font-size:.85rem;">
											<?php echo $messages[ 'write-permissions-' . $patch_id ]; ?>
										</div>
									<?php endif; ?>
									<?php echo $patch_args['description'][0]; ?>
								</td>
								<td class="patch-status">
									<?php if ( $patch_failed ) : ?>
										<span style="color:#E53935;" class="dashicons dashicons-no"></span>
									<?php elseif ( $patch_applied ) : ?>
										<span style="color:#4CAF50;" class="dashicons dashicons-yes"></span>
									<?php endif; ?>
								</td>
								<td class="patch-apply">
									<?php if ( $can_apply ) : ?>
										<form method="post" action="options.php">
											<?php settings_fields( 'avada_patcher_' . $patch_id ); ?>
											<?php do_settings_sections( 'avada_patcher_' . $patch_id ); ?>
											<input type="hidden" name="avada_patch_contents_<?php echo $patch_id; ?>" value="<?php echo self::format_patch( $patch_args ); ?>" />
											<?php if ( $patch_applied ) : ?>
												<?php submit_button( esc_attr__( 'Patch Applied', 'Avada' ) ); ?>
											<?php else : ?>
												<?php submit_button( esc_attr__( 'Apply Patch', 'Avada' ) ); ?>
												<?php if ( $patch_failed ) : ?>
													<a class="button" style="margin-top:10px;font-size:11px;color:#b71c1c;display:block;" href="<?php echo esc_url_raw( admin_url( 'admin.php?page=avada-support&manually-applied-patch=' . $patch_id ) ); ?>"><?php esc_attr_e( 'Dismiss Notices', 'Avada' ); ?></a>
												<?php endif; ?>
											<?php endif; ?>
										</form>
									<?php else : ?>
										<span class="button disabled button-small">
											<?php if ( isset( $available_patches[ $key - 1 ] ) ) : ?>
												<?php printf( esc_html__( 'Please apply patch #%s first.', 'Avada' ), $available_patches[ $key - 1 ] ); ?>
											<?php else : ?>
												<?php esc_html_e( 'Patch cannot be currently aplied.', 'Avada' ); ?>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		// Delete some messages.
		Avada_Patcher_Admin_Notices::remove_messages_option( 'server-unreachable' );
		Avada_Patcher_Admin_Notices::remove_messages_option( 'no-patch-contents' );
		Avada_Patcher_Admin_Notices::remove_messages_option( 'patch-empty' );
		Avada_Patcher_Admin_Notices::remove_messages_option( 'invalid-patch-target' );
	}

	/**
	 * Format the patch.
	 * We're encoding everything here for security reasons.
	 * We're also going to check the current versions of Avada & Fusion-Core,
	 * and then build the hash for this patch using the files that are needed.
	 *
	 * @since 4.0.0
	 * @access private
	 * @param array $patch The patch array.
	 * @return string
	 */
	private static function format_patch( $patch ) {
		// Get the fusion-core plugin version.
		$fusion_core_version = ( class_exists( 'FusionCore_Plugin' ) ) ? FusionCore_Plugin::VERSION : false;
		// Get the avada theme version.
		$avada_version = Avada::get_theme_version();
		// Get the fusion-builder plugin version.
		$fusion_builder_version = ( class_exists( 'FusionBuilder' ) ) ? FUSION_BUILDER_VERSION : false;

		$patches = array();
		if ( ! isset( $patch['patch'] ) ) {
			return;
		}
		foreach ( $patch['patch'] as $key => $args ) {
			if ( ! isset( $args['context'] ) || ! isset( $args['path'] ) || ! isset( $args['reference'] ) ) {
				continue;
			}
			switch ( $args['context'] ) {

				case 'avada':
					$v1 = Avada_Helper::normalize_version( $avada_version );
					$v2 = Avada_Helper::normalize_version( $args['version'] );
					if ( version_compare( $v1, $v2, '==' ) ) {
						$patches[ $args['context'] ][ $args['path'] ] = $args['reference'];
					}
					break;
				case 'fusion-core':
					$v1 = Avada_Helper::normalize_version( $fusion_core_version );
					$v2 = Avada_Helper::normalize_version( $args['version'] );
					if ( version_compare( $v1, $v2, '==' ) ) {
						$patches[ $args['context'] ][ $args['path'] ] = $args['reference'];
					}
					break;
				case 'fusion-builder':
					$v1 = Avada_Helper::normalize_version( $fusion_builder_version );
					$v2 = Avada_Helper::normalize_version( $args['version'] );
					if ( version_compare( $v1, $v2, '==' ) ) {
						$patches[ $args['context'] ][ $args['path'] ] = $args['reference'];
					}
					break;

			}
		}
		return base64_encode( wp_json_encode( $patches ) );
	}

	/**
	 * Make sure manually applied patches show as successful.
	 *
	 * @access private
	 * @since 5.0.3
	 */
	private function manually_applied_patches() {

		$manual_patches_found = '';
		if ( isset( $_GET['manually-applied-patch'] ) ) {
			$manual_patches_found = $_GET['manually-applied-patch'];
		}

		if ( defined( 'AVADA_MANUALLY_APPLIED_PATCHES' ) ) {
			$manual_patches_found = AVADA_MANUALLY_APPLIED_PATCHES . ',' . $manual_patches_found;
		}
		if ( empty( $manual_patches_found ) ) {
			return;
		}
		$messages_option = get_site_option( Avada_Patcher_Admin_Notices::$option_name );
		$patches         = Avada_Patcher_Client::get_patches();
		$manual_patches  = explode( ',', $manual_patches_found );
		$applied_patches = get_site_option( 'avada_applied_patches' );
		$failed_patches  = get_site_option( 'avada_failed_patches', array() );

		foreach ( $manual_patches as $patch ) {
			$patch = (int) trim( $patch );

			// Update the applied-patches option.
			if ( ! in_array( $patch, $applied_patches ) ) {
				$applied_patches[] = $patch;
				update_site_option( 'avada_applied_patches', $applied_patches );
			}

			// If the patch is in the array of failed patches, remove it.
			if ( in_array( $patch, $failed_patches ) ) {
				$failed_key = array_search( $patch, $failed_patches );
				unset( $failed_patches[ $failed_key ] );
				update_site_option( 'avada_failed_patches', $failed_patches );
			}

			// Remove messages if they exist.
			if ( isset( $patches[ $patch ] ) ) {
				foreach ( $patches[ $patch ]['patch'] as $args ) {
					$message_id = 'write-permissions-' . $patch;
					if ( isset( $messages_option[ $message_id ] ) ) {
						unset( $messages_option[ $message_id ] );
						update_site_option( Avada_Patcher_Admin_Notices::$option_name, $messages_option );
					}
				}
			}
		}
	}
}
