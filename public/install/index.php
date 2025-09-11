<?php // v0.4.1
/* public/install/index.php
Purpose: Старый установщик перенесён под админку. Файл оставлен для обратной совместимости и
         перенаправляет в /admin/?page=install.
FIX: Добавлен 302-редирект на /admin/?page=install с текстовым уведомлением.
*/

declare(strict_types=1);

$target = '../admin/index.php?page=install';
header('Location: ' . $target, true, 302);
echo "Перенесено: воспользуйтесь новым адресом {$target}\n";
