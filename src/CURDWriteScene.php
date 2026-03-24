<?php

namespace Xin\ThinkPHP\Repository;

use Xin\Support\Enum;

final class CURDWriteScene extends Enum
{
	/**
	 * 新增
	 */
	public const CREATE = 'create';

	/**
	 * 修改
	 */
	public const UPDATE = 'update';
}
