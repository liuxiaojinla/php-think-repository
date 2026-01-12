<?php

namespace Xin\ThinkPHP\Repository;

use InvalidArgumentException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Model;

/**
 * 查询构造器
 * @template TModel of Model
 * @template TKey of array-key
 */
trait Queryable
{
	/**
	 * 获取查询构造器
	 * @return Query|class-string<TModel>
	 */
	abstract protected function newQuery();

	/**
	 * 获取查询构造器
	 * @return Query
	 */
	protected function query($withTrashed = false)
	{
		$query = $this->newQuery();

		// 是否是 model class-string
		if (is_string($query)) {
			$query = new $query();
		}

		// 是否是 model instance
		if (!is_subclass_of($query, Model::class)) {
			throw new InvalidArgumentException('Query must be a model class-string or instance.');
		}

		// 获取查询构造器
		$query = $query->db();
		$this->emit('BuildingQuery', $query);

		if ($withTrashed) {
			// 获取带软删除的查询构造器
			if (method_exists($query, 'withTrashed')) {
				$query = $query->withTrashed();
			}

			// 触发构建带软删除的查询构造器事件
			$this->emit('BuildingWithTrashedQuery', $query);
		}

		return $query;
	}

	/**
	 * 过滤查询器
	 * @param mixed $search
	 * @param array|null $with
	 * @param array|string|callable $orders
	 * @param array $options
	 * @return Query
	 */
	protected function filterQuery($search = [], array $with = null, $orders = ['id desc'], array $options = [])
	{
		$query = $this->query($options['with_trashed'] ?? false);

		// 兼容 simple，只获取简单数据
		if (method_exists($query->getModel(), 'simple') ||
			method_exists($query->getModel(), 'scopeSimple')) {
			$query->simple();
		}

		// 筛选条件
		if (is_callable($search)) {
			$search($query);
		} elseif ($search instanceof Filter) {
			$search->apply($query, $options);
		} else {
			$search = $this->newFilter($search);
			$search->apply($query, $options);
		}

		// 获取关联数据
		if (!empty($with)) {
			$query->with($with);
		}

		// 排序
		if ($orders) {
			$options['orders'] = $orders;
		}

		// Query 应用更多的查询条件
		$this->applyQueryForOptions($query, $options);

		return tap($query, function (Query $query) {
			$this->emit('BuildingFilterQuery', $query);
		});
	}

	/**
	 * 获取查询构造器
	 * @param mixed $idOrWhere
	 * @param array|null $with
	 * @param array $options
	 * @return Query
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	protected function findQuery($idOrWhere, array $with = null, array $options = [])
	{
		$query = $this->query();

		// 获取关联数据
		if (!empty($with)) {
			$query->with($with);
		}

		if (is_callable($idOrWhere)) {
			$idOrWhere($query);
		} elseif (!is_array($idOrWhere)) {
			$query->where('id', $idOrWhere);
		} else {
			$query->where($idOrWhere);
		}

		// Query 应用更多的查询条件
		$this->applyQueryForOptions($query, $options);

		return tap($query, function (Query $query) {
			$this->emit('BuildingFindQuery', $query);
		});
	}

	/**
	 * 获取删除查询构造器
	 * @return Query
	 */
	protected function deleteQuery()
	{
		return tap($this->query(true), function (Query $query) {
			$this->emit('BuildingDeleteQuery', $query);
		});
	}

	/**
	 * 查询条件应用
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	private function applyQueryForOptions(Query $query, array $options)
	{
		// 处理字段
		if (!empty($options['fields'])) {
			$query->field($options['fields']);
		}

		// 处理关联计数
		if (!empty($options['withCount'])) {
			$query->withCount($options['withCount']);
		} elseif (!empty($options['withCounts'])) {
			$query->withCount($options['withCounts']);
		}

		// 处理关联求和
		if (!empty($options['withSum'])) {
			foreach ($options['withSum'] as $relation => $column) {
				$query->withSum($relation, $column);
			}
		}

		// 处理关联平均
		if (!empty($options['withAvg'])) {
			foreach ($options['withAvg'] as $relation => $column) {
				$query->withAvg($relation, $column);
			}
		}

		// 处理关联最小
		if (!empty($options['withMin'])) {
			foreach ($options['withMin'] as $relation => $column) {
				$query->withMin($relation, $column);
			}
		}

		// 处理关联最大
		if (!empty($options['withMax'])) {
			foreach ($options['withMax'] as $relation => $column) {
				$query->withMax($relation, $column);
			}
		}

		// 处理排序
		$orders = $options['orders'] ?? [];
		if (!is_array($orders)) {
			$orders = [$orders];
		}

		foreach ($orders as $order) {
			if (is_string($order)) {
				$order = explode(" ", $order, 2);
			}

			if (is_array($order)) {
				$query->order($order[0], $order[1] ?? 'asc');
			} elseif (is_callable($order)) {
				call_user_func($orders, $query);
			}
		}

		// 处理偏移与偏移
		if (!empty($options['offset']) && !empty($options['limit'])) {
			$query->limit($options['offset'], $options['limit']);
		} elseif (!empty($options['offset'])) {
			$query->limit($options['offset']);
		} elseif (!empty($options['limit'])) {
			$query->limit(0, $options['limit']);
		}

		// 自定义查询条件
		if (!empty($options['where'])) {
			$query->where($options['where']);
		}
	}
}
