<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * The built frontend is a single page. A direct open of /report or /organization/report
 * (the print view) is not a file, so without this the server answers 404. Real assets
 * keep their own 404 when the file is missing.
 */
Route::fallback(function () {
    $index = public_path('index.html');
    $extension = pathinfo(request()->path(), PATHINFO_EXTENSION);

    if (request()->is('api/*') || $extension !== '' || ! is_file($index)) {
        abort(404);
    }

    return response()->file($index, ['Content-Type' => 'text/html; charset=UTF-8']);
});
