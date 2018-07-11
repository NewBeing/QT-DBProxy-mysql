<?php
/**
 * ORM核心实现类
 * 注：ArrayAccess 使对象可以像数组一样被访问
 *     JsonSerializable 序列化物体（Object）成能被 json_encode()原生地序列化的值
 */
abstract class Tc_Orm_ActiveRecord implements ArrayAccess, JsonSerializable
{
    //插入、更新、删除前操作
    const EVENT_BEFORE_INSERT = 'beforeInsert';
    const EVENT_BEFORE_UPDATE = 'beforeUpdate';
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    
    //插入、更新、删除后操作
    const EVENT_AFTER_INSERT  = 'afterInsert';
    const EVENT_AFTER_UPDATE  = 'afterUpdate';
    const EVENT_AFTER_DELETE  = 'afterDelete';

    public static $tableName         = '';
    public static $dbName            = '';
    public static $clusterName       = '';//线上库配置
    public static $rdviewClusterName = '';//线下从库配置
    public static $encodeColumns     = [];

    protected $_fields      = [];//从schemas获取所有表字段并初始化
    protected $_dirtyFields = [];//
    protected $_events      = [];//

    public function __construct()
    {
        $this->_fields = array_fill_keys(array_keys(static::getColumnsDefine()), null);
    }

    /**
     * 获取Schemas配置信息
     * @return  array  Schema配置信息
     */
    public static function getTableSchema()
    {
        $schema = Tc_Orm_TableSchema::load(static::$dbName, static::$tableName);
        return $schema;
    }

    /**
     * 获取表名
     * @return  string  表名
     */
    public static function getTableName()
    {
        return static::$tableName;
    }

    /**
     * 获取数据库簇名
     * @return  string  cluster名称
     */
    public static function getClusterName()
    {
        return static::$clusterName;
    }

    /**
     * 获取Schema表结构中字段定义
     * @return  array  字段定义
     */
    public static function getColumnsDefine()
    {
        return Tc_Orm_TableSchema::getColumnsDefine(static::$dbName, static::$tableName);
    }

    /**
     * 获取Schemas表结构中主键
     * @return  string  主键名
     */
    public static function getPrimaryKey()
    {
        return Tc_Orm_TableSchema::getPrimaryKey(static::$dbName, static::$tableName);
    }

    /**
     * 获取Schemas表结构中唯一键
     * @return  array  唯一键
     */
    public static function getUniqueKeys()
    {
        return Tc_Orm_TableSchema::getUniqueKeys(static::$dbName, static::$tableName);
    }

    /**
     * 判断Schema表结构中是否包含自增主键
     * @return  boolean
     */
    public static function isAutoIncrement()
    {
        return Tc_Orm_TableSchema::isAutoIncrement(static::$dbName, static::$tableName);
    }

    /**
     * 获取Tc_Orm_Connection对象
     * @return object
     */
    public static function getConnection()
    {
        return Tc_Orm_Connection::getConnection(static::getClusterName());
    }

    public static function findBySql($sql, $asArray = false)
    {
        return static::find()->setSql($sql)->asArray($asArray)->all();
    }

    public static function find($cond = [])
    {
        return static::createQuery()->where($cond);
    }

    /**
     * 创建Tc_Orm_Query对象
     */
    public static function createQuery()
    {
        return static::getConnection()->createQuery(static::getTableName(), get_called_class());
    }

    /**
     * 将json_encode字段转为数组
     */
    public function toArray()
    {
        $result = $this->_fields;
        foreach (static::$encodeColumns as $column) {
            $result[$column] = $this->$column;
        }
        return $result;
    }

    /**
     * 批量更新
     * @param  array  $fields    更新字段数组
     * @param  array  $condition 更新条件数组
     * @return 影响行数 | false
     */
    public static function updateAll(array $fields, $condition)
    {
        return static::find($condition)->update($fields);
    }

    /**
     * 批量删除
     * @param  array  $condition   更新条件数组
     * @return 影响行数 | false
     */
    public static function deleteAll($condition)
    {
        return static::find($condition)->delete();
    }

    /**
     * 处理结果集(array -> orm对象)
     * @param  array  $row  需处理的结果集
     * @return object 
     */
    public static function populateRecord($row)
    {
        $model         = new static();
        $columnsDefine = static::getColumnsDefine();
        foreach ($row as $name => $value) {
            if (isset($columnsDefine[$name])) {
                $model->_fields[$name] = Tc_Orm_ColumnType::cast($columnsDefine[$name]['type'], $value);
            }
        }
        $model->handleDecodeColumns();
        return $model;
    }

