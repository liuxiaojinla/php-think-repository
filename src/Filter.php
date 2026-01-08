<?php

namespace Xin\ThinkPHP\Repository;

use BadMethodCallException;
use Closure;
use think\db\Query;
use think\helper\Str;
use Xin\Support\Arr;
use Xin\Support\Time;
use Xin\ThinkPHP\Repository\Contracts\Filter as FilterContract;

class Filter implements FilterContract
{
	/**
	 * The registered string macros.
	 *
	 * @var array
	 */
	protected $macros = [];

	/**
	 * 输入数据
	 * @var array
	 */
	protected $input = [];

	/**
	 * 配置
	 * @var array
	 */
	protected $options = [];

	/**
	 * 搜索字段
	 * 格式：['field' => ['operator', condition_config, db_mapping_name]]
	 * 示例：['title' => 'like', 'name', 'status' => ['enum', ['0', '1']]]
	 * operator: like, notLike, leftLike, rightLike, between, in, notIn, enum, notEnum
	 * @var array
	 */
	protected $searchFields = [];

	/**
	 * 排序字段
	 * 示例：['id' => 'desc', 'name' => 'asc']
	 * @var array
	 */
	protected $sortFields = [];

	/**
	 * Filter 构造函数
	 * @param array $input
	 * @param array $searchFields
	 * @param array $sortFields
	 * @param array $options
	 */
	public function __construct(array $input = [], array $searchFields = [], array $sortFields = [], array $options = [])
	{
		$this->input = $input;
		$this->searchFields = $searchFields;
		$this->sortFields = $sortFields;
		$this->options = $options;

		$this->initialize();
	}

	/**
	 * 初始化
	 * @return void
	 */
	protected function initialize()
	{
	}

	/**
	 * 获取要搜索的字段列表
	 * @return array
	 */
	public function getSearchFields()
	{
		return $this->searchFields;
	}

	/**
	 * 定义搜索字段
	 * @param string $fieldName
	 * @param string $operator
	 * @param array $fieldConfig
	 * @param string|null $mappingName
	 * @return void
	 */
	protected function defineSearchField(string $fieldName, string $operator = 'eq', array $fieldConfig = [], string $mappingName = null)
	{
		$this->searchFields[$fieldName] = [$operator, $fieldConfig, $mappingName];
	}

	/**
	 * 获取排序字段
	 * @return array
	 */
	public function getSortFields()
	{
		return $this->sortFields;
	}

	/**
	 * 获取输入数据
	 * @param string|null $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getInput(string $key = null, $default = null)
	{
		return Arr::get($this->input, $key, $default);
	}

	/**
	 * @inheritDoc
	 */
	public function apply(Query $query, array $options = [])
	{
		$options = $options + $this->options;

		// 验证参数
		$this->validate($options);

		// 应用搜索字段
		$this->applySearch($query, $options);

		// 应用排序字段
		$this->applySort($query, $options);

		// 自定义
		$this->customize($query, $options);
	}

	/**
	 * 验证参数
	 * @param array $options
	 * @return void
	 * @todo 验证字段
	 */
	protected function validate(array $options = [])
	{
	}

	/**
	 * 应用搜索字段
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	protected function applySearch(Query $query, array $options = [])
	{
		$searchFields = $this->getSearchFields();
		$keywordParameter = $this->getSearchKeywordParameter();

		// 如未设置搜索字段，添加默认搜索字段
		if ($keywordParameter && empty($searchFields[$keywordParameter]) && $this->getSearchKeywordFields()) {
			$searchFields[$this->getSearchKeywordParameter()] = ['like', [], null];
		}

		static::withSearch($query, $searchFields, $this->getInput(), true, $options, $this);
	}

	/**
	 * 应用排序字段
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	protected function applySort(Query $query, array $options = [])
	{
		static::withSort($query, $this->getSortFields(), $this->getInput(), $options, $this);
	}

	/**
	 * 自定义查询
	 * @param Query $query
	 * @param array $options
	 * @return void
	 */
	protected function customize(Query $query, array $options = [])
	{
	}

	/**
	 * 标题搜索器
	 * @param Query $query
	 * @param string $value
	 * @return void
	 */
	public function searchKeywordsAttr(Query $query, $value)
	{
		$value = is_array($value) ? array_map(function ($item) {
			return '%' . $item . '%';
		}, $value) : '%' . $value . '%';

		$keywordsFields = static::getSearchKeywordFields();
		$field = is_array($keywordsFields) ? implode('|', static::getSearchKeywordFields()) : $keywordsFields;

		$query->where($field, 'like', $value);
	}

	/**
	 * 获取关键字搜索字段
	 * @return string[]
	 */
	public function getSearchKeywordFields()
	{
		return $this->getOption('search_keywords_fields', []);
	}

	/**
	 * 获取关键字搜索参数
	 * @return string
	 */
	public function getSearchKeywordParameter()
	{
		return $this->getOption('search_keywords_parameter', "keywords");
	}

	/**
	 * 注册时间搜索器
	 * @param Query $query
	 * @param string $value
	 * @return void
	 */
	public function searchCreateTimeAttr(Query $query, $value)
	{
		$value = Time::parseRange($value);
		$query->whereBetweenTime('create_time', $value[0], $value[1]);
	}

	/**
	 * 获取配置
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption(string $key, $default = null)
	{
		return Arr::get($this->options, $key, $default);
	}

	/**
	 * 设置配置
	 * @param string $key
	 * @param mixed $value
	 */
	public function setOption(string $key, $value)
	{
		Arr::set($this->options, $key, $value);
	}

