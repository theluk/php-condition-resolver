<?php

namespace theluk;
/**
 * A condition resolver, that can uses some data array and a condition array
 * to determine, if the data array matches the conditions.
 *
 * a typical single condition looks like
 *
 *  array(
 *      "cmp" => "==",
 *      "left" => "value1",
 *      "right" => "value2"
 *  )
 *
 * as "left" and "right" keys you can use a path syntax, which will try to fetch
 * the data from the main data object.
 *
 *  "left" => "My.Object.some_value"
 *  "right" => "something"
 *
 * $this->setConditions() method expects an array of many conditions arrays
 *
 *  array(
 *      array( "cmp" => ... ),
 *      array( "cmp" => ... )
 *  )
 *
 * you can also use two operators "or" and "and".
 *
 * array(
 *     array("or" => array(
 *         array( "cmp" => ... ),
 *         array( "cmp" => ... )
 *     ))
 * ) 
 *
 * the operators can also be nested, the only important thing is that an
 * operator syntax expects that the array must contain a single key, which is
 * "or" or "and" and the value is again a list of conditions.
 *
 * another example that should work
 *
 *  array(
 *      "or" => array(
 *          array(
 *              "and" => array(
 *                  array( condition )
 *                  array( condition )
 *                  array(
 *                      "or" => array(
 *                          array ( ... )
 *                      )
 *                  )
 *              )
 *          )
 *      )
 *  )
 */
class ConditionResolver {

    const
        /**
         * Value is equal.
         */
        COND_CMP_EQUAL = '==',
        /**
         * Value isn't equal.
         */
        COND_CMP_NOT_EQUAL = '!=',
        /**
         * Value is equal.
         */
        COND_CMP_GREATER = '>',
        /**
         * Value isn't equal.
         */
        COND_CMP_LESS = '<',
        /**
         * Value is equal.
         */
        COND_CMP_GREATER_OR_EQUAL = '>=',
        /**
         * Value isn't equal.
         */
        COND_CMP_LESS_OR_EQUAL = '<=',
        /**
         * Contains string
         */
        COND_CMP_CONTAINS = 'contains',
        /**
         * value begins with another string
         */
        COND_CMP_STARTSWITH = 'startswith',
        /**
         * value string endswith another string
         */
        COND_CMP_ENDSWITH = 'endswith',

        /**
         * value is null
         */
        COND_CMP_NULL = 'null',
        /**
         * value is not null
         */
        COND_CMP_NNULL = 'nnull',
        /**
         * value is in a list
         */
        COND_CMP_IN = 'in',
        /**
         * value is not in a list
         */
        COND_CMP_NOT_IN = 'nin',

        /**
         * values matches a regular expression
         */
        COND_CMP_MATCH = 'match',

        /**
         * left has at least all the same values as in right (intersection)
         * expects arrays on both sides
         */
        COND_CMP_ANY = 'any',

        /**
         * left has at least one value specified in right
         * expects array on both sides
         */
        COND_CMP_ALL = 'all',

        /**
         * Value is equal.
         */
        COND_OP_AND = 'and',
        /**
         * Value isn't equal.
         */
        COND_OP_OR = 'or';


    protected $data;
    protected $conditions;

    public function __construct($conditions = null, $data = null) {
        if (!is_null($conditions))
            $this->setConditions($conditions);
        if (!is_null($data))
            $this->setData($conditions);
    }

    public function getComparators() {
        return array(
            self::COND_CMP_EQUAL, 
            self::COND_CMP_NOT_EQUAL, 
            self::COND_CMP_GREATER, 
            self::COND_CMP_LESS, 
            self::COND_CMP_GREATER_OR_EQUAL, 
            self::COND_CMP_LESS_OR_EQUAL, 
            self::COND_CMP_CONTAINS, 
            self::COND_CMP_STARTSWITH, 
            self::COND_CMP_ENDSWITH,
            self::COND_CMP_NULL,
            self::COND_CMP_NNULL, 
            self::COND_CMP_MATCH, 
            self::COND_CMP_IN, 
            self::COND_CMP_NOT_IN, 
            self::COND_OP_AND, 
            self::COND_OP_OR,
            self::COND_CMP_ANY,
            self::COND_CMP_ALL
        );
    }

    /**
     * Main api method, that takes the main conditions and main data and
     * checks it against each other
     * @return boolean
     */
    public function resolve() {
        $conditions = $this->getConditions();
        if (($op = $this->_getOperation($conditions))) {
            return $this->_resolveInternal($conditions[$op], $op);
        }
        return $this->_resolveInternal($this->getConditions());
    }

    /**
     * Checks if a condition match against internal dataset
     * @param  array  $condition
     * @return boolean
     */
    public function isMatch($condition) {

        if (is_string($condition) && ($condition = $this->parseCondition($condition)) === false)
            return false;

        $result = $this->getComparison(
            $condition["cmp"], 
            $this->extractValue($condition["left"]),
            $this->extractValue(@$condition["right"])
        );

        return $result;
    }

    /** 
     * If path used and found in the dataset, it will return the value, otherwise
     * it will return what you put in
     * @param  string $pathOrValue
     * @return mixed            
     */
    public function extractValue($pathOrValue) {
        list($found, $value) = $this->extract($pathOrValue, $this->data);
        if ($found) return $value;
        return $pathOrValue;
    }

