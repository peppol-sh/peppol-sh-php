<?php

declare(strict_types=1);

namespace PeppolSh\Tests\Laravel;

use PeppolSh\Client;

/**
 * A class that gets the client through constructor injection, as an
 * application service does.
 */
final class ClientConsumer
{
    /** @var Client */
    public $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }
}