	/**
	 * 设置配置
	 * @param array $options
	 * @param bool $merge
	 */
	public function setOptions(array $options, bool $merge = true)
	{
		$this->options = $merge ? array_replace_recursive($this->options, $options) : $options;
	}

	/**
	 * Checks if macro is registered.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasMacro(string $name)
	{
		return isset($this->macro[$name]);
	}

	/**
	 * Register a custom macro.
	 *
	 * @param string $name
	 * @param object|callable $macro
	 * @return void
	 */
	public function macro(string $name, $macro)
	{
		$this->macro[$name] = $macro;
	}

	/**
	 * @inerhitDoc
	 */
	public static function make(array $input)
	{
		return new static($input);
	}

	/**
	 * 返回搜索的字段及规则
	 * 输入：['title' => 'like', 'name', 'status' => ['enum', ['0', '1']]]
	 * 输出：['title' => ['like'], 'name' => ['eq'], 'status' => ['enum', ['0', '1']], 'price' => ['between']]]
	 * @return array
	 */
	protected static function resolveSearchFields(array $search)
	{
		$result = [];
		foreach ($search as $fieldName => $fieldConfig) {
			if (is_numeric($fieldName)) {
				$result[$fieldConfig] = ['eq', [], null];
			} else {
				$result[$fieldName] = is_array($fieldConfig) ? $fieldConfig : [$fieldConfig, [], null];
			}
		}
		return $result;
	}

	/**
	 * 解析表达式
	 * @param string $operator
	 * @param mixed $condition
	 * @param array $fieldConfig
	 * @return array
	 */
	protected static function resolveExpression(string $operator, $condition, $fieldConfig)
	{
		if (in_array($operator, ['like', 'notLike'])) {
			$condition = '%' . $condition . '%';
			$operator = 'like' == $operator ? 'like' : 'not like';
		} elseif (in_array($operator, ['leftLike', 'likeLeft', 'notLeftLike', 'notLikeLeft'])) {
			$condition = '%' . $condition;
			$operator = in_array($operator, ['leftLike', 'ikeLeft']) ? 'like' : 'not like';
		} elseif (in_array($operator, ['rightLike', 'likeRight', 'notRightLike', 'notLikeRight'])) {
			$condition = $condition . '%';
			$operator = in_array($operator, ['rightLike', 'likeRight']) ? 'like' : 'not like';
		} elseif (in_array($operator, ['between', 'notBetween'])) {
			$condition = is_string($condition) ? explode(',', $condition, 2) : $condition;
			$operator = 'between' == $operator ? 'between' : 'not between';
		} elseif (in_array($operator, ['in', 'notIn'])) {
			$condition = is_string($condition) ? explode(',', $condition) : $condition;
			$operator = 'in' == $operator ? 'in' : 'not in';
		} elseif (in_array($operator, ['enum', 'notEnum'])) {
			$conditionConfig = $fieldConfig[1];
			// 判断枚举值
			if (!in_array($condition, $conditionConfig)) {
				return [null, null];
			}
			$operator = 'enum' == $operator ? '=' : '<>';
		} elseif (in_array($operator, ['not', 'notEqual'])) {
			$operator = '<>';
		} elseif (in_array($operator, ['equal', 'eq'])) {
			$operator = '=';
		}

		return [$operator, $condition];
	}

	/**
	 * 搜索字段
	 * @param Query $query
	 * @param array $fields
	 * @param array $data
	 * @param bool $strict
	 * @param array $options
	 * @param mixed $targetInstance
	 */
	public static function withSearch(Query $query, array $fields, array $data = [], bool $strict = false, array $options = [], $targetInstance = null)
	{
		$fields = static::resolveSearchFields($fields);

		foreach ($fields as $fieldName => $fieldConfig) {
			// 判断字段是否存在
			if (!isset($data[$fieldName])) {
				continue;
			}

			// 获取筛选条件值
			$condition = $data[$fieldName];

			// 判断是否是闭包
			if ($fieldConfig instanceof Closure) {
				$fieldConfig($targetInstance, $condition, $data);
			} else {
				// 如果严格模式，忽略空值
				if ($strict && (empty($condition) && !in_array($condition, ['0', 0]))) {
					continue;
				}

				$fieldName = $fieldConfig[2] ?: $fieldName;
				$method = 'search' . Str::studly($fieldName) . 'Attr';
				$methodAlias = 'withSearch' . Str::studly($fieldName);
				if (method_exists($targetInstance, $method) ||
					(method_exists($targetInstance, 'hasMacro') && $targetInstance->hasMacro($method))) {
					$targetInstance->$method($query, $condition, $data);
				} elseif (method_exists($targetInstance, $methodAlias) ||
					(method_exists($targetInstance, 'hasMacro') && $targetInstance->hasMacro($methodAlias))) {
					$targetInstance->$methodAlias($query, $condition, $data);
				} else { // 默认搜索规则
					$operator = $fieldConfig[0];
					[$operator, $condition] = static::resolveExpression($operator, $condition, $fieldConfig);
					if (is_null($operator)) {
						continue;
					}

					$query->where($fieldName, $operator, $condition);
				}
			}
		}
	}

	/**
	 * 排序字段
	 * @param Query $query
	 * @param array $orders
	 * @param array $data
	 * @param array $options
	 * @param mixed $targetInstance
	 */
	public static function withSort(Query $query, array $orders, array $data = [], array $options = [], $targetInstance = null)
	{
	}

	/**
	 * 动态方法支持
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call(string $method, array $arguments)
	{
		if ($this->hasMacro($method)) {
			return call_user_func_array($this->macro[$method], $arguments);
		}

		throw new BadMethodCallException(sprintf(
			'Method %s::%s does not exist.', static::class, $method
		));
	}
}
