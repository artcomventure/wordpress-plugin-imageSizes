<?php

/**
 * Plugin Name: Image Sizes
 * Plugin URI: https://github.com/artcomventure/wordpress-plugin-cropImageSizes
 * Description: Edit all available image sizes.
 * Version: 1.3.1
 * Text Domain: image-sizes
 * Author: artcom venture GmbH
 * Author URI: http://www.artcom-venture.de/
 */

/**
 * Add css.
 */
add_action( 'admin_enqueue_scripts', 'imagesizes_admin_style' );
function imagesizes_admin_style() {
	global $pagenow;
	if ( $pagenow != 'options-media.php' ) {
		return;
	}

	wp_enqueue_style( 'imagesize-options-media', plugin_dir_url( __FILE__ ) . 'css/options-media.css' );
}

/**
 * Get 'all' image sizes and its default settings.
 * @see https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
 *
 * @param bool $defaults
 *
 * @return array
 */
function imagesizes_get_image_sizes( $defaults = false ) {
	global $_wp_additional_image_sizes;

	// @see ROOT/wp-admin/includes/schema.php
	$default_sizes = array(
		'thumbnail'    => array(
			'width'  => 150,
			'height' => 150,
			'crop'   => true
		),
		'medium'       => array(
			'width'  => 300,
			'height' => 300,
			'crop'   => false
		),
		'medium_large' => array(
			'width'  => 768,
			'height' => 0,
			'crop'   => false
		),
		'large'        => array(
			'width'  => 1024,
			'height' => 1024,
			'crop'   => false
		),
	);

	$image_sizes = array();

	foreach ( get_intermediate_image_sizes() as $image_size ) {
		if ( in_array( $image_size, array(
			'thumbnail',
			'medium',
			'medium_large',
			'large'
		) ) ) {
			$image_sizes[ $image_size ] = array(
				'width'  => ( $defaults ? $default_sizes[ $image_size ]['width'] : get_option( "{$image_size}_size_w" ) ),
				'height' => ( $defaults ? $default_sizes[ $image_size ]['height'] : get_option( "{$image_size}_size_h" ) ),
				'crop'   => ( $defaults ? $default_sizes[ $image_size ]['crop'] : get_option( "{$image_size}_crop" ) )
			);
		} elseif ( isset( $_wp_additional_image_sizes[ $image_size ] ) ) {
			// default values
			$image_sizes[ $image_size ] = array(
				'width'  => $_wp_additional_image_sizes[ $image_size ]['width'],
				'height' => $_wp_additional_image_sizes[ $image_size ]['height'],
				'crop'   => $_wp_additional_image_sizes[ $image_size ]['crop'],
			);

			// current values
			if ( ! $defaults ) {
				$image_sizes[ $image_size ]['width']  = get_option( "{$image_size}_size_w", $image_sizes[ $image_size ]['width'] );
				$image_sizes[ $image_size ]['height'] = get_option( "{$image_size}_size_h", $image_sizes[ $image_size ]['height'] );
				$image_sizes[ $image_size ]['crop']   = get_option( "{$image_size}_crop", $image_sizes[ $image_size ]['crop'] ) . '';
			}
		}

		// make string to array
		if ( ! $defaults && ! is_array( $image_sizes[ $image_size ]['crop'] ) && ! is_bool( $image_sizes[ $image_size ]['crop'] ) && ! is_numeric( $image_sizes[ $image_size ]['crop'] ) ) {
			$image_sizes[ $image_size ]['crop']                = explode( ' ', $image_sizes[ $image_size ]['crop'] );
			$_wp_additional_image_sizes[ $image_size ]['crop'] = $image_sizes[ $image_size ]['crop'];
		}
	}

	return $image_sizes;
}

/**
 * Load plugin's textdomain.
 */
add_action( 'after_setup_theme', 'imagesizes_t9n' );
function imagesizes_t9n() {
	load_theme_textdomain( 'image-sizes', plugin_dir_path( __FILE__ ) . 'languages' );
}

/**
 * Register crop settings for image sizes.
 */
