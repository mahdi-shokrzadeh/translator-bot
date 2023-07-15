<?php

require 'vendor/autoload.php';

use Telegram\Bot\Api;

$telegram = new Api('');

$webhook = $telegram ->setWebhook(['url' => '']);

echo "sucssees"

?>