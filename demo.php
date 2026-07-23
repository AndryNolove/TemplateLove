<?php

require_once __DIR__ . '/template.php';

$data = [
    'title' => 'Список пользователей',
    'users' => [
        ['name' => 'Анна'],
        ['name' => 'Иван'],
    ],
];

$template = <<<'HTML'
<h1>[title]</h1>
<ul>[%users%<li>{name}</li>]</ul>
HTML;

echo template($data, $template, null, true);
