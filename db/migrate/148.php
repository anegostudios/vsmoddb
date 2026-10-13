<?php
// Tags migration, going from 200+ to 33 tags.
//! Do not run it until the UI is ready to support the new tag policy.

// MARK: CONFIG
$config = [];
// gets root path
$config['basepath'] = dirname(dirname(__DIR__)) . "/";
$_SERVER['SERVER_NAME'] = 'mods.vintagestory.stage';
$_SERVER['REQUEST_URI'] = '/';
include($config['basepath'] . 'lib/config.php');
include($config['basepath'] . 'lib/core.php');

// MARK: OPERATIONS
$operations = [
	// MARK: delete
	// Most of these contain mods that :
	// - have nothing in common,
	// - target a facet that is irrelevant to players
	// - are too niche, or too far removed from the vanilla experience to constitute a tag
	// - target a facet that can be replaced by other search tools
	['delete', 'name'=>'Travel & Exploration'], // It's a mix of structures, mapping, transportation, and adventures.
	['delete', 'name'=>'Exploration'], // Same as Travel & Exploration
	['delete', 'name'=>'Mechanic'], // Both Mechanic and Mechanics have a mix of technology&automation, QoL, Immersion
	['delete', 'name'=>'Mechanics'], // Same as Mechanic.
	['delete', 'name'=>'Compilation'],
	['delete', 'name'=>'Crafting'], // The only thing they have in common in new recipes. It's a core mechanic of the game, but it's a mean not an end. A tag of 1503 mods is useless.
	['delete', 'name'=>'Utility'], // 1418 mods and nothing in common. The tag means nothing.
	['delete', 'name'=>'Gameplay'], // Means a different thing to everybody
	['delete', 'name'=>'Underwater'], // apart from the fact that it targets the sea. It's too small of an aspect of the game, and it can be replaced by collections or search
	['delete', 'name'=>'Behavior'],
	['delete', 'name'=>'Addon'],
	['delete', 'name'=>'Loot'],
	['delete', 'name'=>'Tweak'], // it's a scale Facet. It would be nice to have a scale facet, but we can't trust user input for it.
	['delete', 'name'=>'Entity Logic(AI)'], // too technical. Irrelevant to most players.
	['delete', 'name'=>'AI'], // Entity logic old name, for STAGE
	['delete', 'name'=>'Search'],
	['delete', 'name'=>'Oven'],
	['delete', 'name'=>'Time'],
	['delete', 'name'=>'Non-Working in 1.22'],
	['delete', 'name'=>'Chutes'],
	['delete', 'name'=>'Brony'],
	['delete', 'name'=>'Bonk'],
	['delete', 'name'=>'Mares'],
	['delete', 'name'=>'Dwarf'],
	['delete', 'name'=>'Fealism'],
	['delete', 'name'=>'Age of Seraphs'], // Appears to be a server
	['delete', 'name'=>'Meme'], // Irrelevant
	['delete', 'name'=>'Client'], // Already a field
	['delete', 'name'=>'Clientside'], // Already a field
	['delete', 'name'=>'Dead'],
	['delete', 'name'=>'Level'],
	['delete', 'name'=>'Lottery'],
	['delete', 'name'=>'Strange'],
	['delete', 'name'=>'Bell'],
	['delete', 'name'=>'Salty'],
	['delete', 'name'=>'Other'],
	['delete', 'name'=>'Youtube'],
	['delete', 'name'=>'Over powered'],
	['delete', 'name'=>'Pay'], // 1 mod, which has more relevant tags
	['delete', 'name'=>'Furry'], // it contains both character customization and actual animals
	['delete', 'name'=>'Overhaul'], // it's a scale facet
	['delete', 'name'=>'Halloween Modjam 2025'],
	['delete', 'name'=>'Hardcore'], // Replaced by collections
	['delete', 'name'=>'Tracking'],
	['delete', 'name'=>'Ocean'], // Not important enough to become a tag
	['delete', 'name'=>'Sea'], // Not important enough to become a tag
	['delete', 'name'=>'Difficulty'], // Replaced by collections
	['delete', 'name'=>'Bonus'],
	['delete', 'name'=>'Sci-fi'], // Too small and far away from the game
	['delete', 'name'=>'Neutral'],
	['delete', 'name'=>'Manager'],
	['delete', 'name'=>'Head Tracking'], // Irrelevant
	['delete', 'name'=>'Fun'], // Irrelevant
	['delete', 'name'=>'Humor'], // Irrelevant
	['delete', 'name'=>'Code'], // Irrelevant
	['delete', 'name'=>'attr'],

	// RECATEGORIZATION
	// I cannot recategorize sadly because authors have put actual mods into "compatibility", 'fixes', 'modpack', 'translation'. This would remove foundational mods from the search page. It will have to be done with a manual pass on the first 100 results of each tag + sorting.
	['delete', 'name'=>'Language'],
	['delete', 'name'=>'Translation'],
	['delete', 'name'=>'Korean'],
	['delete', 'name'=>'Spanish'],
	['delete', 'name'=>'Compatibility'],
	['delete', 'name'=>'Harmony Patching'],
	['delete', 'name'=>'Modpack'],
	['delete', 'name'=>'Rename'],
	['delete', 'name'=>'Fix'],
	['delete', 'name'=>'Hotfix'],
	['delete', 'name'=>'Patch'],
	// exception for library, only 1 mod is wrongly categorized in it
	['move_mods_to_category_from_tag', 'name' => 'Library', 'category' => CATEGORY_LIBRARY], // 0100 0001, work with the new category mask 0001 1111

	// 'Optimization' => Stays the same
	// 'Combat' => Stays the same
	// 'Storage' => Stays the same
	// 'Magic' => Stays the same
	// 'Accessibility' => Stays the same
	// 'Cheat' => Stays the same

	// MARK: merge
	// MULTIPLAYER
	['merge', 'from'=>'Claims', 'into'=>'Server-specific'],
	['merge', 'from'=>'Chat', 'into'=>'Server-specific'],
	['rename', 'from'=>'Server-specific', 'to'=>'Multiplayer'],

	// TOOLS & WEAPONS
	['merge', 'from'=>'Shields', 'into'=>'Weapons'],
	['merge', 'from'=>'Tools', 'into'=>'Weapons'],
	['merge', 'from'=>'Ranged', 'into'=>'Weapons'],
	['rename', 'from'=>'Weapons', 'to'=>'Tools and Weapons'],

	// ARMOR, CLOTHING and ACCESSORIES
	['merge', 'from'=>'Fashion', 'into'=>'Armor'],
	['merge', 'from'=>'Clothing', 'into'=>'Armor'],
	['rename', 'from'=>'Armor', 'to'=>'Armor, Clothing and Accessories'],

	// CREATURES
	['merge', 'from'=>'Mob-spawns', 'into'=>'Creatures'],
	['merge', 'from'=>'Animals', 'into'=>'Creatures'],
	['merge', 'from'=>'Goat', 'into'=>'Creatures'],
	['merge', 'from'=>'Genetics', 'into'=>'Creatures'],
	['merge', 'from'=>'Mob drops', 'into'=>'Creatures'],
	['merge', 'from'=>'Bosses', 'into'=>'Creatures'],
	['merge', 'from'=>'Imposter', 'into'=>'Creatures'],
	['merge', 'from'=>'Feverstone', 'into'=>'Creatures'],
	['merge', 'from'=>'Genelib', 'into'=>'Creatures'],
	['merge', 'from'=>'Taming', 'into'=>'Creatures'],

	// MOUNTS
	['merge', 'from'=>'Jaunt', 'into'=>'Mounts'],
	['merge', 'from'=>'Mount', 'into'=>'Mounts'],
	['merge', 'from'=>'Tack', 'into'=>'Mounts'], // accessories for mounts
	['become_subtag_of', 'child'=>'Mounts', 'parent'=>'Creatures'],

	// PETS
	['become_subtag_of', 'child'=>'Pets', 'parent'=>'Creatures'],


	// VISUAL AND GRAPHICS
	['merge', 'from'=>'Texture Pack', 'into'=>'Graphics'],
	['merge', 'from'=>'Oreshape', 'into'=>'Graphics'],
	['merge', 'from'=>'Cloud', 'into'=>'Graphics'],
	['merge', 'from'=>'Cosmetics', 'into'=>'Graphics'],
	['merge', 'from'=>'Visual', 'into'=>'Graphics'],
	['merge', 'from'=>'Shaders', 'into'=>'Graphics'],
	['rename', 'from'=>'Graphics', 'to'=>'Visual and Graphics'],

	// SIMULATION AND IMMERSION
	['merge', 'from'=>'Immersive', 'into'=>'Immersion'],
	['merge', 'from'=>'Realism', 'into'=>'Immersion'],
	['merge', 'from'=>'Survival', 'into'=>'Immersion'],
	['merge', 'from'=>'Roleplay', 'into'=>'Immersion'],
	['rename', 'from'=>'Immersion', 'to'=>'Immersion and Simulation'],


	// TRANSPORTATION
	['merge', 'from'=>'Teleport', 'into'=>'Transportation'],
	['merge', 'from'=>'Vehicle', 'into'=>'Transportation'],
	['merge', 'from'=>'Flight', 'into'=>'Transportation'],
	['merge', 'from'=>'Boat', 'into'=>'Transportation'],
	['merge', 'from'=>'Airship', 'into'=>'Transportation'],


	// MAP
	['merge', 'from'=>'Mapping', 'into'=>'Map'],
	['merge', 'from'=>'Donions', 'into'=>'Map'],


	// QoL, the most flawed tag of this migration, with 3000 mods... I need to find a way to break it
	['merge', 'from'=>'Simplicity', 'into'=>'QoL'],
	['merge', 'from'=>'Simplification', 'into'=>'QoL'],
	['merge', 'from'=>'Ergonomic', 'into'=>'QoL'],
	['merge', 'from'=>'Statistics', 'into'=>'QoL'],
	['merge', 'from'=>'Scrapping', 'into'=>'QoL'],
	['merge', 'from'=>'CCTV', 'into'=>'QoL'],
	['merge', 'from'=>'Camera', 'into'=>'QoL'],
	['merge', 'from'=>'HUD', 'into'=>'QoL'],
	['merge', 'from'=>'UI', 'into'=>'QoL'],
	['merge', 'from'=>'User Interface', 'into'=>'QoL'],
	['merge', 'from'=>'User experience', 'into'=>'QoL'],


	// Automation and Technology
	['merge', 'from'=>'Technology', 'into'=>'Automation'],
	['rename', 'from'=>'Automation', 'to'=>'Automation and Technology'],


	// Economy
	['merge', 'from'=>'Passive income', 'into'=>'Economy'],


	// World Generation
	['merge', 'from'=>'Space', 'into'=>'Worldgen'],
	['merge', 'from'=>'Ore generation', 'into'=>'Worldgen'],
	['merge', 'from'=>'Oregen', 'into'=>'Worldgen'],
	['merge', 'from'=>'Ore gen', 'into'=>'Worldgen'],
	['merge', 'from'=>'Terrain', 'into'=>'Worldgen'],
	['merge', 'from'=>'Underground', 'into'=>'Worldgen'],
	['merge', 'from'=>'Cave gen', 'into'=>'Worldgen'],
	['rename', 'from'=>'Worldgen', 'to'=>'World Generation'],


	// FOOD, COOKING and FARMING
	['merge', 'from'=>'Nutrients', 'into'=>'Food'],
	['merge', 'from'=>'Yield', 'into'=>'Food'],
	['merge', 'from'=>'Alcohol', 'into'=>'Food'],
	['merge', 'from'=>'Farming', 'into'=>'Food'],
	['merge', 'from'=>'Nutrition', 'into'=>'Food'],
	['merge', 'from'=>'Drink', 'into'=>'Food'],
	['merge', 'from'=>'Firepit', 'into'=>'Food'],
	['rename', 'from'=>'Food', 'to'=>'Food, Cooking and Farming'],


	// NPCs
	['merge', 'from'=>'NPC', 'into'=>'NPCs'],
	['merge', 'from'=>'Villagers', 'into'=>'NPCs'],


	// SOUND, MUSIC and VOICE
	['merge', 'from'=>'Voice', 'into'=>'Audio'],
	['merge', 'from'=>'Soundtrack', 'into'=>'Audio'],
	['merge', 'from'=>'Symphony', 'into'=>'Audio'],
	['merge', 'from'=>'Loud', 'into'=>'Audio'],
	['merge', 'from'=>'Music', 'into'=>'Audio'],
	['merge', 'from'=>'Sound', 'into'=>'Audio'],
	['merge', 'from'=>'Orchestra', 'into'=>'Audio'],
	['merge', 'from'=>'Orchestral', 'into'=>'Audio'],
	['rename', 'from'=>'Audio', 'to'=>'Sound, Music and Voice'],


	// Character customization and progression
	['merge', 'from'=>'Playermodel', 'into'=>'Classes'],
	['merge', 'from'=>'Race', 'into'=>'Classes'],
	['merge', 'from'=>'xskills', 'into'=>'Classes'],
	['rename', 'from'=>'Classes', 'to'=>'Character customization and progression'],


	// Story, Lore and Quests
	['merge', 'from'=>'Lore', 'into'=>'Story'],
	['rename', 'from'=>'Story', 'to'=>'Story, Lore and Quests'],


	// Structures and Location
	['merge', 'from'=>'Location', 'into'=>'Structures'],
	['rename', 'from'=>'Structures', 'to'=>'Structures and Location'],


	// FLORA
	['merge', 'from'=>'Biodiversity', 'into'=>'Flora'],
	['merge', 'from'=>'Pine', 'into'=>'Flora'],
	['merge', 'from'=>'Grass', 'into'=>'Flora'],
	['merge', 'from'=>'Resin', 'into'=>'Flora'],
	['merge', 'from'=>'Leaves', 'into'=>'Flora'],
	['merge', 'from'=>'Fruit Trees', 'into'=>'Flora'],
	['merge', 'from'=>'Foliage', 'into'=>'Flora'],


	// BUILDING AND DECORATION
	['merge', 'from'=>'Construction', 'into'=>'Building'],
	['merge', 'from'=>'Veneer', 'into'=>'Building'],
	['merge', 'from'=>'Glass', 'into'=>'Building'],
	['merge', 'from'=>'Confession', 'into'=>'Building'],
	['merge', 'from'=>'Decoration', 'into'=>'Building'],
	['merge', 'from'=>'Furniture', 'into'=>'Building'],
	['merge', 'from'=>'Chisel', 'into'=>'Building'],
	['merge', 'from'=>'Woodworking', 'into'=>'Building'],
	['rename', 'from'=>'Building', 'to'=>'Building and Decoration'],


	// SMITHING, MINING and PANNING
	['merge', 'from'=>'Mining', 'into'=>'Smithing'],
	['merge', 'from'=>'Metal', 'into'=>'Smithing'],
	['merge', 'from'=>'Fuel', 'into'=>'Smithing'],
	['merge', 'from'=>'Panning', 'into'=>'Smithing'],
	['rename', 'from'=>'Smithing', 'to'=>'Smithing, Mining and Panning'],


	// COLLECTABLES, TREASURES
	['merge', 'from'=>'Collect', 'into'=>'Collectables'],


	///////////
	// GENRES
	// Most mod can be tagged as one or more of these 3.
	// Each of them might end up having thousands of mods

	// HISTORICAL
	['rename', 'from'=>'Medieval', 'to'=>'Historical'],


	// HORROR
	// Meant for mods that aim to induce fear in the player. It’s important to select it properly, to allow players to specifically search for horror content, or exclude it from their research.
	['merge', 'from'=>'Spooky', 'into'=>'Horror'],


	// FANTASY
	['merge', 'from'=>'Elf', 'into'=>'Fantasy'],


	///////////////////
	// SPLIT OPERATIONS
	// MARK: replace w many
	['replace_with_many', 'from' => 'Traders', 'with' => ['NPCs', 'Economy']],
	['replace_with_many', 'from' => 'Ancient tools', 'with' => ['Tools and Weapons', 'Historical']],
	['replace_with_many', 'from' => 'Unicorns', 'with' => ['Creatures', 'Fantasy']],
	['replace_with_many', 'from' => 'Horses', 'with' => ['Creatures', 'Mounts']],
	['replace_with_many', 'from' => 'Pegasus', 'with' => ['Creatures', 'Fantasy']],
	['replace_with_many', 'from' => 'Dragons', 'with' => ['Creatures', 'Fantasy']],
	['replace_with_many', 'from' => 'Foraging', 'with' => ['Flora', 'Food, Cooking and Farming']],
	['replace_with_many', 'from' => 'Trading Cards', 'with' => ['Collectables', 'Immersion and Simulation']],
];




