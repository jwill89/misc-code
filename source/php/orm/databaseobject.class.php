<?php

/**
 * Created by PhpStorm.
 * User: jwill
 * Date: 10/6/2017
 * Time: 11:19 AM
 */

/**
 * Class DatabaseObject
 * The basic class for all objects that are stored in the database.
 */
abstract class DatabaseObject
{

    // Abstract Methods Must be implemeneted by Child Classes
    
    abstract protected static function getPrimaryKeyName();

    abstract protected function getPrimaryKeyValue();

    abstract protected static function getTableName();
    
    abstract public static function filterPost();

    /**
     * DatabaseObject constructor. Takes array of properties and sets matching keys.
     * @param array|null $arr
     */
    public function __construct(array $arr = null)
    {
        // Is the Array Not Empty?
        if (!empty($arr) && is_array($arr)) {

            // Loop Through Array
            foreach ($arr as $key => $value) {

                // Does the Key Match a Property of the Class?
                if (property_exists(get_class($this), $key)) {

                    // Is the Value Set?
                    if (isset($value) && $value != "" && !is_array($value)) {

                        // Set the Property Value
                        $this->$key = $value;
                    }
                }
            }
        }
    }

    /**
     * Gets all properties of the object except the primary key and returns an array.
     * @return array
     */
    protected function getProperties(): array
    {

        // Return Array without Primary Key Property
        $class_props = get_class_vars($this);
        $properties = array_filter(get_object_vars($this), function ($key) use ($class_props) {
                return array_key_exists($key, $class_props);
        }, ARRAY_FILTER_USE_KEY);
        unset($properties[static::getPrimaryKeyName()]);

        return $properties;
    }

    /**
     * Takes an object and adds it to the database.
     * @return stdClass
     */
    final public function add()
    {

        // DB Connection
        $db = DB::getInstance();

        // Set the Return Value object
        $return_value = new stdClass;

        // Set default empty arrays
        $field_array = array();
        $param_array = array();

        // If property is not empty, then set to add it.
        foreach (static::getProperties() as $key => $value) {
            if (isset($value)) {
                $field_array[] = $key;
                $param_array[] = $value;
            }
        }

        // Setup the Query
        $sql = "INSERT INTO " . static::getTableName() . "  (" . implode(",", $field_array) . ") VALUES (" . implode(",", array_fill(0, count($field_array), "?")) . ")";
        $sth = $db->prepare($sql);

        // If successful, return true, if false, return false and error.
        if ($sth->execute($param_array)) {
            $return_value->success = true;
            $return_value->last_id = $db->lastInsertId();
        } else {
            $return_value->success = false;
            $return_value->errorInfo = implode(", ", $sth->errorInfo());
        }

        // Return the info
        return $return_value;

    }

    /**
     * Updates an object already in the database.
     * @return stdClass
     */
    final public function update()
    {

        // Use GLOBAL to access DB
        $db = DB::getInstance();

        // Set the Return Value object
        $return_value = new stdClass;

        // Set default empty arrays
        $field_array = array();
        $param_array = array();

        // If property is not empty, then set to update it.
        foreach (static::getProperties() as $key => $value) {
            if (isset($value)) {
                $field_array[] = $key . " = ?";
                $param_array[] = $value;
            }
        }

        // Setup the Query
        $sql = "UPDATE " . static::getTableName() . " SET " . implode(",", $field_array) . " WHERE " . static::getPrimaryKeyName() . " = " . $this->getPrimaryKeyValue();
        $sth = $db->prepare($sql);

        // If successful, return true, if false, return false and error.
        if ($sth->execute($param_array)) {
            $return_value->success = true;
        } else {
            $return_value->success = false;
            $return_value->errorInfo = implode(", ", $sth->errorInfo());
        }

        // Return the info
        return $return_value;

    }

    /**
     * Deleted an existing object from the datase.
     * @return stdClass
     */
    final public function delete()
    {

        // Use GLOBAL to access DB
        $db = DB::getInstance();

        // Set the Return Value object
        $return_value = new stdClass;

        // Setup the Query
        $sql = "DELETE FROM " . static::getTableName() . " WHERE " . static::getPrimaryKeyName() . " = " . $this->getPrimaryKeyValue();

        // If successful, return true, if false, return false and error.
        if ($db->exec($sql)) {
            $return_value->success = true;
        } else {
            $return_value->success = false;
            $return_value->errorInfo = implode(", ", $db->errorInfo());
        }

        // Return the info
        return $return_value;

    }

