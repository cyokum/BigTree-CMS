<?php
	$admin->verifyCSRFToken();
	$admin->refreshLock($_POST["table"],$_POST["id"]);
