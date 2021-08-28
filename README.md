# vsmoddb
Repository for https://mods.vintagestory.at



# VS Mod DB API Docs

## Format

Request: Normal GET requests

Response: Json. Every response contains a *statuscode* property which uses [HTTP Error Codes](https://en.wikipedia.org/wiki/List_of_HTTP_status_codes) to denote success/failure of a request.

## URLS

*Api base url*
http://mods.vintagestory.at/api

*Base url for all returned files*
http://mods.vintagestory.at/files/


## Interfaces

### /api/tags
List all mod tags

Example: http://mods.vintagestory.at/api/tags

### /api/gameversions
List all game version tags

Example: http://mods.vintagestory.at/api/gameversions

### /api/mods
List all mods

Example: http://mods.vintagestory.at/api/mods

Get Parameters:<br>
**tagids[]**: Filter by tag id<br>
**gameversion**: Filter by game version id<br>
**text**: Search by mod text and title<br>

Search Example: http://mods.vintagestory.at/api/mods?text=jack&tagids[]=7&tagids[]=8


### /api/mod/[modid]
List all info for given mod. Modid can be either the numbered id as retrieved by the mod list interface or the modid string from the modinfo.json

Example: http://mods.vintagestory.at/api/mod/6<br>
String example: http://mods.vintagestory.at/api/mod/carrycapacity

