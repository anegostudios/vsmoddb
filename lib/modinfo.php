<?php

//NOTE8Rennorb): The documentation for ModDependencies specified that the version marks the 'minimum version' of the dependency!
// https://github.com/anegostudios/vsapi/blob/master/Common/API/ModDependency.cs
// The modinfo array therefore contains dependencies with version 0 for instances where the version was not specified or set to '*'.
// :ModDependenciesSpecifyMinVersion

/**
 * @param string $filepath
 * @param array{'id':string|null, 'name':string|null, 'version':int, 'type':'Theme'|'Content'|'Code'|null, 'side':'Universal'|'Client'|'Server'|null, 'requiredOnClient':bool, 'requiredOnServer':bool, 'networkVersion':int, 'description':string|null, 'rawAuthors':string|null, 'rawContributors':string|null, 'website':string|null, 'iconPath':string|null, 'rawDependencies':string|null, 'errors':string|null} &$modInfo
 * @return bool false on error
 */
function modpeek($filepath, &$modInfo)
{
	global $config;

	$args = ['dotnet', $config['basepath'] . 'util/modpeek.dll', '-p', $filepath];
	$modpeek = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, sys_get_temp_dir());


	//NOTE(Rennorb): php subprocesses are a mess...
	// We had reports of random "null" errors, which are very likely a race condition between the the subprocess and the main one.
	// We cannot call close then get_status, because close destroys the resource.
	// We cannot rely on the exit code returned by close, because there are 5 different reasons it might be unreliable (see remarks / comments on https://www.php.net/manual/en/function.proc-close.php).
	// So we do the thing every other framework also does... a sleep loop while polling the exit status.
	// I think this is acceptable, because this only really triggers for file uploads which are a reasonably rare occurrence, and the sleeping should only trigger in some cases.
	// Would still like to not have to do this, but there doesn't seem to be another way for now.
	//NOTE(Rennorb): On windows, you cannot really test this issue, because steams are blocking (which you cannot change)
	// and the stream_get_contents calls will only release once the process has already exited.
	$info = ''; $errors = '';
	do {
		$info   .= stream_get_contents($pipes[1]);
		$errors .= stream_get_contents($pipes[2]);

		$status = proc_get_status($modpeek);
		if($status['running'] === false) {
			$exitcode = $status['exitcode'];
			break;
		}
		usleep(10_000); // 10ms
	} while(true);

	proc_close($modpeek); // Exit process and closes pipes.


	$modInfo = [
		'id'               => null,
		'name'             => null,
		'version'          => 0,
		'type'             => null,
		'side'             => null,
		'requiredOnServer' => false,
		'requiredOnClient' => false,
		'networkVersion'   => 0,
		'description'      => null,
		'website'          => null,
		'iconPath'         => null,
		'rawAuthors'       => null,
		'rawContributors'  => null,
		'rawDependencies'  => null,
		'errors'           => trim($errors) ?: null
	];

	//NOTE(Rennorb): Validation happens in modpeek, the only thing we need to be aware of is that a description or name may contain arbitrary characters.
	for($line = strtok($info, "\r\n"); $line !== false; $line = strtok("\r\n")) {
		splitOnce($line, ': ', $prop, $rawValue);
		switch($prop) {
			case 'Id':          $modInfo['id']          = $rawValue ?: null; break;
			case 'Name':        $modInfo['name']        = $rawValue ?: null; break;
			case 'Website':     $modInfo['website']     = $rawValue ?: null; break;
			case 'IconPath':    $modInfo['iconPath']    = $rawValue ?: null; break;
			case 'Type':        $modInfo['type']        = $rawValue ?: null; break;
			case 'Side':        $modInfo['side']        = $rawValue ?: null; break;

			case 'RequiredOnClient':  $modInfo['requiredOnClient']  = boolval($rawValue); break;
			case 'RequiredOnServer':  $modInfo['requiredOnServer']  = boolval($rawValue); break;

			case 'Description': $modInfo['description'] = str_replace('\n', "\n", $rawValue) ?: null; break;

			case 'Version':        $modInfo['version']        = compileSemanticVersion($rawValue) ?: 0; break;
			case 'NetworkVersion': $modInfo['networkVersion'] = compileSemanticVersion($rawValue) ?: 0; break;

			case 'Authors':      $modInfo['rawAuthors']      = $rawValue ?: null; break;
			case 'Contributors': $modInfo['rawContributors'] = $rawValue ?: null; break;
			case 'Dependencies': $modInfo['rawDependencies'] = $rawValue ?: null; break;
		}
	}

	return $exitcode == 0 && !$errors;
}

/** Deserializes 'rawAuthors', 'rawContributors' and 'rawDependencies' into array fields 'authors', 'contributors' and 'dependencies'.
  * @param array{'id':string|null, 'name':string|null, 'version':int, 'networkVersion':int, 'description':string|null, 'rawAuthors':string|null, 'authors':string[], 'rawContributors':string|null, 'contributors':string[], 'website':string|null, 'rawDependencies':string|null, 'dependencies':array{string:int}, 'errors':string|null} &$modInfo
 */
function deserializeModInfoArrayFields(&$modInfo)
{
	$modInfo['autors'] = $modInfo['rawAuthors']
		// Unescape escaped names here     ',\ '  -> ', '
		? array_map(fn($n) => str_replace(',\ ', ', ', $n), explode(', ', $modInfo['rawAuthors']))
		: [];

	$modInfo['contributors'] = $modInfo['rawContributors']
		// Unescape escaped names here     ',\ '  -> ', '
		? array_map(fn($n) => str_replace(',\ ', ', ', $n), explode(', ', $modInfo['rawContributors']))
		: [];

	$deps = [];
	if($modInfo['rawDependencies']) {
		foreach(explode(', ', $modInfo['rawDependencies']) as $dep) {
			splitOnce($dep, '@', $id, $version);
			// modpeek will give us a dependency without version (and without '@') in the 'any' case.
			$deps[$id] = $version ? compileSemanticVersion($version) : 0; // :ModDependenciesSpecifyMinVersion
		}
	}
	$modInfo['dependencies'] = $deps;
}
