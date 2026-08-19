<? 
/**
 * @var array $logEntry
 * @var array $user
 */
?>
<td><?= (!($logEntry['flags'] & AUDIT_LOG_FLAG_MODACTION) || canModerate(null, $user)) ? escapeHtml($logEntry['username']) : 'A&nbsp;Moderator' ?></td>
