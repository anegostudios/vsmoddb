{include file="header" pagetitle="Notifications - "}

<h2>Your Notifications{if count($notifications)}<small style="float: right;"><a id="clear-selected" href="#" style="display: none;">Clear Selected</a>&nbsp;<a href="/notification/clearall">Clear All</a></small>{/if}</h2>

{if count($notifications)}
<form id="notifications-list" autocomplete="off">
	{foreach from=$notifications item=notification}
		<label class="list-entry" for="nid-{$notification['notificationId']}" data-label="{formatDateRelative($notification['created'])}">
			<input type="checkbox" name="nids[]" value="{$notification['notificationId']}" id="nid-{$notification['notificationId']}" />
			{$notification['text']}
			<div class="flex-spacer"></div>
			<a href="/notification/{$notification['notificationId']}">Go There</a>
			<a class="n-clear" href="#">Clear</a>
		</label>
	{/foreach}
</form>
{else}
	<span>All caught up!</span>
{/if}

{capture name="footerjs"}
	<script nonce="{$cspNonce}" type="text/javascript">
		const $list = $('#notifications-list');
		const $clearSelected = $('#clear-selected');

		$list.on('click', function(e) \{
			const target = e.target;
			if(!target.classList.contains('n-clear')) return;

			clearSpecific([target.parentElement.getAttribute('for').substr(4)])
				.done(function() \{
					if(e.target.parentElement.firstElementChild.checked) activeCheckboxes--;
					if(activeCheckboxes === 0) $clearSelected.hide();
				});

			e.preventDefault();
			return false;
		});

		let activeCheckboxes = 0;
		$list.on('change', function(e) \{
			if(e.target.checked) activeCheckboxes++; else activeCheckboxes--;
			if(activeCheckboxes > 0) $clearSelected.show(); else $clearSelected.hide();
		});

		let suppressFurtherClearSelected = false;
		$clearSelected.on('click', function() \{
			if(suppressFurtherClearSelected) return;
			suppressFurtherClearSelected = true;
			$checked = $list.find(':checked');
			clearSpecific($checked.map(function(_, e) \{ return e.value }).toArray())
				.done(function() \{
					activeCheckboxes = 0;
					$clearSelected.hide();
				})
				.always(function() \{
					suppressFurtherClearSelected = false;
				});
		})

		function clearSpecific(ids) \{
			const xhr = $.post('/api/v2/notifications/clear', \{ 'ids[]': ids })
				.done(function() \{
					for(const id of ids) \{
						$list.find(`label[for="nid-${id}"]`).remove();
					}
				});
			R.attachDefaultFailHandler(xhr, 'Failed to clear notification(s)');
			return xhr;
		}
	</script>
{/capture}

{include file="footer"}
