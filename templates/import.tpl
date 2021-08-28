	{include file="header"}
	<style type="text/css">
		.filename { margin-left: 30px; color:gray;}
		.pendingasset { 
			display:inline-block; 
			padding:5px; 
			border: 1px solid gray; 
			min-width: 700px;
			margin-top: 3px;
			margin-bottom: 3px;
		}
	</style>
	<script type="text/javascript">
		var importtype = "{$type}";
	</script>
	<script type="text/javascript" src="web/js/import.js"></script>

	<h3>Import {ucfirst($type)}</h3>
	
	{if !empty($message)}
		<p class="message">{$message}</p>
	{/if}
	
	<div class="dropinfo">
		<p>Yea ok, can do, Drag and Drop files here</p>

		<p class="status"></p>
		
		<div class="pendingassetsform" style="display:none;">
			<p><strong>Pending Assets</strong> (check if correctly detected, then submit)</p>
			<form name="form1" method="post">
				<input type="hidden" name="submittype" value="{$type}">
				<div class="pendingassets"></div>
				<br><br>
				<input type="submit" name="submitassets" value="Submit assets">
			</form>
		</div>
	</div>
	
{include file="footer"}
