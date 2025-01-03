<?php

// config.php: Configuration des options d'antibot
return [
    'mobile_only' => false, // Activer le verrouillage mobile
    'allow_countries' => ['CA', 'FR'], // Liste des pays autorisés (codes ISO)
    'allow_regions' => ['Quebec'], // Liste des régions autorisées
    'block_proxies' => true, // Bloquer les connexions proxy/VPN
    'verify_user_agent' => true, // Vérifier les User-Agent
    'captcha_enabled' => true // Activer la vérification CAPTCHA
];

?>