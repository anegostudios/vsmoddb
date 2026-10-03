# vsmoddb
Repository for https://mods.vintagestory.at



# VS Mod DB API Docs

## URLS

*Api base url*
http://mods.vintagestory.at/api

*Api v2 base url - currently still in development*
http://mods.vintagestory.at/api/v2

~~*Base url for all returned files*~~
~~http://mods.vintagestory.at/~~
Always respect the full uris returned by the api.


## Interfaces

## V1

Request: Normal GET requests  
Response format: Json.  
Every response contains a *statuscode* property which uses [HTTP Error Codes](https://en.wikipedia.org/wiki/List_of_HTTP_status_codes) to denote success/failure of a request.

### /api/tags
List all mod tags

Example: http://mods.vintagestory.at/api/tags

### /api/gameversions
List all game version tags

Example: http://mods.vintagestory.at/api/gameversions

### /api/authors
List all authors (users)

Example: http://mods.vintagestory.at/api/authors

### /api/comments/[assetid]
Lists all comments for given assetid or latest 100 if assetid is not specified

Example: http://mods.vintagestory.at/api/comments

### /api/mods
List all mods

Example: http://mods.vintagestory.at/api/mods

Get Parameters:<br>
**tagids[]**: Filter by tag id (AND)<br>
**gameversion** or **gv**: Filter by game version id<br>
**gameversions[]**: Filter by game version ids (OR)<br>
**author**: Filter by author id<br>
**text**: Search by mod text and title<br>
**orderby**: Order by, one of: ``'asset.created', 'lastreleased', 'downloads', 'follows', 'comments', 'trendingpoints'`` (default: **asset.created**)<br>
**orderdirection**: Order direction, one of: ``'desc', 'asc'`` (default: **desc**)

Search Example: http://mods.vintagestory.at/api/mods?text=jack&tagids[]=7&tagids[]=8&orderby=downloads


### /api/mod/[modid]
List all info for given mod. Modid can be either the numbered id as retrieved by the mod list interface or the modid string from the modinfo.json

Example: http://mods.vintagestory.at/api/mod/6<br>
String example: http://mods.vintagestory.at/api/mod/carrycapacity


## V2 - still under development

### /api/v2/users/by-name/{search}
- `get`:
	- Args:
		- Path arg `{search}`
		- `limit`: Optional result count limit between 1 and 200 inclusive. Defaults to 10.
		- `contributors-only`: Optional filter to only return users that have contributed to at least one mod.
	- `200`: string - string dictionary, where keys are user hashes and values are usernames.

> [!IMPORTANT]  
> Endpoints marked as `auth` require authentication and response with `401` if it is missing.  
> Endpoints marked as `at` additionally require a valid actiontoken and response with `400` if it is missing. The token can be provided as a query parameter or in the POST body.  
> Endpoints marked as `token` require a mod api token in an `Authorization: Bearer <token>` header instead of a login session, and respond with `401` if it is missing, unknown or expired. If such a header is present any session cookie is ignored, and every other endpoint that requires authentication responds with `403`. See [Uploading releases from CI](#uploading-releases-from-ci).  

### /api/v2/mods/install-information
- `get`:
	- Args:
		- Query arg `ids`: Comma separated list of `{identifier}@{version}` pairs, where `{identifier}` is the identifier specified in the modinfo, and `{version}` is the semvar string of the desired version. If a `gv` is specified, the `@{version}` segment becomes optional.
		- Optional query arg `gv`: semvar string of the target game version
		- Optional query arg `ignore-retraction`: truthy value indicating that retracted releases should return their attachment anyways.
		- Optional query arg `hosted-mode`: truthy value indicating that the game is running in hosted mode, and only special mods are allowed.
	- `400`: Malformed game version, no ids or all ids are malformed.
	- `200`: json of the following format is returned: 
		```json
		{
			"data": {
				"modidentifier": {
					"errorCode": 1234,
				},
				"modidentifier2": {
					"fileName": "file.zip",
					"fileUrl": "/download/12331/file.zip"
				},
				"modidentifier3": {
					"fileName": "file.zip",
					"fileUrl": "/download/12332/file.zip",
					"recommendedUpgrade": "2.3.4"
				},
				"modidentifier5": {
					"errorCode": 4101,
					"retractionReason": "The reason why this release was retracted",
					"recommendedUpgrade": "2.3.4"
				},
				...
			}
		}
		```
		- `recommendedUpgrade` is provided if 
			- a) no version was provided as part of the mod spec -> this is the selected version
			- b) a gameversion was specified, and there is a newer version of the mod than specified that is compatible with that game version. The other data still pertains to the version of the mod spec.
		- Without the `ignore-retraction` argument specified, retracted releases will not respond with `fileName` and `fileUrl`.  
		- `errorCode` is a numeric code similar to http status codes indicating the error in a machine readable format:
			- `4001`: Failed to parse mod spec
			- `4002`: Mod spec does not contain a version and no gameversion was provided.
			- `4031`: Mod not allowed in hosted mode
			- `4032`: Cannot ignore release retraction
			- `4031`: Mod spec not found
			- `4101`: Release is retracted
			- `4102`: Release is force-retracted (cannot be ignored)


