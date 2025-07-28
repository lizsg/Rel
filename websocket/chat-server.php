<?php
// websocket-server.php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $users;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if($data['type'] === 'auth') {
            $this->users[$data['userId']] = $from;
            return;
        }
        
        if($data['type'] === 'message') {
            // Enviar mensaje al otro usuario en la conversación
            $recipientId = obtenerOtroUsuarioEnConversacion($data['conversationId'], $data['senderId']);
            
            if(isset($this->users[$recipientId])) {
                $this->users[$recipientId]->send(json_encode($data));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Ejecutar servidor
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);

$server->run();
?>