    /**
     * 获取本次会话新增记录操作ID
     * @return  int  本次会话新增记录操作ID
     */
    public static function getLastInsertID()
    {
        return static::getConnection()->lastInsertID();
    }

    /**
     * 用默认值回填没有赋值字段
     */
    public function setDefaultValue()
    {
        foreach (static::getColumnsDefine() as $column => $define) {
            if (!is_null($define['default']) && is_null($this->_fields[$column])) {
                $this->_fields[$column] = ($define['default'] === 'CURRENT_TIMESTAMP') ? date('Y-m-d H:i:s') : $define['default'];
            }
        }
        return $this;
    }

    /**
     * 插入一条记录
     * @param  array   $row                     插入字段数组
     * @param  boolean $ignore                  插入过程是否忽略报错
     * @param  array   $onDuplicateUpdateFields 插入冲突更新字段数组
     * @return mixed   影响行数 | false
     */
    public static function insert($row, $ignore = false, $onDuplicateUpdateFields = [])
    {
        if (!$row) {
            return 0;
        }
        return static::createQuery()->insert($row, $ignore, $onDuplicateUpdateFields);
    }

    /**
     * 插入批量记录
     * @param  array   $rows                    插入字段数组
     * @param  boolean $ignore                  插入过程是否忽略报错
     * @param  array   $onDuplicateUpdateFields 插入冲突更新字段数组
     * @return mixed   影响行数 | false
     */
    public static function batchInsert(array $rows, $ignore = false, $onDuplicateUpdateFields = [])
    {
        if (!$rows) {
            return 0;
        }
        return static::createQuery()->batchInsert($rows, $ignore, $onDuplicateUpdateFields);
    }

    /**
     * 与setField方法类似,处理数组字段
     */
    protected function handleEncodeColumns()
    {
        foreach (static::$encodeColumns as $column) {
            $encodeValue = json_encode($this->$column);
            if ($encodeValue === $this->_fields[$column]) {
                continue;
            }
            if ($this->_fields[$column]) {
                $decodeValue = json_decode($this->_fields[$column], true);
                if (is_array($decodeValue) && $this->$column == $decodeValue) {
                    continue;
                }
            }
            $this->_dirtyFields[$column] = $this->_fields[$column];
            $this->_fields[$column]      = $encodeValue;
        }
    }

    /**
     * 处理需要json_encode字段数组
     */
    protected function handleDecodeColumns()
    {
        foreach (static::$encodeColumns as $column) {
            $this->$column = json_decode($this->_fields[$column], true);
        }
    }

    /**
     * 向数据库插入一条数据
     * @param  array   $fields                  插入字段、数值
     * @param  boolean $ignore                  是否忽略
     * @param  array   $onDuplicateUpdateFields 记录存在字段更新数组
     * @return boolean
     */
    public function create($fields = [], $ignore = false, $onDuplicateUpdateFields = [])
    {
        //插入前处理钩子
        if ($this->beforeInsert() === false) {
            return false;
        }
        foreach ($fields as $name => $value) {
            $this->setField($name, $value);
        }
        $values = $this->getNewFields();
        $result = static::insert($values, $ignore, $onDuplicateUpdateFields);
        if (!$result) {
            return $result;
        }

        $schema        = static::getTableSchema();
        $pk            = $schema['primaryKey'];
        $autoIncrement = (bool)$schema['autoIncrement'];
        if (!isset($this->_fields[$pk])) {
            if ($autoIncrement) {
                $valuePk = static::getLastInsertID();
                $this->_fields[$pk] = Tc_Orm_ColumnType::cast($schema['columns'][$pk]['type'], $valuePk);
            } else {
                throw new Exception(static::$tableName . 'primary key is not set', Tc_Error_Code::ORM_COMMON_ERROR);
            }
        }

        //用默认值回填没有赋值字段
        $this->setDefaultValue();
        
        //插入后处理钩子
        $this->afterInsert();
        
        //清空非默认值字段数组
        $this->resetDirtyFields();
        
        return $result;
    }

