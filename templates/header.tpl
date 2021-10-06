<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Vintage Story Mod DB</title>
	

	<link rel="apple-touch-icon" sizes="180x180" href="/web/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/web/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/web/favicon/favicon-16x16.png">
	<link rel="manifest" href="/web/favicon/site.webmanifest">
	<link rel="mask-icon" href="/web/favicon/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#da532c">
	<meta name="theme-color" content="#ffffff">


	<link href="/web/css/style.css?version=9" rel="stylesheet" type="text/css">
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
	

	{if isset($head)}{$head}{/if}
</head>

<body>
	<div class="okmessagepopup messagepopup bg-success text-success" style="display:none;">
		<div class="checkmark">&#10003;</div>
		<div class="text">{if isset($okmessage)}{$okmessage}{/if}</div>
	</div>
	<div class="errormessagepopup messagepopup bg-error text-error" style="display:none;">
		<div class="checkmark">&#10006;</div>
		<div class="text">{if isset($errormessage)}{$errormessage}{/if}</div>
	</div>
	
	<a name="top"></a>
	
	<div class="content">
		<a href="/" class="logo"><img src="/web/img/vsmoddb-logo-s.png" align="left"></a>
		<!--<div class="betanotice">Beta!</div>-->
		
		<div class="navigation">
			<ul>
				<li class="mainmenuitem {if in_array($urltarget, array('home'))}active{/if}"><a href="/home">Home</a></li
				
				><li class="mainmenuitem {if in_array($urltarget, array('list/mod'))}active{/if}"><a style="padding: 5px 30px;" href="/list/mod">All Mods</a></li
				
				>{if (!empty($user))}<li class="mainmenuitem">
				
						<a href="/edit/mod"><img src="/web/img/upload.png"><span>Submit a mod</span></a>
					</li
					
					><li class="mainmenuitem right {if in_array($urltarget, array('accountsettings'))}active{/if}" style="margin-left:10px;">
						<a href="#">{$user["name"]}</a>
						<ul class="submenu">
							<li><a href="/accountsettings">Settings</a></li>
							<li><a href="/logout?at={$user['actiontoken']}">Logout</a></li>
						</ul>
					</li>

					{if ($user['rolecode'] == 'admin')}
					<li class="mainmenuitem right {if in_array($urltarget, array('list/user', 'list/tag', 'list/connectiontype', 'list/stati', 'list/assettypes'))}active{/if}">
						<a href="#">Admin</a>
						<ul class="submenu">
							<li class="menuitem"><a href="/list/user">Users</a></li>
							<li class="menuitem"><a href="/list/tag">Tags</a></li>
						</ul>
					</li>
					{/if}
					

					
				{else}
					<li class="mainmenuitem right">
						<a href="/login"><img src="/web/img/login.png"><span>Log in</span></a>
					</li>
				{/if}
				


				
				{if isset($mainmenuext)}{$mainmenuext}{/if}
				
			</ul>
		</div>

		<div class="innercontent">
		