add_action( 'admin_init', 'imagesizes__admin_init', 100 );
function imagesizes__admin_init() {
	// notice to regenerate the thumbnails
	add_settings_field( 'image-sizes-note', '', function () {
		imagesizes_notice( __( 'Be aware that the following image sizes have their dimension for a <i>reason</i><br />... so be <i>careful</i> changing them and always check the output.', 'image-sizes' ) );
	}, 'media' );

	foreach ( imagesizes_get_image_sizes() as $image_size => $settings ) {
		// override image sizes
		add_image_size( $image_size, get_option( "{$image_size}_size_w", $settings['width'] ), get_option( "{$image_size}_size_h", $settings['height'] ), $settings['crop'] );

		if ( $image_size == 'thumbnail' ) {
			continue;
		}

		// register the size's crop setting so that $_POST handling is done for us
		register_setting( 'media', "{$image_size}_crop" );

		// ignore because they are already added by WP
		if ( in_array( $image_size, array(
			'medium',
			'large'
		) ) ) {
			continue;
		}

		// register the size's width and height setting so that $_POST handling is done for us
		register_setting( 'media', "{$image_size}_size_w" );
		register_setting( 'media', " {$image_size}_size_h" );

		// add image size inputs to media settings page
		$title = sprintf( __( '%s size', 'image-sizes' ), implode( ' ', array_map( 'ucfirst', explode( '_', $image_size ) ) ) );
		add_settings_field( $image_size, $title, function () use ( $image_size, $settings, $title ) {
			$name = "{$image_size}_size_w"; ?>

			<fieldset>
				<legend class="screen-reader-text">
					<span><?php echo $title; ?></span>
				</legend>

				<label for="<?php echo $name; ?>">
					<?php _e( $settings['crop'] ? 'Width' : 'Max Width' ); ?>
				</label>

				<input name="<?php echo $name; ?>" type="number" step="1"
				       min="0"
				       id="<?php echo $name; ?>"
				       value="<?php echo esc_attr( $settings['width'] ); ?>"
				       class="small-text"/>

				<?php $name = "{$image_size}_size_h"; ?>

				<label for="<?php echo $name; ?>">
					<?php _e( $settings['crop'] ? 'Height' : 'Max Height' ); ?>
				</label>

				<input name="<?php echo $name; ?>" type="number" step="1"
				       min="0"
				       id="<?php echo $name; ?>"
				       value="<?php echo esc_attr( $settings['height'] ); ?>"
				       class="small-text"/>
			</fieldset>

		<?php }, 'media' );
	}

	add_settings_field( 'image-sizes-actions', __( 'For all image sizes', 'image-sizes' ), function () { ?>

		<p class="image-size__actions">
			<span class="progress"></span>
			<span class="status"></span>
			<a href="#regenerate-all" class="button"
			   data-image_size=""><?php _e( 'Regenerate all images', 'image-sizes' ) ?></a>

			<a href="#reset-all" class="button"
			   data-image_size=""><?php _e( 'Reset all to default', 'image-sizes' ) ?></a>
		</p>

		<script type="text/javascript">
			(function () {
				// immediately add crop option and reset button to image sizes
				var crop = {
					<?php $crop = array();
						foreach ( imagesizes_get_image_sizes() as $image_size => $settings ) {
							$crop[] = "\n            '{$image_size}': '" . ( $settings['crop'] ? ( is_array( $settings['crop'] ) ? implode( ' ', $settings['crop'] ) : $settings['crop'] ) : 0 ) . "'";
						};
						echo implode( ', ', $crop ) . "\n"; ?>
				};

				for ( var size in crop ) {
					if ( !crop.hasOwnProperty( size ) ) continue;

					var $heightInput = document.getElementById( size + '_size_h' );
					if ( !$heightInput ) continue;

					var $wrapper = document.createElement( 'p' );

					if ( size == 'thumbnail' ) {
						// remove default thumbnail input
						(function () {
							var $label = document.querySelector( 'label[for="thumbnail_crop"]' );
							if ( !$label ) return;

							$label.previousElementSibling.remove(); // checkbox
							$label.previousElementSibling.remove(); // br
							$label.remove(); // label itself
						})();
					}

					if ( crop[size] ) {
						var $label = document.querySelector( '[for="' + size + '_size_w"]' );
						$label.innerHTML = '<?php _e( 'Width' ) ?>';

						$label = document.querySelector( '[for="' + size + '_size_h"]' );
						$label.innerHTML = '<?php _e( 'Height' ) ?>';
					}

					$heightInput.parentNode.appendChild( document.createElement( 'br' ) );

					$wrapper.innerHTML = '<select name="' + size + '_crop" id="' + size + '_crop">'
					+ '<option value="0"' + ( crop[size] == 0 ? ' selected="selected"' : '' ) + '><?php _e( 'Do not', 'image-sizes' ) ?></option>'
					+ '<option value="1"' + ( crop[size] == 1 ? ' selected="selected"' : '' ) + '><?php _e( 'Centered', 'image-sizes' ) ?></option>'
					<?php foreach ( array( 'Left top', 'Center top', 'Right top', 'Left center', 'Right center', 'Left bottom', 'Center bottom', 'Right bottom' ) as $position ) : ?>
					+ '<option value="<?php echo strtolower( $position ) ?>"' + ( crop[size] == '<?php echo strtolower( $position ) ?>' ? ' selected="selected"' : '' ) + '><?php _e( $position, 'image-sizes' ) ?></option>'
					<?php endforeach; ?>
					+ '</select>';
					$heightInput.parentNode.appendChild( $wrapper.children[0] );

					// 'create' and append label
					$wrapper.innerHTML = '<label for="' + size + '_crop"><?php _e( 'crop this image size to exact dimensions', 'image-sizes' ); ?></label>';
					$heightInput.parentNode.appendChild( $wrapper.children[0] );

					// 'create' and append reset button
					$wrapper.innerHTML = '<span class="progress"></span><span class="status"></span>'
					+ '<input class="button button-small button-primary" value="<?php _e( 'Save Changes' ) ?>" type="submit" style="display: none">'
					+ '<a href="#regenerate-' + size + '" class="button button-small" data-image_size="' + size + '"><?php _e( 'Regenerate images', 'image-sizes' ) ?></a> '
					+ '<a href="#reset-' + size + '" class="button button-small" data-image_size="' + size + '"><?php _e( 'Reset to default', 'image-sizes' ) ?></a>';
					$wrapper.className = 'image-size__actions';
					$heightInput.parentNode.appendChild( $wrapper );
				}

				var request = new XMLHttpRequest();

				// trigger input change
				// and replace regenerate button with save button
				// because changes must be saved first!
				Array.prototype.forEach.call( document.querySelectorAll( 'input[type="number"], input[type="checkbox"], select' ), function ( $input ) {
					// 'save' old (initial) value to compare with
					$input.setAttribute( 'data-value', $input.type == 'checkbox' ? $input.checked : $input.value );

					var $save = $input.parentNode.getElementsByClassName( 'button-primary' )[0],
						$regenerate = $input.parentNode.querySelector( '[href^="#regenerate-"]' );

					['click', 'change', 'blur', 'keyup'].forEach( function ( event ) {
						$input.addEventListener( event, function () {
							var bChanged = false,
								oldValue = this.getAttribute( 'data-value' );

							switch ( $input.type ) {
								default:
									if ( this.value != oldValue ) {
										bChanged = true;
									}
									break;

								case 'checkbox':
									if ( oldValue != $input.checked + '' ) {
										bChanged = true;
									}
									break;
							}

							if ( bChanged ) {
								if ( $input.className.indexOf( ' changed' ) < 0 ) {
									$input.className += ' changed';
								}
							}
							else {
								$input.className = $input.className.replace( ' changed', '' );
							}

							// check if any input
							if ( $input.parentNode.getElementsByClassName( 'changed' ).length ) {
								$save.style.display = '';
								$regenerate.style.display = 'none';
							}
							else {
								$save.style.display = 'none';
								$regenerate.style.display = '';
							}
						} );
					} );
				} );

				Array.prototype.forEach.call( document.querySelectorAll( 'a[data-image_size]' ), function ( $action ) {
					$action.addEventListener( 'click', function ( e ) {
						e.preventDefault();

						var button = this;

						var action = button.getAttribute( 'href' ).match( /^#(reset|regenerate)-(.+)/ ),
							image_size = button.getAttribute( 'data-image_size' );

						switch ( action[1] ) {
							case 'regenerate':
								if ( button.parentNode.className.indexOf( ' progress-bar' ) < 0 ) {
									var $progress = button.parentNode.getElementsByClassName( 'progress' )[0],
										$status = button.parentNode.getElementsByClassName( 'status' )[0],
										message = '<?php _e( 'Initializing ...', 'image-sizes' ) ?>';

									$progress.innerHTML = message;
									$status.innerHTML = message;

									button.parentNode.className += ' progress-bar';

									function removeProgressBar( msg ) {
										$progress.innerHTML = msg || '<?php _e( 'Regenerating completed!', 'image-size' ) ?>';

										setTimeout( function () {
											button.parentNode.className = button.parentNode.className.replace( ' progress-bar', '' );

											$progress.style.width = '';
											$progress.innerHTML = '';
											$status.innerHTML = '';
										}, 3000 );
									}
								}
								break;

							case 'reset':
								button.className += ' pending';
								break;
						}

						request.open( 'GET', '<?php echo admin_url( 'admin-ajax.php' ) ?>'
						+ '?action=imagesizes_' + action[1]
						+ '&image_size=' + image_size );

						request.onreadystatechange = function () {
							if ( this.readyState === 4 ) {
								button.className = button.className.replace( ' pending', ' done' );

								if ( action[1] == 'reset' ) location.href = location.href; // reload page
								else {
									var attachments = JSON.parse( this.responseText ),
										i = 0;

									// no attachments to regenerate
									if ( !attachments || !attachments.length ) {
										$progress.style.width = '100%';
										return removeProgressBar( '<?php _e( 'Nothing to regenerate.', 'image-size' ) ?>' );
									}

									function regenerateAttachment( attachment ) {
										message = '<?php _e( 'Regenerating image %s of %s: %s', 'image-sizes' ) ?>'
											.replace( '%s', ++i ).replace( '%s', attachments.length ).replace( '%s', attachment['title'] );
										$progress.innerHTML = message;
										$status.innerHTML = message;

										$progress.style.width = i * 100 / attachments.length + '%';

										request.open( 'GET', '<?php echo admin_url( 'admin-ajax.php' ) ?>'
										+ '?action=imagesizes_' + action[1]
										+ '&image_size=' + image_size
										+ '&attachment_id=' + attachment['id'] );

										request.onreadystatechange = function () {
											if ( this.readyState === 4 ) {
												if ( !!attachments[i] ) regenerateAttachment( attachments[i] ); // next image
												else removeProgressBar(); // ... or end
											}
										};

										request.send();
									}

									// start regeneration images one by one
									regenerateAttachment( attachments[i] );
								}
							}
						};

						request.send();
						button.blur();
					} );
				} );

			})();
		</script>

	<?php }, 'media' );
}

/**
 * Retrieve message.
 *
 * @param string $message
 * @param string $type
 * @param bool $inline
 *
 * @return string
 */
function get_imagesizes_notice( $message = '', $type = 'info', $inline = true ) {
	return sprintf( '<div class="notice notice-%1$s ' . ( $inline ? 'inline' : 'is-dismissible' ) . '"><p>%2$s</p></div>', esc_attr( $type ), $message );
}

/**
 * Display message.
 *
 * @param string $message
 * @param string $type
 * @param bool $inline
 *
 * @return string
 */
function imagesizes_notice( $message = '', $type = 'info', $inline = true ) {
	echo get_imagesizes_notice( $message, $type, $inline );
}

/**
 * Regenerate image(s).
 *
 * @param null $attachment_id
 * @param null $regenerate
 *
 * @return array|bool
 */
add_action( 'wp_ajax_imagesizes_regenerate', 'imagesizes_regenerate' );
add_action( 'wp_ajax_nopriv_imagesizes_regenerate', 'imagesizes_regenerate' );
function imagesizes_regenerate( $attachment_id = NULL, $regenerate = NULL ) {
	if ( empty( $_GET['attachment_id'] ) ) {
		$attachments = array();

		foreach (
			get_children( array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => - 1,
				'post_status'    => NULL,
				'post_parent'    => NULL, // any parent
				'output'         => 'object',
			) ) as $attachment
		) {
			$attachments[] = array(
				'id'    => $attachment->ID,
				'title' => $attachment->post_title
			);
		}

		if ( wp_doing_ajax() ) {
			die( json_encode( $attachments ) );
		}

		return $attachments;
	}

	// regenerate

	$attachment_id = $_GET['attachment_id'];

	if ( ( $file = get_attached_file( $attachment_id ) ) && @file_exists( $file ) ) {
		@error_reporting( 0 );
		@set_time_limit( 115 );

		// filter image sizes
		// disabled for now ... because of missing image sizes in media popup :/
//		add_filter( 'intermediate_image_sizes_advanced', 'imagesizes_intermediate_image_sizes_advanced', 10, 2 );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		if ( wp_doing_ajax() ) {
			die( 1 );
		}

		return true;
	}

	if ( wp_doing_ajax() ) {
		die( 0 );
	}

	return false;
}

