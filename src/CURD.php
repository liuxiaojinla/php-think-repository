<?php

namespace Xin\ThinkPHP\Repository;

use app\service\repository\contract\Filter;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\exception\ValidateException;
use think\facade\Db;
use think\Model;
use think\model\Collection;
use think\Paginator;
use think\Validate;
use Xin\Support\Reflect;

/**
 * CURD 操作
 * @template TModel of Model
 * @template TKey of array-key
 * @mixin CURDEventEmitter<TModel>
 */
trait CURD
{
	use CURDCaching, CURDEventEmitter;

	/**
	 * @var bool
	 */
	protected $useTransaction = false;


	/**
	 * @var array
	 */
	protected $deleteTogethers = [];

	/**
	 * 数据过滤
	 * @param callable $callback
	 * @param array|callable|Filter $search
	 * @param array|null $with
	 * @param array|string|callable $orders
	 * @param array $options
	 * @return Paginator<TModel>|Collection<TKey,TModel>|Model
	 */
	protected function filterOn(callable $callback, $search = [], array $with = null, $orders = ['id desc'], array $options = [])
	{
		// 数据过滤查询器
		$query = $this->filterQuery($search, $with, $orders, $options);

		// 触发过滤前事件
		$this->emitFiltering($query, $search, $options);

		// 执行回调
		$result = $callback($query);

		// 触发过滤完成事件
		return $this->emitFiltered($result, $query, $search, $options);
	}

	/**
	 * @param callable $callback
	 * @param mixed $search
	 * @param array|null $with
	 * @param string[]|string|callable $orders
	 * @param array $options
	 * @return Paginator<TModel>
	 * @throws DbException
	 */
	protected function paginateOn(callable $callback, $search = [], array $with = null, $orders = ['id desc'], array $options = [])
	{
		return $this->filterOn(function (Query $query) use (&$callback, &$options) {
			// 执行回调
			/** @var Query $query */
			$query = $callback($query);

			// 获取分页参数
			$pageSize = $options['page_size'] ?? $options['list_rows'] ?? 15;
			$page = $options['page'] ?? 1;

			return $query->paginate([
				'list_rows' => $pageSize,
				'page' => $page,
			]);
		}, $search, $with, $orders, $options);
	}

	/**
	 * 分页获取数据
	 * @param mixed $search
	 * @param array|null $with
	 * @param string[]|string|callable $orders
	 * @param array $options
	 * @return Paginator<TModel>
	 * @throws DbException
	 */
	public function paginate($search = [], array $with = null, $orders = ['id desc'], array $options = [])
	{
		return $this->paginateOn(function (Query $query) {
			return $query;
		}, $search, $with, $orders, $options);
	}

