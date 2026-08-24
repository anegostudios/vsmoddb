<?php

include_once "prelude.php";
include_once $config['basepath'].'lib/version.php';
include_once $config['basepath'].'lib/relations.php';

use PHPUnit\Framework\TestCase;

final class RelationsParseTest extends TestCase
{
	public function testParsesBasicTwoEntries(): void
	{
		$r = parseRawDeps('modA@1.0.0, modB@2.5.0');
		$this->assertCount(2, $r);
		$this->assertEquals(compileSemanticVersion('1.0.0'), $r['modA']);
		$this->assertEquals(compileSemanticVersion('2.5.0'), $r['modB']);
	}

	public function testReturnsZeroForUnversionedEntry(): void
	{
		$r = parseRawDeps('modA');
		$this->assertEquals(0, $r['modA']);
	}

	public function testFiltersIgnoredIdentifiers(): void
	{
		$r = parseRawDeps('game@1.20.0, survival@1.20.0, creative@1.20.0, modA@1.0.0');
		$this->assertEquals(['modA' => compileSemanticVersion('1.0.0')], $r);
	}

	public function testFiltersWildcardAndEmpty(): void
	{
		$r = parseRawDeps('*, modA@1.0.0');
		$this->assertEquals(['modA' => compileSemanticVersion('1.0.0')], $r);
	}

	public function testDeduplicatesByKeepingHighestVersion(): void
	{
		$r = parseRawDeps('modA@1.0.0, modA@2.0.0, modA@1.5.0');
		$this->assertEquals(compileSemanticVersion('2.0.0'), $r['modA']);
	}

	public function testEmptyInputReturnsEmptyArray(): void
	{
		$this->assertEquals([], parseRawDeps(''));
		$this->assertEquals([], parseRawDeps(null));
	}

    public function testInvalidVersionStringFallsBackToZero(): void
    {
        $r = parseRawDeps('modA@garbage, modB@1.0');  // 1.0 is also invalid (needs three components)
        $this->assertEquals(0, $r['modA']);
        $this->assertEquals(0, $r['modB']);
    }

    public function testFiltersEmptyIdentifierFromLeadingComma(): void
    {
        // Leading ", " produces an empty first segment after explode.
        $r = parseRawDeps(', modA@1.0.0');
        $this->assertEquals(['modA' => compileSemanticVersion('1.0.0')], $r);
    }
}

final class RelationsMergeRangesTest extends TestCase
{
	public function testSingleConstraintReturnsItself(): void
	{
		$out = mergeRanges([['from' => 'A', 'min' => 100, 'max' => 200]]);
		$this->assertEquals(100, $out['effectiveMin']);
		$this->assertEquals(200, $out['effectiveMax']);
		$this->assertFalse($out['unsatisfiable']);
	}

	public function testNullBoundsAreOpen(): void
	{
		$out = mergeRanges([
			['from' => 'A', 'min' => null, 'max' => 200],
			['from' => 'B', 'min' => 100, 'max' => null],
		]);
		$this->assertEquals(100, $out['effectiveMin']);
		$this->assertEquals(200, $out['effectiveMax']);
		$this->assertFalse($out['unsatisfiable']);
	}

	public function testOverlappingTakesIntersection(): void
	{
		$out = mergeRanges([
			['from' => 'A', 'min' => 100, 'max' => 300],
			['from' => 'B', 'min' => 200, 'max' => 400],
		]);
		$this->assertEquals(200, $out['effectiveMin']);
		$this->assertEquals(300, $out['effectiveMax']);
		$this->assertFalse($out['unsatisfiable']);
	}

	public function testNonOverlappingIsUnsatisfiable(): void
	{
		$out = mergeRanges([
			['from' => 'A', 'min' => 100, 'max' => 200],
			['from' => 'B', 'min' => 300, 'max' => 400],
		]);
		$this->assertTrue($out['unsatisfiable']);
	}

	public function testMinOnlyConstraintsTakeMaxOfMins(): void
	{
		$out = mergeRanges([
			['from' => 'A', 'min' => 100, 'max' => null],
			['from' => 'B', 'min' => 250, 'max' => null],
		]);
		$this->assertEquals(250, $out['effectiveMin']);
		$this->assertNull($out['effectiveMax']);
		$this->assertFalse($out['unsatisfiable']);
	}

