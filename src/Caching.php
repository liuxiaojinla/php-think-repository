<?php

namespace Xin\ThinkPHP\Repository;

/**
 * CURD 缓存
 */
trait Caching
{
	/**
	 * 缓存是否开启
	 * @var bool
	 */
	protected $isCacheEnabled = false;

	/**
	 * 优化应该刷新缓存标识
	 * @param null|bool $shouldRefreshCache
	 * @return bool
	 */
	protected function optimizeShouldRefreshCache($shouldRefreshCache)
	{
		if ($shouldRefreshCache === null) {
			$shouldRefreshCache = $this->isCacheEnabled;
		}

		return (bool)$shouldRefreshCache;
	}

	/**
	 * 是否需要刷新缓存
	 * @param bool|null $shouldRefreshCache
	 * @return bool
	 */
	protected function isNeedRefreshCache($shouldRefreshCache)
	{
		$shouldRefreshCache = $this->optimizeShouldRefreshCache($shouldRefreshCache);
		if (!$shouldRefreshCache || !method_exists($this, 'updateCache')) {
			return false;
		}

		return true;
	}

	/**
	 * 是否需要更新缓存
	 * @param bool|null $shouldRefreshCache
	 * @return bool
	 */
	protected function isNeedUpdateCache($shouldRefreshCache)
	{
		$shouldRefreshCache = $this->optimizeShouldRefreshCache($shouldRefreshCache);
		if (!$shouldRefreshCache || !method_exists($this, 'updateCache')) {
			return false;
		}

		return true;
	}

	/**
	 * 是否需要忘记缓存
	 * @param bool|null $shouldRefreshCache
	 * @return bool
	 */
	protected function isNeedForgetCache($shouldRefreshCache)
	{
		$shouldRefreshCache = $this->optimizeShouldRefreshCache($shouldRefreshCache);

		if (!$shouldRefreshCache || !method_exists($this, 'forgetCache')) {
			return false;
		}

		return true;
	}

	/**
	 * 设置是否使用缓存
	 * @param bool $shouldRefreshCache
	 * @return $this
	 */
	public function shouldRefreshCache(bool $shouldRefreshCache = true)
	{
		$this->isCacheEnabled = $shouldRefreshCache;

		return $this;
	}
}
