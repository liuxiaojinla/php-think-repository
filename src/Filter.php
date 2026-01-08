<?php

namespace Xin\ThinkPHP\Repository;

use Xin\Support\Arr;
use Xin\ThinkPHP\Repository\Contracts\Filter as FilterContract;

abstract class Filter implements FilterContract
{
	/**
	 * @var array
	 */
	protected $input;

	/**
	 * @param array $input
	 */
	public function __construct(array $input)
	{
		$this->input = $input;
	}

	/**
	 * @inerhitDoc
	 */
	public function search()
	{
		return $this->input;
	}

	/**
	 * @inerhitDoc
	 */
	public function getInput($key = null, $default = null)
	{
		if ($key) {
			return Arr::get($this->input, $key, $default);
		}

		return $this->input;
	}

	/**
	 * @inerhitDoc
	 */
	public static function make(array $input)
	{
		return new static($input);
	}
}
