<?php

/**
 * Plugin Name: Image Sizes
 * Plugin URI: https://github.com/artcomventure/wordpress-plugin-cropImageSizes
 * Description: Edit all available image sizes.
 * Version: 1.1.0
 * Text Domain: image-sizes
 * Author: artcom venture GmbH
 * Author URI: http://www.artcom-venture.de/
 */

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
		// ignore
		if ( in_array( $image_size, array( 'thumbnail' ) ) ) {
			continue;
		}

		if ( in_array( $image_size, array(
			'medium',
			'medium_large',
			'large'
		) ) ) {
			$image_sizes[ $image_size ] = array(
				'width'  => ( $defaults ? $default_sizes[ $image_size ]['width'] : get_option( "{$image_size}_size_w" ) ),
				'height' => ( $defaults ? $default_sizes[ $image_size ]['height'] : get_option( "{$image_size}_size_h" ) ),
				'crop'   => ( $defaults ? $default_sizes[ $image_size ]['crop'] : (bool) get_option( "{$image_size}_crop" ) )
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
				$image_sizes[ $image_size ]['crop']   = (bool) get_option( "{$image_size}_crop", $image_sizes[ $image_size ]['crop'] );
			}
		}
	}

	if ( $defaults ) {
		// add 'thumbnail' defaults
		$image_sizes += $default_sizes;
	}

	return $image_sizes;
}

/**
 * Implements action 'after_setup_theme'.
 */
add_action( 'after_setup_theme', 'imagesizes__after_setup_theme' );
function imagesizes__after_setup_theme() {
	// t9n
	load_theme_textdomain( 'image-sizes', plugin_dir_path( __FILE__ ) . 'languages' );
}

/**
 * Register crop settings for image sizes.
 */
add_action( 'admin_init', 'imagesizes__admin_init', 100 );
function imagesizes__admin_init() {
	// notice to regenerate the thumbnails
	add_settings_field( 'image-sizes-note', '', function () {
		imagesizes_notice( __( 'Be aware that the following image sizes have their dimension for a reason<br />... so be <i>careful</i> changing them and always check the output.', 'image-sizes' ) );

		imagesizes_notice(
			sprintf( __( 'If you change any setting below, you must regenerate the images to apply the changes to already uploaded images.', 'image-sizes' ), '' ),
			'info',
			false
		);
	}, 'media' );

	foreach ( imagesizes_get_image_sizes() as $image_size => $settings ) {
		// override image sizes
		add_image_size( $image_size, get_option( "{$image_size}_size_w", $settings['width'] ), get_option( "{$image_size}_size_h", $settings['height'] ), $settings['crop'] );

		// register the size's crop setting so that $_POST handling is done for us
		register_setting( 'media', "{$image_size}_crop" );

		// ignore because they are already added by WP
		if ( in_array( $image_size, array( 'medium', 'large' ) ) ) {
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

	// notice to regenerate the thumbnails
	add_settings_field( 'image-sizes-crop', __( 'Reset', 'image-sizes' ), function () { ?>

		<a href="#reset-all" class="button"
		   data-image_size=""><?php _e( 'Reset all image sizes', 'image-sizes' ) ?></a>

		<script type="text/javascript">
			(function () {
				// immediately add crop option and reset button to image sizes
				var crop = {
					'thumbnail': <?php echo get_option( 'thumbnail_crop' ) ? 1 : 0 ?>,
					<?php $crop = array();
						foreach ( imagesizes_get_image_sizes() as $image_size => $settings ) {
							$crop[] = "\n            '{$image_size}': " . ( $settings['crop'] ? 1 : 0 );
						};
						echo implode( ', ', $crop ) . "\n"; ?>
				};

				for ( var size in crop ) {
					if ( !crop.hasOwnProperty( size ) ) continue;

					var $heightInput = document.getElementById( size + '_size_h' );
					if ( !$heightInput ) continue;

					var $wrapper = document.createElement( 'div' );

					if ( size != 'thumbnail' ) {
						if ( crop[size] ) {
							var $label = document.querySelector( '[for="' + size + '_size_w"]' );
							$label.innerHTML = '<?php _e( 'Width' ) ?>';

							$label = document.querySelector( '[for="' + size + '_size_h"]' );
							$label.innerHTML = '<?php _e( 'Height' ) ?>';
						}

						$heightInput.parentNode.appendChild( document.createElement( 'br' ) );

						// 'create' and append checkbox
						$wrapper.innerHTML = '<input name="' + size + '_crop" type="checkbox" id="' + size + '_crop" value="1"' + ( crop[size] ? ' checked="checked"' : '' ) + '/>';
						$heightInput.parentNode.appendChild( $wrapper.children[0] );

						// 'create' and append label
						$wrapper.innerHTML = '<label for="' + size + '_crop"><?php _e( 'Crop this image size to exact dimensions', 'image-sizes' ); ?></label>';
						$heightInput.parentNode.appendChild( $wrapper.children[0] );
					}

					// 'create' and append reset button
					$heightInput.parentNode.appendChild( document.createElement( 'br' ) );
					$wrapper.innerHTML = '<a href="#reset-' + size + '" class="button" data-image_size="' + size + '" style="margin-top: .5em"><?php _e( 'Reset to default', 'image-sizes' ) ?></a>';
					$heightInput.parentNode.appendChild( $wrapper.children[0] );
				}

				var request = new XMLHttpRequest();

				Array.prototype.forEach.call( document.querySelectorAll( 'a[data-image_size]' ), function ( $reset ) {
					$reset.addEventListener( 'click', function ( e ) {
						e.preventDefault();

						request.open( 'GET', '<?php echo admin_url( 'admin-ajax.php' ) ?>'
						+ '?action=imagesizes_reset'
						+ '&image_size=' + this.getAttribute( 'data-image_size' ) );

						request.onreadystatechange = function () {
							if ( this.readyState === 4 ) {
								location.reload();
							}
						};

						request.send();
					} );
				} );

			})();
		</script>

	<?php }, 'media' );
}

/**
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
 * Delete traces in db on deactivation or reset.
 */
add_action( 'wp_ajax_imagesizes_reset', 'imagesizes_deactivate' );
add_action( 'wp_ajax_nopriv_imagesizes_reset', 'imagesizes_deactivate' );
register_deactivation_hook( __FILE__, 'imagesizes_deactivate' );
function imagesizes_deactivate() {
	$reset = NULL; // deactivate
	// ... or reset
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
