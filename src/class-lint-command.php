<?php

// Check so WP_CLI exists or return.
if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * Lint command for WP CLI.
 */

class Lint_Command extends WP_CLI_Command {

	/**
	 * Get config value from lint config.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */

	private function get_lint_config( $key ) {
		$config = WP_CLI::get_configurator()->to_array();

		if ( count( $config ) === 1 || ! isset( $config[1]['lint'] ) ) {
			return;
		}

		if ( ! isset( $config[1]['lint'][$key] ) ) {
			return;
		}

		return $config[1]['lint'][$key];
	}

	/**
	 * Find PHPCS ruleset.
	 *
	 * @param string $root_path
	 *
	 * @return string
	 */

	private function find_phpcs_ruleset( $root_path ) {
		if ( $phpcs_ruleset = $this->get_lint_config( 'ruleset' ) ) {
			return $phpcs_ruleset;
		}

		if ( in_array( '--standard', $_SERVER['argv'] ) ) {
			$pos = array_search( '--standard', $_SERVER['argv'] );

			if ( isset( $_SERVER['argv'][$pos + 1] ) ) {
				return $_SERVER['argv'][$pos + 1];
			}
		}

		$paths = [
			$root_path,
			rtrim( preg_replace( '/\/web\/wp$/', '', $root_path ), '/' ),
			rtrim( $_SERVER['HOME'], '/' ) . '/.wp-cli',
		];

		if ( ! in_array( getcwd(), $paths ) ) {
			$paths[] = getcwd();
		}

		$files = [
			'.phpcs.ruleset.xml',
			'phpcs.ruleset.xml',
			'ruleset.xml'
		];

		$phpcs_ruleset = '';

		foreach ( $paths as $path ) {
			if ( empty( $path ) || ! file_exists( $path ) ) {
				continue;
			}

			foreach ( $files as $file ) {

				if ( file_exists( $path . '/' . $file ) ) {
					$phpcs_ruleset = $path . '/' . $file;
					break;
				}
			}
		}

		return $phpcs_ruleset;
	}

	/**
	 * Get PHPCS bin.
	 *
	 * @param string $root_path
	 *
	 * @return string
	 */

	private function get_phpcs_bin( $root_path ) {
		if ( $phpcs_bin = $this->get_lint_config( 'bin' ) ) {
			return $phpcs_bin;
		}

		$paths = [
			$root_path,
			preg_replace( '/\/web\/wp$/', '', $root_path )
		];

		if ( ! in_array( getcwd(), $paths ) ) {
			$paths[] = getcwd();
		}

		$file      = '/vendor/bin/phpcs';
		$phpcs_bin = 'phpcs';

		foreach ( $paths as $path ) {
			if ( empty( $path ) ) {
				continue;
			}

			if ( file_exists( $path . $file ) ) {
				$phpcs_bin = $path . $file;
				break;
			}
		}

		return $phpcs_bin;
	}

	/**
	 * Invoke the lint command.
	 *
	 * @param array $args
	 * @when before_wp_load
	 */

	public function __invoke( array $args = [] ) {
		if ( empty( $args ) ) {
			WP_CLI::error( "No directory to lint\n\nExample:\n\n    $ wp lint path/to/directory\n" );
		}

		if ( ! file_exists( $args[0] ) ) {
			WP_CLI::error( sprintf( 'The file "%s" does not exist', $args[0] ) );
		}

		$root_path     = rtrim( ABSPATH, '/' );
		$phpcs_bin     = $this->get_phpcs_bin( $root_path );
		$phpcs_ruleset = $this->find_phpcs_ruleset( $root_path );

		if ( empty( $phpcs_ruleset ) ) {
			WP_CLI::error( 'Cannot find a standard to use.' );
		}

		$command_args  = '-s --extensions=php --standard=' . $phpcs_ruleset;
		$command       = sprintf( '%s %s %s', $phpcs_bin, $command_args, $args[0] );

		if ( WP_CLI::get_config( 'debug' ) ) {
			echo sprintf( "Running command: %s \n", $command );
		}

		exec( $command, $output, $status );

		if ( count( $output ) === 1 && strpos( $output[0], 'ERROR' ) === false ) {
			$output = [];
		}

		foreach ( $output as $line ) {
			if ( strpos( $output[0], 'ERROR' ) === false ) {
				WP_CLI::log( $line );
			} else {
				WP_CLI::error( str_replace( 'ERROR: ', '', $line ) );
			}
		}

		if ( $status !== 0 ) {
			WP_CLI::error( 'Sorry, but your code does not follow the code style. Please fix before commit.' );
		} else {
			WP_CLI::success( 'Good job! Your code follows the code style.' );
		}
	}

}

\WP_CLI::add_command( 'lint', 'Lint_Command' );

