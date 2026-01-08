<?php

namespace Xin\ThinkPHP\Repository\Contracts;

use think\db\Query;

interface Filter
{
	/**
	 * 场景级基础约束（状态、权限、排序、字段等）
	 * @param Query $query
	 * @param array $filter
	 * @param array $with
	 * @param array $order
	 * @param array $options
	 * @return void
	 */
	public function apply(Query $query, array $options = []);
}
