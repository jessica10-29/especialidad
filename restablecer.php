<?php
if (!empty($_GET['token'])) {
    header('Location: reset_password.php?token=' . urlencode((string)$_GET['token']), true, 302);
    exit;
}

header('Location: recover_password.php', true, 302);
exit;