    /**
     * Begins the query to find objects of the type of the calling class.
     * @return mixed
     */
    final public static function find()
    {
        $class_name = get_called_class();

        $obj = new $class_name();
        $obj->where_clause = "WHERE ";
        $obj->order_clause = "ORDER BY ";

        return $obj;
    }

    /**
     * Adds to the "WHERE" clause of the query to find objects.
     * @param string $property
     * @param string $operator
     * @param        $value
     * @param null   $value_2
     * @return $this
     * @throws Exception
     */
    final public function where(string $property, string $operator, $value, $value_2 = null)
    {
        $class_name = get_class($this);
        $operator = strtoupper($operator);
        $allowed_operators = ['=', '!=', '>=', '<=', '>', '<', '<>', 'BETWEEN', 'NOT BETWEEN', 'IN', 'NOT IN', 'LIKE', 'NOT LIKE', '~', '&', '|', '^'];

        // Ensure property is valid for class.
        if (!array_key_exists($property, get_class_vars($class_name))) {
            throw new Exception("Property '" . $property . "' was not found in class '" . $class_name . "'. (WHERE)");
        }

        // Ensure operator is allowed and safe.
        if (!in_array($operator, $allowed_operators)) {
            throw new Exception("Operator '" . $operator . "' is not an allowed operator.");
        }

        // Ensure that BETWEEN and NOT BETWEEN have both values.
        if ($operator == "BETWEEN" || $operator == "NOT BETWEEN") {
            if (!isset($value_2)) {
                throw new Exception($operator . " requires 2 values; only one was passed.");
            }
        }

        // If this is an additional clause, add AND
        if ($this->where_clause !== "WHERE ") {
            $this->where_clause .= "AND ";
        }

        // Surround values with single quotes to ensure strings work. MySQL implictly converts to other formats.
        if ($operator != "IN" && $operator != "NOT IN") {
            $value = "'" . $value . "'";
        }
        if (isset($value_2)) {
            $value_2 = "'" . $value_2 . "'";
        }

        // Add to the clause
        $this->where_clause .= "{$property} {$operator} {$value} ";

        // If this is a BETWEEN clause, we need to do AND and then add the second value.
        if ($operator == "BETWEEN" || $operator == "NOT BETWEEN") {
            $this->where_clause .= "AND {$value_2} ";
        }

        // Return the instance to allow for fluent interfacing.
        return $this;

    }

    /**
     * Adds to the "ORDER BY" clause of the query to find objects.
     * @param string $property
     * @param string $order
     * @return $this
     * @throws Exception
     */
    final public function order(string $property, string $order = "DESC")
    {
        $class_name = get_class($this);
        $order = strtoupper($order);

        // Ensure property is valid for class.
        if (!array_key_exists($property, get_class_vars($class_name))) {
            throw new Exception("Property '" . $property . "' was not found in class '" . $class_name . "'. (ORDER)");
        }

        // Ensure order is allowed and safe.
        if (!in_array($order, ["ASC", "DESC"])) {
            throw new Exception("Order '" . $order . "' is not an allowed order.");
        }

        // If this is an additional clause, add a comma
        if ($this->order_clause !== "ORDER BY ") {
            $this->order_clause .= ", ";
        }

        // Add order to clause
        $this->order_clause .= "{$property} {$order}";

        // Return the instance to allow for fluent interfacing.
        return $this;

    }

    /**
     * Finalizes the query and gets objects that match. Returns single objects, array of objects, or false for no objects.
     * @return array|bool|mixed
     * @throws Exception
     */
    final public function get()
    {

        // DB Connection
        $db = DB::getInstance();

        $class_name = get_class($this);

        // Query the DB and Get the Info of the item we are going to edit
        $sql = "SELECT * FROM " . static::getTableName() . " ";

        // If there where clause was used, added it.
        if (isset($this->where_clause)) {
            if (strlen($this->where_clause) > 6) {
                $sql .= $this->where_clause;
            }
        }

        // If there order clause was used, added it.
        if (isset($this->order_clause)) {
            if (strlen($this->order_clause) > 9) {
                $sql .= $this->order_clause;
            }
        }

        $result = $db->query($sql, PDO::FETCH_CLASS, $class_name);

        // Determine if any results were had.
        if ($result) {
            if ($result->rowCount() == 1) {
                return $result->fetch();
            } else if ($result->rowCount() > 1) {
                return $result->fetchAll();
            } else {
                return false;
            }
        } else {
            throw new Exception("Error Code " . $result->errorCode() . ": " . $result->errorInfo() . ".");
        }

    }

}
