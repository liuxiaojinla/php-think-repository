<?php

namespace Xin\ThinkPHP\Repository;

trait Filterable
{
	/**
	 * 获取允许搜索字段
	 * @return array
	 */
	protected function getAllowedSearchFields()
	{
		return [];
	}

	/**
	 * 获取允许排序字段
	 * @return array
	 */
	protected function getAllowedSortFields()
	{
		return [];
	}

	/**
	 * 获取过滤器选项
	 * @return array
	 */
	protected function getFilterOptions()
	{
		return [];
	}

	/**
	 * 获取搜索关键字字段
	 * @return array|null
	 */
	protected function getSearchKeywordFields()
	{
		return null;
	}

	/**
	 * 初始化默认的过滤器
	 * @param Filter $filter
	 * @return void
	 */
	protected function initDefaultFilter(Filter $filter)
	{
	}
}