    /**
     * 返回一个orm对象或null
     * @param  array  $condition  查询条件数组
     * @param  array  $orderBy    排序数组
     * @param  string $lockOption 查询锁类型'r'或'w'
     * @return object
     */
    public static function findOne($condition, $orderBy = [], $lockOption = '')
    {
        $row = static::find($condition)->select(array_keys(static::getColumnsDefine()))->orderBy($orderBy)->setLockOption($lockOption)->one();
        return $row ? static::populateRecord($row) : null;
    }

    /**
     * 返回查询关联数组
     * @param  array  $columns   查询字段数组
     * @param  array  $condition 查询条件数组
     * @param  array  $orderBy   排序数组
     * @return array
     */
    public static function findRow($columns, $condition, $orderBy = [])
    {
        $rows = static::findRows($columns, $condition, $orderBy, 0, 1);
        return $rows ? $rows[0] : [];
    }

    /**
     * 返回orm对象数组
     * @param  array  $cond    查询条件数组
     * @param  array  $orderBy 排序字段数组
     * @param  int    $offset  起始行数
     * @param  int    $limit   限制行数
     * @return array
     */
    public static function findAll($cond, $orderBy = [], $offset = 0, $limit = null)
    {
        $rows = static::findRows(array_keys(static::getColumnsDefine()), $cond, $orderBy, $offset, $limit);
        return array_map(function ($row) {
            return forward_static_call([get_called_class(), 'populateRecord'], $row);
        }, $rows);
    }

    /**
     * find系列的返回迭代器版本(节省内存)
     * @param  array  $cond    查询条件数组
     * @param  array  $orderBy 排序字段数组
     * @param  int    $offset  起始行数
     * @param  int    $limit   限制行数
     * @return array
     */
    public static function yieldAll($cond, $orderBy = [], $offset = 0, $limit = null)
    {
        yield from static::find($cond)->orderBy($orderBy)->offset($offset)->limit($limit)->yieldObject();
    }

    /**
     * 返回关联数组
     * @param  array  $columns  查询字段数组
     * @param  array  $cond     查询条件数组
     * @param  array  $orderBy  排序字段数组
     * @param  int    $offset   起始行
     * @param  int    $limit    限制行数
     * @return array           
     */
    public static function findRows($columns, $cond, $orderBy = [], $offset = 0, $limit = null)
    {
        return static::find($cond)->orderBy($orderBy)->offset($offset)->limit($limit)->select($columns)->all();
    }

    /**
     * find系列的返回迭代器版本(节省内存)
     * @param  array  $columns  查询字段数组
     * @param  array  $cond     查询条件数组
     * @param  array  $orderBy  排序字段数组
     * @param  int    $offset   起始行
     * @param  int    $limit    限制行数
     * @return array           
     */
    public static function yieldRows($columns, $cond, $orderBy = [], $offset = 0, $limit = null)
    {
        yield from static::find($cond)->orderBy($orderBy)->offset($offset)->limit($limit)->select($columns)->yieldRow();
    }

    /**
     * 返回指定列数组
     * @param  string  $column  指定列
     * @param  array   $cond    查询条件数组
     * @param  array   $orderBy 排序条件数组
     * @param  int     $offset  起始行
     * @param  int     $limit   限制行数
     * @return array
     */
    public static function findColumn($column, $cond, $orderBy = [], $offset = 0, $limit = null)
    {
        return static::find($cond)->select([$column])->orderBy($orderBy)->offset($offset)->limit($limit)->column();
    }

    /**
     * find系列的返回迭代器版本(节省内存)
     * @param  string  $column  指定列
     * @param  array   $cond    查询条件数组
     * @param  array   $orderBy 排序条件数组
     * @param  int     $offset  起始行
     * @param  int     $limit   限制行数
     * @return array
     */
    public static function yieldColumn($column, $cond, $orderBy = [], $offset = 0, $limit = null)
    {
        yield from static::find($cond)->orderBy($orderBy)->offset($offset)->limit($limit)->select([$column])->yieldColumn();
    }

    /**
     * 查询指定列值
     * @param  string  $column  指定列
     * @param  array   $cond    查询条件数组
     * @param  array   $orderBy 排序数组
     * @return mixed
     */
    public static function findValue($column, $cond, $orderBy = [])
    {
        $row = static::find($cond)->select([$column])->orderBy($orderBy)->offset(0)->limit(1)->column();
        return $row ? reset($row) : null;
    }

