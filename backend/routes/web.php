<?php

use Illuminate\Support\Facades\Route;

/*
| The built admin panel (public/admin) and board (public/board) are static
| files the web server serves directly. These routes cover what it cannot:
| the start page, and the admin panel's own addresses (/admin/groups, ...)
| after a reload, which must all return its index.html.
*/

Route::redirect('/', '/admin/');

Route::get('/admin/{path?}', function () {
    $index = public_path('admin/index.html');
    abort_unless(is_file($index), 404, 'Admin panel is not built: run deploy\build.bat');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('path', '.*')->name('admin.app');
