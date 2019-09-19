<?php defined("GITHOOK_VERSION") || exit;

class GitHookWebhook {
	private $secret;
	private $repository;
	private $repo_path;
	private $branch;
	private $algo;

	public function __construct(string $secret, string $repo_path,
		string $branch, string $private_key_fp) {
		$this->secret = $secret;
		$this->repo_path = $repo_path;
		$this->branch = $branch;

		$this->git = sprintf("ssh-agent bash -c 'ssh-add %s; git {{command}}'",
			$private_key_fp);

		$this->execute();
	}

	/**
	 * Encodes a git command.
	 * @param  string $command The git command (i.e. "pull")
	 * @return string The git command string.
	 */
	private function git_command($command): string {
		return str_replace("{{command}}", $command, $this->git);
	}

	/**
	 * Runs the main git events.
	 * Verifies GitHub signatures, etc.
	 * @return void
	 */
	public function execute() {
		$content = file_get_contents("php://input");
		if (! $content) {
			$this->response(400, "No content received.");
			return;
		}

		$json = json_decode($content, true);
		if (! $json) {
			$this->response(400, "The content received was not JSON. Please ensure you've set your webhook content type as 'application/json' on GitHub.");
			return;
		}

		if (! $secret && isset($_SERVER["HTTP_X_HUB_SIGNATURE"])) {
			list($algo, $secret) = explode("=", $_SERVER["HTTP_X_HUB_SIGNATURE"], 2) + array("", "");
		} elseif (isset($_SERVER["HTTP_X_GITLAB_$this->secret"])) {
			$secret = $_SERVER["HTTP_X_GITLAB_$this->secret"];
		} elseif (isset($_GET["token"])) {
			$secret = $_GET["token"];
		}

		if (isset($json["checkout_sha"])) {
			$sha = $json["checkout_sha"];
		} elseif (isset($_SERVER["checkout_sha"])) {
			$sha = $_SERVER["checkout_sha"];
		} elseif (isset($_GET["sha"])) {
			$sha = $_GET["sha"];
		}

		$final_buffer = "";

		// Check for a GitHub signature
		if (!empty($this->secret) && isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) && $secret !== hash_hmac($algo, $content, $this->secret)) {
			$this->response(403, "X-Hub-Signare does not match secret key");
			return;
		// Check for a GitLab token
		} elseif (!empty($this->secret) && isset($_SERVER["HTTP_X_GITLAB_$this->secret"]) && $secret !== $this->secret) {
			$this->response(403, "X-GitLab-Token does not match secret key");
			return;
		// Check for a $_GET token
		} elseif (!empty($this->secret) && isset($_GET["token"]) && $secret !== $this->secret) {
			$this->response(403, "Query param 'token' does not match secret key");
			return;
		// If none of the above match, but a token exists, exit
		} elseif (!empty($this->secret) && !isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) && !isset($_SERVER["HTTP_X_GITLAB_$this->secret"]) && !isset($_GET["token"])) {
			$this->response(403, "No token was provided to verify against the secret key");
			return;
		} else {
			// Only execute on matching branch events.
			if (
				$json["ref"] === $this->branch ||
				$json["ref"] === sprintf("refs/heads/%s", $this->branch) ||
				$this->branch == "") {

				// Make sure the directory is a repository.
				if (file_exists($this->repo_path . "/.git") && is_dir($this->repo_path)) {
					chdir($this->repo_path);

					/**
					* Attempt to reset specific hash if specified
					*/
					if (! empty($_GET["reset"]) && $_GET["reset"] === "true") {
						exec($this->git_command(" reset --hard HEAD 2>&1"), $output, $exit);

						// Reformat the output as string.
						$output = (! empty($output) ? implode("\n", $output) : "") . "\n";

						if ($exit !== 0) {
							$this->response(500, sprintf("=== ERROR: Reset to head failed in '%s' ===\n%s",
								$this->repo_path, $output));
							return;
						}

						$final_buffer .= sprintf("=== Reset to head OK ===\n%s", $output);
					}


					/**
					 * NOTE: This is where pre git event hooks could be executed.
					 */

					/**
					* Attempt to pull, returing the output and exit code
					*/
					exec($this->git_command(" pull 2>&1"), $output, $exit);
					$output = (! empty($output) ? implode("\n", $output) : "") . "\n";

					if ($exit !== 0) {
						$this->response(500, sprintf("=== ERROR: Pull failed in '%s' ===\n%s",
							$this->repo_path, $output));
						return;
					}

					$final_buffer .= sprintf("\n=== Pull OK ===\n%s", $output);

					/**
					* Attempt to checkout specific hash if specified
					*/
					if (! empty($sha)) {
						exec($this->git_command(" reset --hard {$sha} 2>&1"), $output, $exit);
						$output = (! empty($output) ? implode("\n", $output) : "") . "\n";

						// if an error occurred, return 500 and log the error
						if ($exit !== 0) {
							$this->response(500, sprintf("=== ERROR: Reset to hash using SHA '%s' in '%s' failed ===\n%s",
								$sha, $this->repo_path, $output));
							return;
						}

						$final_buffer .= sprintf("\n=== Reset to hash '%s' OK ===\n%s", $sha, $output);
					}

					/**
					 * NOTE: This is where post git event hooks could be executed.
					 */

					$this->response(200, $final_buffer);
					return;

				} else {
					// prepare the generic error
					$error = "=== ERROR: DIR `" . $this->repo_path . "` is not a repository ===\n";

					// try to detemrine the real error
					if (!file_exists($this->repo_path)) {
						$error = "=== ERROR: DIR `" . $this->repo_path . "` does not exist ===\n";
					} elseif (!is_dir($this->repo_path)) {
						$error = "=== ERROR: DIR `" . $this->repo_path . "` is not a directory ===\n";
					}

					$this->response(400, $error);
				}
			} else {
				$this->response(200, sprintf("Event was for branch '%s' but GitHook is only configured to execute on the '%s' branch",
					$json["ref"], $this->branch));
				return;
			}
		}

	}

	/**
	 * Writes response data down the line.
	 * @param  int $http_status_code The HTTP status code.
	 * @param  string  $message The message to send.
	 * @return void
	 */
	public function response(int $http_status_code = 500,
		string $message = "An error occurred") {

		http_response_code($http_status_code);
		header("Content-Type: text/plain");
		echo $message;
		exit;
	}
}