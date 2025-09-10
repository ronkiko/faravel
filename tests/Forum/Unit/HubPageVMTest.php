<?php
/* tests/Forum/Unit/HubPageVMTest.php v0.1.0
Назначение: юнит-проверки HubPageVM — формирование ссылок и структур.
FIX: первый релиз. */

use App\Http\ViewModels\Forum\HubPageVM;

function test__HubPageVM_builds_urls_and_exports(): void {
    $vm = HubPageVM::from(
        tag: ['id'=>'t1','slug'=>'linux','title'=>'Linux'],
        pager: ['page'=>1,'per_page'=>20,'total'=>2],
        sort: ['key'=>'last','dir'=>'desc'],
        topics: [
            ['id'=>'111','slug'=>'s1','title'=>'A','posts_count'=>1,'created_at'=>1],
            ['id'=>'222','title'=>'B','posts_count'=>0,'created_at'=>2],
        ]
    );
    $arr = $vm->toArray();
    assert_eq($arr['topics'][0]['url'], '/forum/t/s1/');
    assert_eq($arr['topics'][1]['url'], '/forum/t/222/');
}
