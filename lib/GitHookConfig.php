<?php

class GitHookConfig {

	const CONFIG_SCHEMA = [
		[
			"name" => "githook_payload_url",
			"label" => "Payload URL",
			"description" => "This is the url you will set in your GitHub / GitLab web hook",
			"type" => "text",
			"readonly" => true,
			"default" => ""
		],
		[
			"name" => "githook_content_type",
			"label" => "Content type",
			"description" => "This is the content type you will set on your GitHub / GitLab web hook",
			"type" => "text",
			"readonly" => true,
			"default" => "application/json"
		],
		[
			"name" => "githook_secret",
			"label" => "Secret",
			"description" => "This is the `Secret` you'll need to save into your GitHub / GitLab web hook",
			"type" => "text",
			"readonly" => true,
			"default" => ""
		],
		[
			"name" => "githook_repository_directory",
			"label" => "Repository directory",
			"description" => "The directory of the git repository to attach to",
			"type" => "text",
			"readonly" => false,
			"default" => ""
		],
		[
			"name" => "githook_branch",
			"label" => "Git branch",
			"description" => "Only trigger githook events in a specified branch. Leave blank to trigger on all branches.",
			"type" => "text",
			"readonly" => false,
			"default" => "master"
		],
		[
			"name" => "githook_repo_uri",
			"label" => "Git repo address",
			"description" => "The remote address of the git repo. Should be something like `git@github.com/[username]/[repository].git`",
			"type" => "text",
			"readonly" => false,
			"default" => ""
		],
		[
			"name" => "githook_git_method",
			"label" => "Git private key specification method",
			"description" => "Method used to specify the GitHook deploy key when invoking git",
			"type" => "select",
			"options" => [
				GITHOOK_GIT_METHOD_SSH_AGENT => "Use ssh-agent for specification"
			],
			"readonly" => false,
			"default" => "ssh-agent"
		]
	];

	/**
	 * Fetches and returns an associative array of configuration data for a GitHook post.
	 * @param  WP_Post $post The post object to fetch config for.
	 * @return Array An array matching the schema of GithookPost::CONFIG_SCHEMA
	 */
	public static function get_config($post) {
		$config = GithookConfig::CONFIG_SCHEMA;

		foreach ($config as &$entry) {
			$value = get_post_meta($post->ID, $entry["name"], true);
			if ($value) {
				$entry["value"] = $value;
			} else {
				switch ($entry["name"]) {
					// Default the repository directory to the current theme directory.
					case "githook_repository_directory":
					$entry["value"] = get_template_directory();
					break;
					case "githook_payload_url":
					$entry["value"] = sprintf("%s/githook/%s", get_bloginfo("wpurl"),
					$post->post_name);
					break;
					default:
					$entry["value"] = $entry["default"];
					break;
				}
			}
		}

		return $config;
	}

	/**
	 * Returns an associative array of name => value for git hook config.
	 * @param  WP_Post $post The WordPress post object.
	 * @return array
	 */
	public static function get_config_assoc($post) {
		$config = GithookConfig::get_config($post);
		$results = [];

		foreach ($config as $entry)
			$results[$entry["name"]] = $entry;

		return $results;
	}

	/**
	 * Returns an array containing both a public and private key for a given
	 * githook secret.
	 * @param  string $secret The Githook secret.
	 * @return array
	 */
	public static function get_keys($secret) {
		$base_file = GitHookConfig::get_key_fp($secret);
		if (file_exists($base_file) && file_exists(sprintf("%s.pub", $base_file))) {
			return [
				"public" => file_get_contents(sprintf("%s.pub", $base_file)),
				"private" => file_get_contents($base_file)
			];
		}

		return [];
	}

	/**
	 * Generates keys for a given secret.
	 * @param  string $secret The secret to generate keys for.
	 * @return bool True on success, false on failure.
	 */
	public static function generate_keys($secret) {
		$output_fp = GitHookConfig::get_key_fp($secret);
		$gen_cmd = sprintf('ssh-keygen -f %s -N ""', escapeshellarg($output_fp));
		exec($gen_cmd, $output, $exit_code);

		return (bool)($exit_code == 0);
	}

	/**
	 * Returns the file location for a given secret key.
	 * @param  string $secret The GitHook secret.
	 * @return string
	 */
	public static function get_key_fp($secret) {
		return sprintf("%s/.keys/%s", GITHOOK_BASE_PATH, sha1($secret));
	}
}