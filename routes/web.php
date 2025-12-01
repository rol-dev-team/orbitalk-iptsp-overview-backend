<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});




Route::get('/docs', function () {
    $path = resource_path('views/scribe/index.blade.php');

    if (!File::exists($path)) {
        abort(404, 'Scribe docs not generated.');
    }

    return Response::make(view('scribe.index'));
});
