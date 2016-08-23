<?php
namespace def\Error;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Handler
{
    private $errorReporting = E_ALL;

    private $includeTrace = false;
    private $traceOptions = DEBUG_BACKTRACE_PROVIDE_OBJECT;

    protected $handlers = [
        E_ERROR             => [], E_WARNING         => [],
        E_PARSE             => [], E_NOTICE          => [],
        E_CORE_ERROR        => [], E_CORE_WARNING    => [],
        E_COMPILE_ERROR     => [], E_COMPILE_WARNING => [],
        E_USER_ERROR        => [], E_USER_WARNING    => [],
        E_USER_NOTICE       => [], E_STRICT          => [],
        E_RECOVERABLE_ERROR => [], E_DEPRECATED      => [],
        E_USER_DEPRECATED   => [],
    ];

    public function errorReporting($errorReporting = null)
    {
        if (func_num_args()) {
            $this->errorReporting = $errorReporting;
        }

        return $this->errorReporting;
    }

    public function setIncludeTrace($includeTrace = true, $traceOptions = DEBUG_BACKTRACE_PROVIDE_OBJECT)
    {
        $this->includeTrace = $includeTrace;
        $this->traceOptions = $traceOptions;
    }

    public function on($error, callable $handler)
    {
        $j = 1;
        while ($j <<= 1 < E_ALL) {
            if ($j & $error) {
                array_unshift($this->handlers[$j], $handler);
            }
        }
    }

    public function off($error, callable $handler = null)
    {
        $j = 1;
        while ($j <<= 1 < E_ALL) {
            if ($j & $error) {
                if (!isset($handler)) {
                    $this->handlers[$j] = [];
                } elseif (false !== $key = array_search($handler, $this->handlers[$j], true)) {
                    unset($this->handlers[$j][$key]);
                }
            }
        }
    }

    protected function getTrace()
    {
        $trace = debug_backtrace($this->traceOptions);
        array_shift($trace);

        foreach (array_reverse($trace, true) as $index => $item) {
            if (isset($item['class']) && (__CLASS__ == $item['class'] || \is_subclass_of($item['class'], __CLASS__))) {
                array_splice($trace, 0, $index + 1);
                break;
            }
        }

        return $trace;
    }

    public function handle($type, $message, $file = null, $line = null, array $context = null)
    {
        if ($type & $this->errorReporting & error_reporting()) {
            $trace = [];

            if ($this->includeTrace) {
                $trace = $this->getTrace();
            }

            foreach ($this->handlers[$type] as $handler) {
                if (true === $handler($type, $message, $file, $line, $context, $trace)) {
                     return true;
                }
            }
        }

        return false;
    }

    public function register()
    {
        $reservedMemory = \str_repeat("*", 1024 * 1024);

        $fatalError = E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

        register_shutdown_function(function () use (&$reservedMemory, $fatalError) {
            $reservedMemory = null;

            if ($e = error_get_last() and ($e["type"] & $fatalError)) {
                return $this->handle($e["type"], $e["message"], $e["file"], $e["line"]);
            }
        });

        return set_error_handler([$this, "handle"]);
    }

    public function bindLogger(LoggerInterface $logger, $level = LogLevel::ERROR, $mask = \E_ALL)
    {
        return $this->on($mask, function (
            $type,
            $message,
            $file = null,
            $line = null,
            array $context = null,
            array $trace
        ) use (
            $logger,
            $level
        ) {
            return $logger->log(
                $level,
                $message,
                ["error" => [$type, $message, $line, $file, $context, $trace]]
            );
        });
    }
}
