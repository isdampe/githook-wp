(function(window, $) {

	/**
	 * Sets the value of #githook_generate_keys to "yes".
	 * @param  {Event} e The event handler.
	 * @return {Void}
	 */
	const setGenerateKeys = function(e) {
		$("#githook_generate_keys").val("yes");
	};

	/**
	 * Called when the page is ready.
	 * @return {Void}
	 */
	$(document).ready(function() {
		$("#githook-trigger-key-generation").on("click", setGenerateKeys);
	});

})(window, jQuery);