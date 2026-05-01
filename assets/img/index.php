<?php
// Security file - Prevent directory listing
header('HTTP/1.0 403 Forbidden');
echo 'Directory access is forbidden.';
exit;
?>