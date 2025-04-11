<?php

require __DIR__ . '/../vendor/autoload.php'; // Asegúrate de que la ruta sea correcta

use React\Http\Server;
use React\Socket\Server as SocketServer;
use React\EventLoop\Factory as LoopFactory;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

$loop = LoopFactory::create(); // Crear el bucle de eventos

// Servir el contenido según las rutas solicitadas
$server = new Server(function (ServerRequestInterface $request) {
    $uri = $request->getUri()->getPath(); // Obtener la ruta solicitada

    // Página de Inicio
    if ($uri === '/') {
        $content = file_get_contents(__DIR__ . '/../public/index.html');
        return new Response(
            200,
            ['Content-Type' => 'text/html'],
            $content
        );
    }

    // Página de Contacto
    if ($uri === '/contact') {
        $content = file_get_contents(__DIR__ . '/../public/contacto.html');
        return new Response(
            200,
            ['Content-Type' => 'text/html'],
            $content
        );
    }

    // Ruta de Datos - devolver datos en formato JSON
    if ($uri === '/data') {
        $data = json_decode(file_get_contents(__DIR__ . '/../data/data.json'), true); // Cargar datos JSON
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
    }

    // Archivos Estáticos: servir el archivo CSS y otros recursos
    if ($uri === '/style.css') {
        $content = file_get_contents(__DIR__ . '/../public/style.css');
        return new Response(
            200,
            ['Content-Type' => 'text/css'],
            $content
        );
    }

    // Respuesta para rutas no encontradas (404)
    return new Response(
        404,
        ['Content-Type' => 'text/html'],
        'Page not found'
    );
});

// Crear el servidor en el puerto 8080
$socket = new SocketServer('0.0.0.0:8080', $loop);
$server->listen($socket);

// Iniciar el servidor
echo "Server running at http://localhost:8080\n";
$loop->run();
