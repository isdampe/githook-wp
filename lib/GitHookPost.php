<?php

class GitHookPost {

	public function __construct() {
		add_action("init", [$this, "register_post_type"]);
	}

	/**
	 * Registers the GitHook post type.
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(GITHOOK_POST_TYPE, [
			"label" => __("GitHook"),
			"labels" => [
				"name" => __("GitHooks"),
				"singular_name" => __("GitHook"),
				"add_new" => __("Add New GitHook"),
				"add_new_item" => __("Add New GitHook"),
				"edit_item" => __("Edit GitHook"),
				"new_item" => __("New GitHook"),
				"view_item" => __("View GitHook"),
				"view_items" => __("View GitHooks")
			],
			"description" => __("Associate GitHub and GitLab web hooks with events."),
			"public" => false,
			"exclude_from_search" => true,
			"publicly_queryably" => false,
			"show_ui" => true,
			"show_in_nav_menus" => false,
			"show_in_menu" => true,
			"show_in_admin_bar" => false,
			"menu_position" => 80,
			"menu_icon" => "dashicons-admin-plugins",
			"hierarchical" => false,
			"supports" => ["title", "revisions"],
			"register_meta_box_cb" => [$this, "register_meta_boxes"],
			"has_archive" => false,
			"can_export" => true,
			"delete_with_user" => false,
			"show_in_rest" => false
		]);

		add_action("wp_insert_post", [$this, "on_githook_insert"], 10, 3);
		add_action("admin_enqueue_scripts", [$this, "enqueue_scripts"]);
	}

	/**
	 * Enqueues the GitHook admin front-end script.
	 * @param  string $hook The hook string identifier.
	 * @return void
	 */
	public function enqueue_scripts($hook) {
		if ($hook !== "post.php")
			return;

		wp_enqueue_script("githook-admin-post-script", plugins_url("/../js/githook.js", __FILE__),
			["jquery"], GITHOOK_VERSION, false);
	}

	/**
	 * Automatically generates the secret key for a new githook.
	 * @param  int $post_ID The post ID.
	 * @param  WP_Post $post The current post object.
	 * @param  bool $update Whether an update is taking place or not.
	 * @return void
	 */
	public function on_githook_insert($post_ID, $post, $update) {
		if ($update) {
			$this->process_config_updates($post);
		} else {
			// This is only post creation. Just create the secret.
			update_post_meta($post_ID, "githook_secret",
				wp_generate_password(32, true));
		}
	}

	/**
	 * Processes POST data and saves it against a post.
	 * @param  WP_Post $post The current post.
	 * @return void
	 */
	private function process_config_updates($post) {
		$config = GitHookConfig::get_config($post);
		foreach ($config as $entry) {
			if ($entry["readonly"] == true)
				continue;

			if (! empty($_POST[$entry["name"]])) {
				// Only update if we've changed.
				if ($entry["value"] !== $_POST[$entry["name"]])
					update_post_meta($post->ID, $entry["name"],
						sanitize_text_field($_POST[$entry["name"]]));
			} else {
				if ($entry["value"])
					delete_post_meta($post->ID, $entry["name"]);
			}
		}

		//Do we need to generate keys?
		if (! empty($_POST["githook_generate_keys"]) &&
			$_POST["githook_generate_keys"] == "yes") {

			$secret = get_post_meta($post->ID, "githook_secret", true);
			if ($secret) {
				if (! GitHookConfig::generate_keys($secret)) {
					//Error.
					throw new Exception("Could not generate githook deploy keys.");
				}
			}
		}

	}

	/**
	 * Registers the necessary meta boxes for a githook post.
	 * @param  WP_Post $post The current post object.
	 * @return void
	 */
	public function register_meta_boxes($post) {
		add_meta_box("githook_config", __("Configuration", "githook"),
			[$this, "render_config_meta_box"], null, "normal", "high");

		if ($post->post_status == "publish") {
			add_meta_box("githook_keys", __("Deploy key", "githook"),
				[$this, "render_deploy_keys_meta_box"], null, "normal", "low");
		}
	}

	/**
	 * Renders the githook configuration meta box.
	 * @param  WP_Post $post The current post object.
	 * @return void
	 */
	public function render_config_meta_box($post) {
		$config = GitHookConfig::get_config($post);

		foreach ($config as $entry) {
			// Don't render the secret until the post is "published".
			if ((
					$entry["name"] == "githook_secret" ||
					$entry["name"] == "githook_payload_url"
				) && $post->post_status !== "publish")
					continue;

			switch ($entry["type"]) {
				case "text":
					echo sprintf('<p>
						<label for="%s"><strong>%s</strong></label><br>
						<em>%s</em><br>
						<input type="text" %s class="widefat" name="%s" id="%s" value="%s" />
						</p>',
						$entry["name"], $entry["label"], $entry["description"],
						($entry["readonly"] ? "readonly" : ""), $entry["name"],
						$entry["name"], esc_html($entry["value"]));
				break;
			}
		}

	}

	/**
	 * Renders the githook deploy keys meta box.
	 * @param  WP_Post $post The current post object.
	 * @return void
	 */
	public function render_deploy_keys_meta_box($post) {
		$secret = get_post_meta($post->ID, "githook_secret", true);
		if (! $secret) {
			echo "<p><strong>Error:</strong> no secret has been set.</p>";
			return;
		}

		$keys = GitHookConfig::get_keys($secret);
		if (! array_key_exists("public", $keys)) {
			echo '<p>If your repository is private, you\'ll need to generate a set
				of deploy keys and save them in your GitHub / GitLab repository so
				that GitHook has read access to your repository. If your repository
				is public, you don\'t need to worry about deploy keys.</p>';
			echo '<input type="hidden" id="githook_generate_keys" name="githook_generate_keys" value="no" />';
			echo '<p><button id="githook-trigger-key-generation" class="button button-primary button-large">Generate keys</button></p>';
			return;
		}

		echo '<p>Note: For security reasons, do not give this key write access to your git repository.</p>';
		echo sprintf('<textarea readonly class="widefat" rows="10">%s</textarea>',
			esc_html($keys["public"]));
	}

}