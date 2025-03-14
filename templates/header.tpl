<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>{if isset($pagetitle)}{$pagetitle}{/if}Vintage Story Mod DB</title>
	
	<link rel="apple-touch-icon" sizes="180x180" href="/web/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/web/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/web/favicon/favicon-16x16.png">
	<link rel="manifest" href="/web/favicon/site.webmanifest">
	<link rel="mask-icon" href="/web/favicon/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#da532c">
	<meta name="theme-color" content="#ffffff">

	<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

	<link href="/web/css/chosen.min.css" rel="stylesheet" type="text/css">
	<link href="/web/css/dialog.css" rel="stylesheet" type="text/css">
	<link href="/web/css/jquery.fancybox.min.css" rel="stylesheet" type="text/css">
	<link href="/web/js/chosen/chosen.min.css" rel="stylesheet" type="text/css">
	<link href="/web/css/datepicker.min.css" rel="stylesheet" type="text/css">
	<link href="/web/js/tinymce/plugins/spoiler/css/spoiler.css" rel="stylesheet" type="text/css">
	<link href="/web/css/style.css?version=52" rel="stylesheet" type="text/css">

	<link href="/web/js/tinymce/skins/ui/oxide/skin.mobile.min.css" as="style">
	<link href="/web/js/tinymce/skins/ui/oxide/content.mobile.min.css" as="style">
	<link href="/web/js/tinymce/skins/ui/oxide/fonts/tinymce-mobile.woff?8x92w3" as="font">

	<link href="/web/js/tinymce/skins/ui/oxide/skin.min.css" as="style">
	<link href="/web/js/tinymce/skins/ui/oxide/content.min.css" as="style">
	<link href="/web/css/editor_content.css" as="style">

	{if isset($assetserver) && startsWith($assetserver, 'http')}<link rel="dns-prefetch" href="{$assetserver}" />{/if}

	<script type="text/javascript" src="/web/js/jquery-1.11.1.min.js"></script>
	<script type="text/javascript" src="/web/js/chosen/chosen.jquery.min.js?v=2"></script>
	{if isset($head)}{$head}{/if}
</head>

<body{if !empty($user) && $user['isbanned']} class="banned"{/if}>
	<div class="okmessagepopup messagepopup bg-success text-success" style="display:none;">
		<div class="checkmark">&#10003;</div>
		<div class="text">{if isset($okmessage)}{$okmessage}{/if}</div>
	</div>
	<div class="warningmessagepopup messagepopup bg-warning text-warning" style="display:none;">
		<div class="checkmark">&#10006;</div>
		<div class="text">{if isset($warningmessage)}{$warningmessage}{/if}</div>
	</div>
	<div class="errormessagepopup messagepopup bg-error text-error" style="display:none;">
		<div class="checkmark">&#10006;</div>
		<div class="text">{if isset($errormessage)}{$errormessage}{/if}</div>
	</div>
	
	<a name="top"></a>
	
	<div class="content">
		<a href="/" class="logo"><img src="/web/img/vsmoddb-logo-s.png" align="left"></a>
		
		<nav id="main-nav">
			<a href="/home"{if in_array($urltarget, array('home', ''))} class="active"{/if}>Home</a>
			<a href="/list/mod"{if ($urltarget === 'list/mod')} class="active"{/if}>All Mods</a>
			{if (!empty($user))}
				<a href="/edit/mod"{if ($urltarget === 'edit/mod')} class="active"{/if}><img src="/web/img/upload.png"><span>Submit a mod</span></a>
			{/if}
			<span class="flex-spacer" style="max-width: 6em; flex-grow: .5"></span>
			<a class="external" href="https://wiki.vintagestory.at/Troubleshooting_Mods" target="_blank">Mod Troubleshooting</a>

			<span class="flex-spacer"></span>

			{if (!empty($user))}
				<span class="icon-only submenu notifications{if $urltarget === 'notifications'} active{/if}">
					<a href="/notifications">
						<span class="notificationcount{if $notificationcount && $urltarget !== 'notifications'} visible{/if}">{$notificationcount}</span>
						<i class="bx bxs-bell"></i>
					</a>
					<nav>
						{if $urltarget !== 'notifications'}
							{foreach from=$notifications item=notification}
								<a href="{$notification['link']}">{$notification['text']}<br>{fancyDate($notification['created'])}</a>
							{/foreach}
							{if $notificationcount == 0}
								<span>No new notifications, you're all caught up!</a>
							{else}
								<a href="/notification/clearall">Clear all notifications</a>
							{/if}
						{/if}
					</nav>
				</span>
				
				{if ($user['rolecode'] == 'admin')}
					<span class="icon-only submenu{if in_array($urltarget, array('list/user', 'list/tag', 'list/connectiontype', 'list/stati', 'list/assettypes'))} active{/if}">
						<span><i class="bx bxs-cog"></i></span>
						<nav>
							<a href="/list/user">Users</a>
							<a href="/list/tag">Tags</a>
						</nav>
					</span>
				{/if}

				<span class="submenu{if in_array($urltarget, array('accountsettings', 'show/user', 'show/user/'.$user['hash']))} active{/if}">
					<span>{$user["name"]}</span>
					<nav>
						<a href="/show/user/{$user['hash']}">Profile</a>
						<a href="/accountsettings">Settings</a>
						<a href="/logout?at={$user['actiontoken']}">Logout</a>
					</nav>
				</span>
			{else}
				<a href="/login"><img src="/web/img/login.png"><span>Log in</span></a>
			{/if}
		</nav>

		{if !empty($user) && $user['isbanned']}
			<div class="ban-notification" style="padding: 0.5em 1em; border: solid red 2px;">
				<h3 style="text-align: center;">You are currently banned until {formatDateWhichMightBeForever($user['banneduntil'], 'M jS Y, H:i:s', 'further notice')}.</h3>
				<p>
					<h4 style="margin-bottom: 0.25em;">Reason:</h4>
					{$user['bannedreason']}
				</p>
			</div>
		{/if}

		<div class="innercontent">
		
