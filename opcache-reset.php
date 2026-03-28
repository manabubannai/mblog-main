<?php
// Called by deploy.yml after git pull to clear PHP opcache
// Protected by X-Deploy-Token header
if (php_sapi_name() === 'cli') { exit('CLI not supported'); }
if (($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '') !== 'mblog-opcache-2026') { http_response_code(403); exit; }
if (function_exists('opcache_reset')) { opcache_reset(); echo 'ok'; } else { echo 'no-opcache'; }
