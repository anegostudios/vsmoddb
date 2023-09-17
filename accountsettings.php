<?php
if (empty($user)) {
	$view->display("404");
}

$view->assign("user", $user);

if (!empty($_POST["save"])) {

	$followwebhook = NULL;
	$errormessage = NULL;
	if (!empty($_POST["followwebhook"])) {
		if(isValidHttpUrl($_POST["followwebhook"])){
			$followwebhook = $_POST["followwebhook"];
		} else {
			$errormessage = "The Follow Webhook needs to be a valid http or https url.";
		}
	}

	$mentionwebhook = NULL;
	if (!empty($_POST["mentionwebhook"])) {
		if(isValidHttpUrl($_POST["mentionwebhook"])){
			$mentionwebhook = $_POST["mentionwebhook"];
		} else {
			if(!isset($errormessage)){
				$errormessage = "";
			}else{
				$errormessage .= " ";
			}
			$errormessage .= "The Mention Webhook needs to be a valid http or https url.";
		}
	}
	if(isset($errormessage)){
		$view->assign("errormessage", $errormessage);
	}

	$data = array(
		//"name" => strip_tags($_POST["name"]),
		//"email" =>strip_tags($_POST["email"]),
		"followwebhook" => $followwebhook,
		"mentionwebhook" => $mentionwebhook,
		"timezone" => array_keys($timezones)[intval($_POST["timezone"])],
	);

	update("user", $user["userid"], $data);
	$view->assign("okmessage", "New profile information saved.");

	$user = array_merge($user, $data);
}


$view->assign("timezones", array_keys($timezones));

$view->assign("user", $user);
$view->display("accountsettings.tpl");


function isValidHttpUrl($url)
{
	// Use filter_var to check if it's a valid URL format
	if (filter_var($url, FILTER_VALIDATE_URL) === false) {
		return false;
	}

	// Use parse_url to extract components
	$parsedUrl = parse_url($url);

	// Check if the scheme is 'http' or 'https'
	if ($parsedUrl && isset($parsedUrl['scheme']) && ($parsedUrl['scheme'] === 'http' || $parsedUrl['scheme'] === 'https')) {
		// Check if the host is 'discord.com' or 'discordapp.com'
        if (isset($parsedUrl['host']) && (strpos($parsedUrl['host'], 'discord.com') !== false || strpos($parsedUrl['host'], 'discordapp.com') !== false)) {
            // Check if the path starts with '/api/webhooks/'
            if (isset($parsedUrl['path']) && strpos($parsedUrl['path'], '/api/webhooks/') === 0) {
                return true;
            }
        }
	}

	return false;
}