    public function testEmptyConstraintsReturnsOpenRange(): void
    {
        $out = mergeRanges([]);
        $this->assertNull($out['effectiveMin']);
        $this->assertNull($out['effectiveMax']);
        $this->assertFalse($out['unsatisfiable']);
    }

    public function testSingleContradictoryConstraintIsUnsatisfiable(): void
    {
        $out = mergeRanges([['from' => 'A', 'min' => 300, 'max' => 100]]);
        $this->assertTrue($out['unsatisfiable']);
    }
}

final class RelationsBfsResolveTest extends TestCase
{
    private function loaderFromGraph(array $graph): callable
    {
        return function (string $id) use ($graph) {
            return array_map(function ($edge) {
                return [
                    'targetIdentifier' => $edge[0],
                    'relationType'     => $edge[1] ?? REL_REQUIRED,
                    'minVersion'       => $edge[2] ?? null,
                    'maxVersion'       => $edge[3] ?? null,
                ];
            }, $graph[$id] ?? []);
        };
    }

    private function defaultPicker(array $available): callable
    {
        return function (string $id) use ($available) {
            return in_array($id, $available, true) ? ['fileName' => $id.'.zip', 'fileUrl' => '/dl/'.$id] : null;
        };
    }

    public function testLinearChainIsFullyResolved(): void
    {
        $graph = ['A' => [['B']], 'B' => [['C']], 'C' => []];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B','C']));
        $this->assertEquals([], $out['warnings']);
        $this->assertCount(3, $out['resolved']);
        $this->assertEquals(0, $out['resolved']['A']['depth']);
        $this->assertEquals(1, $out['resolved']['B']['depth']);
        $this->assertEquals(2, $out['resolved']['C']['depth']);
    }

    public function testDiamondInstallsTargetOnce(): void
    {
        $graph = ['A' => [['B'], ['C']], 'B' => [['D']], 'C' => [['D']], 'D' => []];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B','C','D']));
        $this->assertEquals([], $out['warnings']);
        $this->assertCount(4, $out['resolved']);
        $this->assertContains('B', $out['resolved']['D']['requiredBy']);
        $this->assertContains('C', $out['resolved']['D']['requiredBy']);
    }

    public function testDirectCycleEmitsWarning(): void
    {
        $graph = ['A' => [['B']], 'B' => [['A']]];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B']));
        $cycles = array_filter($out['warnings'], fn($w) => $w['kind'] === 'cycle');
        $this->assertNotEmpty($cycles);
        $first = array_values($cycles)[0];
        $this->assertContains('A', $first['path']);
        $this->assertContains('B', $first['path']);
    }

    public function testIndirectCycleEmitsWarning(): void
    {
        $graph = ['A' => [['B']], 'B' => [['C']], 'C' => [['A']]];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B','C']));
        $cycles = array_filter($out['warnings'], fn($w) => $w['kind'] === 'cycle');
        $this->assertNotEmpty($cycles);
    }

    public function testSelfCycleEmitsWarning(): void
    {
        $graph = ['A' => [['A']]];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A']));
        $cycles = array_filter($out['warnings'], fn($w) => $w['kind'] === 'cycle');
        $this->assertNotEmpty($cycles);
    }

    public function testMissingDepEmitsWarning(): void
    {
        $graph = ['A' => [['MissingMod']]];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A']));
        $missing = array_filter($out['warnings'], fn($w) => $w['kind'] === 'missing_dep');
        $this->assertNotEmpty($missing);
        $first = array_values($missing)[0];
        $this->assertEquals('MissingMod', $first['identifier']);
        $this->assertEquals(['A'], $first['requiredBy']);
    }

