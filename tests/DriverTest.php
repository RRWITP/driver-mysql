<?php
/**
 * Plasma Driver MySQL component
 * Copyright 2018 PlasmaPHP, All Rights Reserved
 *
 * Website: https://github.com/PlasmaPHP
 * License: https://github.com/PlasmaPHP/driver-mysql/blob/master/LICENSE
*/

namespace Plasma\Drivers\MySQL\Tests;

class DriverTest extends TestCase {
    /**
     * @var \Plasma\Drivers\MySQL\DriverFactory
     */
    public $factory;
    
    /**
     * @var \Plasma\Drivers\MySQL\Driver
     */
     public $driver;
    
    function setUp() {
        parent::setUp();
        $this->factory = new \Plasma\Drivers\MySQL\DriverFactory($this->loop, array());
    }
    
    function connect(\Plasma\DriverInterface $driver, string $uri): \React\Promise\PromiseInterface {
        $creds = (\getenv('MDB_USER') ? \getenv('MDB_USER').':'.\getenv('MDB_PASSWORD').'@' : 'root:@');
        
        return $driver->connect($creds.$uri);
    }
    
    function testGetLoop() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $loop = $driver->getLoop();
        $this->assertInstanceOf(\React\EventLoop\LoopInterface::class, $loop);
        $this->assertSame($this->loop, $loop);
    }
    
    function testGetConnectionState() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $state = $driver->getConnectionState();
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_CLOSED, $state);
    }
    
    function testGetBusyState() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $state = $driver->getBusyState();
        $this->assertSame(\Plasma\DriverInterface::STATE_IDLE, $state);
    }
    
    function testGetBacklogLength() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $state = $driver->getBacklogLength();
        $this->assertSame(0, $state);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->any())
            ->method('checkinConnection')
            ->with($driver);
        
        $ping = new \Plasma\Drivers\MySQL\Commands\PingCommand();
        $driver->runCommand($client, $ping);
        
        $state = $driver->getBacklogLength();
        $this->assertSame(1, $state);
    }
    
    function testConnect() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->await($prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_OK, $driver->getConnectionState());
        
        $prom2 = $this->connect($driver, 'localhost');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom2);
    }
    
    function testConnectWithPort() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $prom2 = $this->connect($driver, 'localhost');
        $this->assertSame($prom, $prom2);
        
        $this->await($prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_OK, $driver->getConnectionState());
    }
    
    function testConnectInvalidCredentials() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $driver->connect('root:abc-never@localhost');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessageRegExp('/^Access denied for user/i');
        
        $this->await($prom);
    }
    
    function testConnectInvalidHost() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $driver->connect('');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->await($prom);
    }
    
    /**
     * @group tls
     */
    function testConnectForceTLSLocalhost() {
        $factory = new \Plasma\Drivers\MySQL\DriverFactory($this->loop, array('tls.force' => true, 'tls.forceLocal' => true));
        $driver = $factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT_SECURE') ?: (\getenv('MDB_PORT') ?: 3306)));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->await($prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_OK, $driver->getConnectionState());
    }
    
    /**
     * @group tls
     */
    function testConnectForceTLSLocalhostIgnored() {
        $factory = new \Plasma\Drivers\MySQL\DriverFactory($this->loop, array('tls.force' => true));
        $driver = $factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->await($prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_OK, $driver->getConnectionState());
    }
    
    /**
     * @group tls
     */
    function testConnectForceTLSIgnoredSecureServer() {
        $factory = new \Plasma\Drivers\MySQL\DriverFactory($this->loop, array('tls.force' => true));
        $driver = $factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT_SECURE') ?: (\getenv('MDB_PORT') ?: 3306)));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->await($prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_OK, $driver->getConnectionState());
    }
    
    /**
     * @group tls
     */
    function testConnectForceTLSFailure() {
        $factory = new \Plasma\Drivers\MySQL\DriverFactory($this->loop, array('tls.force' => true, 'tls.forceLocal' => true));
        $driver = $factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('TLS is not supported by the server');
        
        $this->await($prom, 30.0);
    }
    
    function testPauseStreamConsumption() {
        $this->markTestSkipped('Not implemented yet');
    }
    
    function testResumeStreamConsumption() {
        $this->markTestSkipped('Not implemented yet');
    }
    
    function testClose() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, '127.0.0.1:'.(\getenv('MDB_PORT') ?: 3306));
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->exactly(2))
            ->method('checkinConnection')
            ->with($driver);
        
        $ping = new \Plasma\Drivers\MySQL\Commands\PingCommand();
        $driver->runCommand($client, $ping);
        
        $promC = $driver->runCommand($client, $ping);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promC);
        
        $prom2 = $driver->close();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom2);
        
        $this->await($promC);
        $this->await($prom2);
        
        $prom3 = $driver->close();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom3);
    }
    
    function testQuit() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $deferred = new \React\Promise\Deferred();
        
        $driver->once('close', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $prom = $this->connect($driver, '127.0.0.1:'.(\getenv('MDB_PORT') ?: 3306));
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->exactly(2))
            ->method('checkinConnection')
            ->with($driver);
        
        $ping = new \Plasma\Drivers\MySQL\Commands\PingCommand();
        $driver->runCommand($client, $ping);
        
        $promC = $driver->runCommand($client, $ping);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promC);
        
        $this->assertNull($driver->quit());
        
        try {
            $this->assertInstanceOf(\Throwable::class, $this->await($promC));
        } catch (\Plasma\Exception $e) {
            $this->assertInstanceOf(\Plasma\Exception::class, $e);
        }
        
        $this->await($deferred->promise());
    }
    
    function testTransaction() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306));
        $this->await($prom);
        
        $this->assertFalse($driver->isInTransaction());
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->once())
            ->method('checkinConnection')
            ->with($driver);
        
        $prom2 = $driver->beginTransaction($client, \Plasma\TransactionInterface::ISOLATION_COMMITTED);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom2);
        
        $transaction = $this->await($prom2);
        $this->assertInstanceof(\Plasma\TransactionInterface::class, $transaction);
        
        $this->assertTrue($driver->isInTransaction());
        
        $prom3 = $transaction->rollback();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom3);
        
        $this->await($prom3);
        
        $this->assertFalse($driver->isInTransaction());
    }
    
    function testAlreadyInTransaction() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306));
        $this->await($prom);
        
        $this->assertFalse($driver->isInTransaction());
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->never())
            ->method('checkinConnection')
            ->with($driver);
        
        $prom2 = $driver->beginTransaction($client, \Plasma\TransactionInterface::ISOLATION_COMMITTED);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom2);
        
        $transaction = $this->await($prom2);
        $this->assertInstanceof(\Plasma\TransactionInterface::class, $transaction);
        
        $this->expectException(\Plasma\Exception::class);
        $driver->beginTransaction($client, \Plasma\TransactionInterface::ISOLATION_COMMITTED);
    }
    
    function testRunCommand() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306));
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->exactly(2))
            ->method('checkinConnection')
            ->with($driver);
        
        $ping = new \Plasma\Drivers\MySQL\Commands\PingCommand();
        $promC = $driver->runCommand($client, $ping);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promC);
        
        $this->await($promC);
        
        $ping2 = (new class() extends \Plasma\Drivers\MySQL\Commands\PingCommand {
            function onComplete(): void {
                $this->finished = true;
                $this->emit('error', array((new \LogicException('test'))));
            }
        });
        
        $promC2 = $driver->runCommand($client, $ping2);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promC2);
        
        $this->expectException(\LogicException::class);
        $this->await($promC2);
    }
    
    function testQuery() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306).'/plasma_tmp');
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->exactly(2))
            ->method('checkinConnection')
            ->with($driver);
        
        $prom = $driver->query($client, 'CREATE TABLE IF NOT EXISTS `tbl_tmp` (`test` VARCHAR(50) NOT NULL) ENGINE = InnoDB');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        
        $res = $this->await($prom);
        $this->assertInstanceOf(\Plasma\QueryResultInterface::class, $res);
        
        $prom2 = $driver->query($client, 'SHOW DATABASES');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom2);
        
        $res2 = $this->await($prom2);
        $this->assertInstanceOf(\Plasma\StreamQueryResultInterface::class, $res2);
        
        $data = null;
        $deferred = new \React\Promise\Deferred();
        
        $res2->once('close', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $res2->on('error', function (\Throwable $e) use (&$deferred) {
            $deferred->reject($e);
        });
        
        $res2->on('data', function ($row) use (&$data) {
            if($row['Database'] === 'plasma_tmp') {
                $data = $row;
            }
        });
        
        $this->await($deferred->promise());
        $this->assertSame(array('Database' => 'plasma_tmp'), $data);
    }
    
    function testQuerySelectedDatabase() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306).'/information_schema');
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->once())
            ->method('checkinConnection')
            ->with($driver);
        
        $prom = $driver->query($client, 'SELECT DATABASE()');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        
        $res = $this->await($prom);
        $this->assertInstanceOf(\Plasma\StreamQueryResultInterface::class, $res);
        
        $data = null;
        $deferred = new \React\Promise\Deferred();
        
        $res->once('close', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $res->on('error', function (\Throwable $e) use (&$deferred) {
            $deferred->reject($e);
        });
        
        $res->on('data', function ($row) use (&$data) {
            $data = $row;
        });
        
        $this->await($deferred->promise());
        $this->assertSame(array('DATABASE()' => 'information_schema'), $data);
    }
    
    function testQueryConnectionCharset() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306).'/information_schema?charset=utf8mb4');
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_STARTED, $driver->getConnectionState());
        
        $this->await($prom);
        $this->assertSame(\Plasma\DriverInterface::CONNECTION_OK, $driver->getConnectionState());
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->once())
            ->method('checkinConnection')
            ->with($driver);
        
        $prom = $driver->query($client, 'SHOW SESSION VARIABLES LIKE "character\_set\_%"');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        
        $res = $this->await($prom);
        $this->assertInstanceOf(\Plasma\StreamQueryResultInterface::class, $res);
        
        $data = null;
        $deferred = new \React\Promise\Deferred();
        
        $res->once('close', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $res->on('error', function (\Throwable $e) use (&$deferred) {
            $deferred->reject($e);
        });
        
        $res->on('data', function ($row) use (&$data) {
            if($row['Variable_name'] === 'character_set_connection') {
                $data = $row;
            }
        });
        
        $this->await($deferred->promise());
        $this->assertSame(array('Variable_name' => 'character_set_connection', 'Value' => 'utf8mb4'), $data);
    }
    
    function testPrepare() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306).'/information_schema');
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->once())
            ->method('checkinConnection')
            ->with($driver);
        
        $prom = $driver->prepare($client, 'SELECT * FROM `SCHEMATA` WHERE `SCHEMA_NAME` = ?');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        
        $statement = $this->await($prom);
        $this->assertInstanceOf(\Plasma\StatementInterface::class, $statement);
        
        $prom2 = $statement->execute(array('plasma_tmp'));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom2);
        
        $res = $this->await($prom2);
        $this->assertInstanceOf(\Plasma\StreamQueryResultInterface::class, $res);
        
        $data = null;
        $deferred = new \React\Promise\Deferred();
        
        $res->once('close', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $res->once('error', function (\Throwable $e) use (&$deferred) {
            $deferred->reject($e);
        });
        
        $res->on('data', function ($row) use (&$data) {
            if($row['SCHEMA_NAME'] === 'plasma_tmp') {
                $data = $row;
            }
        });
        
        $this->await($deferred->promise());
        $this->assertNotNull($data);
        
        $this->await($statement->close());
        
        // TODO: maybe remove if statement test succeeds?
        // Unfortunately the destructor mechanism CAN NOT be tested,
        // as the destructor runs AFTER the test ends
    }
    
    function testExecute() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost:'.(\getenv('MDB_PORT') ?: 3306).'/information_schema');
        $this->await($prom);
        
        $client = $this->createClientMock();
        
        $client
            ->expects($this->once())
            ->method('checkinConnection')
            ->with($driver);
        
        $prom = $driver->execute($client, 'SELECT * FROM `SCHEMATA` WHERE `SCHEMA_NAME` = ?', array('plasma_tmp'));
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $prom);
        
        $res = $this->await($prom);
        $this->assertInstanceOf(\Plasma\StreamQueryResultInterface::class, $res);
        
        $data = null;
        $deferred = new \React\Promise\Deferred();
        
        $res->once('close', function () use (&$deferred) {
            $deferred->resolve();
        });
        
        $res->once('error', function (\Throwable $e) use (&$deferred) {
            $deferred->reject($e);
        });
        
        $res->on('data', function ($row) use (&$data) {
            if($row['SCHEMA_NAME'] === 'plasma_tmp') {
                $data = $row;
            }
        });
        
        $this->await($deferred->promise());
        $this->assertNotNull($data);
        
        // Waiting 2 seconds for the automatic close to occurr
        $deferredT = new \React\Promise\Deferred();
        $this->loop->addTimer(2, array($deferredT, 'resolve'));
        
        $this->await($deferredT->promise());
    }
    
    function testGoingAwayConnect() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        
        $connect = $driver->connect('whatever');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $connect);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Connection is going away');
        
        $this->await($connect, 0.1);
    }
    
    function testGoingAwayStreamConsumption() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertFalse($driver->resumeStreamConsumption());
        $this->assertFalse($driver->pauseStreamConsumption());
        
        $this->assertNull($driver->quit());
        
        $this->assertFalse($driver->resumeStreamConsumption());
        $this->assertFalse($driver->pauseStreamConsumption());
    }
    
    function testGoingAwayClose() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        
        $close = $driver->close();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $close);
        
        $this->await($close, 0.1);
    }
    
    function testGoingAwayQuit() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        $this->assertNull($driver->quit());
    }
    
    function testGoingAwayQuery() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        $client = $this->createClientMock();
        
        $query = $driver->query($client, 'whatever');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $query);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Connection is going away');
        
        $this->await($query, 0.1);
    }
    
    function testGoingAwayPrepare() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        $client = $this->createClientMock();
        
        $query = $driver->prepare($client, 'whatever');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $query);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Connection is going away');
        
        $this->await($query, 0.1);
    }
    
    function testGoingAwayExecute() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        $client = $this->createClientMock();
        
        $query = $driver->execute($client, 'whatever');
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $query);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Connection is going away');
        
        $this->await($query, 0.1);
    }
    
    function testGoingAwayBeginTransaction() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        $client = $this->createClientMock();
        
        $query = $driver->beginTransaction($client, \Plasma\TransactionInterface::ISOLATION_COMMITTED);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $query);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Connection is going away');
        
        $this->await($query, 0.1);
    }
    
    function testGoingAwayRunCommand() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->assertNull($driver->quit());
        
        $cmd = new \Plasma\Drivers\MySQL\Commands\QuitCommand();
        $client = $this->createClientMock();
        
        $command = $driver->runCommand($client, $cmd);
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $command);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Connection is going away');
        
        $this->await($command, 0.1);
    }
    
    function testUnconnectedQuery() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Unable to continue without connection');
        
        $client = $this->createClientMock();
        $query = $driver->query($client, 'whatever');
    }
    
    function testUnconnectedPrepare() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Unable to continue without connection');
        
        $client = $this->createClientMock();
        $query = $driver->prepare($client, 'whatever');
    }
    
    function testUnconnectedExecute() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Unable to continue without connection');
        
        $client = $this->createClientMock();
        $query = $driver->execute($client, 'whatever');
    }
    
    function testQuote() {
        $driver = new \Plasma\Drivers\MySQL\Driver($this->loop, array('characters.set' => ''));
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost');
        $this->await($prom);
        
        $str = $driver->quote('hello "world"');
        $this->assertContains($str, array(
            '"hello \"world\""',
            '"hello ""world"""'
        ));
    }
    
    function testQuoteWithOkResponse() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost');
        $this->await($prom);
        
        $str = $driver->quote('hello "world"');
        $this->assertContains($str, array(
            '"hello \"world\""',
            '"hello ""world"""'
        ));
    }
    
    function testQuoteWithoutConnection() {
        $driver = new \Plasma\Drivers\MySQL\Driver($this->loop, array('characters.set' => ''));
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $this->expectException(\Plasma\Exception::class);
        $this->expectExceptionMessage('Unable to continue without connection');
        
        $str = $driver->quote('hello "world"');
    }
    
    function testQuoteQuotes() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $str = $driver->escapeUsingQuotes('UTF-8', 'hello "world"');
        $this->assertSame('"hello ""world"""', $str);
    }
    
    function testQuoteBackslashes() {
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $str = $driver->escapeUsingBackslashes('UTF-8', 'hello "world"');
        $this->assertSame('"hello \"world\""', $str);
    }
    
    function insertIntoTestString(int $colnum, string $value): array {
        $values = array();
        
        for($i = 0; $i < 18; $i++) {
            if($colnum === $i) {
                $values[] = $value;
            } else {
                $values[] = '';
            }
        }
        
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost');
        $this->await($prom);
        
        $client = $this->client->createClientMock();
        
        $prep = $driver->execute(
            $client,
            'INSERT INTO `test_strings` VALUES ('.\implode(', ', \array_fill(0, 18, '?')).')',
            $values
        );
        $result = $this->await($prep);
        
        $this->assertSame(1, $result->getAffectedRows());
        
        $selprep = $driver->execute($client, 'SELECT * FROM `test_strings`');
        $select = $this->await($selprep);
        
        $dataProm = \React\Promise\Stream\all($select);
        return $this->await($dataProm);
    }
    
    function testBinaryTypeChar() {
        $data = $this->insertIntoTestString(0, 'hell');
        
        $this->assertContains(array(
            'testcol1' => 'hell',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeVarchar() {
        $data = $this->insertIntoTestString(1, 'hello');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => 'hello',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeTinyText() {
        $data = $this->insertIntoTestString(2, 'hallo');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => 'hallo',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeText() {
        $data = $this->insertIntoTestString(3, 'hallo2');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => 'hallo2',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeMediumText() {
        $data = $this->insertIntoTestString(4, 'hallo3');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => 'hallo3',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeLongText() {
        $data = $this->insertIntoTestString(5, 'hallo4');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => 'hallo4',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeBinary() {
        $data = $this->insertIntoTestString(6, 'hallo5');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => 'hallo5',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeVarBinary() {
        $data = $this->insertIntoTestString(7, 'hallo6');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => 'hallo6',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeTinyBlob() {
        $data = $this->insertIntoTestString(8, 'hallo7');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => 'hallo7',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeMediumBlob() {
        $data = $this->insertIntoTestString(9, 'hallo8');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => 'hallo8',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeBlob() {
        $data = $this->insertIntoTestString(10, 'hallo9');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => 'hallo9',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeLongBlob() {
        $data = $this->insertIntoTestString(11, 'hello world');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => 'hello world',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeEnum() {
        $data = $this->insertIntoTestString(12, 'hey');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => 'hey',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeSet() {
        $data = $this->insertIntoTestString(13, 'world');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => 'world',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeGeometry() {
        $data = $this->insertIntoTestString(14, '');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeBit() {
        $data = $this->insertIntoTestString(15, '1');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '1',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeDecimal() {
        $data = $this->insertIntoTestString(16, '5.2');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '5.2',
            'testcol18' => '',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeNewDecimal() {
        $data = $this->insertIntoTestString(17, '5.32');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '5.2',
            'testcol18' => '5.32',
            'testcol19' => ''
        ), $data);
    }
    
    function testBinaryTypeNewJSON() {
        $data = $this->insertIntoTestString(18, '{"hello":true}');
        
        $this->assertContains(array(
            'testcol1' => '',
            'testcol2' => '',
            'testcol3' => '',
            'testcol4' => '',
            'testcol5' => '',
            'testcol6' => '',
            'testcol7' => '',
            'testcol8' => '',
            'testcol9' => '',
            'testcol10' => '',
            'testcol11' => '',
            'testcol12' => '',
            'testcol13' => '',
            'testcol14' => '',
            'testcol15' => '',
            'testcol16' => '',
            'testcol17' => '',
            'testcol18' => '',
            'testcol19' => '{"hello":true}'
        ), $data);
    }
    
    function insertIntoTestInt(int $colnum, int $value): array {
        $values = array();
        
        for($i = 0; $i < 6; $i++) {
            if($colnum === $i) {
                $values[] = $value;
            } else {
                $values[] = 0;
            }
        }
        
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost');
        $this->await($prom);
        
        $client = $this->client->createClientMock();
        
        $prep = $driver->execute(
            $client,
            'INSERT INTO `test_ints` VALUES ('.\implode(', ', \array_fill(0, 5, '?')).')',
            $values
        );
        $result = $this->await($prep);
        
        $this->assertSame(1, $result->getAffectedRows());
        
        $selprep = $driver->execute($client, 'SELECT * FROM `test_ints`');
        $select = $this->await($selprep);
        
        $dataProm = \React\Promise\Stream\all($select);
        return $this->await($dataProm);
    }
    
    function testBinaryTypeTiny() {
        $data = $this->insertIntoTestInt(0, 5);
        
        $this->assertContains(array(
            'testcol1' => 0,
            'testcol2' => 0,
            'testcol3' => 0,
            'testcol4' => 0,
            'testcol5' => 0,
            'testcol6' => 0
        ), $data);
    }
    
    function testBinaryTypeShort() {
        $data = $this->insertIntoTestInt(1, 62870);
        
        $this->assertContains(array(
            'testcol1' => 0,
            'testcol2' => 62870,
            'testcol3' => 0,
            'testcol4' => 0,
            'testcol5' => 0,
            'testcol6' => 0
        ), $data);
    }
    
    function testBinaryTypeYear() {
        $data = $this->insertIntoTestInt(2, 2014);
        
        $this->assertContains(array(
            'testcol1' => 0,
            'testcol2' => 0,
            'testcol3' => 2014,
            'testcol4' => 0,
            'testcol5' => 0,
            'testcol6' => 0
        ), $data);
    }
    
    function testBinaryTypeInt24() {
        $data = $this->insertIntoTestInt(3, 1677416);
        
        $this->assertContains(array(
            'testcol1' => 0,
            'testcol2' => 0,
            'testcol3' => 0,
            'testcol4' => 1677416,
            'testcol5' => 0,
            'testcol6' => 0
        ), $data);
    }
    
    function testBinaryTypeLong() {
        $data = $this->insertIntoTestInt(4, 2147483648);
        
        $this->assertContains(array(
            'testcol1' => 0,
            'testcol2' => 0,
            'testcol3' => 0,
            'testcol4' => 0,
            'testcol5' => 2147483648,
            'testcol6' => 0
        ), $data);
    }
    
    function testBinaryTypeLongLong() {
        $data = $this->insertIntoTestInt(6, 4611686018427388000);
        
        $this->assertContains(array(
            'testcol1' => 0,
            'testcol2' => 0,
            'testcol3' => 0,
            'testcol4' => 0,
            'testcol5' => 0,
            'testcol6' => 4611686018427388000
        ), $data);
    }
    
    function insertIntoTestFloat(int $colnum, float $value): array {
        $values = array();
        
        for($i = 0; $i < 2; $i++) {
            if($colnum === $i) {
                $values[] = $value;
            } else {
                $values[] = 0.0;
            }
        }
        
        $driver = $this->factory->createDriver();
        $this->assertInstanceOf(\Plasma\DriverInterface::class, $driver);
        
        $prom = $this->connect($driver, 'localhost');
        $this->await($prom);
        
        $client = $this->client->createClientMock();
        
        $prep = $driver->execute(
            $client,
            'INSERT INTO `test_floats` VALUES (?, ?)',
            $values
        );
        $result = $this->await($prep);
        
        $this->assertSame(1, $result->getAffectedRows());
        
        $selprep = $driver->execute($client, 'SELECT * FROM `test_floats`');
        $select = $this->await($selprep);
        
        $dataProm = \React\Promise\Stream\all($select);
        return $this->await($dataProm);
    }
    
    function testBinaryTypeFloat() {
        $data = $this->insertIntoTestFloat(0, 5.2);
        
    }
    
    function testBinaryTypeDouble() {
        $data = $this->insertIntoTestInt(6, 4611686018427388000);
        
    }
    
    function testBinaryTypeString() {
        
    }
    
    function createClientMock(): \Plasma\ClientInterface {
        return $this->getMockBuilder(\Plasma\ClientInterface::class)
            ->setMethods(array(
                'create',
                'getConnectionCount',
                'checkinConnection',
                'beginTransaction',
                'close',
                'quit',
                'runCommand',
                'query',
                'prepare',
                'execute',
                'quote',
                'listeners',
                'on',
                'once',
                'emit',
                'removeListener',
                'removeAllListeners'
            ))
            ->getMock();
    }
}