// filter image sizes
function imagesizes_intermediate_image_sizes_advanced( $sizes, $metadata ) {
	// re/generate specific size
	if ( isset( $_GET['action'] ) && $_GET['action'] == 'imagesizes_regenerate' && ! empty( $_GET['image_size'] ) ) {
		$sizes = array_filter( $sizes, function ( $image_size ) {
			return $image_size == $_GET['image_size'];
		}, ARRAY_FILTER_USE_KEY );
	}

	return $sizes;
}

/**
 * Remove update notification (since this plugin isn't listed on https://wordpress.org/plugins/).
 */
add_filter( 'site_transient_update_plugins', 'imagesizes__site_transient_update_plugins' );
function imagesizes__site_transient_update_plugins( $value ) {
	$plugin_file = plugin_basename( __FILE__ );

	if ( isset( $value->response[ $plugin_file ] ) ) {
		unset( $value->response[ $plugin_file ] );
	}

	return $value;
}

/**
 * Change details link to GitHub repository.
 */
add_filter( 'plugin_row_meta', 'imagesizes__plugin_row_meta', 10, 2 );
function imagesizes__plugin_row_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) == $file ) {
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file );

		$links[2] = '<a href="' . $plugin_data['PluginURI'] . '">' . __( 'Visit plugin site' ) . '</a>';

		$links[] = '<a href="' . admin_url( 'options-media.php' ) . '">' . __( 'Settings' ) . '</a>';
	}

	return $links;
}

