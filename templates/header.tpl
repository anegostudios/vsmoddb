<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
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

	<link href="/web/css/style.css?version=33" rel="stylesheet" type="text/css">
	<link href="/web/css/chosen.min.css" rel="stylesheet" type="text/css">
	<link href="/web/css/dialog.css" rel="stylesheet" type="text/css">
	<link href="/web/css/jquery.fancybox.min.css" rel="stylesheet" type="text/css">
	<link href="/web/js/chosen/chosen.min.css" rel="stylesheet" type="text/css">
	<link href="/web/css/datepicker.min.css" rel="stylesheet" type="text/css">
	<link href="/web/js/tinymce/plugins/spoiler/css/spoiler.css" rel="stylesheet" type="text/css">

	<link rel="preload" href="/web/js/tinymce/skins/ui/oxide/skin.mobile.min.css" as="style">
	<link rel="preload" href="/web/js/tinymce/skins/ui/oxide/content.mobile.min.css" as="style">
	<link rel="preload" href="/web/js/tinymce/skins/ui/oxide/fonts/tinymce-mobile.woff?8x92w3" as="font">

	<link rel="preload" href="/web/js/tinymce/skins/ui/oxide/skin.min.css" as="style">
	<link rel="preload" href="/web/js/tinymce/skins/ui/oxide/content.min.css" as="style">
	<link rel="preload" href="/web/css/editor_content.css" as="style">
	
	<script type="text/javascript" src="/web/js/jquery-1.11.1.min.js"></script>
	<script type="text/javascript" src="/web/js/chosen/chosen.jquery.min.js"></script>
	{if isset($head)}{$head}{/if}
</head>

<body{if $user['isbanned']} class="banned"{/if}>
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
		
		<div class="navigation">
			<ul>
				<li class="mainmenuitem {if in_array($urltarget, array('home'))}active{/if}"><a href="/home">Home</a></li>
				<li class="mainmenuitem {if in_array($urltarget, array('list/mod'))}active{/if}"><a style="padding: 5px 30px;" href="/list/mod">All Mods</a></li>
				{if (!empty($user))}
					<li class="mainmenuitem"><a href="/edit/mod"><img src="/web/img/upload.png"><span>Submit a mod</span></a></li>
				{/if}
				<li class="mainmenuitem" style="margin-left: 70px;"><a style="padding: 5px 10px; font-size:90%;" href="https://wiki.vintagestory.at/Troubleshooting_Mods" target="_blank">Mod Troubleshooting</a></li>
				
				
				{if (!empty($user))}
					<li class="mainmenuitem right {if in_array($urltarget, array('accountsettings'))}active{/if}" style="margin-left:10px;">
						<a href="#">{$user["name"]}</a>
						<ul class="submenu">
							<li><a href="/show/user/{getUserHash($user['userid'], $user['created'])}">Profile</a></li>
							<li><a href="/accountsettings">Settings</a></li>
							<li><a href="/logout?at={$user['actiontoken']}">Logout</a></li>
						</ul>
					</li>

					{if ($user['rolecode'] == 'admin')}
					<li class="mainmenuitem right  icon {if in_array($urltarget, array('list/user', 'list/tag', 'list/connectiontype', 'list/stati', 'list/assettypes'))}active{/if}">
						<a href="#"><i style="color: white; font-size: 22px;" class="bx bxs-cog"></i></a>
						<ul class="submenu">
							<li class="menuitem"><a href="/list/user">Users</a></li>
							<li class="menuitem"><a href="/list/tag">Tags</a></li>
						</ul>
					</li>
					{/if}
					<li class="mainmenuitem right icon" style="position:relative">
						<a href="#">
							<span class="notificationcount {if $notificationcount>0}visible{/if}">{$notificationcount}</span>
							<i style="color: white; font-size: 22px;" class="bx bxs-bell"></i>
						</a>
						<ul class="submenu notifications">
						{foreach from=$notifications item=notification}
							<li style="clear:both;" class="menuitem"><a href="{$notification['link']}">{$notification['text']}{if $notification['type']!='clearall'}<br>{fancyDate($notification['created'])}{/if}</a></li>
						{/foreach}
						{if $notificationcount==0}
							<li class="menuitem nolink" style="display:block;"><span>No new notifications, you're all caught up!</a></li>
						{/if}
						</ul>
					</li>
					

					
				{else}
					<li class="mainmenuitem right">
						<a href="/login"><img src="/web/img/login.png"><span>Log in</span></a>
					</li>
				{/if}


				{if !empty($user) && $user['isbanned']}
					<div class="ban-notification" style="padding: 0.5em 1em; border: solid red 2px;">
						<h3 style="text-align: center;">You are currently banned until {formatDateWhichMightBeForever($user['banneduntil'], 'M jS Y, H:i:s', 'further notice')}.</h3>
						<p>
							<h4 style="margin-bottom: 0.25em;">Reason:</h4>
							{$user['bannedreason']}
						</p>
					</div>
				{/if}
				
				{if isset($mainmenuext)}{$mainmenuext}{/if}

			</ul>
		</div>

		<div class="innercontent">
		
