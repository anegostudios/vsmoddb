{include file="header"}
	<p></p>
	<p>401 Unauthorized. This page requires you to be logged in.</p>
	
<p>
{if !empty($reason)}
	{$reason}
{/if}
</p>
<p>
<a href="javascript:window.history.back();">Go back</a>
</p>
{include file="footer"}