	/**
	 * 获取数据
	 * @param mixed $search
	 * @param array|null $with
	 * @param array|string|callable $orders
	 * @param array $options
	 * @return Collection<TKey,TModel>
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function lists($search = [], array $with = null, $orders = ['id desc'], array $options = [])
	{
		return $this->filterOn(function (Query $query) {
			return $query->select();
		}, $search, $with, $orders, $options);
	}

	/**
	 * 数据查询
	 * @param callable $callback
	 * @param mixed $idOrWhere
	 * @param array $with
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	protected function findOn(callable $callback, $idOrWhere, array $with = [], array $options = [])
	{
		// 数据查询器
		$query = $this->findQuery($idOrWhere, $with, $options);

		// 触发查询前事件
		$this->emitRetrieving($query, $options);

		// 执行回调
		$info = $callback($query);

		// 触发查询完成事件
		if ($info) {
			$this->emitRetrieved($info, $query, $options);
		}

		return $info;
	}

	/**
	 * 获取数据
	 * @param mixed $idOrWhere
	 * @param array $with
	 * @param array|null $columns
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function find($idOrWhere, array $with = [], array $columns = null, array $options = [])
	{
		return $this->findOn(function (Query $query) use (&$columns) {
			return $query->field($columns ?: true)->find();
		}, $idOrWhere, $with, $options);
	}

	/**
	 * 获取数据
	 * @param string $field
	 * @param mixed $value
	 * @param array $with
	 * @param array|null $columns
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findBy(string $field, $value, array $with = [], array $columns = null, array $options = [])
	{
		return $this->find(function (Query $query) use (&$field, &$value) {
			$query->where($field, $value);
		}, $with, $columns, $options);
	}

	/**
	 * 获取最新数据
	 * @param string $field
	 * @param mixed $value
	 * @param array $with
	 * @param array|null $columns
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findLatestBy(string $field, $value, array $with = [], array $columns = null, array $options = [])
	{
		return $this->find(function (Query $query) use (&$field, &$value) {
			$query->where($field, $value)->order($query->getPk(), 'desc');
		}, $with, $columns, $options);
	}

	/**
	 * 获取数据
	 * @param mixed $idOrWhere
	 * @param array $with
	 * @param array|null $columns
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findOrFail($idOrWhere, array $with = [], array $columns = null, array $options = [])
	{
		return $this->findOn(function (Query $query) use (&$columns) {
			return $query->field($columns ?: true)->findOrFail();
		}, $idOrWhere, $with, $options);
	}

	/**
	 * 获取数据
	 * @param string $field
	 * @param mixed $value
	 * @param array $with
	 * @param array|null $columns
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findOrFailBy(string $field, $value, array $with = [], array $columns = null, array $options = [])
	{
		return $this->findOrFail(function (Query $query) use (&$field, &$value) {
			$query->where($field, $value);
		}, $with, $columns, $options);
	}

	/**
	 * 获取最新数据
	 * @param string $field
	 * @param mixed $value
	 * @param array $with
	 * @param array|null $columns
	 * @param array $options
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findLatestOrFailBy(string $field, $value, array $with = [], array $columns = null, array $options = [])
	{
		return $this->findOrFail(function (Query $query) use (&$field, &$value) {
			$query->where($field, $value)->order($query->getPk(), 'desc');
		}, $with, $columns, $options);
	}

	/**
	 * 判断数据是否存在
	 * @param mixed $idOrWhere
	 * @return bool
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function exists($idOrWhere)
	{
		return $this->findQuery($idOrWhere)->field('id')->findOrEmpty()->isEmpty();
	}

	/**
	 * 统计数据
	 * @param array|callable $where
	 * @param string|array $columns
	 * @return int
	 */
	public function count($where, $columns = '*')
	{
		$query = $this->query();

		if (is_callable($where)) {
			$where($query);
		} else {
			$query->where($where);
		}

		return $query->count($columns);
	}

	/**
	 * 统计数据
	 * @param string $field
	 * @param mixed $value
	 * @param array $where 添加其他条件
	 * @param string|array $columns
	 * @return int
	 */
	public function countBy($field, $value, array $where = [], $columns = '*')
	{
		$query = $this->query();
		if (is_array($value)) {
			$query->whereIn($field, $value);
		} else {
			$query->where($field, $value);
		}

		// 添加其他条件
		if (!empty($where)) {
			if (is_callable($where)) {
				$where($query);
			} else {
				$query->where($where);
			}
		}

		return $query->count($columns);
	}

