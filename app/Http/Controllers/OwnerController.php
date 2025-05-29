<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;  // لازم تستورده
use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:owner']);
    }

    public function index()
    {
        return view('owner.dashboard');
    }
}

