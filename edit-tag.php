<?php
if (empty($user)) {
	header('Location: /login');
	exit();
}
if ($user['roleCode'] != 'admin') showErrorPage(HTTP_FORBIDDEN);

$tagId = $_REQUEST['tagid'] ?? 0;

$save = !empty($_POST['save']);
$delete = !empty($_POST['delete']);

if ($save || $delete) {
	validateActionToken();
}

if ($save) {
	$isNew = false;

	$color = intval(substr($_POST['color'], 1), 16) << 8 | 0x000000FF;
	$name = strip_tags($_POST['name']);
	$text = strip_tags($_POST['text']);

	if (!$tagId) {
		$isNew = true;

		$con->execute('INSERT INTO tags (name, text, color, kind) VALUES (?, ?, ?, 2)', [$name, $text, $color]);

		addMessage(MSG_CLASS_OK, 'Tag created.');

		if (!empty($_POST['saveandback']))  header('Location: /list/tag?saved=1');
		else header("Location: /edit/tag?tagid=$tagId");
		exit();
	} else {
		$con->execute('UPDATE tags SET name = ?, text = ?, color = ? WHERE tagId = ?', [$name, $text, $color, $tagId]);

		addMessage(MSG_CLASS_OK, 'Tag saved.');

		if (!empty($_POST['saveandback']))  header('Location: /list/tag?saved=1');
		else   forceRedirectAfterPOST();
		exit();
	}
}
else if ($delete) {
	$con->execute('DELETE FROM tags WHERE tagId = ?', [$tagId]);
	header('Location: /list/tag?deleted=1');
	exit();
}

if ($tagId) {
	$row = $con->getRow("SELECT *, LPAD(HEX(color), 8, '0') as color FROM tags WHERE tagId = ?", [$_REQUEST['tagid']]);
} else {
	$row = ['tagId' => 0, 'name' => '', 'text' => '', 'color' => '000000'];
}

cspPushAllowedInlineHandlerHash('sha256-nTlTeikEEupAQmSPlHWFcoJvMdPCIBu+Zu+G64E7uC4='); // javascript:submitForm(0)
cspPushAllowedInlineHandlerHash('sha256-XKuSPEJjbu3T+mAY9wlP6dgYQ4xJL1rP4m3GrDwZ68c='); // javascript:submitForm(1)

$view->assign('row', $row);
$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->display("edit-tag");
