<?php

/**
 * Plugin Name: Image Sizes
 * Plugin URI: https://github.com/artcomventure/wordpress-plugin-cropImageSizes
 * Description: Edit all available image sizes.
 * Version: 1.0.0
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
function image_sizes_get_image_sizes( $defaults = false ) {
	global $_wp_additional_image_sizes;

	// @see ROOT/wp-admin/includes/schema.php
	$default_sizes = array(
		'medium' => array(
			'width' => 300,
			'height' => 300,
			'crop' => false
		),
		'medium_large' => array(
			'width' => 768,
			'height' => 0,
			'crop' => false
		),
		'large' => array(
			'width' => 1024,
			'height' => 1024,
			'crop' => false
		),
	);

	$image_sizes = array();

	foreach ( get_intermediate_image_sizes() as $image_size ) {
		// ignore
		if ( in_array( $image_size, array( 'thumbnail', 'post-thumbnail' ) ) ) {
			continue;
		}

		if ( in_array( $image_size, array(
			'medium',
			'medium_large',
			'large'
		) ) ) {
			$image_sizes[ $image_size ] = array(
				'width'  => ( $defaults ? $default_sizes['width'] : get_option( "{$image_size}_size_w" ) ),
				'height' => ( $defaults ? $default_sizes['height'] : get_option( "{$image_size}_size_h" ) ),
				'crop'   => ( $defaults ? $default_sizes['crop'] : (bool) get_option( "{$image_size}_crop" ) )
			);
		} elseif ( isset( $_wp_additional_image_sizes[ $image_size ] ) ) {
			// default values
			$image_sizes[ $image_size ] = array(
				'width'  => $_wp_additional_image_sizes[ $image_size ]['width'],
				'height' => $_wp_additional_image_sizes[ $image_size ]['height'],
				'crop'   => $_wp_additional_image_sizes[ $image_size ]['crop'],
			);

			// current values
			if ( !$defaults ) {
				$image_sizes[ $image_size ]['width'] = get_option( "{$image_size}_size_w", $image_sizes[ $image_size ]['width'] );
				$image_sizes[ $image_size ]['height'] = get_option( "{$image_size}_size_h", $image_sizes[ $image_size ]['height'] );
				$image_sizes[ $image_size ]['crop'] = (bool) get_option( "{$image_size}_crop", $image_sizes[ $image_size ]['crop'] );
			}
		}
	}

	return $image_sizes;
}

/**
 * Implements action 'after_setup_theme'.
 */
add_action( 'after_setup_theme', 'image_sizes__after_setup_theme' );
function image_sizes__after_setup_theme() {
	// t9n
	load_theme_textdomain( 'image-sizes', plugin_dir_path( __FILE__ ) . 'languages' );
}

/**
 * Register crop settings for image sizes.
 */
add_action( 'admin_init', 'image_sizes__admin_init', 100 );
function image_sizes__admin_init() {
	// notice to regenerate the thumbnails
	add_settings_field( 'image-sizes-note', '', function () {
		image_sizes_notice( __( 'Be aware that the following image sizes have their dimension for a reason<br />... so be <i>careful</i> changing them and always check the output.', 'image-sizes' ) );

		image_sizes_notice(
			sprintf( __( 'If you change any setting below, you must regenerate the images to apply the changes to already uploaded images.', 'image-sizes' ), '' ),
			'info',
			false
		);
	}, 'media' );

	foreach ( image_sizes_get_image_sizes() as $image_size => $settings ) {
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
	add_settings_field( 'image-sizes-crop', '', function () { ?>

		<script type="text/javascript">
			// add crop option to image sizes
			var crop = {
				<?php $crop = array();
					foreach ( image_sizes_get_image_sizes() as $size_image => $settings ) {
						$crop[] = "\n            '{$size_image}': " . ( $settings['crop'] ? 1 : 0 );
					};
					echo implode( ', ', $crop ) . "\n"; ?>
			};

			for ( var size in crop ) {
				if ( !crop.hasOwnProperty( size ) ) continue;

				var $heightInput = document.getElementById( size + '_size_h' );
				if ( !$heightInput ) continue;

				if ( crop[size] ) {
					var $label = document.querySelector( '[for="' + size + '_size_w"]' );
					$label.innerHTML = '<?php _e( 'Width' ) ?>';

					$label = document.querySelector( '[for="' + size + '_size_h"]' );
					$label.innerHTML = '<?php _e( 'Height' ) ?>';
				}

				$heightInput.parentNode.appendChild( document.createElement( 'br' ) );

				var $wrapper = document.createElement( 'div' );

				// 'create' and append checkbox
				$wrapper.innerHTML = '<input name="' + size + '_crop" type="checkbox" id="' + size + '_crop" value="1"' + ( crop[size] ? ' checked="checked"' : '' ) + '/>';
				$heightInput.parentNode.appendChild( $wrapper.children[0] );

				// 'create' and append label
				$wrapper.innerHTML = '<label for="' + size + '_crop"><?php _e( 'Crop this image size to exact dimensions', 'image-sizes' ); ?></label>';
				$heightInput.parentNode.appendChild( $wrapper.children[0] );
			}
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
function get_image_sizes_notice( $message = '', $type = 'info', $inline = true ) {
	return sprintf( '<div class="notice notice-%1$s ' . ( $inline ? 'inline' : 'is-dismissible' ) . '"><p>%2$s</p></div>', esc_attr( $type ), $message );
}

/**
 * @param string $message
 * @param string $type
 * @param bool $inline
 *
 * @return string
 */
function image_sizes_notice( $message = '', $type = 'info', $inline = true ) {
	echo get_image_sizes_notice( $message, $type, $inline );
}