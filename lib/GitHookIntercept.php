<?php defined("GITHOOK_VERSION") || exit;

class GitHookIntercept {
	private $hook_input_uri;
	private $settings;

	public function __construct() {
		$this->hook_input_uri = "githook/notify";
		add_action("template_redirect", [$this, "pull"]);
	}

	/**
	 * Executes the pull event if suitable.
	 * @return void
	 */
	public function pull() {
		$githook_request = $this->verify_request();
		if ($githook_request == "")
			return;

		if (! $this->route($githook_request)) {
			http_response_code(404);
			exit;
		}
	}

	/**
	 * Checks that the request should be intercepted by GitHook.
	 * @return string The post_name of the githook requested.
	 */
	private function verify_request(): string {

		$base_uri = $_SERVER["REQUEST_URI"];
		if (substr($base_uri, 0, 1) == "/")
			$base_uri = substr($base_uri, 1);

		if (substr($base_uri, strlen($base_uri) -1, 1) == "/")
			$base_uri = substr($base_uri, 0, strlen($base_uri) -1);

		$parts = explode("/", $base_uri);
		if (count($parts) !== 2)
			return "";

		if ($parts[0] !== "githook")
			return "";

		return $parts[1];
	}

	/**
	 * Verifies the requested git hook, fetches the configuration, checks it,
	 * and then executes the event.
	 * @param  string $githook_request The post name.
	 * @return bool
	 */
	private function route($githook_request): bool {
		$githook_posts = get_posts([
			"name" => $githook_request,
			"post_type" => GITHOOK_POST_TYPE,
			"post_status" => "publish",
			"numberposts" => 1
		]);

		if (count($githook_posts) < 1)
			return false;

		$githook_config = GitHookConfig::get_config_assoc($githook_posts[0]);

		// We need a secret and a repository directory to execute.
		if (! $githook_config["githook_secret"]["value"] ||
			! $githook_config["githook_repository_directory"]["value"])
			return false;

		$webhook = new GitHookWebHook($githook_config["githook_secret"]["value"],
			$githook_config["githook_repository_directory"]["value"],
			$githook_config["githook_branch"]["value"],
			GitHookConfig::get_key_fp($githook_config["githook_secret"]["value"]));

		return true;
	}
}