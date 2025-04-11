<?php
require __DIR__ . '/../vendor/autoload.php';

use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Socket\SocketServer;

$loop = Factory::create();

function getDataFile() {
    return __DIR__ . '/../data/data.json';
}

function readData() {
    $file = getDataFile();
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function saveData($data) {
    $file = getDataFile();
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$server = new HttpServer(function (ServerRequestInterface $request) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if ($path === '/') {
        return new Response(200, ['Content-Type' => 'text/html'], file_get_contents(__DIR__ . '/../public/index.html'));
    }

    if ($path === '/contacto') {
        return new Response(200, ['Content-Type' => 'text/html'], file_get_contents(__DIR__ . '/../public/contacto.html'));
    }

    if ($path === '/crud') {
        return new Response(200, ['Content-Type' => 'text/html'], file_get_contents(__DIR__ . '/../public/crud.html'));
    }

    if ($path === '/style.css') {
        return new Response(200, ['Content-Type' => 'text/css'], file_get_contents(__DIR__ . '/../public/style.css'));
    }

    if ($path === '/data') {
        $body = $request->getBody()->getContents();
        $input = json_decode($body, true);

        if ($method === 'GET') {
            // Obtener todos los usuarios
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(readData()));
        }

        if ($method === 'POST') {
            // Crear nuevo usuario
            $datos = readData();
            $nuevo = [
                'id' => count($datos) > 0 ? end($datos)['id'] + 1 : 1,
                'nombre' => $input['nombre'],
                'email' => $input['email']
            ];
            $datos[] = $nuevo;
            saveData($datos);
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'ok']));
        }

        if ($method === 'DELETE') {
            // Eliminar usuario
            $datos = readData();
            $datos = array_filter($datos, fn($d) => $d['id'] != $input['id']);
            saveData(array_values($datos));
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'deleted']));
        }

        if ($method === 'PATCH') {
            // Editar usuario
            $datos = readData();
            foreach ($datos as &$user) {
                if ($user['id'] == $input['id']) {
                    $user['nombre'] = $input['nombre'];
                    $user['email'] = $input['email'];
                    break;
                }
            }
            saveData($datos);
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'updated']));
        }

        return new Response(405, [], "Método no permitido");
    }

    return new Response(404, [], "Ruta no encontrada");
});

$socket = new SocketServer('127.0.0.1:8000', [], $loop);
$server->listen($socket);

echo "Servidor ejecutándose en http://127.0.0.1:8000\n";
$loop->run();
