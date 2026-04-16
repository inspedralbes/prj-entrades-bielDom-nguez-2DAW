<?php

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Support\Facades\Route;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

Route::get('/', function () {
    return view('welcome');
});
