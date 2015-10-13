<?php
namespace def\Error;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ExceptionHandler
{
	protected $handlers = [];

	public function on($class, callable $handler)
	{
		if(!isset($this->handlers[$class])) {
			$this->handlers[$class] = [];
		}

		\array_unshift($this->handlers[$class], $handler);
	}

	public function off($class, callable $handler = null)
	{
		if(!isset($handler))
			unset($this->handlers[$class]);
		elseif(isset($this->handlers[$class]) && false !== $key = \array_search($handler, $this->handlers[$class]))
			unset($this->handlers[$class][$key]);
	}

	public function handle(\Exception $e)
	{
		$class = \get_class($e);

		do if(isset($this->handlers[$class]))
			foreach($this->handlers[$class] as $handler) {
				if(true === $handler($e)) return true;
		} while($class = \get_parent_class($class));

		throw $e;
	}

	public function register()
	{
		return \set_exception_handler([$this, 'handle']);
	}

	public function bindLogger(LoggerInterface $logger, $level = LogLevel::ERROR, $class = 'Exception')
	{
		return $this->on($class, function(\Exception $e) use($logger, $level) {
			return $logger->log($level, $e->getMessage(), ['exception' => $e]);
		});
	}

}
