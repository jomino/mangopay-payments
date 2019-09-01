<?php

namespace App\Controllers;

class AssetsController extends \Core\Controller
{
    private $paths = [
      'js' => 'text/javascript',
      'css' => 'text/css',
      'fonts' => 'application/octet-stream',
      'images' => FILEINFO_MIME_TYPE
    ];

    public function __invoke($request, $response, $args)
    {
        $assets = $this->settings['assets'];
        $resource = $assets['path'] . '/' . $args['path'] . '/' . $args['file'];
        $content_type = $this->paths[$args['path']];
        if(is_file($resource) && is_readable($resource)){
            return $response->write(file_get_contents($resource))
                ->withHeader('Content-Type', $content_type);
        }else{
            $notFoundHandler = $this->notFoundHandler;
            return $notFoundHandler($request, $response);
        }
    }

}