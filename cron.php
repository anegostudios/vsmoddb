<?php

$config = array();
$config["basepath"] = getcwd() . '/';
$_SERVER["SERVER_NAME"] = "stage.mods.vintagestory.at";
include("lib/core.php");

function processWebhooks(): void
{
    global $con;
    $rows = $con->getAll("SELECT * from commentwebhook");

    $multiHandle = curl_multi_init();
    $curlHandles = [];
    curl_multi_setopt($multiHandle,CURLMOPT_MAXCONNECTS, 10);

    foreach ($rows as $row){
        $userid = $row["userid"];
        $webhookUrl = $con->getRow("select commentwebhook,cwhFails from user where userid=?", array($userid));
        if(empty($webhookUrl["commentwebhook"]) || $webhookUrl["cwhFails"] > 5) {
            echo "\nskip wh to many fails or no url set\n";
            $con->Execute("DELETE from commentwebhook where userid=?", array($userid));
            continue;
        }
        $title = $row["isComment"] ? "New Comment" : "New Mention";
        $webhookData = createWebhookComment($row["linkurl"], $row["username"], $title);
        $encodeData = json_encode($webhookData);

        initCurlHandles($webhookUrl["commentwebhook"], $encodeData, $userid, $multiHandle, $curlHandles);
    }

    runAndCheck($multiHandle, $curlHandles, true);

    $rows = $con->getAll("SELECT * from followwebhook");
    foreach ($rows as $row){
        $followWebhookUserIds = $con->getAll("select followwebhookuser.userid from followwebhookuser left join followwebhook on (followwebhook.id = followwebhookuser.followwebhookid) where followwebhook.id =?", array($row["id"]));

        foreach ($followWebhookUserIds as $userid){
            $webhookUrl = $con->getCol("select followwebhook,fwhFails from user where userid=?", array($userid));
            if(empty($webhookUrl["followwebhook"]) || $webhookUrl["cwhFails"] > 5) {
                echo "\nskip wh to many fails or no url set\n";
                $con->Execute("DELETE from followwebhookuser where userid=? LIMIT 1", array($userid));
                continue;
            }
            $data = $row["data"];
            initCurlHandles($webhookUrl["followwebhook"], $data, $userid, $multiHandle, $curlHandles);
        }
    }

    runAndCheck($multiHandle, $curlHandles);

    curl_multi_close($multiHandle);

    //$yeet = $con->getAll("select followwebhookuser.followwebhookid from followwebhookuser left join followwebhook on (followwebhook.id = followwebhookuser.followwebhookid)");

    //    $con->Execute("DELETE from followwebhook");
//    $con->Execute("DELETE from followwebhookuser");
}

/**
 * @param CurlMultiHandle $multiHandle
 * @param array $curlHandles
 * @param bool $isComment
 */
function runAndCheck(CurlMultiHandle $multiHandle, array $curlHandles, bool $isComment = false): void
{
    global $con;
    $running = 0;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    foreach ($curlHandles as $ch) {
        $handle = $ch["ch"];
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($httpCode < 200 || $httpCode > 299) {
            echo "\nwebhook failed " . $httpCode . "\n";
            if($isComment){
                $con->Execute("UPDATE user SET cwhFails = cwhFails + 1 where userid=?", array($ch["userid"]));
            }else{
                $con->Execute("UPDATE user SET fwhFails = fwhFails + 1 where userid=?", array($ch["userid"]));
            }
        } else {
            if($isComment){
                $con->Execute("DELETE from commentwebhook where userid=? LIMIT 1", array($ch["userid"]));
            }else{
                $con->Execute("DELETE from followwebhookuser where userid=?", array($ch["userid"]));
            }
        }
        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);
    }
}

/**
 * @param mixed $webhookUrl
 * @param mixed $data
 * @param CurlMultiHandle $multiHandle
 * @param mixed $userid
 * @param array $curlHandles
 */
function initCurlHandles(string $webhookUrl, mixed $data, int $userid, CurlMultiHandle $multiHandle, array &$curlHandles): void
{
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_multi_add_handle($multiHandle, $ch);
    $curlHandles[] = array(
        "ch" => $ch,
        "url" => $webhookUrl,
        "data" => $data,
        "userid" => $userid
    );
}

function createWebhookComment($linkurl, $username, $title){
    return [
        "content" => null,
        "embeds" => [
            [
                "title" => $title,
                "color" => 9544535,
                "fields" => [
                    [
                        "name" => "Mod:",
                        "value" => $linkurl,
                        "inline" => true
                    ],
                    [
                        "name" => "From:",
                        "value" => $username,
                        "inline" => true
                    ]
                ],
                "thumbnail" => [
                    "url" => "https://mods.vintagestory.at/web/img/vsmoddb-logo.png"
                ]
            ]
        ],
        "attachments" => [ ]
    ];
}

function testWebhooks(){
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    curl_multi_setopt($multiHandle,CURLMOPT_MAXCONNECTS, 10);

    $webhookUrl = "https://test.com/api/webhooks/1/2";
    initCurlHandles($webhookUrl, json_encode(createWebhookComment("testlink", "someuser", "New Test")), 1, $multiHandle, $curlHandles);

    $webhookUrl = "https://discord.com/api/webhooks/1/2";
    initCurlHandles($webhookUrl, json_encode(createWebhookComment("testlink", "someuser", "New Test")), 2, $multiHandle, $curlHandles);

    runAndCheck($multiHandle, $curlHandles);

    curl_multi_close($multiHandle);
}

processWebhooks();
//testWebhooks();