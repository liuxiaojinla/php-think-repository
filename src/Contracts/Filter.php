<?php

namespace Xin\ThinkPHP\Repository\Contracts;

use think\db\Query;

interface Filter
{
	/**
	 * 返回允许被搜索的字段及规则
	 * 例：['title' => ['like','标题'], 'status' => '0,1', 'price' => ['between',[100,200]]]
	 * @return array
	 */
	public function search();

	/**
	 * 场景级基础约束（状态、权限、排序、字段等）
	 * @param Query $query
	 * @return void
	 */
	public function apply(Query $query);
}
