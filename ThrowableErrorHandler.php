<?php
namespace def\Error;

use Throwable;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ThrowableErrorHandler
{
    protected $handlers = [];

    public function __construct(callable $defaultHandler = null)
    {
        if (isset($defaultHandler)) {
            $this->on(Throwable::class, $defaultHandler);
        }
    }

    public function on(string $class, callable $handler)
    {
        if (!isset($this->handlers[$class])) {
            $this->handlers[$class] = [];
        }

        array_unshift($this->handlers[$class], $handler);
    }

    public function off(string $class, callable $handler = null)
    {
        if (!isset($this->handlers[$class])) {
            return;
        }

        if (!isset($handler)) {
            unset($this->handlers[$class]);
        } elseif (false !== $key = array_search($handler, $this->handlers[$class], true)) {
            unset($this->handlers[$class][$key]);
        }
    }

    public function handle(Throwable $e)
    {
        $class = get_class($e);

        do {
            if (isset($this->handlers[$class])) {
                foreach ($this->handlers[$class] as $handler) {
                    if (true === $handler($e)) {
                        return true;
                    }
                }
            }
        } while ($class = get_parent_class($class));

        if (isset($this->handlers[Throwable::class])) {
            foreach ($this->handlers[Throwable::class] as $handler) {
                if (true === $handler($e)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function register()
    {
        return set_exception_handler(function (Throwable $e) {
            if (!$this->handle($e)) {
                throw $e;
            }
        });
    }

    public function bindLogger(
        LoggerInterface $logger,
        string $level = LogLevel::ERROR,
        string $class = Throwable::class
    ) {
        return $this->on($class, function (\Exception $e) use ($logger, $level) {
            $logger->log($level, $e->getMessage(), ["exception" => $e]);
        });
    }
}
