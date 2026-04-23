<?php

namespace Xin\ThinkPHP\Repository;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Model;
use think\model\Collection;
use think\Paginator;

/**
 * 基于 CURD 封装「带 user_id」的快捷方法
 * 必须同时 use CURD
 * @mixin CURD
 * @template TModel of Model
 */
trait CURDWithUserId
{

	/**
	 * 根据 UserId 进行列表分页
	 * @param int $userId
	 * @param mixed $search
	 * @param array|null $with
	 * @param array $orders
	 * @param array $options
	 * @return Paginator
	 * @throws DbException
	 */
	public function paginateByUserId(
		int   $userId,
		mixed $search = [],
		array $with = null,
		mixed $orders = ['id desc'],
		array $options = []
	)
	{
		// 获取分页数据
		return $this->paginate(function (Query $query) use (&$userId, &$search) {
			$query->where($this->getUserIdKey(), $userId)->search($search);
		}, $with, $orders, $options);
	}

	/**
	 * 根据用户ID
	 * @param int $userId
	 * @param array $with
	 * @return TModel
	 * @throws DbException
	 * @throws DataNotFoundException
	 * @throws ModelNotFoundException
	 */
	public function findByUserId(int $userId, array $with = [])
	{
		return $this->findBy($this->getUserIdKey(), $userId, $with);
	}

	/**
	 * 根据用户ID查找最后一条数据
	 * @param int $userId
	 * @param array $with
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findLatestByUserId(int $userId, array $with = [])
	{
		return $this->findLatestBy($this->getUserIdKey(), $userId, $with);
	}

	/**
	 * 根据用户ID查找第1条数据
	 * @param int $userId
	 * @param array $with
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findOrFailByUserId(int $userId, array $with = [])
	{
		return $this->findOrFailBy($this->getUserIdKey(), $userId, $with);
	}

	/**
	 * 根据用户ID查找最后一条数据
	 * @param int $userId
	 * @param array $with
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findLatestOrFailByUserId(int $userId, array $with = [])
	{
		return $this->findLatestOrFailBy($this->getUserIdKey(), $userId, $with);
	}

	/**
	 * 根据id和userId查询数据
	 * @param int $id
	 * @param int $userId
	 * @param array $with
	 * @param array $columns
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function findOrFailWithUserId(
		int   $id,
		int   $userId,
		array $with = [],
		array $columns = ['*']
	)
	{
		return $this->findOrFail([
			'id' => $id,
			$this->getUserIdKey() => $userId,
		], $with, $columns);
	}

	/**
	 * 根据userId创建数据
	 * @param int $userId
	 * @param array $data
	 * @param bool $forceFill
	 * @param bool|null $shouldRefreshCache
	 * @return TModel
	 */
	public function createByUserId(
		int   $userId,
		array $data,
		bool  $forceFill = false,
		bool  $shouldRefreshCache = null
	)
	{
		$data[$this->getUserIdKey()] = $userId;
		return $this->create($data, $forceFill, $shouldRefreshCache);
	}

	/**
	 * 根据id和userId更新数据
	 * @param int $id
	 * @param int $userId
	 * @param array $data
	 * @param bool $forceFill
	 * @param bool|null $shouldRefreshCache
	 * @return TModel
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function updateWithUserId(
		int   $id,
		int   $userId,
		array $data,
		bool  $forceFill = false,
		bool  $shouldRefreshCache = null
	)
	{
		$info = $this->findOrFailWithUserId($id, $userId);

		$data[$this->getUserIdKey()] = $userId;
		return $this->updateOn($info, $data, null, $forceFill, $shouldRefreshCache);
	}

	/**
	 * 根据id和userId删除数据
	 * @param array $ids
	 * @param int $userId
	 * @param bool $isForce
	 * @param bool|null $shouldForgetCache
	 * @return Collection
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function deleteWithUserId(
		array $ids,
		int   $userId,
		bool  $isForce = false,
		bool  $shouldForgetCache = null
	)
	{
		return $this->deleteOn(function (Query $query) use (&$ids, &$userId) {
			return $query->whereIn('id', $ids)->where($this->getUserIdKey(), $userId);
		}, $isForce, $shouldForgetCache);
	}

	/**
	 * 根据用户ID清空数据
	 * @param int $userId
	 * @param bool $isForce
	 * @param bool|null $shouldForgetCache
	 * @return Collection
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function deleteByUserId(
		int  $userId,
		bool $isForce = false,
		bool $shouldForgetCache = null
	)
	{
		return $this->deleteOn(function (Query $query) use ($userId) {
			return $query->where($this->getUserIdKey(), $userId);
		}, $isForce, $shouldForgetCache);
	}

	/**
	 * 获取用户ID的Key
	 * @return string
	 */
	protected function getUserIdKey()
	{
		if (property_exists($this, 'userIdKey')) {
			return $this->userIdKey;
		}

		if (method_exists(static::class, 'getDefaultUserIdKey')) {
			return call_user_func([static::class, 'getDefaultUserIdKey']);
		}

		return 'user_id';
	}
}
