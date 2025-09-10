<?php
/* tests/Forum/Unit/CategoryPageVMTest.php v0.1.0
Назначение: юнит-проверки CategoryPageVM — ссылки на хабы.
FIX: первый релиз. */

use App\Http\ViewModels\Forum\CategoryPageVM;

function test__CategoryPageVM_urls(): void {
    $vm = CategoryPageVM::from(
        ['id'=>'c1','slug'=>'cat','title'=>'Категория'],
        [['slug'=>'linux','title'=>'Linux']]
    );
    $arr = $vm->toArray();
    assert_eq($arr['hubs'][0]['url'], '/forum/f/linux/');
}
