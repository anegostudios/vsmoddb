<?php

include_once "prelude.php";

include_once $config['basepath']. 'lib/recommend-release.php';

function mkRelease($version, $gameVersions)
{
	return ['version' => $version, 'v' => formatSemanticVersion($version), 'compatibleGameVersions' => $gameVersions, 'maxCompatibleGameVersion' => max($gameVersions)];
}

function stable($version) { return $version << 32 | 0xffff; }
function pre($stable, $minor) { return ($stable << 32) | (8 << 12) | $minor; }

//NOTE(Rennorb): This is not a assert wrapper to preserve the error lines in test reports.
//TODO(Rennorb) @cleanup: Figure out how to turn this into a wrapper while keeping the correct error locations.
function formatVersionComp($a, $b) { return 'expected ' . formatSemanticVersion($a) . ' but got '. formatSemanticVersion($b); }

use PHPUnit\Framework\TestCase;

final class ReleaseRecommendationsTest extends TestCase {
	/** @test */
	public function selectLatestVersionPrimary() : void
	{
		$allGameVersions = [ stable(5), stable(4) ];

		$influenced = selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(false, $influenced);
		$this->assertEquals(stable(5), $highest, formatVersionComp($highest, stable(5)));
		$this->assertEquals(stable(5), $maxStable, formatVersionComp($maxStable, stable(5)));
		$this->assertEquals(null, $maxUnstable);
	}

