<?php

namespace App\Controllers;

use Vendor\View;

class HelloController
{
    public function index()
    {
        return View::make('hello', ['message' => 'Hello World!']);
    }
}
