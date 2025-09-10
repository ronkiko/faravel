<?php
/* tests/Forum/Unit/CreateTopicPageVMTest.php v0.1.0
Назначение: юнит-проверка CreateTopicPageVM.
FIX: первый релиз. */

use App\Http\ViewModels\Forum\CreateTopicPageVM;

function test__CreateTopicPageVM_contract(): void {
    $vm = CreateTopicPageVM::from(
        ['id'=>'t','slug'=>'linux','title'=>'Linux'],
        ['id'=>'c','slug'=>'cat','title'=>'Cat'],
        '/forum/f/linux/create',
        'Заголовок','Текст'
    );
    $a = $vm->toArray();
    assert_eq($a['postUrl'], '/forum/f/linux/create');
    assert_eq($a['tag']['slug'], 'linux');
    assert_eq($a['category']['slug'], 'cat');
}
