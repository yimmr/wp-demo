<?php

$replace = $Multiple::createReplace('title', $Multiple::affix('菜单', '', 8));

$data['primary']['items'] = $Multiple::create($data['primary']['items'], 8, [], $replace);

$replace = $Multiple::createReplace('title', $Multiple::affix('链接', '', 10));

$data['footer-links']['items'] = $Multiple::create($data['footer-links']['items'], 10, [], $replace);

$modelSave->menuMany($data);