<?php
/**
 * Plasma Driver MySQL component
 * Copyright 2018 PlasmaPHP, All Rights Reserved
 *
 * Website: https://github.com/PlasmaPHP
 * License: https://github.com/PlasmaPHP/driver-mysql/blob/master/LICENSE
*/

namespace Plasma\Drivers\MySQL\Tests;

class ResetConnectionCommandTest extends TestCase {
    function testGetEncodedMessage() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        $this->assertFalse($command->hasFinished());
        
        $this->assertSame(\chr(0x1F), $command->getEncodedMessage());
        $this->assertTrue($command->hasFinished());
    }
    
    function testSetParserState() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        
        $this->assertSame(-1, $command->setParserState());
    }
    
    function testOnComplete() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        
        $deferred = new \React\Promise\Deferred();
        
        $command->on('end', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $command->onComplete();
        $this->await($deferred->promise(), 0.1);
    }
    
    function testOnError() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        
        $deferred = new \React\Promise\Deferred();
        
        $command->on('error', function (\Throwable $e) use (&$deferred) {
            $deferred->reject($e);
        });
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test');
                
        $command->onError((new \RuntimeException('test')));
        $this->await($deferred->promise(), 0.1);
    }
    
    function testOnNext() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        $this->assertNull($command->onNext());
    }
    
    function testWaitForCompletion() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        $this->assertTrue($command->waitForCompletion());
    }
    
    function testResetSequence() {
        $command = new \Plasma\Drivers\MySQL\Commands\ResetConnectionCommand();
        $this->assertTrue($command->resetSequence());
    }
}
