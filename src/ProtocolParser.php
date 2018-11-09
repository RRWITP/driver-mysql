<?php
/**
 * Plasma Driver MySQL component
 * Copyright 2018 PlasmaPHP, All Rights Reserved
 *
 * Website: https://github.com/PlasmaPHP
 * License: https://github.com/PlasmaPHP/driver-mysql/blob/master/LICENSE
*/

namespace Plasma\Drivers\MySQL;

/**
 * The MySQL Protocol Parser.
 * @internal
 */
class ProtocolParser implements \Evenement\EventEmitterInterface {
    use \Evenement\EventEmitterTrait;
    
    /**
     * @var int
     */
    const STATE_INIT = 0;
    
    /**
     * @var int
     */
    const STATE_HANDSHAKE = 1;
    
    /**
     * @var int
     */
    const STATE_HANDSHAKE_ERROR = 2;
    
    /**
     * @var int
     */
    const STATE_AUTH = 5;
    
    /**
     * @var int
     */
    const STATE_AUTH_SENT = 6;
    
    /**
     * @var int
     */
    const STATE_AUTH_ERROR = 7;
    
    /**
     * @var int
     */
    const STATE_OK = 9;
    
    /**
     * @var int
     */
    const CLIENT_CAPABILITIES = (
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_LONG_PASSWORD |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_LONG_FLAG |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_LOCAL_FILES |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_INTERACTIVE |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_TRANSACTIONS |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_SECURE_CONNECTION |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_PROTOCOL_41 |
        \Plasma\Drivers\MySQL\CapabilityFlags::CLIENT_DEPRECATE_EOF
    );
    
    /**
     * @var int
     */
    const CLIENT_MAX_PACKET_SIZE = 0x1000000;
    
    /**
     * @var int
     */
    const CLIENT_CHARSET_NUMBER = 0x21;
    
    /**
     * @var \Plasma\Drivers\MySQL\Driver
     */
    protected $driver;
    
    /**
     * @var \React\Socket\ConnectionInterface
     */
    protected $connection;
    
    /**
     * @var int
     */
    protected $state = static::STATE_INIT;
    
    /**
     * @var string
     */
    protected $buffer = '';
    
    /**
     * The sequence ID is incremented with each packet and may wrap around.
     * It starts at 0 and is reset to 0 when a new command begins in the Command Phase.
     * @var int
     * @see https://dev.mysql.com/doc/internals/en/sequence-id.html
     */
    protected $sequenceID = 0;
    
    /**
     * @var string[]
     */
    protected $messageClasses = array();
    
    /**
     * @var \Plasma\Drivers\MySQL\Commands\CommandInterface|null
     */
    protected $currentCommand;
    
    /**
     * @var \Plasma\Drivers\MySQL\Messages\HandshakeMessage|null
     */
    protected $handshakeMessage;
    
    /**
     * Constructor.
     * @param \Plasma\Drivers\MySQL\Driver       $driver
     * @param \React\Socket\ConnectionInterface  $connection
     */
    function __construct(\Plasma\Drivers\MySQL\Driver $driver, \React\Socket\ConnectionInterface $connection) {
        $this->driver = $driver;
        $this->connection = $connection;
        
        $this->registerMessages();
        $this->addEvents();
    }
    
    /**
     * Invoke a command to execute.
     * @param \Plasma\Drivers\MySQL\Commands\CommandInterface  $command
     * @return void
     */
    function invokeCommand(?\Plasma\Drivers\MySQL\Commands\CommandInterface $command): void {
        if($command === null) {
            return;
        }
        
        $this->currentCommand = $command;
        $this->processCommand();
    }
    
    /**
     * Marks the command itself as finished, if currently running.
     * @param \Plasma\Drivers\MySQL\Commands\CommandInterface  $command
     * @return void
     */
    function markCommandAsFinished(\Plasma\Drivers\MySQL\Commands\CommandInterface $command): void {
        if($command === $this->currentCommand) {
            $this->currentCommand = null;
            $command->onComplete();
        }
    }
    
    /**
     * Get the parser state.
     * @return int
     */
    function getState(): int {
        return $this->state;
    }
    
    /**
     * Get the handshake message, or null.
     * @return \Plasma\Drivers\MySQL\Messages\HandshakeMessage|null
     */
    function getHandshakeMessage(): ?\Plasma\Drivers\MySQL\Messages\HandshakeMessage {
        return $this->handshakeMessage;
    }
    
    /**
     * Processes a command.
     * @return void
     */
    protected function processCommand() {
        if(!$this->currentCommand) {
            return;
        }
        
        $state = $this->currentCommand->setParserState();
        if($state !== -1) {
            $this->state = $state;
        }
        
        $packet = $this->currentCommand->getEncodedMessage();
        $this->connection->write($packet);
        
        if(!$this->currentCommand->waitForCompletion()) {
            $this->currentCommand = null;
        }
    }
    