// Add the possibility to have subtags, necessary when we expect players to search this category/tag of mods, but each mods in the
// tag will systematically be tagged with another. Example : All Pets are creatures. All Mounts are creatures. Players might want
// to check Pets and Mounts as a category. These two specifically were easy to add because the tags already exist.
// MARK: subtag
$hasParentColumn = $con->getOne(
	"SELECT COUNT(*) FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
	AND TABLE_NAME = 'tags'
	AND COLUMN_NAME = 'parentTagId'"
);

if(!$hasParentColumn){
	$con->execute('ALTER TABLE `tags` ADD COLUMN `parentTagId` INT NULL DEFAULT NULL AFTER `kind`');
	$con->execute('ALTER TABLE `tags` ADD INDEX `parentTagId` (`parentTagId`)');
	$con->execute('
		ALTER TABLE `tags` ADD CONSTRAINT `FK_tags_parentTagId` FOREIGN KEY (`parentTagId`) REFERENCES `tags` (tagId)
		ON UPDATE CASCADE
		ON DELETE SET NULL
	');
}


// I'll target tags individually, by name.
function resolveTagByName($con, string $name, bool $optional): ?array {
	$tagRow = $con->getRow('SELECT tagId, name, text, kind, parentTagId FROM tags WHERE name = ?', [$name]);

	if(!$tagRow && !$optional) {
		throw new InvalidArgumentException("No tag named ".json_encode($name));
	}

	return $tagRow;
}

function copyTagRelations($con, int $sourceId, int $targetId) {

	// Moves modTagVotes
	// Create new rows of modTagVotes for the target tag, from the vote given to the source tag.
	// When multiple tags to merge are voted by the same user, the votes are summed and clamped. (+1, +1 => +1 | -1, +1 => 0...)
	$con->execute(
		'INSERT INTO modTagVotes (modId, tagId, userId, vote)
		SELECT src.modId, ?, src.userId, src.vote
		FROM (
			SELECT modId, userId, vote
			FROM modTagVotes
			WHERE tagId = ?
		) AS src
		ON DUPLICATE KEY UPDATE modTagVotes.vote = LEAST(1, GREATEST(-1, modTagVotes.vote + VALUES(vote)))', // adds the votes, and clamps them between -1 and 1
		[$targetId, $sourceId]
	);

	// Copies all modTags relations rows from the source, as the target.
	$con->execute(
		'INSERT INTO modTags (modId, tagId, votes)
		SELECT modId, ?, 0 FROM modTags WHERE tagId = ?
		ON DUPLICATE KEY UPDATE modTags.votes = modTags.votes',
		[$targetId, $sourceId]
	);

	// Recompute the vote count
	$con->execute(
		'UPDATE modTags modTag
		INNER JOIN (
			SELECT modId, SUM(vote) AS total
			FROM modTagVotes
			WHERE tagId = ?
			GROUP BY modId
		) modTagVotes ON modTagVotes.modId = modTag.modId AND modTag.tagId = ?
		SET modTag.votes = modTagVotes.total',
		[$targetId, $targetId]
	);
}


// MARK: Rename tag
function renameTag($con, string $from, string $to): void {
	$source = resolveTagByName($con, $from, true);
	$target = resolveTagByName($con, $to, true);

	if (!$source && $target) {
		echo "rename: skip '$from' (already renamed '$to')\n";
		return;
	}

	$con->execute('UPDATE tags SET name = ? WHERE tagId = ?', [$to, $source['tagId']]);

	echo "Renamed '$from' into '$to'\n";
}



// MARK: Merge
// Moves the modTagVotes and recomputes the modTags.votes
function mergeTags($con, string $from, string $into): void {
	$source = resolveTagByName($con, $from, true);

	if(!$source) {
		echo "merge: skip (no '$from')\n";
		return;
	}

	$target = resolveTagByName($con, $into, false);

	$sourceId = (int) $source['tagId'];
	$targetId = (int) $target['tagId'];

	copyTagRelations($con, $sourceId, $targetId);

	// Delete the source tag
	$con->execute('DELETE FROM tags WHERE tagId = ?', [$sourceId]);

	echo "Merged '$from' into '$into'\n";
}


// MARK: Delete
function deleteTag($con, string $name): void {
	$tag = resolveTagByName($con, $name, true);

	if(!$tag) {
		echo "delete: skip (no '$name')\n";
		return;
	}

	$con->execute('DELETE FROM tags WHERE tagId = ?', [$tag['tagId']]);

	echo "Deleted '$name'\n";
}



// MARK: Subtag fn
function becomeSubtagOf($con, string $childName, string $parentName): void {
	$child = resolveTagByName($con, $childName, false);
	$parent = resolveTagByName($con, $parentName, false);

	$childId = (int) $child['tagId'];
	$parentId = (int) $parent['tagId'];

	$con->execute('UPDATE tags SET parentTagId = ? WHERE tagId = ?', [$parentId, $childId]);

	echo "becomeSubtagOf : '$childName' became child of '$parentName'\n";
}


// MARK: replace w many
function replaceWithMany($con, string $from, array $with): void {
	$source = resolveTagByName($con, $from, true);

	if (!$source) {
		echo "replaceWithMany: skip (no '$from')\n";
		return;
	}

	$sourceId = (int) $source['tagId'];

	$targetIds = [];
	foreach ($with as $to) {
		$targetId = (int) resolveTagByName($con, $to, false)['tagId']; 
		$targetIds[] = $targetId;
	}

	foreach ($targetIds as $targetId) {
		copyTagRelations($con, $sourceId, $targetId);
	}

	$con->execute('DELETE FROM tags WHERE tagId = ?', [$sourceId]);

	echo "replace_with_many: '$from' was replaced with the tags [".implode(', ', $with)."]\n";
}

// MOVE FROM tag to library (currently its only used for Libraries)
function moveModsToCategoryFromTag($con, string $tagName, int $category, bool $deleteTagAfter = true) {
	$tag = resolveTagByName($con, $tagName, true);

	if (!$tag) {
		echo "move_mods_to_category_from_tag: skip (no tag ".json_encode($tagName).")\n";
		return;
	}

	$tagId = (int) $tag['tagId'];
	$modIds = $con->getCol(
		'SELECT DISTINCT modId FROM modTags WHERE tagId = ?',
		[$tagId]
	);

	// No mods to move
	if ($modIds === false || $modIds === []) {
		echo "move_mods_to_category_from_tag: no mods tagged ".json_encode($tagName)."\n";
		if ($deleteTagAfter) {
			deleteTag($con, $tagName);
		}
		return;
	}

	// Move selected mods to the given category
	$ids = implode(',', array_map('intval', $modIds));
	$con->execute("UPDATE mods SET category = ? WHERE modId IN ($ids)", [$category]);

	// log how many mod where moved to the new category
	echo "move_mods_to_category_from_tag: set category=$category on ".count($modIds)." mod(s) tagged ".json_encode($tagName)."\n";

	// Delete the tag
	if ($deleteTagAfter) {
		deleteTag($con, $tagName);
	}
}

// MARK: run operation
function runOperation($con, array $ope): void {
	$type = $ope[0];

	switch($type) {
		case 'delete':
			deleteTag($con, $ope['name']);
			break;

		case 'rename':
			renameTag($con, $ope['from'], $ope['to']);
			break;

		case 'merge':
			mergeTags($con, $ope['from'], $ope['into']);
			break;

		case 'become_subtag_of':
			becomeSubtagOf($con, $ope['child'], $ope['parent']);
			break;

		case 'replace_with_many':
			replaceWithMany($con, $ope['from'], $ope['with']);
			break;

		case 'move_mods_to_category_from_tag':
			moveModsToCategoryFromTag($con, $ope['name'], $ope['category']);
			break;

		default:
			echo "Wrong operation '$type'\n";
	}
}



$con->startTrans();
foreach ($operations as $operation) {
	runOperation($con, $operation);
}
$con->completeTrans();

echo "Done.\n";

