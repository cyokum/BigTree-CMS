<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */
	
	Auth::user()->requireLevel(1);
	
	$analytics->oAuthSetToken($_GET["code"]);
	
	if ($analytics->OAuthError) {
		Utils::growl("Google Analytics", $analytics->OAuthError, "error");
		Router::redirect(MODULE_ROOT."configure/");
	} else {
		Utils::growl("Analytics", "Successfully Authenticated");
		Router::redirect(MODULE_ROOT);
	}
	