### /api/v2/mods/{modid}/releases
- `get`: 
	- Args:
		- Path arg `{modid}` (numeric)
		- Optional get arg `ignore-retraction`: truthy value to include retracted releases where the retraction may be ignored.
	- `404`: Mod not found.
	- `200`: json map keyed by release id:
		```json
		{
			"12345": {
				"identifier": "modidentifier",
				"version": "1.2.3"
			},
			"12345": {
				"identifier": "modidentifierlinux",
				"version": "1.2.3",
				"retractionReason": "Corrupts game files."
			},
		}
		```
- `post`: `token`
	- Creates and immediately publishes a new release, the same way the "Add release" form does. Followers of the mod get notified. The release is attributed to the user who created the token.
	- Only supported for mods in the "Game Mod" category. Also works for draft mods.
	- Args:
		- Path arg `{modid}` (numeric): Must be the mod the token belongs to.
		- Request body: `multipart/form-data` with the fields
			- `file`: The mod file. Exactly one file per request. The mod identifier and version of the release are read from the modinfo in this file, they cannot be passed separately.
			- `gameversions[]`: Compatible game version, e.g. `1.20.4`. Repeat the field for multiple versions. At least one is required, each one must be a known game version (see `GET /api/v2/game-versions`).
			- Optional `changelog`: Changelog html. It gets sanitized the same way as in the web form and may be at most 65535 bytes afterwards.
	- `400`: Content-Type is not `multipart/form-data`.
	- `400`: The mod is not in the "Game Mod" category.
	- `400`: Missing file, or more than one file.
	- `400`: Missing, malformed or unknown game versions.
	- `400`: Malformed or too large changelog.
	- `400`: File type not allowed, or the modinfo in the file could not be parsed.
	- `400`: The mod identifier is reserved (`game`, `creative`, `survival`).
	- `401`: Missing, malformed, unknown or expired token.
	- `403`: The token belongs to a different mod.
	- `403`: The creator of the token is currently banned, or is no longer the owner or a team member with edit permissions of the mod.
	- `403`: The mod is locked by a moderator.
	- `404`: Mod not found.
	- `409`: This version of the mod identifier has already been released for this mod, or the mod identifier is already in use by another mod.
	- `413`: The request or the file is too large. The file size limit is the same as in the web form, including per mod upload limit overrides.
	- `429`: More than 10 releases (`API_RELEASE_LIMIT_PER_MOD_PER_HOUR`) were created for this mod using api tokens within the last hour. The `Retry-After` header contains the number of seconds until the next release can be created. Failed uploads and releases created through the web form do not count towards this limit.
	- `503`: The site is in readonly mode. Contains a `Retry-After` header.
	- `201`: The release was created. The `Location` header contains the path of the new release (`/api/v2/mods/{modid}/releases/{releaseid}`), the body contains the release in the same format as `GET /api/v2/mods/{modid}/releases/{releaseid}`:
		```json
		{
			"releaseId": 456,
			"identifier": "modidentifier",
			"version": "1.2.3",
			"compatibleGameVersions": ["1.20.5", "1.20.4"],
			"created": 1758291166,
			"fileName": "modidentifier-1.2.3.zip",
			"fileUrl": "/download/123/modidentifier-1.2.3.zip"
		}
		```
		The `Location` is served by the public endpoint, which responds with `404` as long as the mod is still a draft.
	- Errors respond with a json object of the form `{"error": "message"}`.
	- Example:
		```sh
		curl -H "Authorization: Bearer $VSMODDB_TOKEN" \
			-F file=@mymod-1.2.3.zip \
			-F 'gameversions[]=1.20.4' -F 'gameversions[]=1.20.5' \
			--form-string 'changelog=<p>Fixes</p>' \
			https://mods.vintagestory.at/api/v2/mods/1234/releases
		```
		Use `--form-string` for the changelog: with `-F` curl treats a value starting with `<` as a file to read the value from.

