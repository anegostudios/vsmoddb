			<div style="clear:both;"><br></div>
			<h3><a name="comments"></a>{count($comments)} Comments <span style="font-size:70%">(<a href="#orderoldestfirst">oldest first</a> | <a href="#ordernewestfirst">newest first</a>)</span></h3>
			<div class="comments">
				{if !empty($user)}
				<div class="comment comment-editor editbox overlay-when-banned" style="clear:both; display:none;">
					<div class="title">
						{$user['name']}, 0 seconds ago
					</div>
					<div class="body">
						
						<form name="commentformtemplate" autocomplete="off">
							<textarea name="commenttext" class="whitetext editor" data-editorname="comment" style="width: 100%; height: 50px;"></textarea>
						</form>
					</div>
					<p style="margin:4px; margin-top:5px;"><button type="submit" name="save">Add Comment</button>
				</div>
				{/if}
			
				{foreach from=$comments item=comment}
					<div id="cmt-{$comment['commentid']}" class="editbox comment" data-timestamp="{strtotime($comment['created'])}" style="clear:both;">
						<div class="title">
							<a style="text-decoration:none;" href="#cmt-{$comment['commentid']}">&#128172;</a>
							{$comment['username']}{if !empty($comment["flaircode"])} <small class="flair flair-{$comment['flaircode']}"></small>{/if}{if $comment['isbanned']}&nbsp;<span style="color:red;">[currently restricted]</span>{/if}, {fancyDate($comment['created'])} {if $comment['modifieddate']}(modified at {$comment['modifieddate']}){/if}{if $comment['lastmodaction']} (edited by moderator){/if}
								{if !empty($user)}
										{if $comment["userid"] == $user["userid"]}
												<span class="buttonlinks strikethrough-when-banned">(<a href="#editcomment" data-commentid="{$comment['commentid']}">edit comment</a> <a style="margin-left:5px;"  href="#deletecomment" data-commentid="{$comment['commentid']}">delete</a>)</span>
										{elseif canModerate($comment['userid'], $user) && !($comment["userid"] == $user["userid"])}
												<span class="buttonlinks strikethrough-when-banned">(<a href="#editcomment" data-commentid="{$comment['commentid']}">edit comment</a> <a style="margin-left:5px;"  href="#deletecomment" data-commentid="{$comment['commentid']}">delete</a> <a style="margin-left:5px;"  href="/moderate/user/{$comment['usertoken']}?source-comment={$comment['commentid']}">moderate user</a>)</span>
										{elseif $asset['createduserid'] == $user['userid']}
												<span class="buttonlinks strikethrough-when-banned">(<a href="#deletecomment" data-commentid="{$comment['commentid']}">delete</a>)</span>
										{/if}
								{/if}
						</div>
						<div class="body">{autoFormat($comment['text'])}</div>
					</div>
				{/foreach}
			</div>
			
			<span class="buttonlinks template">&nbsp;(<a href="#editcomment" data-commentid="0">edit comment</a> <a style="margin-left:5px;" href="#deletecomment" data-commentid="0">delete</a>)</div>
			
			<div style="clear:both;"></div>
