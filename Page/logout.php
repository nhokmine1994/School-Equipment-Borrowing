<?php

require_once __DIR__ . '/../components/session_helper.php';

seb_destroy_session();
seb_redirect_after_logout();
