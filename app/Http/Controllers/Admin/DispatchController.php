<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DispatchController extends Controller
{
    public function index()
    {
        return view('admin.dispatch');
    }
}