    /**
     * Processes the buffer.
     * @return void
     */
    protected function processBuffer() {
        $firstChar = \Plasma\Drivers\MySQL\Messages\MessageUtility::readBuffer($this->buffer, 1);
        
        /** @var \Plasma\Drivers\MySQL\Messages\MessageInterface  $message */
        $message = null;
        
        if($this->state === static::STATE_INIT) {
            $message = new \Plasma\Drivers\MySQL\Messages\HandshakeMessage();
        } elseif($firstChar === "\xFE" && $this->state < static::STATE_OK) {
            $message = new \Plasma\Drivers\MySQL\Messages\AuthSwitchRequestMessage();
        } elseif(isset($this->messageClasses[$firstChar]) && ($firstChar !== \Plasma\Drivers\MySQL\Messages\EOFMessage::getID() || \strlen($this->buffer) < 6)) {
            $cl = $this->messageClasses[$firstChar];
            $message = new $cl();
        } elseif($this->state === static::STATE_OK && $this->currentCommand !== null) {
            $this->buffer = $firstChar.$this->buffer;
            
            $caller = new \Plasma\Drivers\MySQL\ProtocolOnNextCaller($this, $this->buffer);
            $message = $this->currentCommand->onNext($caller);
        }
        
        if(!($message instanceof \Plasma\Drivers\MySQL\Messages\MessageInterface)) {
            return;
        }
        
        $state = $message->setParserState();
        if($state !== -1) {
            $this->state = $state;
        }
        
        if($message instanceof \Plasma\Drivers\MySQL\Messages\HandshakeMessage) {
            $this->handshakeMessage = $message;
        }
        
        $this->handleMessage($message);
    }
    
    /**
     * Handles an incoming message.
     * @param \Plasma\Drivers\MySQL\Messages\MessageInterface  $message
     * @return void
     */
    function handleMessage(\Plasma\Drivers\MySQL\Messages\MessageInterface $message) {
        try {
            $this->buffer = $message->parseMessage($this->buffer, $this);
            
            if($this->currentCommand !== null) {
                if(
                    ($message instanceof \Plasma\Drivers\MySQL\Messages\OkResponseMessage || $message instanceof \Plasma\Drivers\MySQL\Messages\EOFMessage)
                    && $this->currentCommand->isFinished()
                ) {
                    $this->currentCommand->onComplete();
                    $this->currentCommand = null;
                } elseif($message instanceof \Plasma\Drivers\MySQL\Messages\ErrResponseMessage) {
                    $error = new \Plasma\Exception($message->errorMessage, $message->errorCode);
                    $this->currentCommand->onError($error);
                } else {
                    $this->currentCommand->onNext($message);
                }
            }
            
            $this->emit('message', array($message));
        } catch (\Plasma\Drivers\MySQL\Messages\ParseException $e) {
            $state = $e->getState();
            if($state !== null) {
                $this->state = $state;
            }
            
            $buffer = $e->getBuffer();
            if($buffer !== null) {
                $this->buffer = $buffer;
            }
            
            if($this->currentCommand !== null) {
                $this->currentCommand->onError($e);
            }
            
            $this->emit('error', array($e));
            $this->connection->close();
        }
    }
    
    /**
     * Registers the message classes.
     * @return void
     */
    protected function registerMessages() {
        $classes = array(
            (\Plasma\Drivers\MySQL\Messages\AuthMoreDataMessage::getID()) => \Plasma\Drivers\MySQL\Messages\AuthMoreDataMessage::class,
            (\Plasma\Drivers\MySQL\Messages\EOFMessage::getID()) => \Plasma\Drivers\MySQL\Messages\EOFMessage::class,
            (\Plasma\Drivers\MySQL\Messages\ErrResponseMessage::getID()) => \Plasma\Drivers\MySQL\Messages\ErrResponseMessage::class,
            (\Plasma\Drivers\MySQL\Messages\OkResponseMessage::getID()) => \Plasma\Drivers\MySQL\Messages\OkResponseMessage::class
        );
        
        foreach($classes as $id => $class) {
            $this->messageClasses[$id] = $class;
        }
    }
    
    /**
     * Adds the events to the connection.
     * @return void
     */
    protected function addEvents() {
        $this->connection->on('data', function ($chunk) {
            $this->buffer .= $chunk;
            $this->processBuffer();
        });
        
        $this->connection->on('close', function () {
            $this->handleClose();
        });
    }
    
    /**
     * Connection close handler.
     * @return void
     */
    protected function handleClose() {
        if($this->state === static::STATE_AUTH || $this->state === static::STATE_AUTH_SENT) {
            $this->state = static::STATE_AUTH_ERROR;
        }
    }
}
