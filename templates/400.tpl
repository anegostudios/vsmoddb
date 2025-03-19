{include file="header"}
	<p></p>
	<p>400 Bad request</p>
	
<p>
{if !empty($reason)}
	{$reason}
{else}
	Go bug Rennorb about it, or something
{/if}
</p>
<p>
<a href="javascript:window.history.back();">Go back</a>
</p>
{include file="footer"}
