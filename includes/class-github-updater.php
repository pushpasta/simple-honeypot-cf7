<?php
/**
 * GitHub-hosted plugin auto-updater.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks the GitHub Releases API for updates and integrates with the WordPress
 * update system. Uses a release asset named `{repo}_v{version}.zip`.
 *
 * Site transient caches: release API (24 h), readme per tag (1 year).
 *
 * @property string $owner GitHub owner.
 * @property string $repo  GitHub repository name.
 * @property string $slug  Plugin slug (directory name).
 */
final class GitHub_Updater {

	const API_TIMEOUT       = 10;
	const GITHUB_API_ACCEPT = 'application/vnd.github+json';

	/**
	 * GitHub owner.
	 *
	 * @var string
	 */
	private $owner = 'pushpasta';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $repo = 'simple-honeypot-cf7';

	/**
	 * Plugin slug (directory name).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->slug = dirname( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME );

		add_filter( 'site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_name' ), 10, 4 );
	}

	/**
	 * Inject update data into the WordPress update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		if ( ! empty( $transient->response[ SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ] ) ) {
			return $transient;
		}

		$release = $this->fetch_latest_release();

		if ( ! $release || empty( $release->assets ) ) {
			return $transient;
		}

		$asset = $this->find_release_asset( $release->assets );

		if ( ! $asset ) {
			return $transient;
		}

		$version = $this->version_from_tag( $release->tag_name );

		if ( ! $version || version_compare( $version, SIMPLE_HONEYPOT_CF7_VERSION, '<=' ) ) {
			return $transient;
		}

		$transient->response[ SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ] = (object) array(
			'slug'        => $this->slug,
			'plugin'      => SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME,
			'new_version' => $version,
			'url'         => $release->html_url ?? '',
			'package'     => $asset->browser_download_url ?? '',
		);

		return $transient;
	}

	/**
	 * Provide plugin details for the "View details" modal.
	 *
	 * Reads the plugin readme.txt from the release tag on GitHub so that
	 * requirements, sections, and other metadata always reflect the release
	 * being offered, not the currently installed version. Falls back to the
	 * local readme.txt if the remote fetch fails.
	 *
	 * @param mixed  $result  Default result (false).
	 * @param string $action  'plugin_information'.
	 * @param object $args    Request arguments.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $this->slug !== $args->slug ) {
			return $result;
		}

		$release = $this->fetch_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$version = $this->version_from_tag( $release->tag_name );
		$asset   = $this->find_release_asset( $release->assets );

		// Fetch readme.txt from the release tag on GitHub.
		$readme = $this->release_readme( $release->tag_name );

		// Fall back to the local readme.txt if the remote fetch failed.
		if ( null === $readme ) {
			$readme = $this->local_readme();
		}

		$sections = array();

		if ( $readme ) {
			$sections['description']  = isset( $readme['sections']['description'] ) ? $this->readme_to_html( $readme['sections']['description'] ) : '';
			$sections['installation'] = isset( $readme['sections']['installation'] ) ? $this->readme_to_html( $readme['sections']['installation'] ) : '';
			$sections['faq']          = isset( $readme['sections']['faq'] ) ? $this->readme_to_html( $readme['sections']['faq'] ) : '';
		}

		$sections['changelog'] = $this->readme_to_html( $release->body ?? '' );

		$upgrade_notice = '';
		if ( ! empty( $release->body ) && preg_match( '/=+\s*Upgrade Notice\s*=+(.*?)(?====|$)/s', $release->body, $m ) ) {
			$upgrade_notice = $this->readme_to_html( trim( $m[1] ) );
		} elseif ( $readme && isset( $readme['sections']['upgrade-notice'] ) ) {
			$upgrade_notice = $this->readme_to_html( $readme['sections']['upgrade-notice'] );
		}

		$fallback_name = str_replace( '-', ' ', ucwords( $this->repo, '-' ) );

		return (object) array(
			'name'           => $readme['name'] ?? $fallback_name,
			'slug'           => $this->slug,
			'version'        => $version ? $version : SIMPLE_HONEYPOT_CF7_VERSION,
			'author'         => sprintf( '<a href="%s">%s</a>', esc_url( 'https://github.com/' . $this->owner . '/' ), esc_html( $this->owner ) ),
			'homepage'       => $release->html_url ?? '',
			'requires'       => $readme['requires_wp'] ?? '',
			'tested'         => $readme['tested_up_to'] ?? '',
			'requires_php'   => $readme['requires_php'] ?? '',
			'last_updated'   => $release->published_at ?? '',
			'sections'       => $sections,
			'upgrade_notice' => $upgrade_notice,
			'download_link'  => $asset ? $asset->browser_download_url : '',
		);
	}

	/**
	 * Fix the extracted directory name when the zip comes from a release tag.
	 *
	 * @param string       $source        Source directory.
	 * @param string       $remote_source Remote source file.
	 * @param \WP_Upgrader $upgrader      Upgrader instance.
	 * @param array        $hook_extra    Extra arguments.
	 * @return string
	 */
	public function fix_source_name( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}

