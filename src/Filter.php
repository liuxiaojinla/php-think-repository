<?php

namespace Xin\ThinkPHP\Repository;

use app\service\repository\contract\Filter as FilterContract;
use Xin\Support\Arr;

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
