<?php
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//*/

require '.internal.php';
$data = get_context();
$context = &$data['@context'];

header('Content-Disposition: inline; filename="context.jsonld"');
header('Content-Type: application/ld+json');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