### /api/v2/mods/{modid}/releases/{releaseid}
- `get`
	- Args:
		- Path arg `{modid}` (numeric)
		- Path arg `{releaseid}` (numeric)
	- `404`: Mod or release not found.
	- `200`: json of the release:
		```json
		{
			"releaseId": 456,
			"identifier": "modidentifier",
			"version": "1.2.3",
			"fileName": "modidentifier-1.2.3.zip",
			"fileUrl": "/download/123/modidentifier-1.2.3.zip",
			"compatibleGameVersions": ["1.20.7", "1.20.6"],
			"created": 1758291166,
			"retractionReason": "Corrupts game files."
		}
		```
		If the release is retracted, a `retractionReason` is present. `fileName` and `fileUrl` might be absent, if the retraction cannot be ignored.
- `post` `auth` `at`
	- Args:
		- Path arg `{modid}`
		- Path arg `{releaseid}`
	- `400`: Not implemented

### /api/v2/mods/{modid}/releases/latest
- `get`: 
	- Args:
		- Path arg `{modid}` (numeric)
		- Optional get arg `ignore-retraction`: truthy value to include download information for retracted releases.
		- Optional get arg `identifier`: identifier to select the latest release for (mods may host multiple identifiers).
	- `404`: No non-retracted release found.
	- `200`: same output as `GET /api/v2/mods/{modid}/releases/{releaseId}`

### /api/v2/mods/{modid}/releases/all
- `get`: Path arg `{modid}`
	- `400`: Not implemented

### /api/v2/mods/{modid}/releases/{releaseid}/retraction `auth` `at`
- `put`: 
	- Args:
		- Path arg `{modid}`
		- Path arg `{releaseid}`
	- `400`: Invalid action token.
	- `400`: Missing reason.
	- `400`: Release is already retracted.
	- `400`: Mod / release mismatch.
	- `403`: User does not have permission to edit release.
	- `404`: Target release does not exist.
	- `200`: Successfully retracted the release.

### /api/v2/mods/{modid}/releases/upload-limit `auth` `at`
- `get`:
	-	Args:
		- Path arg `{modid}`
	- `400`: Invalid action token.
	- `404`: Target mod does not exist.
	- `200`: Returns numeric limit or empty if there isn't one besides the default.
- `put`:
	-	Args:
		- Path arg `{modid}`
		- Post arg `limit`: Numeric limit or empty to indicate a reset to default.
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod does not exist.
	- `200`: Limit was successfully updated.

### /api/v2/mods/{modid}/api-tokens `auth` `at`
Management of the mod api tokens used by `POST /api/v2/mods/{modid}/releases`. All responses are sent with `Cache-Control: no-store`.
- `get`:
	- Args:
		- Path arg `{modid}` (numeric)
	- `403`: Invalid action token, or the user is neither the mod owner, a team member with edit permissions, nor a moderator / admin.
	- `404`: Target mod does not exist.
	- `200`: Json array of tokens, newest first. The mod owner, moderators and admins get all tokens of the mod, team members with edit permissions (and banned users) only their own. Expired tokens are included until they are revoked. The token itself is never returned.
		```json
		[
			{
				"tokenId": 12,
				"modId": 1234,
				"userId": 56,
				"creatorName": "Username",
				"name": "GitHub Actions",
				"created": "2026-10-01 12:00:00",
				"expires": "2027-10-01 12:00:00",
				"lastUsed": null,
				"isExpired": false
			}
		]
		```
- `post`:
	- Only the mod owner and team members with edit permissions may create tokens. Moderators and admins can not create tokens for mods they are not part of. Tokens can only be created for mods in the "Game Mod" category.
	- Args:
		- Path arg `{modid}` (numeric)
		- Post arg `name`: Name of the token, 1 to 64 characters of valid UTF-8 without control characters.
		- Post arg `lifetimeDays`: Number of days until the token expires, between 1 and 365 (`API_TOKEN_MAX_LIFETIME_DAYS`). Required, tokens always expire.
	- `400`: Name or lifetime missing, malformed or out of range, or the mod is not in the "Game Mod" category.
	- `403`: Invalid action token, the user is currently banned, or the user is not the mod owner or a team member with edit permissions.
	- `404`: Target mod does not exist.
	- `409`: The user already has 5 (`API_TOKEN_MAX_ACTIVE_PER_USER_PER_MOD`) non-expired tokens for this mod.
	- `201`: Token was created. This is the only time the token is returned, only a hash of it is stored.
		```json
		{
			"tokenId": 12,
			"token": "vsmoddb_...",
			"expires": "2027-10-01 12:00:00"
		}
		```

