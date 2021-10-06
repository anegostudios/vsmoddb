			<div style="clear:both;"><br></div>
			<h3><a name="comments"></a>{count($comments)} Comments <span style="font-size:70%">(<a href="#orderoldestfirst">oldest first</a> | <a href="#ordernewestfirst">newest first</a>)</span>{if !empty($user['rolecode'])}  <a href="#addcomment" class="add" title="Add a comment"></a>{/if}</h3>
			<div class="comments">
				<div class="comment template editbox" style="clear:both;">
					<div class="title">
						{if !empty($user)}{$user['name']}, 0 seconds ago{/if}
					</div>
					<div class="body">
						<form name="commentformtemplate">
							<textarea name="commenttext" class="editor" data-editorname="comment" style="width: 100%; height: 135px;"></textarea>
						</form>
					</div>
					<p style="margin-top:5px; margin-bottom:4px; float:right; clear:both; margin-right: 4px;"><button type="submit" name="save">Add Comment</button>
				</div>
			
				{foreach from=$comments item=comment}
					<div class="editbox comment" data-timestamp="{strtotime($comment['created'])}" style="clear:both;">
						<div class="title">
							{$comment['username']}, {fancyDate($comment['created'])} {if $comment['modifieddate']}(modified at {$comment['modifieddate']}){/if}
							{if !empty($user) && $comment["userid"] == $user["userid"]}
								<span class="buttonlinks">
									<a href="#deletecomment" data-commentid="{$comment['commentid']}" style="margin-right: 20px;">delete</a>
									<a href="#editcomment" data-commentid="{$comment['commentid']}">edit</a>
								</span>
							{/if}
						</div>
						<div class="body">{autoFormat($comment['text'])}</div>
					</div>
				{/foreach}
			</div>
			
			<span class="buttonlinks template">
				<a href="#deletecomment" data-commentid="0" style="margin-right: 20px;">delete</a>
				<a href="#editcomment" data-commentid="0">edit</a>
			</div>
			
			<div style="clear:both;"></div>
