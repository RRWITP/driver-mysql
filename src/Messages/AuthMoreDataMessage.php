<?php
/**
 * Plasma Driver MySQL component
 * Copyright 2018-2019 PlasmaPHP, All Rights Reserved
 *
 * Website: https://github.com/PlasmaPHP
 * License: https://github.com/PlasmaPHP/driver-mysql/blob/master/LICENSE
*/

namespace Plasma\Drivers\MySQL\Messages;

/**
 * Represents an Auth More Data Message.
 */
class AuthMoreDataMessage implements \Plasma\Drivers\MySQL\Messages\MessageInterface {
    /**
     * @var string|null
     */
    public $authPluginData;
    
    /**
     * @var \Plasma\Drivers\MySQL\ProtocolParser
     * @internal
     */
    protected $parser;
    
    /**
     * Constructor.
     * @param \Plasma\Drivers\MySQL\ProtocolParser  $parser
     * @internal
     */
    function __construct(\Plasma\Drivers\MySQL\ProtocolParser $parser) {
        $this->parser = $parser;
    }
    
    /**
     * Get the identifier for the packet.
     * @return string
     * @internal
     */
    static function getID(): string {
        return "\x01";
    }
    
    /**
     * Parses the message, once the complete string has been received.
     * Returns false if not enough data has been received, or the remaining buffer.
     * @param \Plasma\BinaryBuffer  $buffer
     * @return bool
     * @throws \Plasma\Drivers\MySQL\Messages\ParseException
     * @internal
     */
    function parseMessage(\Plasma\BinaryBuffer $buffer): bool {
        $this->authPluginData = $buffer->getContents();
        $buffer->clear();
        
        return true;
    }
    
    /**
     * Get the parser which created this message.
     * @return \Plasma\Drivers\MySQL\ProtocolParser
     * @internal
     */
    function getParser(): \Plasma\Drivers\MySQL\ProtocolParser {
        return $this->parser;
    }
    
    /**
     * Sets the parser state, if necessary. If not, return `-1`.
     * @return int
     * @internal
     */
    function setParserState(): int {
        return \Plasma\Drivers\MySQL\ProtocolParser::STATE_AUTH;
    }
}