	/** @test */
	public function selectSearchOldPrimary() : void
	{
		$allGameVersions = [ stable(5), stable(4) ];

		$influenced = selectDesiredVersions($allGameVersions, stable(4) & VERSION_MASK_PRIMARY, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(true, $influenced);
		$this->assertEquals(stable(4), $highest, formatVersionComp($highest, stable(4)));
		$this->assertEquals(stable(4), $maxStable, formatVersionComp($maxStable, stable(4)));
		$this->assertEquals(null, $maxUnstable);
	}

	/** @test */
	public function selectSearchOldExact() : void
	{
		$allGameVersions = [ stable(5), stable(4) ];

		$influenced = selectDesiredVersions($allGameVersions, null, [stable(4)], $highest, $maxStable, $maxUnstable);

		$this->assertEquals(true, $influenced);
		$this->assertEquals(stable(4), $highest, formatVersionComp($highest, stable(4)));
		$this->assertEquals(stable(4), $maxStable, formatVersionComp($maxStable, stable(4)));
		$this->assertEquals(null, $maxUnstable);
	}

	/** @test */
	public function selectLatestVersionUnstable() : void
	{
		$allGameVersions = [ pre(5, 1), stable(4) ];

		$influenced = selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(false, $influenced);
		$this->assertEquals(stable(4), $highest, formatVersionComp($highest, stable(4)));
		$this->assertEquals(stable(4), $maxStable, formatVersionComp($maxStable, stable(4)));
		$this->assertEquals(pre(5, 1), $maxUnstable);
	}

	/** @test */
	public function selectLatestVersionUnstable2() : void
	{
		$allGameVersions = [ stable(5), pre(5, 1), stable(4) ];

		$influenced = selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(false, $influenced);
		$this->assertEquals(stable(5), $highest, formatVersionComp($highest, stable(5)));
		$this->assertEquals(stable(5), $maxStable, formatVersionComp($maxStable, stable(5)));
		$this->assertEquals(null, $maxUnstable);
	}


	///
	/// Releases
	///

	/** @test */
	public function recommendLatestRelease() : void
	{
		$allGameVersions = [ stable(5), stable(4), stable(3), stable(2), stable(1) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$releases = [
			mkRelease(stable(4), [stable(3), stable(4), stable(5)]),
			mkRelease(stable(3), [stable(2), stable(3), stable(4)]),
			mkRelease(stable(2), [stable(2), stable(3)]),
			mkRelease(stable(1), [stable(1), stable(2), stable(3)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(stable(4), $recommended['version'], formatVersionComp(stable(4), $recommended['version']));
		$this->assertEquals(null, $testers);
	}

	/** @test */
	public function recommendLatestWithTestersPrerelease() : void
	{
		$allGameVersions = [ stable(4) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$releases = [
			mkRelease(pre(5, 1), [stable(4)]),
			mkRelease(stable(4), [stable(4)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(stable(4), $recommended['version'], formatVersionComp(stable(4), $recommended['version']));
		$this->assertEquals(pre(5, 1), $testers['version'], formatVersionComp(pre(5, 1), $testers['version']));
		$this->assertEquals(null, $fallback);
	}

	/** @test */
	public function fallbackLatestStableWithNewerTesters() : void
	{
		$allGameVersions = [ pre(5, 3), pre(5, 2), pre(5, 1), stable(4), stable(3) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(stable(4), $maxStable, formatVersionComp(stable(4), $maxStable));
		$this->assertEquals(pre(5, 3), $maxUnstable, formatVersionComp(pre(5, 3), $maxUnstable));

		$releases = [
			mkRelease(stable(4), [pre(5, 3)]),
			mkRelease(stable(3), [pre(5, 2)]),
			mkRelease(stable(2), [pre(5, 1)]),
			mkRelease(stable(1), [stable(3)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(stable(4), $testers['version'], formatVersionComp(stable(4), $testers['version']));
		$this->assertEquals(stable(1), $fallback['version'], formatVersionComp(stable(1), $fallback['version']));
	}

	/** @test */
	public function testersOnlyGameVersion() : void
	{
		$allGameVersions = [ pre(6, 1), stable(5), pre(5, 1) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$releases = [
			mkRelease(stable(1), [pre(5, 1)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(stable(1), $testers['version'], formatVersionComp(stable(1), $testers['version']));
		$this->assertEquals(null, $fallback);
	}

	/** @test */
	public function testersOnlyReleaseVersion() : void
	{
		$allGameVersions = [ stable(1) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$releases = [
			mkRelease(pre(0, 1), [stable(1)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(pre(0, 1), $testers['version'], formatVersionComp(pre(0, 1), $testers['version']));
		$this->assertEquals(null, $fallback);
	}

	/** @test */
	public function fallback() : void
	{
		$allGameVersions = [ stable(5), stable(4) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$releases = [
			mkRelease(stable(1), [stable(4)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(null, $testers);
		$this->assertEquals(stable(1), $fallback['version'], formatVersionComp(stable(1), $fallback['version']));
	}

	/** @test */
	public function fallbackLatest() : void
	{
		$allGameVersions = [ stable(4), stable(3) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$releases = [
			mkRelease(stable(2), [stable(3)]),
			mkRelease(stable(1), [stable(3)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(null, $testers);
		$this->assertEquals(stable(2), $fallback['version'], formatVersionComp(stable(2), $fallback['version']));
	}

	/** @test */
	public function recommendSearchedOutdated() : void
	{
		$allGameVersions = [ stable(4), stable(3) ];

		selectDesiredVersions($allGameVersions, stable(3) & VERSION_MASK_PRIMARY, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(stable(3), $highest, formatVersionComp($highest, stable(3)));
		$this->assertEquals(stable(3), $maxStable, formatVersionComp($maxStable, stable(3)));
		$this->assertEquals(null, $maxUnstable);

		$releases = [
			mkRelease(stable(2), [stable(4)]),
			mkRelease(stable(1), [stable(3)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(stable(1), $recommended['version'], formatVersionComp(stable(1), $recommended['version']));
		$this->assertEquals(null, $testers);
	}

	/** @test */
	public function fallbackIgnoreNewerTestersWhenEvenNewerStableGameVersionIsPresent() : void
	{
		$allGameVersions = [ stable(5), pre(5, 3), pre(5, 2), pre(5, 1), stable(4), stable(3) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(null, $maxUnstable);
		$this->assertEquals(stable(5), $maxStable);

		$releases = [
			mkRelease(stable(4), [pre(5, 3)]),
			mkRelease(stable(3), [pre(5, 2)]),
			mkRelease(stable(2), [pre(5, 1)]),
			mkRelease(stable(1), [stable(3)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(null, $testers);
		$this->assertEquals(stable(4), $fallback['version'], formatVersionComp(stable(4), $fallback['version']));
	}

	/** @test */
	public function fallbackLatestWithNewerTesters() : void
	{
		$allGameVersions = [ pre(5, 3), pre(5, 2), pre(5, 1), stable(4), stable(3) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(pre(5, 3), $maxUnstable);
		$this->assertEquals(stable(4), $maxStable);

		$releases = [
			mkRelease(stable(4), [pre(5, 2)]),
			mkRelease(stable(3), [pre(5, 1)]),
			mkRelease(stable(2), [stable(3)]),
			mkRelease(stable(1), [stable(3)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(stable(4), $testers['version'], formatVersionComp(stable(4), $testers['version']));
		$this->assertEquals(stable(2), $fallback['version'], formatVersionComp(stable(2), $fallback['version']));
	}

	/** @test */
	public function fallbackLatestWithNewerTesters2() : void
	{
		$allGameVersions = [ pre(6, 1), stable(5), stable(4), stable(3), pre(2, 1), stable(1) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(pre(6, 1), $maxUnstable);
		$this->assertEquals(stable(5), $maxStable);

		$releases = [
			mkRelease(pre(4, 1), [stable(4)]),
			mkRelease(stable(3), [stable(3)]),
			mkRelease(stable(2), [pre(2, 1)]), // <- wrong testers rel
			mkRelease(stable(1), [stable(1)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(pre(4, 1), $testers['version'], formatVersionComp(pre(4, 1), $testers['version']));
		$this->assertEquals(stable(3), $fallback['version'], formatVersionComp(stable(3), $fallback['version']));
	}

	/** @test */
	public function fallbackLatestWithExistingPrerelease() : void
	{
		$allGameVersions = [ pre(6, 1), stable(5), stable(4), stable(3), pre(2, 1), stable(1) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(pre(6, 1), $maxUnstable);
		$this->assertEquals(stable(5), $maxStable);

		$releases = [
			mkRelease(stable(5), [stable(4)]),
			mkRelease(stable(4), [stable(4)]),
			mkRelease(stable(2), [pre(2, 1)]), // <- wrong testers rel
			mkRelease(stable(1), [stable(1)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended);
		$this->assertEquals(null, $testers);
		$this->assertEquals(stable(5), $fallback['version'], formatVersionComp(stable(5), $fallback['version']));
	}

	/** @test */
	public function recommendLatestDontSelectForTestersIfReleaseIsAlsoLatestUnstable() : void
	{
		$allGameVersions = [ pre(6, 1), stable(5), stable(4) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(pre(6, 1), $maxUnstable);
		$this->assertEquals(stable(5), $maxStable);

		$releases = [
			mkRelease(stable(2), [stable(5), pre(6, 1)]),
			mkRelease(stable(1), [stable(5)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(stable(2), $recommended['version'], formatVersionComp(stable(2), $recommended['version']));
		$this->assertEquals(null, $testers);
		$this->assertEquals(null, $fallback);
	}

	/** @test */
	public function recommendLatestOldVersionAndTesters() : void
	{
		$allGameVersions = [ stable(3), pre(3, 2), pre(3, 1), stable(2) ];

		selectDesiredVersions($allGameVersions, null, null, $highest, $maxStable, $maxUnstable);

		$this->assertEquals(null, $maxUnstable);
		$this->assertEquals(stable(3), $maxStable);

		$releases = [
			mkRelease(pre(2, 2), [stable(3), pre(3, 2)]),
			mkRelease(pre(2, 1), [pre(3, 1)]),
			mkRelease(stable(1), [stable(2)]),
		];

		recommendReleases($releases, $maxStable, $maxUnstable, $recommended, $testers, $fallback);

		$this->assertEquals(null, $recommended['version']);
		$this->assertEquals(pre(2, 2), $testers['version'], formatVersionComp(pre(2, 2), $testers['version']));
		$this->assertEquals(stable(1), $fallback['version'], formatVersionComp(stable(1), $fallback['version']));
	}
}