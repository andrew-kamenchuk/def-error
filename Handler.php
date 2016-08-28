<?php
namespace def\Error;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Handler
{
    private $errorReporting = E_ALL;

    private $includeTrace = false;
    private $traceOptions = DEBUG_BACKTRACE_PROVIDE_OBJECT;

    private $asException = 0;

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

    public function asException($level)
    {
        $this->asException |= $level;
    }

    public function setIncludeTrace($includeTrace = true, $traceOptions = DEBUG_BACKTRACE_PROVIDE_OBJECT)
    {
        $this->includeTrace = $includeTrace;
        $this->traceOptions = $traceOptions;
    }

    public function on($error, callable $handler)
    {
        for ($i = 1; $i < E_ALL; $i <<= 1) {
            if ($i & $error) {
                array_unshift($this->handlers[$i], $handler);
            }
        }
    }

    public function off($error, callable $handler = null)
    {
        for ($i = 1; $i < E_ALL; $i <<= 1) {
            if (0 == ($i & $error)) {
                continue;
            }

            if (!isset($handler)) {
                $this->handlers[$i] = [];
            } elseif (false !== $key = array_search($handler, $this->handlers[$i], true)) {
                unset($this->handlers[$i][$key]);
            }
        }
    }

    private function getTrace()
    {
        $trace = debug_backtrace($this->traceOptions);

        foreach (array_reverse($trace, true) as $index => $item) {
            if (!isset($item["class"])) {
                continue;
            }

            if (self::class == $item["class"] || is_subclass_of($item["class"], self::class)) {
                array_splice($trace, 0, $index + 1);
                break;
            }
        }

        return $trace;
    }

    public function handle($type, $message, $file = null, $line = null, array $context = null)
    {
        $errorReporting = $this->errorReporting & error_reporting();

        if (0 == ($type & $errorReporting)) {
            return false;
        }

        if ($type & $this->asException) {
            throw new ErrorException($message, $type, $type, $file, $line);
        }

        $trace = $this->includeTrace ? $this->getTrace() : [];

        foreach ($this->handlers[$type] as $handler) {
            if (true === $handler($type, $message, $file, $line, $context, $trace)) {
                 return true;
            }
        }

        return false;
    }

    public function register()
    {
        $reservedMemory = str_repeat("*", 1024 * 1024);

        $fatalError = E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

        register_shutdown_function(function () use (&$reservedMemory, $fatalError) {
            $reservedMemory = null;

            if ($e = error_get_last() and ($e["type"] & $fatalError)) {
                return $this->handle($e["type"], $e["message"], $e["file"], $e["line"]);
            }
        });

        return set_error_handler([$this, "handle"]);
    }

    public function bindLogger(LoggerInterface $logger, $level = LogLevel::ERROR, $mask = E_ALL)
    {
        return $this->on($mask, function (
            $type,
            $message,
            $file,
            $line,
            array $context,
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
