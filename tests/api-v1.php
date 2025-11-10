<?php

include_once 'prelude.php';

class Data extends \Exception {
	public $fail;
	public $data;

	function __construct($f, $d)
	{
		$this->fail = $f;
		$this->data = $d;
	}
}

function fail($statuscode) { throw new Data(true, ['statuscode' => $statuscode]); }
function good($data) { $data['statuscode'] = '200'; throw new Data(false, $data); }

include $config['basepath']. 'lib/api/v1/functions.php';

function isStringOrNull($val) {
	return is_null($val) || is_string($val);
}

function apiGet($endpoint, $queryParams = [])
{
	global $urlparts, /* used in the api handler */ $con, $config;
	$urlparts = is_array($endpoint) ? $endpoint : [$endpoint];

	foreach($queryParams as $k => $v) {
		$_GET[$k] = $v;
	}

	try {
		include $config['basepath']. 'lib/api/v1/logic.php';
	}
	catch(Data $d) {
		return $d;
	}

	return null;
}

use PHPUnit\Framework\TestCase;

final class ApiV1Test extends TestCase {
	/** @test */
	public function tags() : void
	{
		$data = apiGet('tags');
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['tags'] as $tag) {
			$this->assertTrue(isNumber($tag['tagid']));
			$this->assertTrue(is_string($tag['name']));
			$this->assertTrue(is_string($tag['color']));
		}
	}

	/** @test */
	public function gameversions() : void
	{
		$data = apiGet('gameversions');
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['gameversions'] as $tag) {
			$this->assertTrue(isNumber($tag['tagid']));
			$this->assertNotEquals(false, compileSemanticVersion($tag['name']));
			$this->assertTrue(is_string($tag['color']));
		}
	}

	/** @test */
	public function mods() : void
	{
		$data = apiGet('mods');
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		$this->validateMods($data->data['mods']);
	}

	/** @test */
	public function modsSearchBuzzyBees() : void
	{
		$data = apiGet('mods', [
			'orderby'        => 'downloads',
			'orderdirection' => 'asc',
			'text'           => 'Bee',
			'tagids'         => ['14'], // creatures
			'author'         => 8942,
			'gameversion'    => compilePrimaryVersion('1.20'),
			'gameversions'   => compilePrimaryVersion('1.20.9'),
		]);
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		$this->validateMods($data->data['mods']);
	}

	function validateMods($mods)
	{
		foreach($mods as $mod) {
			$this->assertTrue(isNumber($mod['modid']));
			$this->assertTrue(isNumber($mod['assetid']));
			$this->assertTrue(isNumber($mod['downloads']));
			$this->assertTrue(isNumber($mod['follows']));
			$this->assertTrue(isNumber($mod['trendingpoints']));
			$this->assertTrue(isNumber($mod['comments']));
			$this->assertTrue(is_string($mod['name']));
			$this->assertTrue(is_string($mod['summary']));
			foreach($mod['modidstrs'] as $identifier) {
				$this->assertTrue(is_string($identifier));
			}
			$this->assertTrue(is_string($mod['author']));
			$this->assertTrue(isStringOrNull($mod['urlalias']));
			$this->assertTrue(is_string($mod['side']));
			$this->assertTrue(is_string($mod['type']));
			$this->assertTrue(isStringOrNull($mod['logo']));
			foreach($mod['tags'] as $tag) {
				$this->assertTrue(is_string($tag));
			}
			$this->assertTrue(is_string($mod['lastreleased']));
		}
	}

	/** @test */
	public function modMissingId() : void
	{
		$data = apiGet('mod');
		$this->assertTrue($data->fail);
		$this->assertNotEquals('200', $data->data['statuscode']);
	}

	/** @test */
	public function mod() : void
	{
		$data = apiGet(['mod', '10']);
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		$mod = $data->data['mod'];
		$this->assertTrue(isNumber($mod['modid']));
		$this->assertTrue(isNumber($mod['assetid']));
		$this->assertTrue(is_string($mod['name']));
		$this->assertTrue(is_string($mod['text']));
		$this->assertTrue(is_string($mod['author']));
		$this->assertTrue(isStringOrNull($mod['urlalias']));
		$this->assertTrue(isStringOrNull($mod['logofilename']));
		$this->assertTrue(isStringOrNull($mod['logofile']));
		$this->assertTrue(isStringOrNull($mod['logofiledb']));
		$this->assertTrue(isStringOrNull($mod['homepageurl']));
		$this->assertTrue(isStringOrNull($mod['sourcecodeurl']));
		$this->assertTrue(isStringOrNull($mod['trailervideourl']));
		$this->assertTrue(isStringOrNull($mod['issuetrackerurl']));
		$this->assertTrue(isStringOrNull($mod['wikiurl']));
		$this->assertTrue(isNumber($mod['downloads']));
		$this->assertTrue(isNumber($mod['follows']));
		$this->assertTrue(isNumber($mod['trendingpoints']));
		$this->assertTrue(isNumber($mod['comments']));
		$this->assertTrue(is_string($mod['side']));
		$this->assertTrue(is_string($mod['type']));
		$this->assertTrue(is_string($mod['created']));
		$this->assertTrue(is_string($mod['lastreleased']));
		$this->assertTrue(is_string($mod['lastmodified']));
		foreach($mod['tags'] as $tag) {
			$this->assertTrue(is_string($tag));
		}
		foreach($mod['releases'] as $release) {
			$this->assertTrue(isNumber($release['releaseid']));
			$this->assertTrue(is_string($release['mainfile']));
			$this->assertTrue(is_string($release['filename']));
			$this->assertTrue(isNumber($release['fileid']));
			$this->assertTrue(isNumber($release['downloads']));
			foreach($release['tags'] as $version) {
				$this->assertNotEquals(false, compileSemanticVersion($version));
			}
			$this->assertTrue(is_string($release['modidstr']));
			$this->assertNotEquals(false, compileSemanticVersion($release['modversion']));
			$this->assertTrue(is_string($release['created']));
			$this->assertTrue(isStringOrNull($release['changelog']));
		}
		foreach($mod['screenshots'] as $screenshot) {
			$this->assertTrue(isNumber($screenshot['fileid']));
			$this->assertTrue(is_string($screenshot['mainfile']));
			$this->assertTrue(is_string($screenshot['filename']));
			$this->assertTrue(is_string($screenshot['thumbnailfilename']));
			$this->assertTrue(is_string($screenshot['created']));
		}
	}

	/** @test */
	public function authors() : void
	{
		ini_set('memory_limit', '10240M');

		$data = apiGet('authors');
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['authors'] as $author) {
			$this->assertTrue(isNumber($author['userid']));
			$this->assertTrue(is_string($author['name']));
		}
	}

	/** @test */
	public function authorsSearch() : void
	{
		$data = apiGet('authors', ['name' => 'Tyr']);
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['authors'] as $author) {
			$this->assertTrue(isNumber($author['userid']));
			$this->assertTrue(is_string($author['name']));
		}
	}

	/** @test */
	public function comments() : void
	{
		$data = apiGet('comments');
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['comments'] as $comment) {
			$this->assertTrue(isNumber($comment['commentid']));
			$this->assertTrue(isNumber($comment['assetid']));
			$this->assertTrue(isNumber($comment['userid']));
			$this->assertTrue(is_string($comment['text']));
			$this->assertTrue(is_string($comment['created']));
			$this->assertTrue(is_string($comment['lastmodified']));
		}
	}

	/** @test */
	public function commentsForAsset() : void
	{
		$data = apiGet(['comments', '1']);
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['comments'] as $comment) {
			$this->assertTrue(isNumber($comment['commentid']));
			$this->assertTrue(isNumber($comment['assetid']));
			$this->assertTrue(isNumber($comment['userid']));
			$this->assertTrue(is_string($comment['text']));
			$this->assertTrue(is_string($comment['created']));
			$this->assertTrue(is_string($comment['lastmodified']));
		}
	}

	/** @test */
	public function changelogs() : void
	{
		set_error_handler(function($errno, $errstr) {
			// Suppress that error, its to complicated to "properly" avoid it.
			// Would need to isolate the cache control header somehow, and thats not worth it.
			return contains($errstr, 'Cannot modify header information');
		}, E_WARNING);
		$data = apiGet('changelogs');
		restore_error_handler();

		$this->assertFalse($data->fail);

		foreach($data->data['changelogs'] as $changelog) {
			$this->assertTrue(isNumber($changelog['changelogid']));
			$this->assertTrue(isNumber($changelog['assetid']));
			$this->assertTrue(isNumber($changelog['userid']));
			$this->assertTrue(is_string($changelog['text']));
			$this->assertTrue(is_string($changelog['created']));
			$this->assertTrue(is_string($changelog['lastmodified']));
		}
	}

	/** @test */
	public function updatesMalformed() : void
	{
		$data = apiGet('updates', ['mods' => 'malformed']);
		$this->assertTrue($data->fail);
		$this->assertNotEquals('200', $data->data['statuscode']);
	}

	/** @test */
	public function updates() : void
	{
		$data = apiGet('updates', ['mods' => 'test@1.0.1']);
		$this->assertFalse($data->fail);
		$this->assertEquals('200', $data->data['statuscode']);

		foreach($data->data['updates'] as $ident => $newRelease) {
			$this->assertTrue(is_string($ident));
			$this->assertTrue(isNumber($newRelease['releaseid']));
			$this->assertTrue(is_string($newRelease['mainfile']));
			$this->assertTrue(is_string($newRelease['filename']));
			$this->assertTrue(isNumber($newRelease['fileid']));
			$this->assertTrue(isNumber($newRelease['downloads']));
			foreach($newRelease['tags'] as $versionStr) {
				$this->assertNotEquals(false, compileSemanticVersion($versionStr));
			}
			$this->assertTrue(is_string($newRelease['modidstr']));
			$this->assertNotEquals(false, compileSemanticVersion($newRelease['modversion']));
			$this->assertTrue(is_string($newRelease['created']));
		}
	}
}