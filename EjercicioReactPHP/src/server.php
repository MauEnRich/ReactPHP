<?php
require __DIR__ . '/../vendor/autoload.php';

use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Socket\SocketServer;

$loop = Factory::create();

// Configuración de la base de datos MySQL
$dsn = 'mysql:host=localhost;dbname=tecnologia';
$username = 'root';
$password = '';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("No se pudo conectar: " . $e->getMessage());
}

$server = new HttpServer(function (ServerRequestInterface $request) use ($pdo) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    // Servir el archivo CSS directamente si se accede a /style.css
if ($path === '/style.css') {
    return new Response(200, ['Content-Type' => 'text/css'], file_get_contents(__DIR__ . '/../public/style.css'));
}

    

    // Servir otros archivos estáticos desde /public/
    if (strpos($path, '/public/') === 0) {
        $filePath = __DIR__ . '/../public' . $path;

        if (file_exists($filePath)) {
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimeTypes = [
                'js'  => 'application/javascript',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
            ];
            $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

            return new Response(200, ['Content-Type' => $mimeType], file_get_contents($filePath));
        }

        return new Response(404, [], 'Archivo no encontrado');
    }

    // Página principal / Inicio
    if ($path === '/' || $path === '/inicio') {
        $html = file_get_contents(__DIR__ . '/../public/index.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    // Página de contacto
    if ($path === '/contacto') {
        $html = file_get_contents(__DIR__ . '/../public/contacto.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    // Ruta para mostrar productos
    if ($path === '/data') {
        $stmt = $pdo->query('SELECT * FROM productos');
        $productos = $stmt->fetchAll();
    
        $productosHtml = '';
        foreach ($productos as $producto) {
            $productosHtml .= "
            <tr>
                <td>{$producto['nombre']}</td>
                <td>{$producto['descripcion']}</td>
                <td>{$producto['precio']}</td>
                <td>{$producto['categoria']}</td>
                <td>
                <br>
                    <form method='POST' action='/eliminar_producto' style='display:inline;'>
                        <input type='hidden' name='id' value='{$producto['id']}'>
                        <button type='submit' class='boton-accion eliminar' title='Eliminar'>&#128465; Eliminar</button>
                    </form>
                    <form method='GET' action='/editar_producto' style='display:inline;'>
                        <input type='hidden' name='id' value='{$producto['id']}'>
                        <button type='submit' class='boton-accion editar' title='Editar'>&#9998; Editar</button>
                    </form>
                </td>
            </tr>";
        }
    
        $html = file_get_contents(__DIR__ . '/../public/data.html');
        $html = str_replace('{{productos}}', $productosHtml, $html);
    
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    // Ruta para agregar un producto
    if ($path === '/agregar_producto' && $method === 'POST') {
        $body = $request->getParsedBody();
        $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, categoria) VALUES (?, ?, ?, ?)');
        $stmt->execute([ 
            $body['nombre'],
            $body['descripcion'],
            $body['precio'],
            $body['categoria']
        ]);
        return new Response(302, ['Location' => '/data']);
    }

    // Ruta para eliminar un producto
    if ($path === '/eliminar_producto' && $method === 'POST') {
        $body = $request->getParsedBody();
        $stmt = $pdo->prepare('DELETE FROM productos WHERE id = ?');
        $stmt->execute([$body['id']]);
        return new Response(302, ['Location' => '/data']);
    }

    // Ruta para mostrar el formulario de edición de un producto
    if ($path === '/editar_producto' && $method === 'GET') {
        $queryParams = $request->getQueryParams();
        $id = $queryParams['id'] ?? null;

        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
            $stmt->execute([$id]);
            $producto = $stmt->fetch();

            if ($producto) {
                $htmlForm = "
                <div class='form-editar'>
                    <h2>Editar Producto</h2>
                    <form method='POST' action='/actualizar_producto'>
                        <input type='hidden' name='id' value='{$producto['id']}'>
                        <label>Nombre:</label>
                        <input type='text' name='nombre' value='{$producto['nombre']}' required>
                        <label>Descripción:</label>
                        <input type='text' name='descripcion' value='{$producto['descripcion']}' required>
                        <label>Precio:</label>
                        <input type='number' step='0.01' name='precio' value='{$producto['precio']}' required>
                        <label>Categoría:</label>
                        <input type='text' name='categoria' value='{$producto['categoria']}' required>
                        <button type='submit'>Actualizar</button>
                    </form>
                    <a href='/data'>Cancelar</a>
                </div>";
                return new Response(200, ['Content-Type' => 'text/html'], $htmlForm);
            }
        }

        return new Response(404, [], 'Producto no encontrado');
    }

    // Ruta para actualizar el producto
    if ($path === '/actualizar_producto' && $method === 'POST') {
        $body = $request->getParsedBody();
        $stmt = $pdo->prepare('UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, categoria = ? WHERE id = ?');
        $stmt->execute([
            $body['nombre'],
            $body['descripcion'],
            $body['precio'],
            $body['categoria'],
            $body['id']
        ]);
        return new Response(302, ['Location' => '/data']);
    }

    // Ruta para manejar el formulario de contacto
    if ($path === '/enviar_contacto' && $method === 'POST') {
        $inputJSON = $request->getBody()->getContents();  // Usar el método de ReactPHP para obtener el cuerpo
        $input = json_decode($inputJSON, true);
    
        if (!is_array($input)) {
            return new Response(400, [], json_encode(['error' => 'JSON inválido o vacío']));
        }
    
        if (isset($input['nombre'], $input['email'], $input['mensaje'])) {
            $nuevoMensaje = [
                'nombre' => $input['nombre'],
                'email' => $input['email'],
                'mensaje' => $input['mensaje'],
                'fecha' => date('Y-m-d H:i:s')
            ];
    
            $archivo = 'contactos.json';  // Cambiar a contactos.json

            $mensajes = [];
    
            if (file_exists($archivo)) {
                $contenido = file_get_contents($archivo);
                $mensajes = json_decode($contenido, true) ?? [];
            }
    
            $mensajes[] = $nuevoMensaje;
    
            file_put_contents($archivo, json_encode($mensajes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
            return new Response(200, [], json_encode(['estado' => 'ok', 'mensaje' => 'Mensaje guardado']));
        } else {
            return new Response(400, [], json_encode(['error' => 'Datos incompletos']));
        }
    }
    
    

    return new Response(404, [], 'Página no encontrada');
});

$socket = new SocketServer('127.0.0.1:8000', [], $loop);
$server->listen($socket);

echo "Servidor corriendo en http://127.0.0.1:8000\n";
$loop->run();
