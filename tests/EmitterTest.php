<?php

declare(strict_types=1);

namespace Tests\Wunderwerk\PhpEmitter;

use PHPUnit\Framework\TestCase;
use Wunderwerk\PhpEmitter\Emitter;

/**
 * Test Emitter class.
 */
class EmitterTest extends TestCase {

  const MASTER_KEY = 'H8Sot3N0XPrRptJJr7QeJAK3bqdAaOzS';

  /**
   * @covers ::connect
   * @covers ::isConnected
   */
  public function testConnection(): void {
    $emitter = $this->connect();

    $this->assertTrue($emitter->isConnected());
  }

  /**
   * @covers ::connect
   * @covers ::isConnected
   */
  public function testInvalidConnection(): void {
    $emitter = new Emitter();

    $this->expectException(\Exception::class);
    $emitter->connect($this->getEmitterHost(), 1234);
  }

  /**
   * @covers ::disconnect
   * @covers ::isConnected
   */
  public function testDisconnection(): void {
    $emitter = $this->connect();

    $emitter->disconnect();

    $this->assertFalse($emitter->isConnected());
  }

  /**
   * @covers ::keygen
   */
  public function testKeygen(): void {
    $emitter = $this->connect();

    $key = $emitter->keygen(self::MASTER_KEY, 'article1/', 'rw', 10);

    $this->assertTrue(is_string($key));
    $this->assertEquals(32, strlen($key));
  }

  /**
   * @covers ::addMessageHandler
   * @covers ::subscribe
   * @covers ::publish
   * @covers ::loop
   */
  public function testSubscribe(): void {
    $emitter = $this->connect();
    $channel = "article1/";
    $key = $emitter->keygen(self::MASTER_KEY, $channel, 'rw', 10);
    $message = "hello world";

    $receivedMessage = "";
    $receivedTopic = "";

    $emitter->addMessageHandler(function ($emitter, $topic, $message) use (&$receivedMessage, &$receivedTopic): void {
      $receivedMessage = $message;
      $receivedTopic = $topic;
      $emitter->interrupt();
    });

    $emitter->subscribe($key, $channel);
    $emitter->publish($key, $channel, $message);
    $emitter->loop(TRUE, TRUE);

    $this->assertEquals($channel, $receivedTopic);
    $this->assertEquals($message, $receivedMessage);
  }

  /**
   * @covers ::addMessageHandler
   * @covers ::subscribe
   * @covers ::publish
   * @covers ::loop
   */
  public function testSubscribeWithInvalidKey(): void {
    $emitter = $this->connect();
    $channel = "article1/";
    $key = $emitter->keygen(self::MASTER_KEY, $channel, 'r', 10);
    $message = "hello world";

    $receivedMessage = "";
    $receivedTopic = "";

    $emitter->addMessageHandler(function ($emitter, $topic, $message) use (&$receivedMessage, &$receivedTopic): void {
      $receivedMessage = $message;
      $receivedTopic = $topic;
      $emitter->interrupt();
    });

    $emitter->subscribe($key, $channel);
    $emitter->publish($key, $channel, $message);
    $emitter->loop(TRUE, TRUE);

    $this->assertEquals('emitter/error/', $receivedTopic);
  }

  /**
   * @covers ::unsubscribe
   */
  public function testUnsubscribe(): void {
    $emitter = $this->connect();
    $channel = "article1/";
    $key = $emitter->keygen(self::MASTER_KEY, $channel, 'rw', 10);
    $message = "hello world";

    $receivedMessage = "";
    $receivedTopic = "";

    $emitter->addMessageHandler(function ($emitter, $topic, $message) use (&$receivedMessage, &$receivedTopic): void {
      $receivedMessage = $message;
      $receivedTopic = $topic;
      $emitter->interrupt();
    });

    $emitter->subscribe($key, $channel);
    $emitter->publish($key, $channel, $message);
    $emitter->loop(TRUE, TRUE);

    $this->assertEquals($channel, $receivedTopic);
    $this->assertEquals($message, $receivedMessage);

    $receivedMessage = "";
    $receivedTopic = "";

    $emitter->unsubscribe($key, $channel);
    $emitter->publish($key, $channel, $message);
    $emitter->loop(TRUE, TRUE);

    $this->assertEquals('', $receivedTopic);
    $this->assertEquals('', $receivedMessage);
  }

