<?php 

return [
    'paths' => [base_path('resources/views')],
    'default' => 'blade.php', // или 'php'
    // TTL кеша скомпилированных шаблонов Blade, сек.
    'blade_cache_ttl' => 3,
    // Если true, движок удаляет пустые/whitespace-только строки в финальном HTML.
    'collapse_empty_lines' => true,
];
