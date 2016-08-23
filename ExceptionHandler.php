<?php
namespace def\Error;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ExceptionHandler
{
    protected $handlers = [];

    public function __construct(callable $defaultHandler = null)
    {
        if (isset($defaultHandler)) {
            $this->on(Exception::class, $defaultHandler);
        }
    }

    public function on($class, callable $handler)
    {
        if (!isset($this->handlers[$class])) {
            $this->handlers[$class] = [];
        }

        array_unshift($this->handlers[$class], $handler);
    }

    public function off($class, callable $handler = null)
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

    public function handle(Exception $e)
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

        return false;
    }

    public function register()
    {
        return set_exception_handler(function (Exception $e) {
            if (!$this->handle($e)) {
                throw $e;
            }
        });
    }

    public function bindLogger(LoggerInterface $logger, $level = LogLevel::ERROR, $class = Exception::class)
    {
        return $this->on($class, function (\Exception $e) use ($logger, $level) {
            $logger->log($level, $e->getMessage(), ["exception" => $e]);
        });
    }
}
