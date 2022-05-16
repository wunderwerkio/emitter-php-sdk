This repository provides a PHP SDK for [Emitter](https://emitter.io/).

## Installation

Using composer:

```
composer require wunderwerkio/emitter-sdk
```

## Emitter API

This PHP SDK implements the following methods as described in the [emitter docs](https://emitter.io/develop/creating-sdk/):

- [x] `connect`
- [x] `disconnect`
- [x] `publish`
- [x] `subscribe`
- [x] `unsubscribe`
- [x] `keygen`
- [x] `presence`
- [x] `me`

## Examples

### Connect to emitter server

```php
use Wunderwerkio\EmitterSDK\Emitter;

// Create Emitter instance.
$emitter = new Emitter();

// Connect to emitter server at host `localhost` and port `8080`.
// A username can be optionally passed as the third argument.
// The username is only used for the presence features (see https://emitter.io/develop/presence/).
$emitter->connect('localhost', 8080, 'my-username');

// Disconnect from server.
$emitter->disconnect();
```

### Publish

```php
$emitter->publish(
  key: '__channel-key-with-write-permission__',
  channel: 'article1/',
  message: 'Hello World!'
);
```

### Subscribe

Please note that this method simply subscribes for a channel.  
To receive messages, the `addMessageHandler` method must be used!

```php
$emitter->subscribe(
  key: '__channel-key-with-read-permission__', 
  channel: 'article1/'
);
```

### Listen for messages

In order to receive incoming messages from the server, a callback handler must be registered with `addMessageHandler`.

Additionally, a event loop must be started by calling `loop()` to start listening for server connections.  
This loop method is blocking, but can be released by calling the `interrupt()` method.

To avoid a deadlock, make sure you structure your code so that the `interrupt()` method is called once the desired messages are received.

```php
use Wunderwerkio\EmitterSDK\EmitterInterface;

// Listen for incoming messages.
// Note: Multiple handlers can be assigned!
$handler = function (EmitterInterface $emitter, string $message, string $topic): void {
  printf('Incoming message for topic %s: %s', $topic, $message);

  // Interrupt event loop once the first message is received.
  $emitter->interrupt();
};

$emitter->addMessageHandler($handler);

// A handler can be removed again.
$emitter->removeMessageHandler($handler);

// Start event loop.
// The TRUE here means, that the event loop sleeps for a short amount of time before checking again for new messages. 
$emitter->loop(TRUE);
```

### Loop handler

The internal event loop supports calling custom handlers on each loop cycle.
This can be used to implement timeouts for example to interrupt the event loop if no messages are received in 10 seconds.

```php
use Wunderwerkio\EmitterSDK\EmitterInterface;

$handler = function (EmitterInstance $emitter, float $elapsedTime): void {
  printf('Loop running for %d seconds', $elapsedTime);

  // Interrupt event loop after 10 seconds runtime.
  if ($elapsedTime >= 10) {
    $emitter->interrupt();
  }
}

// Register handler.
$emitter->addLoopHandler($handler);

// Handler can be removed again.
$emitter->removeLoopHandler($handler);
```

### Generate a channel key

To publish and subscribe to channels, emitter needs a channel key.
A channel key can be generated with the `keygen()` method by passing the master key, the channel name, the desired permissions and optionally a TTL in seconds for the channel key.

```php
$channelKey = $emitter->keygen(
  key: '__master-key-from-emitter-server__',
  channel: 'article1/',
  type: 'rwp' // Give permissions for e.g. r(ead), w(write) and p(resence)
  ttl: 0 // TTL in seconds for how long this key should be valid. 0 means key is valid indefinitely.
);

```

### Device presence

Emitter has built-in support for device presence.  
To receive presence data from other users, the `presence()` method can be used to subscribe to presence events.

If the `changes` argument is `FALSE` changes in presence of other users will not be automatically received and have to be requested via `presence()` again.  
Please note, that emitter only sends presence info for a maximum of 1000 users.

```php
$emitter->presence(
  channelKey: '_channel-key-with-presence-permission__',
  channel: 'article1/',
  status: TRUE, // Receive full status in response.
  changes: TRUE, // Subscribe to presence changes.
)
```