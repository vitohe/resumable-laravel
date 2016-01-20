# PHP backend for resumable.js

This is a fork from [dilab/resumable.php](https://github.com/dilab/resumable.php) with changes to make it compatible with the [Laravel Framework](https://github.com/laravel/laravel)

It is currently a work in progress and it is currently developed with Laravel versions 5.1 and 5.2 in mind.

## Installation

To install, use composer:

``` composer require black-bits/resumable.js-laravel-backend ```


## How to use
**app/Http/routes.php**

```
<?php

// resumable.js routes
Route::get ('resumable/upload', 'UploadController@resumableUpload');
Route::post('resumable/upload', 'UploadController@resumableUpload');
```


**app/Http/Controllers/UploadController.php**

```
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Response;

use Illuminate\Support\Facades\File;

use Dilab\Network\SimpleRequest;
use Dilab\Network\SimpleResponse;
use Dilab\Resumable;

class UploadController extends Controller
{
    /**
     * Handles resumeable uploads via resumable.js
     * 
     * @return Response
     */
    public function resumableUpload()
    {
        $tmpPath    = storage_path().'/tmp';
        $uploadPath = storage_path().'/uploads';
        if(!File::exists($tmpPath)) {
            File::makeDirectory($tmpPath, $mode = 0777, true, true);
        }

        if(!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, $mode = 0777, true, true);
        }

        $simpleRequest              = new SimpleRequest();
        $simpleResponse             = new SimpleResponse();

        $resumable                  = new Resumable($simpleRequest, $simpleResponse);
        $resumable->tempFolder      = $tmpPath;
        $resumable->uploadFolder    = $uploadPath;


        if($resumable->process()) {
            return response([
                'message' => 'OK',
            ], 200);
        } else {
            return response([
                'message' => 'Chunk not found',
            ], 204);
        }
    }
}
```



## Testing
```
$ ./vendor/bin/phpunit
```