    /**
     * Uses dot path syntax to extract some values from an array
     * @param  string $path a dot separated path
     * @param  array $data the array where to extract the path from
     * @return an array with two indexes, list($found, $value)
     *                  so, $found tells you if the path was found.
     *                  this is usefull, when the value is null, and
     *                  you do not know if the path was not found or
     *                  if the value is actually null...
     */
    private function extract($path, $data) {

        if (is_array($path)) return array(false, null);

        if (strpos($path, ".") === false) {
            if (array_key_exists($path, $data)) {
                return array(true, $data[$path]);
            } else {
                return array(false, null);
            }
        }
        foreach(explode(".", $path) as $i => $key) {
            if (is_numeric($key) && intval($key) > 0 || $key === '0') {
                if (array_key_exists(intval($key), $data)) {
                    $data = $data[intval($key)];
                } else {
                    return array(false, null);
                }
            } else {
                if (array_key_exists($key, $data)) {
                    $data = $data[$key];
                } else {
                    return array(false, null);
                }
            }
        }
        return array(true, $data);
    }

    /**
     * Compare.
     *
     * @param string $cmp Comparison operator.
     * @param mixed  $x   X value.
     * @param mixed  $y   Y value.
     *
     * @return bool
     */
    public function getComparison($cmp, $x, $y)
    {
        switch ($cmp) {
            case self::COND_CMP_EQUAL:
                return (is_string($x) ? strtolower($x) : $x) == (is_string($y) ? strtolower($y) : $y);
            case self::COND_CMP_GREATER:
                return $x > $y;
            case self::COND_CMP_GREATER_OR_EQUAL:
                return $x >= $y;
            case self::COND_CMP_LESS:
                return $x < $y;
            case self::COND_CMP_LESS_OR_EQUAL:
                return $x <= $y;
            case self::COND_CMP_NOT_EQUAL:
                return $x != $y;
            case self::COND_CMP_CONTAINS:
                return stripos($x, $y) !== false;
            case self::COND_CMP_STARTSWITH:
                return $y === "" || strripos($x, $y, -strlen($x)) !== FALSE;
            case self::COND_CMP_ENDSWITH:
                return $y === "" || (($temp = strlen($x) - strlen($y)) >= 0 && stripos($x, $y, $temp) !== FALSE);
            case self::COND_CMP_IN:
                return is_array($y) && in_array($x, $y);
            case self::COND_CMP_NOT_IN:
                return !is_array($y) || !in_array($x, $y);
            case self::COND_CMP_MATCH:
                return (bool) preg_match($y, $x);
            case self::COND_CMP_NULL:
                return is_null($x) || empty($x);
            case self::COND_CMP_NNULL :
                return !(is_null($x) || empty($x));
            case self::COND_CMP_ALL:
                return count(array_intersect((array) $x, (array) $y)) == count($y);
            case self::COND_CMP_ANY:
                return count(array_intersect((array) $x, (array) $y)) > 0;
        }
        return true;
    }

    /**
     * parses a string and transforms it into a single condition, if possible
     *
     *  from
     *
     *      test.foo.bar == something
     *
     *  to
     *
     *      array(
     *          "cmp" => self::COND_CMP_EQUAL,
     *          "left" => "test.foo.bar",
     *          "right" => "something"
     *      )
     * 
     */
    public function parseCondition($input) {
        $comparators = $this->getComparators();
        foreach($comparators as $cmp) {
            $token = (" ".$cmp." ");
            if (strpos($input, $token)) {
                list($left, $right) = explode($token, $input);
                return compact(array("cmp", "left", "right"));
            }
        }
        return false;
    }

    /**
     * resolves conditions agains the data set
     * @param  array $conditions
     * @param  string $op         
     * @return boolean returns true, if all conditions do match agains the data set
     */
    private function _resolveInternal($conditions, $op = self::COND_OP_AND) {
        $self = $this;
        return array_reduce($conditions, function($carry, $condition) use ($op, $self) {
            $currentResult = true;
            if (($key = $this->_getOperation($condition))) {
                $currentResult = $self->_resolveInternal($condition[$key], $key);
            } else {
                $currentResult = $self->isMatch($condition);
            }
            return $self->getOperator($op, $carry, $currentResult);
        }, $op === self::COND_OP_AND);
    }

    /**
     * Checks if a condition have the operator syntax, which means that it returns true,
     * when only a single key is set in the array and the key is either "or" or "and"
     * @param  array $condition
     * @return boolean
     */
    private function _getOperation($condition) {
        if (!is_string($condition) && count($key = array_keys($condition)) === 1 && in_array(($key = strtolower($key[0])), array(self::COND_OP_AND, self::COND_OP_OR)))
            return $key;
        else return false;
    }

    /**
     * Get operator.
     *
     * @param string $op Operator.
     * @param mixed  $x  X value.
     * @param mixed  $y  Y value.
     *
     * @return bool
     */
    public function getOperator($op, $x, $y)
    {
        switch ($op) {
            case self::COND_OP_AND:
                return $x && $y;
            case self::COND_OP_OR:
                return $x || $y;
        }
        return true;
    }


    /**
     * Gets the value of data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the value of data.
     *
     * @param mixed $data the data
     *
     * @return self
     */
    function setData($data)
    {

        if (is_null($data)) {
            $this->data = $data;
            return $this;
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (!is_array($data)) {
            throw new \InvalidArgumentException("ConditionResolver: argument data could not be converted into an object array");
        }
        $this->data = $data;
        return $this;
    }

    /**
     * Gets the value of conditions.
     *
     * @return mixed
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Sets the value of conditions.
     *
     * @param mixed $conditions the conditions
     *
     * @return self
     */
    function setConditions($conditions)
    {
        $this->conditions = $conditions;

        return $this;
    }
} 
