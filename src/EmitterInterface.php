<?php

declare(strict_types=1);

namespace Wunderwerk\PhpEmitter;

interface EmitterInterface {

  /**
   * Connect to emitter server instance via MQTT protocol.
   * 
   * @param string $host 
   *   The emitter host.
   * @param int $port 
   *   The emitter port.
   * @param string|null $username.
   *   The username used by emitter for presence.
   * 
   * @return self
   *   The instance.
   */
  public function connect(string $host, int $port, ?string $username): self;

  /**
   * Disconnect from emitter server.
   * 
   * @return self
   *   The instance.
   */
  public function disconnect(): self;

  /**
   * Checks if connected to emitter server.
   * 
   * @return bool 
   *   TRUE if connected, FALSE otherwise.
   */
  public function isConnected(): bool;

  /**
   * Add a message handler.
   * 
   * When subscribed to a topic, this handler will be called with when receiving a message.
   * 
   * The handler is called with the following arguments:
   *  - `EmitterInterface $emitter`
   *  - `string $topic`
   *  - `string $message`
   * 
   * @param \Closure $handler 
   *   The handler to add.
   * 
   * @return self
   *   The instance.
   */
  public function addMessageHandler(\Closure $handler): self;

  /**
   * Add a loop handler.
   * 
   * This handler will be called on every cycle when running the loop via `$emitter->loop()`.
   * 
   * The handler is called with the following arguments:
   *  - `EmitterInterface $emitter`
   *  - `float $elapsedTime`
   * 
   * @param \Closure $handler 
   *   The handler to add.
   * 
   * @return self
   *   The instance.
   */
  public function addLoopHandler(\Closure $handler): self;

  /**
   * Remove a message handler.
   * 
   * @param \Closure $handler 
   *   The handler to remove.
   * 
   * @return self
   *   The instance.
   */
  public function removeMessageHandler(\Closure $handler): self;

  /**
   * Remove a loop handler.
   * 
   * @param \Closure $handler 
   *   The handler to remove.
   * 
   * @return self 
   *   The instance.
   */
  public function removeLoopHandler(\Closure $handler): self;

  /**
   * Starts the internal event loop to listen to new messages from the emitter server.
   * 
   * This method is blocking!
   * Make sure to add the appropriate handlers to exit the event loop by calling `$emitter->interrupt()` when necessary.
   * 
   * @param bool $allowSleep 
   *   If TRUE, the event loop will sleep for a while when there are no messages to process.
   * @param bool $exitWhenQueuesEmpty 
   *   If TRUE, the event loop will exit when there are no more messages in the queue.
   * @param int|null $queueWaitLimit 
   *   If set, the event loop will wait for this amount of time for messages to be available in the queue.
   * 
   * @return self 
   *   The instance.
   */
  public function loop(bool $allowSleep = TRUE, bool $exitWhenQueuesEmpty = FALSE, int $queueWaitLimit = NULL): self;

  /**
   * Interrupt the internal event loop.
   * 
   * @return self 
   *   The instance.
   */
  public function interrupt(): self;

  /**
   * Publish a message to given $channel authorized by $key.
   * 
   * Make sure you have a channel key with write (`w`) permissions.
   * 
   * @param string $key 
   *   The channel key.
   * @param string $channel 
   *   The channel to publish to.
   * @param array|string $message 
   *   The message to publish.
   * @param null|int $ttl 
   *   The message TTL.
   * @param null|bool $me 
   *   If TRUE or NULL, the server sends published messages back to sender.
   * 
   * @return self 
   *   The instance.
   */
  public function publish(string $key, string $channel, array|string $message, ?int $ttl = NULL, ?bool $me = NULL): self;

  /**
   * Subscribe to given $channel authorized by $key.
   * 
   * Make sure you have a channel key with read (`r`) permissions.
   * 
   * Subscribing just makes sure to receive messages from the subscribed channel.
   * To receive messages, you need to add a message handler and then start the event loop with `$emitter->loop()`.
   * 
   * @param string $key 
   *   The channel key.
   * @param string $channel 
   *   The channel to subscribe to.
   * @param null|int $last 
   *   The last message ID to receive.
   * 
   * @return self 
   *   The instance.
   */
  public function subscribe(string $key, string $channel, ?int $last = NULL): self;

  /**
   * Unsubscribe from given $channel authorized by $key.
   * 
   * @param string $key 
   *   The channel key.
   * @param string $channel 
   *   The channel to unsubscribe from.
   * 
   * @return self 
   *   The instance.
   */
  public function unsubscribe(string $key, string $channel): self;

  /**
   * Publish a message through a $link.
   * 
   * @param string $link 
   *   The link to publish through.
   * @param array|string $message 
   *   The message to publish.
   * 
   * @return self 
   *   The instance.
   */
  public function publishWithLink(string $link, array|string $message): self;

  /**
   * Create a link to a particular channel.
   * 
   * @param string $key 
   *   The channel key.
   * @param string $channel 
   *   The channel to link to.
   * @param string $name 
   *   The link name.
   * @param bool $private 
   *   If TRUE, the link will be private.
   * @param bool $subscribe 
   *   If TRUE, the link will be subscribed.
   * @param null|int $ttl 
   *   The link TTL.
   * @param null|bool $me 
   *   If TRUE or NULL, the server sends published messages back to sender.
   * 
   * @return self 
   *   The instance.
   */
  public function link(string $key, string $channel, string $name, bool $private, bool $subscribe, ?int $ttl = NULL, ?bool $me = NULL): self;

  /**
   * Generate channel key.
   * 
   * @param string $key 
   *   The master key.
   * @param string $channel 
   *   The channel name.
   * @param string $type 
   *   The channel type.
   * @param int $ttl 
   *   The channel ttl.
   * 
   * @return string 
   *   The channel key.
   */
  public function keygen(string $key, string $channel, string $type, int $ttl): string;

  /**
   * Send a presence request to the server.
   * 
   * Make sure the $key has presence (`p`) permissions.
   * 
   * @param string $key 
   *   The channel key.
   * @param string $channel 
   *   The channel name.
   * @param null|bool $status 
   *   Whether a full status should be sent back in the response.
   * @param null|bool $changes 
   *   Whether we should subscribe this client to presence notification events.
   * 
   * @return self 
   *   The instance.
   */
  public function presence(string $key, string $channel, ?bool $status = NULL, ?bool $changes = NULL): self;

}