### /api/v2/mods/{modid}/api-tokens/{tokenid} `auth` `at`
- `delete`: Revokes (deletes) the token. The action token can be provided as a query parameter or in a form encoded body.
	- Args:
		- Path arg `{modid}` (numeric)
		- Path arg `{tokenid}` (numeric)
	- Allowed for the creator of the token, the mod owner, moderators and admins. Banned users may only revoke their own tokens.
	- `400`: Malformed `{tokenid}` or request body.
	- `403`: Invalid action token.
	- `404`: Target mod does not exist.
	- `404`: The token does not exist for this mod, or the user is not allowed to revoke it. Both cases give the same response.
	- `200`: Token was revoked.

### /api/v2/mods/{modid}/comments
- `get`: Path arg `{modid}`
	- `404`: Not implemented.
- `put`: `auth` `at`
	- Args:
		- Path arg `{modid}`
		- Get arg `response-to`: optional comment id for which this comment is a response to.
		- Request body: Desired comment html.
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod or response-to comment does not exist.
	- `403`: Active user is currently restricted.
	- `200`: Comment got created. Returns the processed html of the newly created comment as the response body, and the link to the comment in the Location header.

### /api/v2/mods/{modid}/lock `auth` `at`
- `post`:
	- Args:
		- Path arg `{modid}`
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod does not exist.
	- `403`: The authenticated user is not allowed to lock mods, or is currently restricted.
	- `200`: Mod was successfully locked.

### /api/v2/mods/{modid}/transfer `auth` `at`
- `post`:
	- Args:
		- Path arg `{modid}`
		- Post arg `newOwnerId`: The userId of the desired new owner. Needs to be a team member.
		- Optional post arg: `immediate`: Moderators may transfer mods immedaitely, without notifications and the prompt for the recipient.
		- Optional post arg: `previousOwnerHandling`: possible values: 'preserve', 'demote', 'remove'. Moderators may influence the membership and permissions of the old owner.
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod or new owner does not exist.
	- `403`: The authenticated user is not allowed to transfer this mod, or is currently restricted.
	- `200`: Mod was successfully transferred (request was initiated).
- `post`:
	- Args:
		- Path arg `{modid}`
		- Post arg `accept`: boolean value indicating whetehr or not the current user accepts a transfer request.
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod does not exist.
	- `403`: The authenticated user is not currently invloved in a transfer request, or is currently restricted.
	- `200`: Mod request was successfully processed.

### /api/v2/mods/{modid}/tags `auth` `at`
- `post`:
	- Args:
		- Path arg `{modid}`
		- Post arg `tags[]`: names of the tags to be added. These do not need to exist yet.
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod does not exist.
	- `403`: The authenticated user is currently restricted.
	- `200`: Tags successfully added.

### /api/v2/mods/{modid}/tags/{tagid}/vote `auth` `at`
- `put`:
	- Args:
		- Path arg `{modid}`
		- Path arg `{tagid}`
		- Post arg `vote`: New vote, either `-1`, `0`, or `1`.
	- `400`: Invalid action token or malformed request.
	- `404`: Target mod does not exist.
	- `403`: The authenticated user is currently restricted.
	- `200`: Vote successfully submitted.

### /api/v2/comments/{commentid} `auth` `at`
- `post`:
	- Args:
		- Path arg `{commentid}`
		- Request body: Desired comment html.
	- `400`: Invalid action token or malformed request.
	- `404`: Target comment does not exist.
	- `403`: Active user is currently restricted or does not have permissions to edit the comment.
	- `200`: Comment got updated. Returns the processed comment html as a object `{html: string}`.

- `delete`: Path arg `{commentid}`
	- `400`: Invalid action token or malformed request.
	- `404`: Target comment does not exist.
	- `403`: Active user is currently restricted or does not have permissions to delete the comment.
	- `200`: Comment got deleted.

