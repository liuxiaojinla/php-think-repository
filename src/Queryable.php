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
			/** @var Query $query */
			$query = $query->simple();
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
		$this->applyQueryFormOptions($query, $options);

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
		$this->applyQueryFormOptions($query, $options);

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
	private function applyQueryFormOptions(Query $query, array $options)
	{
		// 处理字段
		if (!empty($options['fields'])) {
			$query->field($options['fields']);
		}

		// 处理关联聚合查询
		$this->applyRelationAggregateQueryFormOptions($query, $options);

		// 处理排序
		$this->applyOrderQueryFormOptions($query, $options);

		// 处理偏移与偏移
		$this->applyPaginationQueryFormOptions($query, $options);

		// 自定义查询条件
		if (!empty($options['where'])) {
			$query->where($options['where']);
		}
	}

	/**
	 * 应用排序查询
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	private function applyOrderQueryFormOptions(Query $query, array $options)
	{
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
	}

	/**
	 * 应用分页查询
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	private function applyPaginationQueryFormOptions(Query $query, array $options)
	{
		if (!empty($options['offset']) && !empty($options['limit'])) {
			$query->limit($options['offset'], $options['limit']);
		} elseif (!empty($options['offset'])) {
			$query->limit($options['offset']);
		} elseif (!empty($options['limit'])) {
			$query->limit(0, $options['limit']);
		}
	}

	/**
	 * 应用关联聚合查询
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	private function applyRelationAggregateQueryFormOptions(Query $query, array $options)
	{
		// 处理关联计数
		if (!empty($options['withCount'])) { // @deprecated
			$query->withCount($options['withCount']);
		} elseif (!empty($options['withCounts'])) { // @deprecated
			$query->withCount($options['withCounts']);
		} elseif (!empty($options['with_count'])) { // @deprecated
			$query->withCount($options['with_count']);
		} elseif (!empty($options['with_counts'])) { // @shouldUse
			$query->withCount($options['with_counts']);
		}

		// 处理关联求和
		if (!empty($options['withSum'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withSum'], 'withSum');
		} elseif (!empty($options['withSums'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withSums'], 'withSum');
		} elseif (!empty($options['with_sum'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['with_sum'], 'withSum');
		} elseif (!empty($options['with_sums'])) { // @shouldUse
			$this->applyRelationAggregateQuery($query, $options['with_sums'], 'withSum');
		}

		// 处理关联平均
		if (!empty($options['withAvg'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withAvg'], 'withAvg');
		} elseif (!empty($options['withAvgs'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withAvgs'], 'withAvg');
		} elseif (!empty($options['with_avg'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['with_avg'], 'withAvg');
		} elseif (!empty($options['with_avgs'])) { // @shouldUse
			$this->applyRelationAggregateQuery($query, $options['with_avgs'], 'withAvg');
		}

		// 处理关联最小
		if (!empty($options['withMin'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withMin'], 'withMin');
		} elseif (!empty($options['withMins'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withMins'], 'withMin');
		} elseif (!empty($options['with_min'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['with_min'], 'withMin');
		} elseif (!empty($options['with_mins'])) { // @shouldUse
			$this->applyRelationAggregateQuery($query, $options['with_mins'], 'withMin');
		}

		// 处理关联最大
		if (!empty($options['withMax'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withMax'], 'withMax');
		} elseif (!empty($options['withMaxs'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['withMaxs'], 'withMax');
		} elseif (!empty($options['with_max'])) { // @deprecated
			$this->applyRelationAggregateQuery($query, $options['with_max'], 'withMax');
		} elseif (!empty($options['with_maxs'])) { // @shouldUse
			$this->applyRelationAggregateQuery($query, $options['with_maxs'], 'withMax');
		}
	}

	/**
	 * 关联聚合查询
	 * @param Query $query
	 * @param array<string,string|array{string,string|callable}> $relations
	 * @param string $aggregate
	 * @return void
	 */
	private function applyRelationAggregateQuery(Query $query, array $relations, string $aggregate)
	{
		foreach ($relations as $relation => $field) {
			if (is_array($field)) {
				$relation = [
					$relation => isset($field[1]) ? $field[1] : null,
				];
				$field = $field[0];
			}

			$query->{$aggregate}($relation, $field);
		}
	}
}
