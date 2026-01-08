<?php

namespace Xin\ThinkPHP\Repository;

use think\App;
use think\db\Query;
use think\Event;
use think\Model;
use think\model\Collection;
use think\Paginator;
use Xin\Support\Reflect;

/**
 * 事件发射器
 * @template TModel of Model
 * @template TKey of array-key
 */
trait CURDEventEmitter
{
	/**
	 * @var Event
	 */
	protected $emitter;

	/**
	 * 是否临时禁用所有查询事件
	 * @var bool
	 */
	protected $isEventMuted = false;

	/**
	 * 获取事件发射器
	 * @return Event
	 */
	protected function emitter()
	{
		if (!$this->emitter) {
			if (property_exists($this, 'app')) {
				$this->emitter = new Event($this->app);
			} else {
				$this->emitter = new Event(App::getInstance());
			}
		}

		return $this->emitter;
	}

	/**
	 * 触发事件
	 * @param string $event
	 * @param mixed $data
	 * @return array|null
	 */
	protected function emit($event, $data)
	{
		return $this->emitter()->trigger($event, $data);
	}

	/**
	 * 监听事件
	 * @param string $event
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	protected function listen(string $event, callable $callback, $once = false)
	{
		if ($once) {
			$realCallback = $callback;
			$callback = function ($data) use (&$event, &$realCallback, &$callback) {
				try {
					return $realCallback($data);
				} finally {
					$listeners = Reflect::get($this->emitter(), 'listeners');
					foreach ($listeners[$event] as $key => $listener) {
						if ($listener === $callback) {
							unset($listeners[$event][$key]);
							break;
						}
					}
					Reflect::set($this->emitter(), 'listeners', $listeners);
				}
			};
		}

		$this->emitter()->listen($event, $callback);

		return $this;
	}

	/**
	 * 静默事件
	 * @return $this
	 */
	protected function withoutEvents(callable $callback = null)
	{
		if ($callback) {
			$original = $this->isEventMuted;
			try {
				$this->isEventMuted = true;
				call_user_func($callback);
			} finally {
				$this->isEventMuted = $original;
			}
		} else {
			$this->isEventMuted = true;
		}

		return $this;
	}

	/**
	 * 恢复事件
	 * @return $this
	 */
	protected function withEvents()
	{
		$this->isEventMuted = false;
		return $this;
	}

	/**
	 * 获取静默状态
	 * @return bool
	 */
	public function isEventMuted()
	{
		return $this->isEventMuted;
	}

	/**
	 * 监听取回单条数据前事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onRetrieving(callable $callback, $once = false)
	{
		return $this->listen('Retrieving', $callback, $once);
	}

	/**
	 * 监听取回单条数据后事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onRetrieved(callable $callback, $once = false)
	{
		return $this->listen('Retrieved', $callback, $once);
	}

	/**
	 * 监听写入前事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onWriting(callable $callback, $once = false)
	{
		return $this->listen('Writing', $callback, $once);
	}

	/**
	 * 监听写入后事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onWritten(callable $callback, $once = false)
	{
		return $this->listen('Written', $callback, $once);
	}

	/**
	 * 监听删除前事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onDestroying(callable $callback, $once = false)
	{
		return $this->listen('Destroying', $callback, $once);
	}

	/**
	 * 监听删除后事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onDestroyed(callable $callback, $once = false)
	{
		return $this->listen('Destroyed', $callback, $once);
	}

	/**
	 * 监听单个模型删除前事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onDeleting(callable $callback, $once = false)
	{
		return $this->listen('Deleting', $callback, $once);
	}

	/**
	 * 监听单个模型删除后事件
	 * @param callable $callback
	 * @param bool $once
	 * @return $this
	 */
	public function onDeleted(callable $callback, $once = false)
	{
		return $this->listen('Deleted', $callback, $once);
	}

	/**
	 * 触发数据过滤前的事件
	 * @param Query $query
	 * @param array|callable $search
	 * @param array $options
	 */
	protected function emitFiltering(Query $query, $search, array &$options)
	{
		if ($this->isEventMuted()) {
			return;
		}

		$this->filtering($query, $search, $options);

		$this->emit('Filtering', [
			[
				'query' => $query,
				'search' => $search,
				'options' => $options,
			],
		]);
	}

	/**
	 * 触发数据过滤后的事件
	 * @param Paginator|Collection<TKey,TModel>|mixed $result
	 * @param Query $query
	 * @param array|callable $search
	 * @param array $options
	 * @return Collection<TKey,TModel>|Paginator|mixed
	 */
	protected function emitFiltered($result, Query $query, $search, array $options)
	{
		if ($this->isEventMuted()) {
			return $result;
		}

		$result = $this->filtered($result, $query, $search, $options);

		$this->emit('Filtered', [
			[
				'result' => $result,
				'query' => $query,
				'search' => $search,
				'options' => $options,
			],
		]);

		return $result;
	}