		$correct = trailingslashit( WP_PLUGIN_DIR ) . $this->slug;

		if ( rtrim( $source, '/\\' ) === rtrim( $correct, '/\\' ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		$temp = dirname( $source ) . '/' . $this->slug . '-tmp';

		if ( ! $wp_filesystem->move( $source, $temp ) ) {
			return $source;
		}

		if ( ! $wp_filesystem->move( $temp, $correct ) ) {
			return $source;
		}

		return $correct;
	}

	/**
	 * Build the regex pattern for matching release asset names.
	 *
	 * @return string
	 */
	private function asset_pattern() {
		$escaped = preg_quote( $this->repo, '/' );

		return '/^' . $escaped . '_v\d+\.\d+\.\d+\.zip$/';
	}

	/**
	 * Fetch the plugin readme.txt from GitHub at a specific tag.
	 *
	 * Tags are immutable, so the parsed result is cached for a year.
	 *
	 * @param string $tag Release tag (e.g. "v1.1.0").
	 * @return array|null Keys: name, requires_wp, tested_up_to, requires_php, sections.
	 */
	private function release_readme( $tag ) {
		$transient_key = 'shcf7_rdm_' . md5( $tag );
		$cached        = get_site_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		/**
		 * Short-circuit the readme fetch with mock data for testing.
		 *
		 * @param array|null $readme Mock readme data.
		 * @param string     $tag    Release tag.
		 */
		$readme = apply_filters( 'shcf7_github_readme', null, $tag );

		if ( null !== $readme ) {
			set_site_transient( $transient_key, $readme, YEAR_IN_SECONDS );

			return $readme;
		}

		$url = sprintf(
			'https://raw.githubusercontent.com/%s/%s/refs/tags/%s/readme.txt',
			$this->owner,
			$this->repo,
			$tag
		);

		$response = wp_remote_get( $url, array( 'timeout' => self::API_TIMEOUT ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$content = wp_remote_retrieve_body( $response );
		$data    = $this->parse_readme( $content );

		set_site_transient( $transient_key, $data, YEAR_IN_SECONDS );

		return $data;
	}

	/**
	 * Read and parse the local readme.txt file.
	 *
	 * @return array|null
	 */
	private function local_readme() {
		$file = SIMPLE_HONEYPOT_CF7_PATH . 'readme.txt';

		if ( ! is_readable( $file ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		$content = file_get_contents( $file );

		return $this->parse_readme( $content );
	}

	/**
	 * Parse a WordPress readme.txt string into structured data.
	 *
	 * Extracts metadata from the header block and content sections.
	 *
	 * @param string $content Raw readme.txt content.
	 * @return array
	 */
	private function parse_readme( $content ) {
		$lines = explode( "\n", $content );

		$data = array(
			'name'         => '',
			'requires_wp'  => '',
			'tested_up_to' => '',
			'requires_php' => '',
			'sections'     => array(),
		);

		// Plugin name from the first line.
		if ( ! empty( $lines ) && preg_match( '/^===\s*(.+?)\s*===\s*$/', $lines[0], $m ) ) {
			$data['name'] = $m[1];
		}

		// Metadata from the header block.
		foreach ( $lines as $line ) {
			if ( preg_match( '/^Requires at least:\s*(.+)$/i', $line, $m ) ) {
				$data['requires_wp'] = trim( $m[1] );
			}
			if ( preg_match( '/^Tested up to:\s*(.+)$/i', $line, $m ) ) {
				$data['tested_up_to'] = trim( $m[1] );
			}
			if ( preg_match( '/^Requires PHP:\s*(.+)$/i', $line, $m ) ) {
				$data['requires_php'] = trim( $m[1] );
			}
		}

		// Sections: skip header lines until first == section ==.
		$start = 0;
		$count = count( $lines );

		while ( $start < $count && ! preg_match( '/^==\s+.+\s+==/', $lines[ $start ] ) ) {
			++$start;
		}

		$lines = array_slice( $lines, $start );

		$current_section = 'description';
		$buffer          = array();
		$sections        = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/^==\s*(.+?)\s*==\s*$/', $line, $m ) ) {
				if ( ! empty( $buffer ) || isset( $sections[ $current_section ] ) ) {
					$sections[ $current_section ] = trim( implode( "\n", $buffer ) );
					$buffer                       = array();
				}

				$key = sanitize_title( $m[1] );

				$map = array(
					'description'                => 'description',
					'installation'               => 'installation',
					'frequently-asked-questions' => 'faq',
					'faq'                        => 'faq',
					'screenshots'                => 'screenshots',
					'changelog'                  => 'changelog',
					'upgrade-notice'             => 'upgrade-notice',
				);

				$current_section = isset( $map[ $key ] ) ? $map[ $key ] : $key;

				continue;
			}

			$buffer[] = $line;
		}

		if ( ! empty( $buffer ) ) {
			$sections[ $current_section ] = trim( implode( "\n", $buffer ) );
		}

		$data['sections'] = $sections;

		return $data;
	}

	/**
	 * Convert WordPress readme-format text to basic HTML.
	 *
	 * Handles `= heading =`, `* list items`, and paragraph spacing.
	 *
	 * @param string $text Raw readme text.
	 * @return string HTML.
	 */
	private function readme_to_html( $text ) {
		$html    = '';
		$lines   = explode( "\n", $text );
		$in_list = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				if ( $in_list ) {
					$html   .= "</ul>\n";
					$in_list = false;
				}

				continue;
			}

			if ( preg_match( '/^=\s*(.+?)\s*=\s*$/', $trimmed, $m ) ) {
				if ( $in_list ) {
					$html   .= "</ul>\n";
					$in_list = false;
				}

				$html .= '<h4>' . esc_html( $m[1] ) . "</h4>\n";

				continue;
			}

			if ( preg_match( '/^\*\s+(.+)/', $trimmed, $m ) ) {
				if ( ! $in_list ) {
					$html   .= "<ul>\n";
					$in_list = true;
				}

				$html .= '<li>' . esc_html( $m[1] ) . "</li>\n";

				continue;
			}

			if ( $in_list ) {
				$html   .= "</ul>\n";
				$in_list = false;
			}

			$html .= '<p>' . esc_html( $trimmed ) . "</p>\n";
		}

		if ( $in_list ) {
			$html .= "</ul>\n";
		}

		return $html;
	}

	/**
	 * Fetch the latest release from the GitHub API.
	 *
	 * @return object|null
	 */
	private function fetch_latest_release() {
		$transient_key = 'shcf7_github_release';
		$cached        = get_site_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		/**
		 * Short-circuit the API call with mock data for testing.
		 *
		 * @param object|null $release Mock release object.
		 */
		$release = apply_filters( 'shcf7_github_release', null );

		if ( null !== $release ) {
			set_site_transient( $transient_key, $release, DAY_IN_SECONDS );

			return $release;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->owner,
			$this->repo
		);

		/**
		 * Override the GitHub API URL for testing.
		 *
		 * @param string $url The API URL.
		 */
		$url = apply_filters( 'shcf7_github_api_url', $url );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept'     => self::GITHUB_API_ACCEPT,
					'User-Agent' => 'SimpleHoneypotCF7/' . SIMPLE_HONEYPOT_CF7_VERSION,
				),
				'timeout' => self::API_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $release ) || JSON_ERROR_NONE !== json_last_error() || empty( $release->tag_name ) ) {
			return null;
		}

		set_site_transient( $transient_key, $release, DAY_IN_SECONDS );

		return $release;
	}

	/**
	 * Find the release asset matching the naming convention.
	 *
	 * Expects `{repo}_v{version}.zip` (produced by the GitHub Actions release workflow).
	 *
	 * @param array $assets Release assets from the GitHub API.
	 * @return object|null
	 */
	private function find_release_asset( $assets ) {
		if ( ! is_array( $assets ) ) {
			return null;
		}

		$regex = $this->asset_pattern();

		foreach ( $assets as $asset ) {
			if ( isset( $asset->name ) && preg_match( $regex, $asset->name ) ) {
				return $asset;
			}
		}

		return null;
	}

	/**
	 * Strip a leading 'v' from a tag name to get a SemVer version.
	 *
	 * @param string $tag_name Release tag (e.g. "v1.1.0" or "1.1.0").
	 * @return string|null
	 */
	private function version_from_tag( $tag_name ) {
		$version = preg_replace( '/^v/i', '', $tag_name );

		if ( ! is_string( $version ) || ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return null;
		}

		return $version;
	}
}
