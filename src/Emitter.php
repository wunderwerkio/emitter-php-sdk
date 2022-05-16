<?php

declare(strict_types=1);

namespace Wunderwerk\PhpEmitter;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

/**
 * Emitter is a wrapper class over MqttClient to provide more convenient methods to interface with an emitter server.
 */
class Emitter implements EmitterInterface {

  /**
   * The MQTT client.
   */
  private MqttClient $client;

  /**
   * Maps handlers to wrapped handlers.
   */
  private ObjectMap $handlerMap;

  /**
   * Construct new Emitter object.
   */
  public function __construct() {
    $this->handlerMap = new ObjectMap();
  }

  /**
   * {@inheritdoc}
   */
  public function connect(string $host, int $port, ?string $username = NULL): self {
    $this->client = new MqttClient($host, $port);

    $settings = (new ConnectionSettings())
      ->setUsername($username);

    $this->client->connect($settings);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disconnect(): self {
    if ($this->client->isConnected()) {
      $this->client->disconnect();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isConnected(): bool {
    return $this->client->isConnected();
  }

  /**
   * {@inheritdoc}
   */
  public function addMessageHandler(\Closure $handler): self {
    $wrappedHandler = function ($client, string $topic, string $message) use ($handler): void {
      $handler($this, $topic, $message);
    };

    $this->handlerMap->add($handler, $wrappedHandler);
    $this->client->registerMessageReceivedEventHandler($wrappedHandler);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addLoopHandler(\Closure $handler): self {
    $wrappedHandler = function ($client, float $elapsedTime) use ($handler): void {
      $handler($this, $elapsedTime);
    };

    $this->handlerMap->add($handler, $wrappedHandler);
    $this->client->registerLoopEventHandler($wrappedHandler);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMessageHandler(\Closure $handler): self {
    $wrappedHandler = $this->handlerMap->get($handler);

    $this->client->unregisterMessageReceivedEventHandler($wrappedHandler);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeLoopHandler(\Closure $handler): self {
    $wrappedHandler = $this->handlerMap->get($handler);

    $this->client->unregisterLoopEventHandler($wrappedHandler);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function loop(bool $allowSleep = TRUE, bool $exitWhenQueuesEmpty = FALSE, int $queueWaitLimit = NULL): self {
    $this->client->loop($allowSleep, $exitWhenQueuesEmpty, $queueWaitLimit);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function interrupt(): self {
    $this->client->interrupt();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function publish(string $key, string $channel, string $message, ?int $ttl = NULL, ?bool $me = NULL): self {
    $options = [];

    if (is_null($me) || $me === TRUE) {
      $options['me'] = '1';
    }
    else {
      $options['me'] = '0';
    }

    if ($ttl) {
      $options['ttl'] = $ttl;
    }

    $topic = $this->formatChannel($key, $channel, $options);

    $this->client->publish($topic, $message, 0);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(string $key, string $channel, ?int $last = NULL): self {
    $options = [];

    if (!is_null($last)) {
      $options['last'] = $last;
    }

    $topic = $this->formatChannel($key, $channel, $options);
    $this->client->subscribe($topic);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe(string $key, string $channel): self {
    $topic = $this->formatChannel($key, $channel);

    $this->client->unsubscribe($topic);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function publishWithLink(string $link, string $message): self {
    $this->client->publish($link, $message);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function link(string $key, string $channel, string $name, bool $private, bool $subscribe, ?int $ttl = NULL, ?bool $me = NULL): self {
    $options = [];

    if (is_null($me) || $me === TRUE) {
      $options['me'] = '1';
    }
    else {
      $options['me'] = '0';
    }

    if ($ttl) {
      $options['ttl'] = $ttl;
    }

    $formattedChannel = $this->formatChannel($key, $channel, $options);

    $request = [
      'key' => $key,
      'channel' => $formattedChannel,
      'name' => $name,
      'private' => $private,
      'subscribe' => $subscribe,
    ];

    $this->client->publish('emitter/link/', json_encode($request), 0);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function keygen(string $key, string $channel, string $type, int $ttl): string {
    $request = [
      'key' => $key,
      'channel' => $channel,
      'type' => $type,
      'ttl' => $ttl,
    ];

    /** @var array $msg */
    $msg = [];

    // Handle keygen response.
    $handler = function (MqttClient $client, $topic, $message) use (&$msg): void {
      $msg = json_decode($message, TRUE);
      $client->interrupt();
    };

    // Register handler.
    $this->client->registerMessageReceivedEventHandler($handler);

    // Request key.
    $this->client->publish('emitter/keygen/', json_encode($request), 0);
    $this->client->loop(TRUE);

    // Cleanup.
    $this->client->unregisterMessageReceivedEventHandler($handler);

    if ($msg['status'] !== 200) {
      throw new \Exception($msg['message']);
    }

    return $msg['key'];
  }

  /**
   * {@inheritdoc}
   */
  public function presence(string $key, string $channel, ?bool $status = NULL, ?bool $changes = NULL): self {
    $request = [
      'key' => $key,
      'channel' => $channel,
      'status' => $status,
      'changes' => $changes,
    ];

    $this->client->publish('emitter/presence/', json_encode($request));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function me(): self {
    $this->client->publish('emitter/me/', '');

    return $this;
  }

  /**
   * Creates formatted channel string for emitter.
   *
   * @param string $key
   *   The key.
   * @param string $channel
   *   The channel.
   * @param array $options
   *   The options.
   *
   * @return string
   *   The formatted channel string.
   */
  protected function formatChannel(string $key, string $channel, array $options = []): string {
    // Prefix the key if any.
    $formatted = $key;

    if (strlen($key) > 0) {
      $formatted = str_ends_with($key, '/') ? $key . $channel : $key . '/' . $channel;
    }

    // Add trailing slash.
    if (!str_ends_with($formatted, '/')) {
      $formatted .= '/';
    }

    // Add options.
    if (count($options) > 0) {
      $formatted .= '?';

      $i = 0;
      foreach ($options as $key => $value) {
        $formatted .= $key . '=' . $value . '&';

        if (++$i === count($options)) {
          $formatted = rtrim($formatted, '&');
        }
      }
    }

    return $formatted;
  }

}
