<?php

namespace Vendor;

class View
{
    public static function make($view, $data = [])
    {
        extract($data);
        ob_start();
        include __DIR__ . '/../resources/views/' . $view . '.php';
        return ob_get_clean();
    }
}