### /api/v2/comments/{commentid}/unhide `auth` `at`
- `post`:
	- Args:
		- Path arg `{commentid}`
	- `400`: Invalid action token or malformed request.
	- `404`: Target comment does not exist.
	- `403`: Active user is currently restricted or does not have permissions to unhide the comment.
	- `200`: Comment got unhidden.

### /api/v2/notifications `auth`
- `get`: No args.
	- `200`: Array of notification ids for the current user. May be empty.

### /api/v2/notifications/{id} `auth`
- `get`: Path arg `{id}`
	- `404`: Not implemented.

### /api/v2/notifications/all `auth`
- `get`: No args.
	- `404`: Not implemented.

### /api/v2/notifications/clear `auth`
- `post`:
	- Args:
		- `ids`: Comma separated list of integers (takes priority). or
		- `ids[]`: formurlencoded ids.
	- `400`: No ids provided or argument malformed.
	- `403`: List of ids contains notifications that do not belong to the current user.
	- `200`: Notifications were marked as read if they exist.

### /api/v2/settings/gen-ai `auth`
- `post`:
	- Args:
		- Post arg `{tolerance}` specifies the new tolerance to set, range: 0 to 100. 0 means "don't care".
			Specifies the amount of 'low effort' reports required for a mod to be hidden for the current user.
	- `400`: No new value provided or argument is malformed.
	- `200`: Successfully updated settings.

### /api/v2/settings/notifications/followed-mods/{id} `auth`
- `post`:
	- Args:
		- Path arg `{id}` specifies the target mod id. Specifying an id that is not already followed will follow that mod with specified settings.
		- `new`: Integer value specifying the new settings.
			- `1 << 0`: Should receive notifications when this mod is updated.
	- `400`: No new value provided or argument is malformed.
	- `200`: Successfully updated settings.

### /api/v2/settings/notifications/followed-mods/{id}/unfollow `auth`
- `post`: Path arg `{id}` specifies the target mod id.
	- `400`: Argument is malformed.
	- `200`: Successfully unfollowed if mod was followed.

### /api/v2/tags/by-name/{search}
- `get`:
	- Args:
		- Path arg `{search}`
		- `limit`: Optional result count limit between 1 and 200 inclusive. Defaults to 10.
	- `200`: string - string dictionary, where keys are tag ids and values are tag names.

### /api/v2/game-versions
- `get`: (currently also `auth` TODO(Rennorb) @bug)
	- `200`: Returns string array of available game-versions in descending order.
- `post`: `auth` `at`
	- Args:
		- `new` specifies the new version to add. Must parse as our semver derivate.
	- `400`: Argument is malformed.
	- `403`: Active user is currently restricted or does not have permissions to add a new game-version.
	- `409`: The version to be added already exists.
	- `201`: Successfully added the new game-version.

### /api/v2/game-versions/{version}
- `delete`: `auth` `at`
	- Args:
		- Path arg `{version}` specifies the game-version to delete. Must parse as our semver derivate.
	- `400`: Argument is malformed or missing.
	- `403`: Active user is currently restricted or does not have permissions to delete the game-version.
	- `404`: The specified version does not exist.
	- `200`: Successfully deleted the specified game-version.

## Uploading releases from CI
Mod authors can upload new releases from a CI pipeline with a mod api token and `POST /api/v2/mods/{modid}/releases`. This is currently only supported for mods in the "Game Mod" category.

To create a token, open the edit page of the mod, enter a name in the "API tokens" section below the main form, pick an expiry and click "Create token". Copy the token right away, it is only shown once. The numeric `{modid}` of the mod is the `modid` value in the "Add release" link (`/edit/release/?modid=...`) on the mod page.

What a token can do:
- It belongs to one mod and can only create new releases for that mod. It cannot be used for anything else, including editing or retracting releases.
- It acts as the user who created it: releases are attributed to them. It stops working while that user is banned. As soon as they are no longer the owner or a team member with edit permissions of the mod (removed from the team, edit permissions taken away, or demoted / removed during an ownership transfer), all their tokens for that mod are deleted and do not come back if they get edit permissions again.
- It expires after at most 365 days. Each user can have at most 5 non-expired tokens per mod.
- It can be revoked on the mod edit page at any time by its creator, the mod owner, moderators and admins.
- Only a hash of the token is stored. Creating and revoking tokens is recorded in the audit log, and audit log entries of releases created with a token are marked as such.

Since the mod identifier and version are read from the modinfo in the uploaded file, the version in the modinfo has to be bumped for every upload, otherwise the upload fails with `409`.

