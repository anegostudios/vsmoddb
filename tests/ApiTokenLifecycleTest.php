<?php

require_once __DIR__.'/support/api-token-support.php';

/** Tokens die when their creator stops being owner / editor: team changes (updateModTeamMembers) and ownership transfers. */
final class ApiTokenLifecycleTest extends ApiTokenTestCase
{
	private $owner;
	private $editor;
	private $otherEditor;
	private $mod;
	private $otherMod;

	protected function setUp() : void
	{
		parent::setUp();
		$this->owner = Fx::user();
		$this->editor = Fx::user();
		$this->otherEditor = Fx::user();
		$this->mod = Fx::mod($this->owner['userId']);
		$this->otherMod = Fx::mod($this->owner['userId']);
		Fx::teamMember($this->mod['modId'], $this->editor['userId'], true);
		Fx::teamMember($this->mod['modId'], $this->otherEditor['userId'], true);
		Fx::teamMember($this->otherMod['modId'], $this->editor['userId'], true);
	}

	private function exists($token) : bool
	{
		return $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$token['tokenId']]) === 1;
	}

	private function revokeLogs($modId, $initiatorId) : array
	{
		return $this->db()->getAll('SELECT info, flags FROM auditLogs WHERE kind = ? AND referenceId = ? AND initiatorUserId = ? ORDER BY info', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $modId, $initiatorId]);
	}

	private function canEdit($modId, $userId)
	{
		$v = $this->db()->getOne('SELECT canEdit FROM modTeamMembers WHERE modId = ? AND userId = ?', [$modId, $userId]);
		return $v === null || $v === false ? null : intval($v);
	}

	/** Calls the real updateModTeamMembers() like edit-mod.php does. $members: [user, isEditor] */
	private function updateTeam($actingUserId, $members) : void
	{
		$newMembers = [];
		$editorHashes = [];
		foreach($members as [$member, $isEditor]) {
			$newMembers[$member['hash']] = $member['userId'];
			if($isEditor)  $editorHashes[$member['hash']] = 1;
		}
		Fx::actingAs($actingUserId, fn() => updateModTeamMembers(['modId' => $this->mod['modId'], 'assetId' => $this->mod['assetId']], ['userId' => 0, 'notificationId' => 0], $newMembers, $editorHashes));
	}

	private function transfer($moderator, $newOwnerId, $handling) : array
	{
		$form = ['newOwnerId' => $newOwnerId, 'immediate' => '1', 'at' => $moderator['at']];
		if($handling !== null)  $form['previousOwnerHandling'] = $handling;
		return Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/transfer", ['session' => $moderator, 'form' => $form]);
	}

	//
	// team changes
	//

	/** @test */
	public function keepingTheTeamUnchangedKeepsAllTokens() : void
	{
		$editorToken = Fx::token($this->mod, $this->editor['userId']);
		$otherToken = Fx::token($this->mod, $this->otherEditor['userId']);

		$this->updateTeam($this->owner['userId'], [[$this->editor, true], [$this->otherEditor, true]]);

		$this->assertTrue($this->exists($editorToken));
		$this->assertTrue($this->exists($otherToken));
		$this->assertSame([], $this->revokeLogs($this->mod['modId'], $this->owner['userId']));
	}

	/** @test */
	public function demotingAnEditorRevokesExactlyTheirTokensForThatMod() : void
	{
		$t1 = Fx::token($this->mod, $this->editor['userId'], 'editor one');
		$t2 = Fx::token($this->mod, $this->editor['userId'], 'editor two');
		$elsewhere = Fx::token($this->otherMod, $this->editor['userId'], 'editor elsewhere');
		$other = Fx::token($this->mod, $this->otherEditor['userId'], 'other editor');
		$ownerToken = Fx::token($this->mod, $this->owner['userId'], 'owner');

		$this->updateTeam($this->owner['userId'], [[$this->editor, false], [$this->otherEditor, true]]);

		$this->assertSame(0, $this->canEdit($this->mod['modId'], $this->editor['userId']));
		$this->assertFalse($this->exists($t1));
		$this->assertFalse($this->exists($t2));
		$this->assertTrue($this->exists($elsewhere));
		$this->assertTrue($this->exists($other));
		$this->assertTrue($this->exists($ownerToken));
		$this->assertSame([['info' => 'editor one', 'flags' => 0], ['info' => 'editor two', 'flags' => 0]], $this->revokeLogs($this->mod['modId'], $this->owner['userId']));

		$this->assertJsonError($this->upload($this->mod['modId'], $t1['token']), 401, 'Invalid or expired api token.');
		$this->assertSame(201, $this->upload($this->otherMod['modId'], $elsewhere['token'])['status']);
	}

	/** @test */
	public function removingAMemberRevokesTheirTokensForThatMod() : void
	{
		$t1 = Fx::token($this->mod, $this->editor['userId'], 'editor one');
		$elsewhere = Fx::token($this->otherMod, $this->editor['userId'], 'editor elsewhere');
		$other = Fx::token($this->mod, $this->otherEditor['userId'], 'other editor');

		$this->updateTeam($this->owner['userId'], [[$this->otherEditor, true]]);

		$this->assertNull($this->canEdit($this->mod['modId'], $this->editor['userId']));
		$this->assertFalse($this->exists($t1));
		$this->assertTrue($this->exists($elsewhere));
		$this->assertTrue($this->exists($other));
		$this->assertSame([['info' => 'editor one', 'flags' => 0]], $this->revokeLogs($this->mod['modId'], $this->owner['userId']));
	}

	/** @test */
	public function removingAMemberWithoutEditRightsAlsoRemovesStaleTokens() : void
	{
		$member = Fx::user();
		Fx::teamMember($this->mod['modId'], $member['userId'], false);
		$stale = Fx::rawToken($this->mod['modId'], $member['userId'], 'NOW() + INTERVAL 1 DAY', 'stale');

		$this->updateTeam($this->owner['userId'], [[$this->editor, true], [$this->otherEditor, true]]);

		$this->assertFalse($this->exists($stale));
	}

	/** @test */
	public function teamChangesByAModeratorAreLoggedAsModActions() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		Fx::token($this->mod, $this->editor['userId'], 'editor one');

		$this->updateTeam($moderator['userId'], [[$this->editor, false], [$this->otherEditor, true]]);

		$this->assertSame([['info' => 'editor one', 'flags' => AUDIT_LOG_FLAG_MODACTION]], $this->revokeLogs($this->mod['modId'], $moderator['userId']));
	}

	/** @test */
	public function promotingAMemberAgainDoesNotResurrectOldTokens() : void
	{
		$old = Fx::token($this->mod, $this->editor['userId'], 'old');
		$this->assertSame(201, $this->upload($this->mod['modId'], $old['token'])['status']);

		$this->updateTeam($this->owner['userId'], [[$this->editor, false], [$this->otherEditor, true]]);
		$this->updateTeam($this->owner['userId'], [[$this->editor, true], [$this->otherEditor, true]]);

		$this->assertSame(1, $this->canEdit($this->mod['modId'], $this->editor['userId']));
		$this->assertJsonError($this->upload($this->mod['modId'], $old['token']), 401, 'Invalid or expired api token.');

		$new = Fx::token($this->mod, $this->editor['userId'], 'new');
		$this->assertSame(201, $this->upload($this->mod['modId'], $new['token'])['status']);
	}

	/** @test */
	public function promotingAMemberRevokesNothing() : void
	{
		$member = Fx::user();
		Fx::teamMember($this->mod['modId'], $member['userId'], false);
		$editorToken = Fx::token($this->mod, $this->editor['userId']);

		$this->updateTeam($this->owner['userId'], [[$this->editor, true], [$this->otherEditor, true], [$member, true]]);

		$this->assertSame(1, $this->canEdit($this->mod['modId'], $member['userId']));
		$this->assertTrue($this->exists($editorToken));
		$this->assertSame([], $this->revokeLogs($this->mod['modId'], $this->owner['userId']));
	}

	//
	// ownership transfer
	//

	/** @test */
	public function immediateTransferWithDemoteRevokesThePreviousOwnersTokens() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$ownerToken = Fx::token($this->mod, $this->owner['userId'], 'owner ci');
		$ownerElsewhere = Fx::token($this->otherMod, $this->owner['userId'], 'owner other mod');
		$newOwnerToken = Fx::token($this->mod, $this->editor['userId'], 'new owner ci');

		$response = $this->transfer($moderator, $this->editor['userId'], 'demote');

		$this->assertSame(200, $response['status'], $response['body']);
		$this->assertSame($this->editor['userId'], intval($this->db()->getOne('SELECT createdByUserId FROM assets WHERE assetId = ?', [$this->mod['assetId']])));
		$this->assertSame(0, $this->canEdit($this->mod['modId'], $this->owner['userId']));
		$this->assertFalse($this->exists($ownerToken));
		$this->assertTrue($this->exists($ownerElsewhere));
		$this->assertTrue($this->exists($newOwnerToken));
		$this->assertSame([['info' => 'owner ci', 'flags' => 0]], $this->revokeLogs($this->mod['modId'], $moderator['userId']));

		$this->assertJsonError($this->upload($this->mod['modId'], $ownerToken['token']), 401, 'Invalid or expired api token.');
		$this->assertSame(201, $this->upload($this->mod['modId'], $newOwnerToken['token'])['status']);
	}

	/** @test */
	public function immediateTransferWithRemoveRevokesThePreviousOwnersTokens() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$ownerToken = Fx::token($this->mod, $this->owner['userId'], 'owner ci');
		$ownerElsewhere = Fx::token($this->otherMod, $this->owner['userId'], 'owner other mod');

		$response = $this->transfer($moderator, $this->editor['userId'], 'remove');

		$this->assertSame(200, $response['status'], $response['body']);
		$this->assertNull($this->canEdit($this->mod['modId'], $this->owner['userId']));
		$this->assertFalse($this->exists($ownerToken));
		$this->assertTrue($this->exists($ownerElsewhere));
		$this->assertSame([['info' => 'owner ci', 'flags' => 0]], $this->revokeLogs($this->mod['modId'], $moderator['userId']));
	}

	/** @test */
	public function immediateTransferWithPreserveKeepsThePreviousOwnersTokensWorking() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$ownerToken = Fx::token($this->mod, $this->owner['userId'], 'owner ci');

		$response = $this->transfer($moderator, $this->editor['userId'], 'preserve');

		$this->assertSame(200, $response['status'], $response['body']);
		$this->assertSame(1, $this->canEdit($this->mod['modId'], $this->owner['userId']));
		$this->assertTrue($this->exists($ownerToken));
		$this->assertSame([], $this->revokeLogs($this->mod['modId'], $moderator['userId']));
		$this->assertSame(201, $this->upload($this->mod['modId'], $ownerToken['token'])['status']);
	}

	/** @test */
	public function immediateTransferWithoutHandlingDefaultsToPreserve() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$ownerToken = Fx::token($this->mod, $this->owner['userId'], 'owner ci');

		$response = $this->transfer($moderator, $this->editor['userId'], null);

		$this->assertSame(200, $response['status'], $response['body']);
		$this->assertTrue($this->exists($ownerToken));
	}

	/**
	 * Regression: the 'remove' branch used to run `DELETE FROM modTeamMembers WHERE userId = $newOwnerId` without a mod filter,
	 * which removed the new owner from the teams of ALL mods.
	 * @test
	 */
	public function immediateRemoveTransferOnlyTouchesTheTeamOfTheTransferredMod() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$newOwner = $this->editor; // editor on both $this->mod and $this->otherMod
		$thirdMod = Fx::mod(Fx::user()['userId']);
		Fx::teamMember($thirdMod['modId'], $newOwner['userId'], false);
		$tokenOnOtherMod = Fx::token($this->otherMod, $newOwner['userId'], 'b token');
		$tokenOnThisMod = Fx::token($this->mod, $newOwner['userId'], 'a token');

		$response = $this->transfer($moderator, $newOwner['userId'], 'remove');
		$this->assertSame(200, $response['status'], $response['body']);

		// The transferred mod: new owner, no leftover team row, previous owner gone.
		$this->assertSame($newOwner['userId'], intval($this->db()->getOne('SELECT createdByUserId FROM assets WHERE assetId = ?', [$this->mod['assetId']])));
		$this->assertNull($this->canEdit($this->mod['modId'], $newOwner['userId']));
		$this->assertNull($this->canEdit($this->mod['modId'], $this->owner['userId']));
		$this->assertSame(1, $this->canEdit($this->mod['modId'], $this->otherEditor['userId']));
		$this->assertTrue($this->exists($tokenOnThisMod));

		// Other mods: memberships and tokens untouched.
		$this->assertSame(1, $this->canEdit($this->otherMod['modId'], $newOwner['userId']));
		$this->assertSame(0, $this->canEdit($thirdMod['modId'], $newOwner['userId']));
		$this->assertTrue($this->exists($tokenOnOtherMod));
		$this->assertSame(201, $this->upload($this->otherMod['modId'], $tokenOnOtherMod['token'])['status']);
	}
}
