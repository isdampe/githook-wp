<?php defined("GITHOOK_VERSION") || exit;

class GithookIntercept {
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
		if (! $this->verify_request())
			return;

		/*
		$args = $this->settings->get_all();
		$webhook = new WebHook($args["secret"], $args["git_repo"],
			$args["git_dir"], "refs/heads/master");

		$webhook->execute();
		*/
	}

	/**
	 * Checks that the request should be intercepted by GitHook.
	 * @return bool True if an interception is required.
	 */
	private function verify_request(): bool {
		$base_uri = $_SERVER["REQUEST_URI"];
		if (substr($base_uri, 0, 1) == "/")
			$base_uri = substr($base_uri, 1);

		if (substr($base_uri, strlen($base_uri) -1, 1) == "/")
			$base_uri = substr($base_uri, 0, strlen($base_uri) -1);

		$parts = explode("/", $base_uri);
		if (count($parts) < 2)
			return false;

		$tail = $parts[count($parts) -2] . "/" . $parts[count($parts) -1];
		return ($tail == $this->hook_input_uri);
	}
}