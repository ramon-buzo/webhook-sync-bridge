<?php

/**
 * Service Container Pattern (IoC Container)
 *
 * Central registry that wires all dependencies together.
 * Implements Inversion of Control — classes don't instantiate
 * their own dependencies, the container builds and injects them.
 * Keeps all wiring in one place, making the dependency graph explicit and maintainable.
 */

namespace App;

use App\Contracts\CacheClientInterface;
use App\Contracts\PartnerClientInterface;
use App\Infrastructure\PartnerClient;
use App\Infrastructure\RedisClient;
use App\Services\SubscriptionService;
use App\Services\WebhookProcessor;

class Container
{
    private array $bindings = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function make(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException("No binding found for {$abstract}");
        }

        return ($this->bindings[$abstract])($this);
    }

    /**
     * Builds and wires the application container.
     *
     * Each call to bind() registers a factory under an abstract key (usually an interface).
     * When make() is called later, the container executes the factory and returns the instance.
     *
     * Binding order matters — dependencies must be registered before the services that need them.
     */
    public static function build(): self
    {
        $container = new self();

        // Bind infrastructure implementations to their interfaces.
        // Any class that needs CacheClientInterface will receive a RedisClient instance.
        // Any class that needs PartnerClientInterface will receive a PartnerClient instance.
        $container->bind(CacheClientInterface::class, fn() => new RedisClient());
        $container->bind(PartnerClientInterface::class, fn() => new PartnerClient());

        // Bind SubscriptionService, injecting its two dependencies from the container.
        // $c refers to the container itself, allowing recursive resolution.
        $container->bind(SubscriptionService::class, fn(self $c) => new SubscriptionService(
            partner: $c->make(PartnerClientInterface::class),
            cache:   $c->make(CacheClientInterface::class),
        ));

        // Bind WebhookProcessor, injecting its cache dependency from the container.
        $container->bind(WebhookProcessor::class, fn(self $c) => new WebhookProcessor(
            cache: $c->make(CacheClientInterface::class),
        ));

        return $container;
    }
}