	/**
	 * 触发取回单条数据前事件
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	protected function emitRetrieving(Query $query, array $options)
	{
		if ($this->isEventMuted()) {
			return;
		}

		$this->retrieving($query, $options);

		$this->emit('Retrieving', [
			[
				'query' => $query,
				'options' => $options,
			],
		]);
	}

	/**
	 * 触发取回单条数据后事件
	 * @param Model $info
	 * @return Model
	 */
	protected function emitRetrieved($info, Query $query, array $options)
	{
		if ($this->isEventMuted()) {
			return $info;
		}

		$info = $this->retrieved($info, $query, $options);

		$this->emit('Retrieved', [
			[
				'info' => $info,
				'query' => $query,
				'options' => $options,
			],
		]);

		return $info;
	}

	/**
	 * 触发写入前事件
	 * @param array $data
	 * @param Model|null $info
	 * @param string $scene
	 * @return array
	 */
	protected function emitWriting(array $data, $info, string $scene)
	{
		if ($this->isEventMuted()) {
			return $data;
		}

		$data = $this->writing($data, null, CURDWriteScene::CREATE);

		$this->emit('Writing', [
			[
				'data' => $data,
				'info' => $info,
				'scene' => $scene,
			],
		]);

		return $data;
	}

	/**
	 * 触发写入后事件
	 * @param array $data
	 * @param Model $info
	 * @param string $scene
	 * @return Model
	 */
	protected function emitWritten(array $data, $info, string $scene)
	{
		if ($this->isEventMuted()) {
			return $info;
		}

		$info = $this->written($data, $info, CURDWriteScene::CREATE);

		$this->emit('Written', [
			[
				'data' => $data,
				'info' => $info,
				'scene' => $scene,
			],
		]);

		return $info;
	}

	/**
	 * 触发删除前事件
	 * @param array $ids
	 * @param bool $isForce
	 * @return void
	 */
	protected function emitDestroying($ids, Query $query, $isForce)
	{
		if ($this->isEventMuted()) {
			return;
		}

		$this->destroying($ids, $query, $isForce);

		$this->emit('Destroying', [
			[
				'ids' => $ids,
				'query' => $query,
				'force' => $isForce,
			],
		]);
	}

	/**
	 * 触发删除后事件
	 * @param array $ids
	 * @param Collection<TKey,TModel> $items
	 * @param bool $isForce
	 * @return void
	 */
	protected function emitDestroyed($ids, $items, $isForce)
	{
		if ($this->isEventMuted()) {
			return;
		}

		$this->destroyed($ids, $items, $isForce);

		$this->emit('Destroyed', [
			[
				'ids' => $ids,
				'items' => $items,
				'force' => $isForce,
			],
		]);
	}

	/**
	 * 触发单个模型删除前事件
	 * @param Model $info
	 * @param bool $isForce
	 * @return void
	 */
	protected function emitDeleting($info, $isForce)
	{
		if ($this->isEventMuted()) {
			return;
		}

		$this->deleting($info, $isForce);

		$this->emit('Deleting', [
			[
				'info' => $info,
				'force' => $isForce,
			],
		]);
	}

	/**
	 * 触发单个模型删除后事件
	 * @param Model $info
	 * @param bool $isForce
	 * @return void
	 */
	protected function emitDeleted($info, $isForce)
	{
		if ($this->isEventMuted()) {
			return;
		}

		$this->deleted($info, $isForce);

		$this->emit('Deleted', [
			[
				'info' => $info,
				'force' => $isForce,
			],
		]);
	}

	/**
	 * 数据过滤前的事件
	 * @param Query $query
	 * @param array|callable $search
	 * @param array $options
	 */
	protected function filtering(Query $query, $search, array &$options)
	{
	}

	/**
	 * 数据过滤后的事件
	 * @param Paginator|Collection<TKey,TModel>|mixed $result
	 * @param Query $query
	 * @param array|callable $search
	 * @param array $options
	 * @return Collection<TKey,TModel>|Paginator|mixed
	 */
	protected function filtered($result, Query $query, $search, array $options)
	{
		return $result;
	}

	/**
	 * 取回单条数据前事件
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	protected function retrieving(Query $query, array $options)
	{
	}

	/**
	 * 取回单条数据后事件
	 * @param Model $info
	 * @param Query $query
	 * @param array $options
	 * @return Model
	 */
	protected function retrieved($info, Query $query, array $options)
	{
		return $info;
	}

	/**
	 * 写入前事件
	 * @param array $data
	 * @param Model|null $info
	 * @param string $scene
	 * @return array
	 */
	protected function writing(array $data, $info, string $scene)
	{
		return $data;
	}

	/**
	 * 写入后事件
	 * @param array $data
	 * @param Model $info
	 * @param string $scene
	 * @return Model
	 */
	protected function written(array $data, $info, string $scene)
	{
		return $info;
	}

	/**
	 * 删除前事件
	 * @param array $ids
	 * @param bool $isForce
	 * @return void
	 */
	protected function destroying($ids, Query $query, $isForce)
	{
	}

	/**
	 * 删除后事件
	 * @param array $ids
	 * @param Collection<TKey,TModel> $items
	 * @param bool $isForce
	 * @return void
	 */
	protected function destroyed($ids, $items, $isForce)
	{
	}

	/**
	 * 删除单个模型前事件
	 * @param Model $info
	 * @param bool $isForce
	 * @return void
	 */
	protected function deleting($info, $isForce)
	{
	}

	/**
	 * 删除单个模型后事件
	 * @param Model $info
	 * @param bool $isForce
	 * @return void
	 */
	protected function deleted($info, $isForce)
	{
	}
}
