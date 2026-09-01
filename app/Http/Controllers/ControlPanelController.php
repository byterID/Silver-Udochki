<?php

namespace App\Http\Controllers;

class ControlPanelController extends Controller
{
    public function index()
    {
        return view('control-panel.index');
    }
}