    public static function count($cond, $column = '*')
    {
        return static::find($cond)->count($column);
    }

    public static function exists($cond)
    {
        return static::find($cond)->exists();
    }

    public function __get($name)
    {
        if (!array_key_exists($name, $this->_fields)) {
            throw new Tc_Error(Tc_Error_Code::ORM_COMMON_ERROR, get_called_class() . " does not have {$name} in table");
        }
        return $this->_fields[$name];
    }

    public function __set($name, $value)
    {
        $this->setField($name, $value);
    }

    public function __isset($name)
    {
        return isset($this->_fields[$name]);
    }

    /**
     * 设置字段数值(忽略与默认值相关字段)
     * @param  string  $name  字段名
     * @param  string  $value 字段值
     * @return object
     */
    protected function setField($name, $value)
    {
        if (!array_key_exists($name, $this->_fields)) {
            throw new Tc_Error(Tc_Error_Code::ORM_COMMON_ERROR, get_called_class() . " does not have {$name} in table");
        }
        if ($this->_fields[$name] === $value) {
            return $this;
        }
        $this->_fields[$name] = $value;
        if (!array_key_exists($name, $this->_dirtyFields)) {
            $this->_dirtyFields[$name] = $this->_fields[$name];
        }
        return $this;
    }

    /**
     * 获取字段取值非默认值字段并重新设置字段值
     * @return array
     */
    public function getNewFields()
    {
        $values = [];
        foreach ($this->_dirtyFields as $column => $value) {
            $values[$column] = $this->_fields[$column];
        }
        return $values;
    }

    /**
     * 返回非默认值字段数组
     * @return  array
     */
    public function getDirtyFields()
    {
        return $this->_dirtyFields;
    }

    /**
     * 清空非默认值字段数组
     * @return [type] [description]
     */
    public function resetDirtyFields()
    {
        $this->_dirtyFields = [];
        return $this;
    }

    /**
     * 更新一条记录，使用场景只能是先读后写或者插入成功影响行数=1后更新
     * 主键值和unique key的值会自动添加到更新条件中，不用在$cond中指定
     * @param  array  $fields  更新字段数组
     * @param  array  $cond    更新条件数组(可以不用指定)
     * @return 影响行数 | false
     */
    public function update(array $fields = [], $cond = [])
    {
        foreach ($fields as $name => $value) {
            $this->setField($name, $value);
        }
        $result = false;
        if ($this->beforeUpdate() === false) {
            return $result;
        }
        $values = $this->getNewFields();
        if (empty($values)) {
            return 0;
        }
        $pk          = static::getPrimaryKey();
        $dirtyFields = $this->getDirtyFields();
        $uniqueKeys  = static::getUniqueKeys();
        $oldPkValue  = !isset($dirtyFields[$pk]) ? $this->_fields[$pk] : $dirtyFields[$pk];
        if (is_array($cond)) {
            $cond[$pk] = $oldPkValue;
            foreach ($uniqueKeys as $key) {
                $cond[$key] = !isset($dirtyFields[$key]) ? $this->_fields[$key] : $dirtyFields[$key];
            }
        } else if ($cond instanceof Tc_Orm_Criteria) {
            $cond->andWhere($pk, $oldPkValue);
            foreach ($uniqueKeys as $key) {
                $oldValue = !isset($dirtyFields[$key]) ? $this->_fields[$key] : $dirtyFields[$key];
                $cond->andWhere($key, $oldValue);
            }
        }
        $result = static::updateAll($values, $cond);
        if ($result) {
            $this->afterUpdate();
        }
        $this->resetDirtyFields();
        return $result;
    }

    public static function __callStatic($name, $arguments)
    {
        if (preg_match('/FromRdview$/iu', $name)) {
            $realFuncName = preg_replace("/FromRdview$/iu", '', $name);
            return static::findWithRdview($realFuncName, $arguments);
        }
        throw new Tc_Error(Tc_Error_Code::ORM_COMMON_ERROR, get_called_class() . " NO METHOD " . $name);
    }

    protected static function findWithRdview($name, $arguments)
    {
        if (!static::$rdviewClusterName) {
            throw new Tc_Error(Tc_Error_Code::ORM_COMMON_ERROR, "no rdview cluster");
        }
        if (in_array($name, ['updateAll', 'deleteAll'])) {
            throw new Tc_Error(Tc_Error_Code::ORM_COMMON_ERROR, "rdview cannot write {$name}");
        }
        $calledClass = get_called_class();
        $oldClusterName = static::$clusterName;
        try {
            static::$clusterName = static::$rdviewClusterName;
            $result = call_user_func_array([$calledClass, $name], $arguments);
            return $result;
        } finally {
            static::$clusterName = $oldClusterName;
        }
    }