	/**
	 * 获取允许的字段
	 * @param TModel $model
	 * @param bool $forceFill
	 * @return array
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	protected function getAllowedFields($model, bool $forceFill)
	{
		$allowFields = [];
		if (!$forceFill) {
			/** @noinspection PhpUnhandledExceptionInspection */
			$allowFields = Reflect::get($model, 'fillable', []);
		}

		if (!empty($allowFields)) {
			$allowFields[] = "created_at";
			$allowFields[] = "updated_at";
			$allowFields[] = "deleted_at";
		}

		return array_unique($allowFields);
	}

	/**
	 * 实际的创建数据
	 * @param array $data
	 * @param bool $forceFill
	 * @return TModel
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	private function persistActualCreate(array $data, bool $forceFill = false)
	{
		$query = $this->query();
		/** @var Model $model */
		$model = $query->getModel();
		if (!($model instanceof Model)) {
			throw new \LogicException(static::class . 'use Model must be instance of Model');
		}

		// 数据不能为空
		if (empty($data)) {
			throw new \LogicException('数据不能为空！');
		}

		// 获取允许的字段
		$allowFields = $this->getAllowedFields($model, $forceFill);

		// 保存数据
		if ($model->allowField($allowFields)->save($data, true) === false) {
			throw new \LogicException('保存失败！');
		}

		// 刷新数据
		$model->refresh();

		return $model;
	}

	/**
	 * 创建数据
	 * @param array $data
	 * @param callable|null $callback
	 * @param bool $forceFill
	 * @param bool $useTransaction
	 * @param bool|null $shouldRefreshCache
	 * @return TModel
	 */
	protected function createOn(array $data, callable $callback = null, bool $forceFill = false, bool $shouldRefreshCache = null, bool $useTransaction = null)
	{
		// 触发事件
		$data = $this->emitWriting($data, null, CURDWriteScene::CREATE);

		// 创建数据处理器
		$handler = function () use (&$forceFill, &$data, &$callback) {
			// 实际创建数据
			$info = $this->persistActualCreate($data, $forceFill);

			// 执行回调
			$callback && $info = $callback($info);

			// 触发事件
			return $this->emitWritten($data, $info, CURDWriteScene::CREATE);
		};

		// 是否使用事务创建数据
		$useTransaction = $useTransaction === null ? $this->useTransaction : $useTransaction;
		if ($useTransaction) {
			/** @var Model $info */
			$info = Db::transaction($handler);
		} else {
			$info = $handler();
		}

		// 更新缓存
		if ($this->isNeedRefreshCache($shouldRefreshCache)) {
			$this->updateCache($info);
		}

		return $info;
	}

	/**
	 * 创建数据
	 * @param array $data
	 * @param bool $forceFill
	 * @param bool|null $shouldRefreshCache
	 * @param bool|null $useTransaction
	 * @return TModel
	 */
	public function create(array $data, bool $forceFill = false, bool $shouldRefreshCache = null, bool $useTransaction = null)
	{
		return $this->createOn($data, null, $forceFill, $shouldRefreshCache, $useTransaction);
	}

	/**
	 * 实际的更新数据
	 * @param Model $info
	 * @param array $data
	 * @param bool $forceFill
	 * @return TModel
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	private function persistActualUpdate($info, array $data, bool $forceFill = false)
	{
		/** @var Model $model */
		$model = $info->getModel();
		if (!($model instanceof Model)) {
			throw new \LogicException(static::class . 'use Model must be instance of Model');
		}

		// 获取允许的字段
		$allowFields = $this->getAllowedFields($model, $forceFill);

		// 更新数据
		if ($model->allowField($allowFields)->save($data) === false) {
			throw new \LogicException("更新失败！");
		}

		return $info;
	}

	/**
	 * 为模型更新数据
	 * @param TModel $info
	 * @param array $data
	 * @param callable|null $callback
	 * @param bool $forceFill
	 * @param bool|null $shouldRefreshCache
	 * @param bool|null $useTransaction
	 * @return TModel
	 */
	protected function updateOn($info, array $data, callable $callback = null, bool $forceFill = false, bool $shouldRefreshCache = null, bool $useTransaction = null)
	{
		// 触发事件
		$data = $this->emitWriting($data, $info, CURDWriteScene::UPDATE);

		// 更新数据处理器
		$handler = function () use (&$info, &$forceFill, &$data, &$callback) {
			$info = $this->persistActualUpdate($info, $data, $forceFill);

			// 执行回调
			$callback && $info = $callback($info);

			// 触发事件
			return $this->emitWritten($data, $info, CURDWriteScene::UPDATE);
		};

		// 是否使用事务更新数据
		$useTransaction = $useTransaction === null ? $this->useTransaction : $useTransaction;
		if ($useTransaction) {
			/** @var Model $info */
			$info = Db::transaction($handler);
		} else {
			$info = $handler();
		}

		// 更新缓存
		if ($this->isNeedUpdateCache($shouldRefreshCache)) {
			$this->updateCache($info);
		}

		return $info;
	}

	/**
	 * 更新数据
	 * @param int|string|TModel $id
	 * @param array $data
	 * @param bool $forceFill
	 * @param bool|null $shouldRefreshCache
	 * @param bool|null $useTransaction
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws ModelNotFoundException
	 */
	public function update($id, array $data, bool $forceFill = false, bool $shouldRefreshCache = null, bool $useTransaction = null)
	{
		/** @var Model $info */
		if ($id instanceof Model) {
			$modelClass = $this->newQuery();
			if (!($id instanceof $modelClass)) {
				throw new \LogicException(static::class . "::update use Model must be instance of {$modelClass}");
			}

			$info = $id;
		} else {
			$info = $this->query()->where('id', $id)->findOrFail();
		}

		return $this->updateOn($info, $data, null, $forceFill, $shouldRefreshCache, $useTransaction);
	}

	/**
	 * 增加字段值
	 * @param mixed $idOrWhere
	 * @param string $field
	 * @param mixed $amount
	 * @param array $extra
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function increment($idOrWhere, string $field, $amount = 1, array $extra = [])
	{
		$info = $this->findOrFail($idOrWhere);
		if ($info->inc($field, $amount)->allowField([])->save($extra) === false) {
			throw new \LogicException("更新失败！");
		}

		return $info;
	}

	/**
	 * 减少字段值
	 * @param mixed $idOrWhere
	 * @param string $field
	 * @param mixed $amount
	 * @param array $extra
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function decrement($idOrWhere, string $field, $amount = 1, array $extra = [])
	{
		$info = $this->findOrFail($idOrWhere);
		if ($info->dec($field, $amount)->allowField([])->save($extra) === false) {
			throw new \LogicException("更新失败！");
		}

		return $info;
	}

	/**
	 * 获取字段值
	 * @param mixed $idOrWhere
	 * @param string $column
	 * @param mixed $default
	 * @return mixed|null
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function value($idOrWhere, $column, $default = null)
	{
		$value = $this->findQuery($idOrWhere)->value($column);
		return $value === null ? $default : $value;
	}

	/**
	 * 获取字段值
	 * @param string $queryField
	 * @param mixed $queryValue
	 * @param string $column
	 * @param mixed $default
	 * @return mixed|null
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function valueBy(string $queryField, $queryValue, $column, $default = null)
	{
		return $this->value(function (Query $query) use (&$queryField, &$queryValue) {
			$query->where($queryField, $queryValue);
		}, $column, $default);
	}

	/**
	 * 设置当前实例字段值
	 * @param $info
	 * @param string $field
	 * @param mixed $value
	 * @return TModel $info
	 */
	public function setValue($info, string $field, $value)
	{
		if (!$this->isAllowSetField($field)) {
			throw new ValidateException("{$field} not in allow field list.");
		}

		// 验证规则
		$allowSetFields = $this->getAllowSetFields();
		if (isset($allowSetFields[$field]) && ($validateRule = $allowSetFields[$field])) {
			$validator = new Validate();
			$validator->rule([
				$field => $value,
			])->failException(true)->check([
				$field => $validateRule,
			]);
		}

		return $info->save([$field => $value]);
	}

	/**
	 * 设置多条记录字段值
	 * @param array|callable $idsOrCallback
	 * @param string $field
	 * @param mixed $value
	 * @param callable|null $itemCallback
	 * @param callable|null $preventCallback
	 * @return bool
	 */
	public function setManyValue($idsOrCallback, string $field, $value, callable $itemCallback = null, $preventCallback = null)
	{
		return $this->query()->when(
			is_array($idsOrCallback),
			function (Query $query) use (&$idsOrCallback) {
				$query->whereIn('id', $idsOrCallback);
			},
			function (Query $query) use (&$idsOrCallback) {
				$query->where($idsOrCallback);
			}
		)->get()->each(function (Model $item) use (&$field, &$value, &$itemCallback, &$preventCallback) {
			if ($preventCallback && $preventCallback($item) === false) {
				return;
			}

			$flag = $this->setValue($item, $field, $value);

			$itemCallback && $itemCallback($flag);
		});
	}

	/**
	 * 是否允许设置字段
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isAllowSetField(string $field)
	{
		$allowSetFields = $this->getAllowSetFields();

		return in_array($field, array_map('strval', array_keys($allowSetFields)), true);
	}

	/**
	 * 获取允许修改字段
	 *
	 * @return array
	 */
	public function getAllowSetFields()
	{
		return [
			'status' => 'in:1,2',
		];
	}

	/**
	 * 删除数据
	 * @param callable $callback
	 * @param bool $isForce
	 * @param bool|null $shouldForgetCache
	 * @return Collection<TKey,TModel>
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	protected function deleteOn($callback, bool $isForce = false, bool $shouldForgetCache = null)
	{
		// 构建查询
		$query = $this->withTrashedQuery();

		// 触发删除前事件
		$this->emitDestroying([], $query, $isForce);

		// 获取结果
		$result = $callback($query);
		if ($result instanceof Query) {
			$result = $result->select();
		} elseif ($result === null) {
			$result = $query->select();
		}

		// 删除数据
		$result = $this->deleteForCollection(
			$result,
			$isForce,
			$shouldForgetCache
		);

		// 触发删除后事件
		$this->emitDestroyed([], $result, $isForce);

		return $result;
	}

	/**
	 * 删除数据
	 * @param array $ids
	 * @param bool $isForce
	 * @param bool|null $shouldForgetCache
	 * @return Collection<TKey,TModel>
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function delete(array $ids, bool $isForce = false, bool $shouldForgetCache = null)
	{
		return $this->deleteOn(function (Query $query) use (&$ids) {
			return $query->whereIn('id', $ids)->select();
		}, $isForce, $shouldForgetCache);
	}

	/**
	 * 批量删除数据
	 * @param Collection<TKey,TModel> $items
	 * @param bool $isForce
	 * @param bool|null $shouldForgetCache
	 * @return Collection<TKey,TModel>
	 */
	protected function deleteForCollection(Collection $items, bool $isForce = false, bool $shouldForgetCache = null)
	{
		return $items->each(function (Model $item) use ($isForce, $shouldForgetCache) {
			// 触发单个模型删除前事件
			$this->emitDeleting($item, $isForce);

			$item->clone()->force($isForce)->together($this->deleteTogethers)->delete();

			// 触发单个模型删除后事件
			$this->emitDeleted($item, $isForce);

			// 更新缓存
			if ($this->isNeedForgetCache($shouldForgetCache)) {
				$this->forgetCache($item->id);
			}

			return $item;
		});
	}

	/**
	 * 获取查询构造器
	 * @return Query|class-string<TModel>
	 */
	abstract protected function newQuery();

	/**
	 * 获取查询构造器
	 * @return Query
	 */
	protected function query()
	{
		$query = $this->newQuery();

		// 是否是 model class-string
		if (is_string($query)) {
			$query = new $query();
		}

		// 是否是 model instance
		if (!is_subclass_of($query, Model::class)) {
			throw new \InvalidArgumentException('Query must be a model class-string or instance.');
		}

		$query = $query->db();

		return tap($query, function ($query) {
			$this->emit('BuildingQuery', $query);
		});
	}

	/**
	 * 获取带软删除的查询构造器
	 * @return Query
	 */
	protected function withTrashedQuery()
	{
		$query = $this->query();

		// 获取带软删除的查询构造器
		if (method_exists($query, 'withTrashed')) {
			$query = $query->withTrashed();
		}

		// 触发构建带软删除的查询构造器事件
		$this->emit('BuildingWithTrashedQuery', $query);

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
		$query = $this->query();
		if (method_exists($query->getModel(), 'simple') ||
			method_exists($query->getModel(), 'scopeSimple')) {
			$query->simple();
		}

		// 搜索条件
		if ($search instanceof Filter) {
			$filter = $search;
			$search = $search->search();

			$query->search($search);
			$filter->apply($query);
		} elseif (is_callable($search)) {
			$search($query);
		} else {
			$query->search($search);
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
		return tap($this->withTrashedQuery(), function (Query $query) {
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

	/**
	 * 设置是否使用事务
	 * @param bool $useTransaction
	 * @return $this
	 */
	public function useTransaction(bool $useTransaction = true)
	{
		$this->useTransaction = $useTransaction;

		return $this;
	}
}