  /**
   * @covers ::removeMessageHandler
   */
  public function testRemoveMessageHandler(): void {
    $emitter = $this->connect();
    $channel = "article1/";
    $key = $emitter->keygen(self::MASTER_KEY, $channel, 'rw', 10);
    $message = "hello world";

    $receivedMessage = "";
    $receivedTopic = "";

    $handler = function ($emitter, $topic, $message) use (&$receivedMessage, &$receivedTopic): void {
      $receivedMessage = $message;
      $receivedTopic = $topic;
      $emitter->interrupt();
    };

    $emitter->addMessageHandler($handler);
    $emitter->removeMessageHandler($handler);

    $emitter->subscribe($key, $channel);
    $this->publishOnCycle($emitter, 0.5, $key, $channel, $message);

    $this->assertEmpty($receivedTopic);
  }

  /**
   * @covers ::addLoopHandler
   * @covers ::removeLoopHandler
   */
  public function testLoopHandler(): void {
    $emitter = $this->connect();

    $ticks = 0;
    $testTicks = 0;

    $testHandler = function () use (&$testTicks): void {
      $testTicks++;
    };

    $mainHandler = function ($emitter, $elapsedTime) use (&$ticks, &$testHandler): void {
      $ticks++;

      if ($ticks === 5) {
        $emitter->removeLoopHandler($testHandler);
      }

      if ($ticks === 10) {
        $emitter->interrupt();
      }
    };

    $emitter->addLoopHandler($testHandler);
    $emitter->addLoopHandler($mainHandler);
    $emitter->loop(TRUE);

    $this->assertEquals(10, $ticks);
    $this->assertEquals(5, $testTicks);
  }

  /**
   * @covers ::presence
   */
  public function testPresence(): void {
    $username = 'my-username';
    $emitter = $this->connect();
    $emitterTwo = $this->connect($username);
    $channel = "article1/";
    $key = $emitter->keygen(self::MASTER_KEY, $channel, 'rwp', 10);

    $actualEvents = [];

    $i = 0;
    $handler = function ($emitter, $topic, $message) use (&$i, &$actualEvents): void {
      $i++;

      $actualEvents[] = json_decode($message, TRUE);

      if ($i === 3) {
        $emitter->interrupt();
      }
    };

    $emitter->addMessageHandler($handler);

    $emitter->presence($key, $channel, TRUE, TRUE);
    $emitterTwo->subscribe($key, $channel);
    $emitterTwo->unsubscribe($key, $channel);

    $emitter->loop(TRUE);

    $this->assertEquals(3, count($actualEvents));

    $statusTriggered = FALSE;
    $subscribeTriggered = FALSE;
    $unsubscribeTriggered = FALSE;
    for ($x = 0; $x < count($actualEvents); $x++) {
      $event = $actualEvents[$x];

      switch ($event['event']) {
        case 'status':
          $statusTriggered = TRUE;
          break;

        case 'subscribe':
          $subscribeTriggered = TRUE;
          $this->assertEquals($username, $event['who']['username']);
          break;

        case 'unsubscribe':
          $unsubscribeTriggered = TRUE;
          $this->assertEquals($username, $event['who']['username']);
          break;
      }
    }

    if (!$statusTriggered) {
      $this->fail('Status event not triggered');
    }

    if (!$subscribeTriggered) {
      $this->fail('Subscribe event not triggered');
    }

    if (!$unsubscribeTriggered) {
      $this->fail('Unsubscribe event not triggered');
    }
  }

  /**
   * Publish a message on the first cycle.
   *
   * Loop will be interrupted after $timeout has passed.
   *
   * @param \Wunderwerk\PhpEmitter\Emitter $emitter
   *   The emitter instance.
   * @param float $timeout
   *   The timeout in seconds.
   * @param string $key
   *   The key.
   * @param string $channel
   *   The channel.
   * @param string $message
   *   The message.
   */
  protected function publishOnCycle(Emitter $emitter, float $timeout, string $key, string $channel, string $message): void {
    $published = FALSE;
    $handler = function (Emitter $emitter, $elapsedTime) use ($published, $timeout, $key, $channel, $message): void {
      if (!$published) {
        $emitter->publish($key, $channel, $message, NULL, FALSE);
        $published = TRUE;
      }

      if ($elapsedTime > $timeout) {
        $emitter->interrupt();
      }
    };

    $emitter->addLoopHandler($handler);
    $emitter->loop(TRUE);
    $emitter->removeLoopHandler($handler);
  }

  /**
   * Connect to testing emitter server.
   */
  protected function connect(?string $username = NULL): Emitter {
    $emitter = new Emitter();

    $emitter->connect($this->getEmitterHost(), 8080, $username);

    return $emitter;
  }

  /**
   * Get emitter host.
   *
   * @return string
   *   The emitter host.
   */
  protected function getEmitterHost(): string {
    return getenv('EMITTER_HOST') ?: '';
  }

}
