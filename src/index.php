<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

use Utopia\App;
use Swoole\Http\Server;
use Utopia\Swoole\Request;
use Utopia\Validator\Boolean;
use Utopia\Validator\Text;
use Utopia\Swoole\Response;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;


App::get('/') // Define Route
    ->inject('request')
    ->inject('response')
    ->action(
        function (Request $request, Response $response) {
            $response->send("Hello, World!\n");
        }
    );

App::error(function (Throwable $error, Request $request, Response $response) {
    $response->setStatusCode(Response::STATUS_CODE_INTERNAL_SERVER_ERROR);
    $response->json([
        "error" => $error->getMessage()
    ]);
}, ['error', 'request', 'response']);

function fetchTodos()
{
    $path = \realpath('/app/src/todos.json');
    $data = \json_decode(\file_get_contents($path), true);
    return $data;
}

function saveTodos($todos)
{
    $path = \realpath('/app/src/todos.json');
    $jsonData = \json_encode($todos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    \file_put_contents($path, $jsonData);
}

App::post('/todos')
    ->desc('Create Todo')
    ->param('title', "", new Text(50), 'title of todo')
    ->inject('request')
    ->inject('response')
    ->action(
        function (string $title, Request $request, Response $response) {
            $todos = fetchTodos();
            $id = \uniqid();
            $todo = [
                "id" => $id,
                "title" => $title,
                "isComplete" => false
            ];
            $todos[$id] = $todo;
            saveTodos($todos);
            $response->setStatusCode(Response::STATUS_CODE_CREATED);
            $response->json($todo);
        }
    );

App::get('/todos')
    ->desc('List Todos')
    ->inject('request')
    ->inject('response')
    ->action(
        function (Request $request, Response $response) {
            $todos = fetchTodos();
            $data = [];
            foreach ($todos as $id => $todo) {
                $data[] = $todo;
            }
            $response->json($data);
        }
    );

App::get('/todos/:id')
    ->desc('Get Todo')
    ->param('id', "", new Text(50), 'id of the todo')
    ->inject('request')
    ->inject('response')
    ->action(
        function (string $id, Request $request, Response $response) {
            $todos = fetchTodos();
            if (!isset($todos[$id])) {
                $response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
                $response->json([
                    "error" => "todo with id $id not found"
                ]);
                return;
            }
            $response->json($todos[$id]);
        }
    );

App::patch('/todos/:id')
    ->desc('Update Todo')
    ->param('id', "", new Text(50), 'id of the todo')
    ->param('title', null, new Text(50), 'title of todo')
    ->param('isComplete', null, new Boolean(), 'if todo is complete', true)
    ->inject('request')
    ->inject('response')
    ->action(
        function (string $id, string $title, bool $isComplete, Request $request, Response $response) {
            $todos = fetchTodos();
            if (!isset($todos[$id])) {
                $response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
                $response->json([
                    "error" => "todo with id $id not found"
                ]);
                return;
            }

            if ($title !== null) {
                $todos[$id]['title'] = $title;
            }


            if ($isComplete !== null) {
                $todos[$id]['isComplete'] = $isComplete;
            }

            saveTodos($todos);

            $response->json($todos[$id]);
        }
    );

App::delete('/todos/:id')
    ->desc('Delete Todo')
    ->param('id', "", new Text(50), 'id of the todo')
    ->inject('request')
    ->inject('response')
    ->action(
        function (string $id, Request $request, Response $response) {
            $todos = fetchTodos();
            if (!isset($todos[$id])) {
                $response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
                $response->json([
                    "error" => "todo with id $id not found"
                ]);
                return;
            }

            unset($todos[$id]);

            saveTodos($todos);

            $response->noContent();
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
