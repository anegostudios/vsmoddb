			<div style="clear:both;"><br></div>
			<h3><a name="comments"></a>{count($comments)} Comments <span style="font-size:70%">(<a href="#orderoldestfirst">oldest first</a> | <a href="#ordernewestfirst">newest first</a>)</span></h3>
			<div class="comments">
				{if !empty($user)}
				<div class="comment comment-editor editbox overlay-when-banned overlay-when-readonly" style="clear:both; display:none;">
					<div class="title">Add new comment:</div>
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
							<span><a style="text-decoration:none;" href="#cmt-{$comment['commentId']}"><i class="bx bx-link-alt"></i></a>
							<a href="/show/user/{$comment['userHash']}">{$comment['username']}</a>{if !empty($comment["flairCode"])} <small class="flair flair-{$comment['flairCode']}"></small>{/if}{if $comment['isBanned']}&nbsp;<span style="color:red;">[currently restricted]</span>{/if}, {fancyDate($comment['created'])} {if $comment['contentLastModified']}(modified {fancyDate($comment['contentLastModified'])}{if $comment['lastModaction'] == MODACTION_KIND_EDIT} by a moderator{/if}){/if}{if $comment['lastModaction'] == MODACTION_KIND_DELETE} (deleted by moderator){/if}</span>
								{if !empty($user)}
										{if $comment["userId"] == $user["userId"]}
											{if !$comment['deleted']}
												<span class="buttons strikethrough-when-banned strikethrough-when-readonly">(<a href="#e">edit</a>&nbsp;<a href="#d">delete</a>)</span>
											{/if}
										{elseif canModerate($comment['userId'], $user) && !($comment["userId"] == $user["userId"])}
												<span class="buttons strikethrough-when-banned strikethrough-when-readonly">({if !$comment['deleted']}<a href="#e">edit</a>&nbsp;<a href="#d">delete</a>&nbsp;{/if}<a href="/moderate/user/{$comment['userHash']}?source-comment={$comment['commentId']}">moderate user</a>)</span>
										{elseif $asset['createdByUserId'] == $user['userId'] && !$comment['deleted']}
												<span class="buttons strikethrough-when-banned strikethrough-when-readonly">(<a href="#d">delete</a>)</span>
										{/if}
								{/if}
						</div>
						<div class="body">{postprocessCommentHtml($comment['text'])}</div>
						{if $comment['deleted']}<span class="ribbon-tr">Deleted</span>{/if}
					</div>
				{/foreach}
			</div>
