<?php

// Unfortunately we have to do this in php now, as we need all ids for the php ordering and therefore need to have them returned from the database.
$showDeleted = canModerate(null, $user);


$visibleCommentCount = $showDeleted ? count($comments) : array_reduce($comments, fn($count, $comment) => $count + ($comment['deleted'] ? 0 : 1), 0);

?>
			<div style="clear:both;"><br></div>
			<h3><a name="comments"></a>{$visibleCommentCount} Comment{$visibleCommentCount !== 1 ? 's' : ''} <span style="font-size:70%">(<a id="cmt-ord-asc" href="#" onclick="return false;">oldest first</a> | <a id="cmt-ord-desc" href="#" onclick="return false;">newest first</a>) (<a id="cmt-threaded" href="#" onclick="return false;">threaded</a> | <a id="cmt-flat" href="#" onclick="return false;">flat</a>)</span></h3>
			<div class="comments{if $threaded = ($_COOKIE['commentstructure'] ?? '') !== 'flat'} threaded{/if}">
				{if !empty($user)}
				<div class="comment comment-editor editbox overlay-when-banned overlay-when-readonly" style="display:none;">
					<div class="title">Add new comment:</div>
					<div class="body">
						
						<form name="commentformtemplate" autocomplete="off">
							<textarea name="commenttext" class="whitetext editor" data-editorname="comment" style="width: 100%; height: 50px;"></textarea>
						</form>
					</div>
					<p style="margin:4px; margin-top:5px;"><button class="button shine" type="submit" name="save">Add Comment</button>
				</div>
				{/if}
			
<?php

	if($threaded) \{
		// :MirroredLayouting
		$depthStack = [];

		foreach($comments as $i => $comment) \{
			for(; count($depthStack) && $depthStack[count($depthStack) - 1] >= $comment['responseDepth']; array_pop($depthStack)) \{
				?></div><?php
			}

			if($comment['children'] > 0) \{
				?><div class="convo"><?php
				array_push($depthStack, $comment['responseDepth']);
			}

			if(!$comment['deleted'] || $showDeleted) \{

				$view->assign('i', $i, null, true);
				$view->assign('comment', $comment, null, true);
				$view->load('comment');
			}
		}

		for($i = 0; $i < count($depthStack); $i++) \{
			?></div><?php
		}
	}
	else \{
		foreach($comments as $i => $comment) \{
			if(!$comment['deleted'] || $showDeleted) \{
				$view->assign('i', $i, null, true);
				$view->assign('comment', $comment, null, true);
				$view->load('comment');
			}
		}
	}
?>
			</div>
			<script nonce="{$cspNonce}">
			for(const c of document.querySelectorAll('.comment .body')) {
				if(!c.textContent.trim() && c.querySelector('img, iframe'))
					c.parentElement.classList.add('embed-only');
			}
			</script>
