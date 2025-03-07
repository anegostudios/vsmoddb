<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>VS Assets DB</title>
	
	<link rel="shortcut icon" href="/web/img/favicon.ico" type="image/x-icon">

	<link href="/web/css/style.css" rel="stylesheet" type="text/css">

	<script type="text/javascript" src="/web/js/jquery-1.11.1.min.js"></script>
	
	{if isset($head)}{$head}{/if}

</head>

<body>
	<a name="top"></a>
	<div id="navigation">
		<a href="/list/schematic" style="padding:0px;"><img src="/web/img/icon.png" align="left" style="width: 39px; padding-top:2px; padding-left:5px;"></a>
		<ul>
			
			
		</ul>
	</div>

	<div id="content">
		
		

<form method="post">
	<div style="width:400px; margin:auto; text-align:center; margin-top:30px;">
		<h2>Vintage Story Asset DB</h2>
		<p style=" margin-top:30px;">Please log in for great Justice.</p>
	
		<div class="editbox" style="display:inline-block; text-align:left; float:none;">
			<label>E-Mail</label>
			<input type="text" name="email" value="{$email}" style="width:200px;">
		</div>
		
		<div class="editbox flex-fill" style="display:inline-block; text-align:left; float:none;">
			<label>Password</label>
			<input type="password" name="password" style="width:200px;">
		</div>
		
		<div style="clear:both;">
			<input type="submit" name="attemptlogin" value="Initiate asset creation frenzy." style="margin-left: -7px; margin-top: 15px; width:215px;">
		</div>
		
		<div class="text-error" style="margin-top:30px;">
			{$errormessage}
		</div>
	</div>
	
</form>


{include file="footer"}