<?php

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;

class DashboardController
{
    public function index(Request $request): Response
    {

return redirect('/', 302);
        debug($request->session()->all());
#        debug((app(\Faravel\Http\Session::class)->all()));


        $user = Auth::user();
        return response()->view('dashboard', [
            'user' => $user,
        ]);
    }
}