Example GitHub Actions workflow that uploads a release whenever a tag starting with `v` is pushed. Store the token as a repository secret named `VSMODDB_TOKEN`, and replace the build step, the file path, the game versions and the mod id with your own:
```yaml
name: Publish release
on:
  push:
    tags: ['v*']

jobs:
  publish:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5

      - name: Build
        run: ./build.sh # placeholder, has to produce dist/mymod.zip

      - name: Upload to the mod db
        env:
          VSMODDB_TOKEN: ${{ secrets.VSMODDB_TOKEN }}
        run: |
          curl --fail-with-body \
            -H "Authorization: Bearer $VSMODDB_TOKEN" \
            -F "file=@dist/mymod.zip" \
            -F "gameversions[]=1.20.4" \
            --form-string "changelog=<p>Release $GITHUB_REF_NAME</p>" \
            https://mods.vintagestory.at/api/v2/mods/1234/releases
```
`--fail-with-body` makes the step fail on an error response while still printing the error message.

# Development setup
Requirements:
- [Docker](https://www.docker.com/)
- Any text editor

Steps:
- add `127.0.0.1 mods.vintagestory.stage` to your hosts file (this has to be a different domain from `vintagestory.at`, because that domain uses HSTS and so a self signed cert will not be deemed acceptable by browsers)
- create and set up `lib/config.priv.php` to match the (database) settings in [docker/docker-compose.yml](docker/docker-compose.yml). See `lib/config.php` for comments and available options.
- run `docker compose up -d` inside `docker/`

Result:
- [https://mods.vintagestory.stage/](https://mods.vintagestory.stage/)
	- Since we are using ssl now you will need to add an exception for `https://mods.vintagestory.stage` to your browser to view the page.
- [Adminer instance](http://localhost:8080)
- mysql 3306 is exposed

> **Note**  
> the mysql container is set up to automatically execute the provided [DB structure](db/000_tables.sql) + [sample data](db/999_sampledata.sql).

> **Note**  
> in staging environments you can define `DEBUGUSER` to the id of the user you want to impersonate. This might be useful for debugging and testing role related features. 

## Compiling styles
> **Note**  
> The `builder` container will automatically recompile styles and update cache-busting hashes for you if it is running. This section is only for documentation purposes.

We are now using [Sass](https://sass-lang.com/), mostly just to combine multiple stylesheets into one and minify them.

You can either download the standalone version of sass for your os, or install it globally into the [node package manager](https://www.npmjs.com/) via `npm install -g sass`. 

To compile the styles into one file run the command for your method of installation:
- standalone: `sass --style=compressed --update web/_sass/_style.scss:web/css/style.css`
- npm: `npx sass --style=compressed --update web/_sass/_style.scss:web/css/style.css` (simply prepend `npx`)

If you add the `-w` argument to this command, sass will continue running after the first compile instead of terminating, and watch for changes to the source files, which will trigger a automatic recompile.

## Compiling scripts
> **Note**  
> The `builder` container will automatically recompile scripts and update cache-busting hashes for you if it is running. This section is only for documentation purposes.

We are now using [typescript](https://www.typescriptlang.org) in combination with [rollup](https://rollupjs.org/), mostly just to combine multiple scripts into one and minify them.

Both of these want to be installed globally into the [node package manager](https://www.npmjs.com/) via `npm install -g typescript terser`.

To only compile typescript without compression the following command can be used: 
`npx tsc --target ES2021 --module none --removeComments --allowJs --alwaysStrict --outFile tmp/script.js --inlineSourceMap web/_ts/_script.ts`  
To properly compress the output terser is used roughly as follows `npx terser tmp/script.js -c 'passes=3,keep_fargs=false' -m -o web/js/script.js --source-map "url='script.js.map',content='inline'"`.

We purposefully do not include any configuration files for sass or tsc in the repository for now to avoid any unnecessary tool bloat.


# Testing
I've recently started adding tests for some components of the ModDB. For now they are based on php unit and require quite specific arguments to run.

## If you have php installed globally
If you have a global php installation (with Phar handling) and of the correct version (7.4), you can use it to run the tests:  
1. `cd tests`
2. `php phpunit.phar --test-suffix=.php .`

## Using the provided docker container
If you don't have php installed you can still use hte container that already runs the local development version of ModDB:  
1. `docker compose -f docker/docker-compose.yml exec php php tests/phpunit.phar --test-suffix=.php tests`

Both of the methods should yield the same result.