    /**
     * 删除一条记录，使用场景只能是先读后写或者插入成功影响行数=1后删除，$cond表示
     * 删除条件，主键值和unique key的值会自动添加到更新条件中，不用在$cond中指定
     * @param  array  $cond  可以不用指定
     * @return 影响行数 | false
     */
    public function delete($cond = [])
    {
        if ($this->beforeDelete() === false) {
            return false;
        }
        $pk = static::getPrimaryKey();
        $uniqueKeys = static::getUniqueKeys();
        if (is_array($cond)) {
            $cond[$pk] = $this->_fields[$pk];
            foreach ($uniqueKeys as $key) {
                $cond[$key] = $this->_fields[$key];
            }
        } else if ($cond instanceof Tc_Orm_Criteria) {
            $cond->andWhere($pk, $this->_fields[$pk]);
            foreach ($uniqueKeys as $key) {
                $cond->andWhere($key, $this->_fields[$key]);
            }
        }
        $result = static::deleteAll($cond);
        if ($result) {
            $this->afterDelete();
        }
        return $result;
    }

    public function beforeInsert()
    {
        $this->trigger(static::EVENT_BEFORE_INSERT);
        $this->handleEncodeColumns();
        return true;
    }

    public function afterInsert()
    {
        $this->trigger(static::EVENT_AFTER_INSERT);
    }

    public function beforeUpdate()
    {
        $this->trigger(static::EVENT_BEFORE_UPDATE);
        $this->handleEncodeColumns();
        return true;
    }

    public function afterUpdate()
    {
        $this->trigger(static::EVENT_AFTER_UPDATE);
    }

    public function beforeDelete()
    {
        $this->trigger(static::EVENT_BEFORE_DELETE);
        return true;
    }

    public function afterDelete()
    {
        $this->trigger(static::EVENT_AFTER_DELETE);
    }

    /**
     * 实现ArrayAccess中offsetExists方法
     * @param  string  $offset  变量值 
     * @return boolean  
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_fields);
    }

    /**
     * 实现ArrayAccess中offsetUnset方法
     * @param  string  $offset  变量值 
     * @return boolean  
     */
    public function offsetUnset($offset)
    {
        //todo ...
    }

    /**
     * 实现ArrayAccess中offsetGet方法
     * @param  string  $offset  变量值 
     * @return boolean  
     */
    public function offsetGet($offset)
    {
        if (in_array($offset, static::$encodeColumns)) {
            return $this->$offset;
        } else {
            return $this->_fields[$offset];
        }
    }

    /**
     * 实现ArrayAccess中offsetExists方法
     * @param  string  $offset  变量值 
     * @return boolean  
     */
    public function offsetSet($offset, $value)
    {
        if (in_array($offset, static::$encodeColumns)) {
            $this->$offset = $value;
        } else {
            $this->setField($offset, $value);
        }
    }

    public function rewind()
    {
        return reset($this->_fields);
    }

    public function current()
    {
        return current($this->_fields);
    }

    public function key()
    {
        return key($this->_fields);
    }

    public function next()
    {
        return next($this->_fields);
    }

    public function valid()
    {
        return key($this->_fields) !== null;
    }

    /**
     * JsonSerializable实现方法
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * 触发器触发操作
     * @param  array  $event  触发事件
     * @return 无返回值
     */
    public function trigger($event)
    {
        if (isset($this->_events[$event])) {
            foreach ($this->_events[$event] as $callback) {
                call_user_func_array($callback[0], $callback[1]);
            }
        }
    }

    /**
     * 自定义插入、更新、删除数据前，后的钩子 $event取值
     * @param  string   $event   钩子名称
     * @param  Callable $closure 钩子函数
     * @param  array    $args    函数参数
     */
    public function on($event, Callable $closure, $args)
    {
        if (isset($this->_events[$event])) {
            $this->_events[$event]->push([$closure, $args]);
        } else {
            $this->_events[$event] = new SplDoublyLinkedList();
            $this->_events[$event]->push([$closure, $args]);
        }
    }
}