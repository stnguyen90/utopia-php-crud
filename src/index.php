<?php

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
}

use Utopia\App;
use Utopia\Swoole\Request;
use Utopia\Swoole\Response;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

App::get('/') // Define Route
    ->inject('request')
    ->inject('response')
    ->action(
        function(Request $request, Response $response) {
            $response->send("Hello, World!\n");
        }
    );

App::setResource('todos', function() {
    $path = \realpath('/app/src/todos.json');
    $data = \json_decode(\file_get_contents($path));
    return $data;
});

App::shutdown(function($todos) {
    \print_r($todos);
    $path = \realpath('todos.json');
    $jsonData = json_encode($todos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($path, $jsonData);
}, ['todos']);

App::get('/todos')
    ->inject('request')
    ->inject('response')
    ->inject('todos')
    ->action(
        function(Request $request, Response $response, $todos) {
            $data = [];
            foreach ($todos as $id => $todo) {
                $data[] = $todo;
            }
            $response->json($data);
        }
    );

$http = new Server("0.0.0.0", 8080);

$http->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) {
    $request = new Request($swooleRequest);
    $response = new Response($swooleResponse);

    $app = new App('UTC');
    
    try {
        $app->run($request, $response);
    } catch (\Throwable $th) {
        $swooleResponse->end('500: Server Error');
    }
});

$http->start();
