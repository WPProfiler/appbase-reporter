<?php

/** @noinspection AutoloadingIssuesInspection
 * @noinspection SpellCheckingInspection
 */

namespace WPProfiler\Reporter\AppBase {

	use WPProfiler\Core\FileSystemReporter;

	if ( function_exists( 'wp_profiler' ) ) {

		/**
		 * Class Reporter
		 *
		 * @package WPProfiler\Reporter\AppBase
		 */
		class Reporter extends FileSystemReporter {

			/**
			 * @noinspection PhpUnused
			 * @inheritDoc
			 */
			public function execute( $filename, array $data ) {
				add_action( 'wp_profiler_reporter_filesystem_reporter_report_saved', [
					$this,
					'schedule_report_processing',
				] );
				parent::execute( $filename, $data );

			}

			/**
			 * @noinspection PhpUnused
			 *
			 * @param $path
			 */
			public function schedule_report_processing( $path ) {
				/** @noinspection SpellCheckingInspection */
				wp_schedule_single_event( time(), 'wp_profiler_reporter_appbase_process_report', [ $path ] );
			}
		}


		/**
		 * @param $path
		 *
		 * @noinspection PhpUnused
		 */
		function process( $path ) {
			if ( ! file_exists( $path ) ) {
				return;
			}
			$report = file_get_contents( $path );
			$report = json_decode( $report, true );

			if ( ! isset( $report['collectors']['hook'] ) ) {
				return;
			}

			maybe_setup_mappings();

			$hook_info     = [];
			$function_info = [];
			calculate( $report['collectors']['hook'], $hook_info, $function_info );
			$report['collectors']['hook']     = array_values( $hook_info );
			$report['collectors']['function'] = array_values( $function_info );

			api_post( '_doc', $report );
			unset( $hook_info, $function_info, $report );
			@unlink( $path );
		}

		/**
		 * @param     $data
		 * @param     $hook_store
		 * @param     $function_store
		 * @param int $level
		 */
		function calculate( $data, &$hook_store, &$function_store, $level = 0 ) {
			foreach ( $data['children'] as $child ) {
				if ( ! isset( $hook_store[ $child['hook'] ] ) ) {
					$hook_store[ $child['hook'] ] = [ 'name' => $child['hook'], 'functions' => [], 'time' => 0 ];
				}
				$current_hook = &$hook_store[ $child['hook'] ];

				$current_hook['time'] += $child ['time'];

				if ( isset( $child['functions'] ) ) {
					foreach ( $child['functions'] as $function ) {
						if ( ! isset( $function_store[ $function['function'] ] ) ) {
							$function_store[ $function['function'] ] = [
								'name'  => $function['function'],
								'line'  => $function['line'],
								'file'  => $function['file'],
								'count' => 0,
								'time'  => 0,
							];
						}
						$function_store[ $function['function'] ]['time'] += $function['time'];
						$function_store[ $function['function'] ]['count'] ++;
						$current_hook['functions'][] = $function['function'];
					}
				}

				if ( isset( $child['hook']['children'] ) && is_array( $child['hook']['children'] ) ) {
					calculate( $child['hook']['children'], $hook_store, $function_store, ++ $level );
				}
			}
			if ( 0 === $level ) {
				foreach ( $hook_store as $hook => &$hook_data ) {
					$hook_data['functions'] = array_unique( $hook_data['functions'] );
				}
			}
		}

		/**
		 * @param        $path
		 * @param array  $data
		 * @param string $method
		 *
		 * @return mixed
		 * @noinspection PhpUndefinedConstantInspection
		 */
		function api_request( $path, $data = [], $method = 'get' ) {
			$func = 'wp_remote_' . $method;

			if ( ! function_exists( $func ) ) {
				$func = __NAMESPACE__ . '\\' . $func;
			}

			/** @noinspection PhpUndefinedConstantInspection */
			return $func( sprintf( 'https://scalr.api.appbase.io/%s/%s', WP_PROFILER_APPBASE_APP_NAME, $path ), [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( WP_PROFILER_APPBASE_APP_API_KEY ),
					'Content-Type'  => 'application/json',
				],
				'body'    => 'get' === $method ? null : wp_json_encode( $data ),
			] );
		}

		/**
		 * @param $path
		 *
		 * @return mixed
		 */
		function api_get( $path ) {
			return api_request( $path );
		}

		/**
		 * @param       $path
		 * @param array $data
		 *
		 * @return mixed
		 */
		function api_post( $path, $data = [] ) {
			return api_request( $path, $data, 'post' );
		}

		/**
		 * @param       $path
		 * @param array $data
		 *
		 * @return mixed
		 */
		function api_put( $path, $data = [] ) {
			return api_request( $path, $data, 'put' );
		}

		/**
		 * @param       $url
		 * @param array $args
		 *
		 * @return array|\WP_Error
		 * @noinspection PhpUnused
		 */
		function wp_remote_put( $url, $args = [] ) {
			$http     = _wp_http_get_object();
			$defaults = [ 'method' => 'PUT' ];
			$r        = wp_parse_args( $args, $defaults );

			return $http->request( $url, $r );
		}

		/**
		 * @param int $attempt
		 */
		function maybe_setup_mappings( $attempt = 0 ) {
			static $setup = false;

			if ( $setup ) {
				return;
			}

			$response = wp_remote_retrieve_body( api_get( '_mapping' ) );
			if ( empty( $response ) && $attempt < 5 ) {
				maybe_setup_mappings( ++ $attempt );

				return;
			}

			$response = json_decode( $response, true );
			foreach ( [ 'hook', 'function', 'query', 'db' ] as $collector ) {
				/** @noinspection PhpUndefinedConstantInspection */
				if ( ! isset( $response[ WP_PROFILER_APPBASE_APP_NAME ]['mappings']['properties']['collectors'][ $collector ] ) ) {
					$func = __NAMESPACE__ . '\\setup_mappings_' . $collector;
					$func();
				}
			}
			$setup = true;
		}

		/**
		 * @param $structure
		 *
		 * @return mixed
		 */
		function create_mapping( $structure ) {
			return api_put( '_mapping', $structure );
		}

		/**
		 *
		 * @noinspection PhpUnused
		 */
		function setup_mappings_hook() {
			setup_mappings_nested( 'hook' );
		}

		/**
		 *
		 * @noinspection PhpUnused
		 */
		function setup_mappings_function() {
			setup_mappings_nested( 'function' );
		}

		/**
		 *
		 * @noinspection PhpUnused
		 */
		function setup_mappings_query() {
			setup_mappings_nested( 'query' );
		}

		/**
		 *
		 * @noinspection PhpUnused
		 */
		function setup_mappings_db() {
			setup_mappings_nested( 'db' );
		}

		/**
		 * @param $field
		 */
		function setup_mappings_nested( $field ) {
			create_mapping( [
				'properties' => [
					'collectors' => [
						'properties' => [
							$field => [
								'type' => 'nested',
							],
						],
					],
				],
			] );
		}

		/** @noinspection SpellCheckingInspection */
		add_action( 'wp_profiler_reporter_appbase_process_report', __NAMESPACE__ . '\\process' );

		wp_profiler()->set_report_handler( new Reporter() );
	}
}
