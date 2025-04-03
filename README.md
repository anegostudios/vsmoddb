# vsmoddb
Repository for https://mods.vintagestory.at



# VS Mod DB API Docs

## Format

Request: Normal GET requests

Response: Json. Every response contains a *statuscode* property which uses [HTTP Error Codes](https://en.wikipedia.org/wiki/List_of_HTTP_status_codes) to denote success/failure of a request.

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

### /api/changelogs/[assetid]
Lists all changelogs for given assetid or latest 100 if assetid is not specified

Example: http://mods.vintagestory.at/api/changelogs

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

Endpoints marked as `auth` require authentication and response with `401` if it is missing.

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

### /api/v2/notifications/settings/followed-mods/{id} `auth`
- `post`:
	- Args:
		- Path arg `{id}` specifies the target mod id. Specifying an id that is not already followed will follow that mod with specified settings.
		- `new`: Integer value specifying the new settings.
			- `1 << 0`: Should receive notifications when this mod is updated.
	- `400`: No new value provided or argument is malformed.
	- `200`: Successfully updated settings.

### /api/v2/notifications/settings/followed-mods/{id}/unfollow `auth`
- `post`: Path arg `{id}` specifies the target mod id.
	- `400`: Argument is malformed.
	- `200`: Successfully unfollowed if mod was followed.

# Development setup
## VS Code - Remote Containers
You can use the provided vscode devcontainer to get up a running without installing everything on your own.

Required for that is docker installed aswell as docker-compose and vscode with the [Remote-Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension.
Then you can open the [devcontainer.json](.devcontainer/devcontainer.json) in vscode, and it should prompt you 
```
Folder contains a Dev Container configuration file. Reopen folder to develop in a container ([learn more](https://aka.ms/vscode-remote/docker)).
```
Simply click reopen in container, and it should start building the devcontainer and starting the mysql database aswell.

Now edit the [config.php](lib/config.php) to match the settings in the [dockerdocker-compose.yml](.devcontainer/docker-compose.yml) for the db `MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD`
and add `127.0.0.1	stage.mods.vintagestory.at`  to your hosts file on your local machine.

To deploy the database to the mysql instance run the [tables.sql](db/tables.sql) script against the database. You can use MySQL WOrkbench or any other mysql tool. When connecting from your local machine use localhost and 3306 (default) port to connect.

There is also a optional MySQL Workbench container that when enabled in the [dockerdocker-compose.yml](.devcontainer/docker-compose.yml) can be reached at [http://localhost:4444/](http://localhost:4444/). To connect to the mysql database from workbench container use `db` for the hostname.

## Universal (vscode/intellij/notepad)
Requirements:
- [Docker](https://www.docker.com/)

Steps:
- add `127.0.0.1 stage.mods.vintagestory.at` to your hosts file
- run `docker compose up -d` inside [docker/](docker)
- edit [config.php](lib/config.php) to match the settings in [dockerdocker-compose.yml](docker/docker-compose.yml)

Result:
- [http://stage.mods.vintagestory.at/](http://stage.mods.vintagestory.at/)
- [Adminer instance](http://localhost:8080)
- mysql 3306 is exposed

Note: the mysql container is set up to automatically execute the provided [DB structure + sample data](db/tables.sql).

Note: in staging environments oyu can append `?showas=<id>` to any url to load the page the user with that id. This can be used debugging and testing role related features. 

