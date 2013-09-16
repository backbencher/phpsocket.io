<?php

namespace PHPSocketIO\Http;

use PHPSocketIO\ConnectionInterface;
use PHPSocketIO\Request\Request;
use PHPSocketIO\Protocol\Builder as ProtocolBuilder;
use PHPSocketIO\Event;
use PHPSocketIO\Response\ResponseWebSocketFrame;
use PHPSocketIO\Protocol\Handshake;

class HttpWebSocket
{

    protected $heartbeatTimeout = 30;
    protected $websocket;
    /**
     *
     * @var ConnecyionInterface
     */
    protected $connection;

    public function __construct(Request $request, $sessionInited)
    {
        $this->connection = $request->getConnection();
        $this->request = $request;
        $this->websocket = new WebSocket\WebSocket();
        if (!($handshakeResponse = $this->websocket->getHandshakeReponse($connection->getRequest()))) {
            $this->connection->write(new Response('bad protocol', 400), true);
            return;
        }
        $this->connection->write($handshakeResponse);
        $this->sendData(ProtocolBuilder::Connect());
        $this->initEvent();
        $this->setHeartbeatTimeout();
    }

    protected function setHeartbeatTimeout()
    {
        $this->connection->clearTimeout();
        $this->connection->setTimeout($this->heartbeatTimeout, function(){
            $this->sendData(ProtocolBuilder::Heartbeat());
        });
    }

    protected function sendData($data)
    {
        if(!($data instanceof WebSocket\Frame)){
            $data = WebSocket\Frame::generate($data);
        }
        $this->connection->write(new ResponseWebSocketFrame($data), $data->isClosed());
        $this->setHeartbeatTimeout();
    }

    protected function initEvent()
    {
        $dispatcher = Event\EventDispatcher::getDispatcher();
        $dispatcher->addListener("socket.receive", function(Event\MessageEvent $messageEvent) {
                    $message = $messageEvent->getMessage();
                    $frame = $this->websocket->onMessage($message);
                    if(!($frame instanceof WebSocket\Frame)){
                        return;
                    }
                    Handshake::processProtocol($frame->getData(), $this->connection);
                }, $this->connection);

        $dispatcher->addListener("server.emit", function(Event\MessageEvent $messageEvent) {
                    $message = $messageEvent->getMessage();
                    $this->sendData(ProtocolBuilder::Event(array(
                                'name' => $message['event'],
                                'args' => array($message['message']),
                    )));
                }, $this->connection);
    }

}
