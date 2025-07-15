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
					<p style="margin:4px; margin-top:5px;"><button class="button shine" type="submit" name="save">Add Comment</button>
				</div>
				{/if}
			
				{foreach from=$comments item=comment}
					<div id="cmt-{$comment['commentId']}" class="editbox comment{if $comment['deleted']} deleted{/if}" data-timestamp="{strtotime($comment['created'])}">
						<div class="title">
							<a style="text-decoration:none;" href="#cmt-{$comment['commentId']}">&#128172;</a>
							<a href="/show/user/{$comment['userHash']}">{$comment['username']}</a>{if !empty($comment["flairCode"])} <small class="flair flair-{$comment['flairCode']}"></small>{/if}{if $comment['isBanned']}&nbsp;<span style="color:red;">[currently restricted]</span>{/if}, {fancyDate($comment['created'])} {if $comment['contentLastModified']}(modified {fancyDate($comment['contentLastModified'])}{if $comment['lastModaction'] == MODACTION_KIND_EDIT} by a moderator{/if}){/if}{if $comment['lastModaction'] == MODACTION_KIND_DELETE} (deleted by moderator){/if}
								{if !empty($user)}
										{if $comment["userId"] == $user["userId"]}
											{if !$comment['deleted']}
												<span class="buttonlinks strikethrough-when-banned">(<a href="#editcomment" data-commentid="{$comment['commentId']}">edit comment</a> <a href="#deletecomment" data-commentid="{$comment['commentId']}">delete</a>)</span>
											{/if}
										{elseif canModerate($comment['userId'], $user) && !($comment["userId"] == $user["userId"])}
												<span class="buttonlinks strikethrough-when-banned">({if !$comment['deleted']}<a href="#editcomment" data-commentid="{$comment['commentId']}">edit comment</a> <a href="#deletecomment" data-commentid="{$comment['commentId']}">delete</a> {/if}<a href="/moderate/user/{$comment['userHash']}?source-comment={$comment['commentId']}">moderate user</a>)</span>
										{elseif $asset['creadedbyuserid'] == $user['userId'] && !$comment['deleted']}
												<span class="buttonlinks strikethrough-when-banned">(<a href="#deletecomment" data-commentid="{$comment['commentId']}">delete</a>)</span>
										{/if}
								{/if}
						</div>
						<div class="body">{postprocessCommentHtml($comment['text'])}</div>
						{if $comment['deleted']}<span class="ribbon-tr">Deleted</span>{/if}
					</div>
				{/foreach}
			</div>
			
			<span class="buttonlinks template">&nbsp;(<a href="#editcomment" data-commentid="0">edit comment</a> <a href="#deletecomment" data-commentid="0">delete</a>)</span>
