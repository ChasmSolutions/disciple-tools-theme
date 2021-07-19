<?php
/**
 * Dynamically loads the class attempting to be instantiated elsewhere in the
 * plugin.
 *
 * @package WP_Queue\Inc
 */
if ( ! function_exists( 'wp_queue_autoload' ) ) {
	/**
	 * Dynamically loads the class attempting to be instantiated elsewhere in the
	 * plugin by looking at the $class_name parameter being passed as an argument.
	 *
	 * The argument should be in the form: WP_Queue\Namespace. The
	 * function will then break the fully-qualified class name into its pieces and
	 * will then build a file to the path based on the namespace.
	 *
	 * The namespaces in this plugin map to the paths in the directory structure.
	 *
	 * @param string $class_name The fully-qualified name of the class to load.
	 */
	function wp_queue_autoload( $class_name ) {

		// Leave if Class name does not include our namespace.
		if ( false === strpos( $class_name, 'WP_Queue' ) ) {
			return;
		}

		// Replace Namespace backslashes and replace with dir forward slashes.
		$file_name = str_replace( '\\', '/', $class_name ) . '.php';

		// Split the class name into an array to read the namespace and class.
		$file_parts = explode( '\\', $class_name );
		$last_index = count( $file_parts ) - 1;

		// Build the file name.
		if ( isset( $file_parts[ $last_index ] ) ) {
			if ( strpos( strtolower( $file_parts[ $last_index ] ), 'interface' ) ) {
				$file_parts[ $last_index ] = 'interface-' . strtolower( $file_parts[ $last_index ] );
			} else {
				$file_parts[ $last_index ] = 'class-' . strtolower( $file_parts[ $last_index ] );
			}
		}

		// Now build a path to the file location.
		$file_name = implode( '/', $file_parts ) . '.php';
		$filepath  = trailingslashit( dirname( __FILE__ ) );
		$filepath .= $file_name;

		// If the file exists in the specified path, then include it.
		if ( file_exists( $filepath ) ) {
			include_once $filepath;
		} else {
			wp_die(
				esc_html( "The file attempting to be loaded at $filepath does not exist." )
			);
		}
	}
}

spl_autoload_register( 'wp_queue_autoload' );