/**
 * Delete traces in db on deactivation or reset.
 */
add_action( 'wp_ajax_imagesizes_reset', 'imagesizes_deactivate' );
add_action( 'wp_ajax_nopriv_imagesizes_reset', 'imagesizes_deactivate' );
register_deactivation_hook( __FILE__, 'imagesizes_deactivate' );
function imagesizes_deactivate( $reset = NULL ) {
	if ( isset( $_GET['action'] ) && $_GET['action'] == 'imagesizes_reset' ) {
		$reset = isset( $_GET['image_size'] ) ? $_GET['image_size'] : '';
	}

	foreach ( imagesizes_get_image_sizes( true ) as $image_size => $settings ) {
		if ( ! is_null( $reset ) && ( $reset && $image_size != $reset ) ) {
			continue;
		}

		if ( $image_size == 'thumbnail' ) {
			if ( ! is_null( $reset ) ) {
				// only reset thumbnail's crop on reset-action
				update_option( "{$image_size}_crop", $settings['crop'] );
			}
		}
		// delete all '*_crop' options (but thumbnail's)
		// because they are plugin releated
		else {
			delete_option( "{$image_size}_crop" );
		}

		// don't reset these image sizes on deactivation
		// because they are WP-editable
		if ( is_null( $reset ) && in_array( $image_size, array(
				'thumbnail',
				'medium',
				'large'
			) )
		) {
			return;
		}

		// reset default
		if ( in_array( $image_size, array(
			'thumbnail',
			'medium',
			'medium_large',
			'large'
		) ) ) {
			update_option( "{$image_size}_size_w", $settings['width'] );
			update_option( "{$image_size}_size_h", $settings['height'] );
		} else {
			delete_option( "{$image_size}_size_w" );
			delete_option( "{$image_size}_size_h" );
		}
	}
}