    public function testIncompatBetweenRootsEmitsWarning(): void
    {
        $graph = ['A' => [['B', REL_INCOMPATIBLE]], 'B' => []];
        $out = bfsResolve(['A','B'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B']));
        $incompat = array_filter($out['warnings'], fn($w) => $w['kind'] === 'incompatible');
        $this->assertNotEmpty($incompat);
    }

    public function testIncompatWithTransitiveDepEmitsWarning(): void
    {
        $graph = ['A' => [['B'], ['C', REL_INCOMPATIBLE]], 'B' => [['C']], 'C' => []];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B','C']));
        $incompat = array_filter($out['warnings'], fn($w) => $w['kind'] === 'incompatible');
        $this->assertNotEmpty($incompat);
    }

    public function testDepthLimitCutsOffDeepGraphs(): void
    {
        $graph = [];
        $names = [];
        for ($i = 0; $i < MAX_DEPS_DEPTH + 5; $i++) {
            $names[] = "M$i";
            $graph["M$i"] = [["M".($i+1)]];
        }
        $graph["M".count($names)] = [];
        $out = bfsResolve(['M0'], $this->loaderFromGraph($graph), $this->defaultPicker(array_merge($names, ["M".count($names)])));
        $depthLimit = array_filter($out['warnings'], fn($w) => $w['kind'] === 'depth_limit');
        $this->assertNotEmpty($depthLimit);
    }

    public function testOptionalAndTestedWithEmitInformationalWhenUnmet(): void
    {
        $graph = ['A' => [['B', REL_OPTIONAL], ['C', REL_TESTED_WITH]]];
        $out = bfsResolve(['A'], $this->loaderFromGraph($graph), $this->defaultPicker(['A']));
        $kinds = array_column($out['warnings'], 'kind');
        $this->assertContains('optional_unmet', $kinds);
        $this->assertContains('tested_with_unmet', $kinds);
    }

    public function testMultipleRootsSharingDirectDepDedupRequiredBy(): void
    {
        // Two roots A and B both directly require D. requiredBy should not contain duplicate '<root>' entries.
        $graph = ['A' => [['D']], 'B' => [['D']], 'D' => []];
        $out = bfsResolve(['A','B'], $this->loaderFromGraph($graph), $this->defaultPicker(['A','B','D']));
        $this->assertEquals([], $out['warnings']);
        $this->assertSame(array_unique($out['resolved']['D']['requiredBy']), $out['resolved']['D']['requiredBy'],
            'requiredBy must not contain duplicates');
    }
}

final class RelationsCycleGuardTest extends TestCase
{
    public function testDirectCycleDetected(): void
    {
        // graph: B -required-> A. Adding A -required-> B should be a cycle.
        $graph = ['B' => [['target' => 'A', 'type' => REL_REQUIRED]]];
        $this->assertTrue(wouldCreateCycleInGraph('A', 'B', REL_REQUIRED, $graph));
    }

    public function testIndirectCycleDetected(): void
    {
        // graph: B->C, C->A. Adding A->B should be a cycle (A->B->C->A).
        $graph = [
            'B' => [['target' => 'C', 'type' => REL_REQUIRED]],
            'C' => [['target' => 'A', 'type' => REL_REQUIRED]],
        ];
        $this->assertTrue(wouldCreateCycleInGraph('A', 'B', REL_REQUIRED, $graph));
    }

    public function testNonCyclicAdditionAllowed(): void
    {
        $graph = ['B' => [['target' => 'C', 'type' => REL_REQUIRED]]];
        $this->assertFalse(wouldCreateCycleInGraph('A', 'B', REL_REQUIRED, $graph));
    }

    public function testIgnoresOptionalAndIncompat(): void
    {
        // Even if optional cycles exist, they don't form an install cycle.
        $graph = ['B' => [['target' => 'A', 'type' => REL_OPTIONAL]]];
        $this->assertFalse(wouldCreateCycleInGraph('A', 'B', REL_REQUIRED, $graph));
    }

    public function testNonRequiredAdditionNeverCycles(): void
    {
        $graph = ['B' => [['target' => 'A', 'type' => REL_REQUIRED]]];
        $this->assertFalse(wouldCreateCycleInGraph('A', 'B', REL_OPTIONAL, $graph));
        $this->assertFalse(wouldCreateCycleInGraph('A', 'B', REL_INCOMPATIBLE, $graph));
        $this->assertFalse(wouldCreateCycleInGraph('A', 'B', REL_TESTED_WITH, $graph));
    }

    public function testSelfCycleDetected(): void
    {
        // Adding A -required-> A in any (even empty) graph is a cycle.
        $this->assertTrue(wouldCreateCycleInGraph('A', 'A', REL_REQUIRED, []));
    }
}
