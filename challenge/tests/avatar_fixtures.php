<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/avatar_render.php';
$cases = [];
foreach (getAvatarCatalog() as $id => $item) {
    $config = getAvatarDefaultLoadout();
    $config[$item['slot']] = $id;
    $cases[] = ['id' => $id, 'config' => $config, 'svg' => renderKintoAvatar($config, ['size' => 'md', 'animate' => false])];
}
echo json_encode(['catalog' => getAvatarClientCatalog(), 'defaults' => getAvatarDefaultLoadout(), 'cases' => $cases]);
