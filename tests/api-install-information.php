<?php

include_once "prelude.php";
include_once $config['basepath'].'lib/version.php';
include_once $config['basepath'].'lib/relations.php';

use PHPUnit\Framework\TestCase;

/** Helper: invoke the install-information endpoint via cURL against the running dev site. */
function callInstallInfo(array $query): array
{
	// Inside the docker network the gateway is reachable as the 'gateway' service.
	// Outside docker the dev site resolves via the host's hosts file.
	// Resolve gateway dynamically so the test works in either environment.
	$gatewayIp = gethostbyname('gateway');
	if ($gatewayIp === 'gateway') $gatewayIp = '127.0.0.1'; // fallback for host-side runs

	$url = 'https://mods.vintagestory.stage/api/v2/mods/install-information?' . http_build_query($query);
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_RESOLVE        => ['mods.vintagestory.stage:443:'.$gatewayIp],
	]);
	$body = curl_exec($ch);
	curl_close($ch);
	return json_decode($body, true) ?: [];
}

final class ApiInstallInfoTest extends TestCase
{
	public function testBackwardCompatNoResolveDepsKey(): void
	{
		$r = callInstallInfo(['ids' => 'rel-test-A@1.0.0']);
		$this->assertArrayNotHasKey('resolved', $r);
		$this->assertArrayNotHasKey('warnings', $r);
		$this->assertArrayHasKey('data', $r);
	}

	public function testWithResolveDepsReturnsResolvedAndWarnings(): void
	{
		$r = callInstallInfo(['ids' => 'rel-test-A@1.0.0', 'resolve-deps' => '1']);
		$this->assertArrayHasKey('resolved', $r);
		$this->assertArrayHasKey('warnings', $r);
	}
}
