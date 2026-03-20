<?php

declare(strict_types=1);

$bl_config = require __DIR__ . '/../.env/bl_config.php';

require_once __DIR__ . '/../src/bl_exception.php';
require_once __DIR__ . '/../src/bl_db.php';
require_once __DIR__ . '/../src/bl_mail.php';
require_once __DIR__ . '/../src/bl_linktoken.php';
require_once __DIR__ . '/../src/bl_session.php';
require_once __DIR__ . '/../src/bl_auth.php';

return $bl_config;