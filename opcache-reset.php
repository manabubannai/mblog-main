<?php
// Temporary opcache reset endpoint - secured by secret token
if (($_GET['token'] ?? '') !== 'mblog-deploy-2026') { http_response_code(403); exit('Forbidden'); }
if (function_exists('opcache_reset')) { opcache_reset(); echo 'opcache cleared'; } else { echo 'opcache not available'; }
