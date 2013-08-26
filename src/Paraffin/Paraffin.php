<?php
namespace Paraffin;

/**
 * Main Paraffin class. Should not be used directly but subclassed so you can
 * provide more efficient queries for your logic.
 */

class Paraffin extends \ArrayObject {
	/**
	 * Database table this class will return records for
	 *
	 * @var string $table;
	 */
	public static $table;

	/**
	 * Column name that holds the primary key for the table
	 * Assumed generally to be an auto incrementing numeric, but there's no
	 * reason you can't use natural or other keys with this library, as long as
	 * you write your own logic to create key values.
	 *
	 * @var string $id_name
	 */
	public static $id_name = 'id';

	/**
	 * Cache column names so we don't query INFORMATION_SCHEMA multiple times.
	 *
	 * @var array $_cached_cols
	 */
	protected static $_cached_cols = array();

	/**
	 * A badly named array that holds column names that accept a NOW() value
	 * in MySQL. During save/update it will be translated to a value of NOW()
	 * during the query.
	 *
	 * @var array $nowCols
	 */
	private $nowCols = array();

	/**
	 * The PDO connection string used for queries.
	 *
	 * @var string $connstring
	 */
	protected static $connstring = null;

	/**
	 * The PDO connection username.
	 *
	 * @var string $__username
	 */
	protected static $__username = null;

	/**
	 * The PDO connection password.
	 *
	 * @var string $__password
	 */
	protected static $__password = null;

	/**
	 * The PDO connection object instance for this
	 * class.
	 *
	 * @var PDO $dbh
	 */
	protected static $dbh = null;

	/**
	 * Exactly what it says on the tin.
	 *
	 * @param string $connstring PDO connection string
	 * @param string $__username PDO connection username
	 * @param string $__password PDO connection password
	 */
	public static function setPDOConnString($connstring, $__username=null, $__password=null,
		$setInstance=true) {
		if (!$connstring)
			return;
		static::$connstring = $connstring;
		static::$__username = $__username;
		static::$__password = $__password;
		if ($setInstance) {
			static::setInstance();
		}
	}

	/**
	 * Constructor
	 *
	 * @param string $connstring @see function setPDOConnString
	 * @param string $__username @see function setPDOConnString
	 * @param string $__password @see function setPDOConnString
	 */
	public function __construct($connstring=null, $__username=null, $__password=null) {
		static::setPDOConnString($connstring, $__username, $__password);
		static::setInstance();
	}

	/**
	 * Set the statement return class and other options for this object.
	 */
	public static function fixate() {
		static::$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		static::$dbh->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_CLASS);
		static::$dbh->setAttribute(\PDO::ATTR_STATEMENT_CLASS,
			array('Paraffin\SPDOStatement', array(get_called_class())));
	}

	public static function setDBHandle($dbh) {
		static::fixate();
		static::$dbh = $dbh;
	}

	/**
	 * Create a new PDO connection object and return it
	 * @return PDO
	 */
	protected static function setInstance() {
		if (static::$dbh !== null) {
			static::fixate();
			return;
		}
		if (!static::$connstring && !defined('PDO_CONNSTRING'))
			throw new \Exception("Please set a static connstring in the class " .
				"or define PDO_CONNSTRING with a PDO connection string before " .
				"instantiating this class.");
		$cs = (static::$connstring) ? static::$connstring : PDO_CONNSTRING;
		$un = static::$__username;
		if (!$un && defined('PDO_USERNAME')) {
			$un = PDO_USERNAME;
		}
		$pw = static::$__password;
		if (!$un && defined('PDO_PASSWORD')) {
			$un = PDO_PASSWORD;
		}
		$dbh = new \PDO($cs, $un, $pw);
		static::$dbh = $dbh;
		static::fixate();
	}

	/**
	 * Set a column to be set to NOW() in the subsequent database query.
	 *
	 * @param string $colName The column name in the table for this class
	 */
	public function makeNow($colName) {
		if (!in_array($colName, static::_columns()))
			return false;
		if (!in_array($colName, $this->nowCols))
			$this->nowCols[] = $colName;
	}

	/**
	 * Find a record with the specified ID.
	 *
	 * @param mixed $id
	 * @return bool
	 */
	public static function exists($id) {
		static::fixate();
		$sth = static::$dbh->prepare(sprintf("SELECT TRUE FROM %s WHERE %s = :id",
			static::$table, static::$id_name));
		$sth->bindValue(":id", $id);
		$sth->execute();
		return $sth->rowCount() > 0;
	}

	/**
	 * Delete a record with the specified ID.
	 *
	 * @param mixed $id
	 * @return bool
	 */
	public function delete() {
		$sth = static::$dbh->prepare("DELETE FROM " . static::$table . " WHERE " .
			static::$id_name . " = :id");
		$sth->bindValue(":id", $this->{static::$id_name});
		return $sth->execute();
	}

	/**
	 * Save the current record instance.
	 */
	public function save() {
		$_cols = static::_columns();
		$values = array();
		foreach($_cols as $col) {
			$values[$col] = $this->{$col};
		}
		$this->update($values);
	}

	/**
	 * Return the cached columns for this instance
	 *
	 * @return array
	 */
	public function columns() {
		// well this needs a refactor now
		return static::_columns();
	}

	/**
	 * Return the currently selected database.
	 *
	 * @return string
	 */
	protected static function currentDatabase() {
		$sth = static::$dbh->prepare("SELECT DATABASE() AS db");
		$sth->setFetchMode(\PDO::FETCH_ASSOC);
		$sth->execute();
		$row = $sth->fetch();
		return $row['db'];
	}

	/**
	 * Clear the cached column list, invalidating the cached local values
	 *
	 */
	public static function resetCache() {
		static::$_cached_cols = array();
	}

	/**
	 * Return the list of columns from INFORMATION_SCHEMA
	 *
	 * @return array
	 */
	private static function _columns() {
		if (!array_key_exists(static::$table, static::$_cached_cols)) {
			$database = static::currentDatabase();
			static::$_cached_cols[static::$table] = array();
			$sth = static::$dbh->prepare("
				SELECT COLUMN_NAME
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE table_name = :table
				AND table_schema = :database
				ORDER BY `ORDINAL_POSITION`");
			$sth->bindValue(":table", static::$table);
			$sth->bindValue(":database", $database);
			$sth->setFetchMode(\PDO::FETCH_ASSOC);
			$sth->execute();
			foreach($sth->fetchAll() as $row) {
				static::$_cached_cols[static::$table][] = $row['COLUMN_NAME'];
			}
		}
		return static::$_cached_cols[static::$table];
	}

	/**
	 * Filter array indices down to columns actually in the database table.
	 *
	 * @param array $values
	 * @return array
	 */
	protected static function _filterColumns($values) {
		$vkeys = array_keys($values);
		$cols = static::_columns();
		// Remove array keys not in the actual table field set
		foreach($vkeys as $key)
			if (!in_array($key, $cols))
				unset($values[$key]);

		if (count($values) == 0)
			throw new \Exception('No columns defined in query.');
		return $values;
	}

	/**
	 * Set the current record instance's columns to match the
	 * given array, and update the database.
	 *
	 * @param array $values
	 * @return bool
	 */
	public function update($values) {
		$values = static::_filterColumns($values);
		if (!isset($this->{static::$id_name})) {
			// If no ID, create it first.
			$vals = static::_filterColumns($values);
			static::create($vals, true, $this->nowCols);
			foreach($vals as $key => $val)
				$this->{$key} = $val;
			return $this;
		}
		$setpart = array();
		foreach(array_keys($values) as $key)
			if (!in_array($key, $this->nowCols))
				$setpart[] = sprintf("`%s` = :%s", $key, $key);
			else
				$setpart[] = sprintf("`%s` = NOW()", $key);

		$setpart_r = implode(',', $setpart);
		$querystring = "UPDATE " . static::$table . " SET $setpart_r WHERE `" .
			static::$id_name . "` = :__id";
		$sth = static::$dbh->prepare("UPDATE " . static::$table .
			" SET $setpart_r WHERE `" . static::$id_name . "` = :__id");
		$sth->bindValue(":__id", $this->{static::$id_name});
		foreach($values as $key => $val)
			if (!in_array($key, $this->nowCols))
				$sth->bindValue(":$key", $val);
		try {
			$res = $sth->execute();
		} catch (\PDOException $e) {
			throw new \Exception("Encountered PDOException from query " .
				"\"$querystring\": " . $e->getMessage());
		}
		if ($res && $sth->rowCount()) {
			foreach($values as $key => $val)
				$this->{$key} = $val;
			return true;
		}
		return false;
	}

	/**
	 * Find record(s) where the supplied values are present.
	 * This is an AND query. For something more advanced, write it yourself.
	 *
	 * @param array $values
	 * @param bool $one Only return one record if true
	 */
	public static function where($values, $one=false) {
		static::fixate();
		$sets = array();
		foreach(array_keys($values) as $key)
			$sets[] = "`$key` = :$key";
		$sets_r = implode(" AND ", $sets);
		$sth = static::$dbh->prepare("SELECT * FROM " . static::$table .
			" WHERE $sets_r");
		foreach($values as $key => $value) {
			if (is_int($value))
				$datatype = \PDO::PARAM_INT;
			else
				$datatype = \PDO::PARAM_STR;
			$sth->bindValue(":$key", $value, $datatype);
		}
		$sth->execute();
		if ($one)
			return $sth->fetch();
		else
			return $sth->fetchAll();
	}

	/**
	 * Return every record in this table.
	 *
	 * @return array
	 */
	public static function all() {
		static::fixate();
		$sth = static::$dbh->prepare("SELECT * FROM " . static::$table);
		$sth->execute();
		return $sth->fetchAll();
	}

	/**
	 * Return a record with the given ID.
	 *
	 * @param mixed $id
	 * @return mixed
	 */
	public static function get($id) {
		static::fixate();
		$sth = static::$dbh->prepare("SELECT * FROM " . static::$table . " WHERE " .
			static::$id_name . " = :id");
		$sth->bindValue(":id", $id);
		$sth->execute();

		return $sth->fetch();
	}

	/**
	 * Return records with the given IDs.
	 *
	 * @param array $ids
	 * @return array
	 */
	public static function getMany($ids=array()) {
		static::fixate();
		if (count($ids) == 0)
			return array();
		$makeitso = function($item) { return (int)$item; };
		array_walk($ids, $makeitso);
		$sth = static::$dbh->prepare("SELECT * FROM " . static::$table . " WHERE " .
			static::$id_name . " IN (" . implode(',', $ids) . ")");
		$sth->execute();
		return $sth->fetchAll();
	}

	/**
	 * Attempt to create a new instance, falling back to an update if
	 * an integrity error occurs.
	 * @param array $values
	 *
	 * @return Paraffin
	 */
	public static function upsert($values) {
		try {
			$res = static::create($values);
			return $res;
		} catch (\PDOException $e) {
			if ($e->getCode() == 23000) {
				// integrity error, update instead.
				$instance = static::get($values[static::$id_name]);
				unset($values[static::$id_name]);
				$instance->update($values);
				return $instance;
			}
		}
	}

	/**
	 * Create a new instance. By default, it will also insert a new record.
	 * Any columns in $nowCols will be set to NOW() in the query.
	 *
	 * @param array $values
	 * @param bool $save
	 * @param array $nowCols
	 */
	public static function create($values, $save=true, $nowCols=array(),
		$table=null) {

		static::fixate();
		// Extend the values array to include nowCols if the consuming code
		// didn't pass it.
		foreach($nowCols as $nowCol) {
			if (!in_array($nowCol, array_keys($values))) {
				$values[$nowCol] = null;
			}
		}
		$values = static::_filterColumns($values);
		if (!$table)
			$table = static::$table;
		if (!$save) {
			// Allow use of un-saved records
			$instance = new static();
			foreach($values as $key => $value)
				$instance->{$key} = $value;
			return $instance;
		}
		$sets = array();
		foreach(array_keys($values) as $key)
			if (!in_array($key, $nowCols))
				$sets[] = ":$key";
			else
				$sets[] = "NOW()";
		$names_built = sprintf("`%s`", implode("`,`", array_keys($values)));
		$vals_built = implode(", ", $sets);
		$querystring = "INSERT INTO $table ($names_built) VALUES " .
			"($vals_built)";
		$sth = static::$dbh->prepare($querystring);
		foreach($values as $key => $value) {
			if (is_int($value))
				$datatype = \PDO::PARAM_INT;
			else
				$datatype = \PDO::PARAM_STR;
			if (!in_array($key, $nowCols))
				$sth->bindValue(":$key", $value, $datatype);
		}

		$res = $sth->execute();

		if ($res && $sth->rowCount() == 1) {
			$sth = static::$dbh->prepare("SELECT * FROM $table WHERE " .
				static::$id_name . " = :id");
			if (array_key_exists(static::$id_name, $values)) {
				$sth->bindValue(":id", $values[static::$id_name]);
			} else {
				$sth->bindValue(":id", static::$dbh->lastInsertId());
			}
			$sth->execute();
			return $sth->fetch();
		} else {
			return false;
		}
	}

	/*
	 * Simple interface for generating a simple array of values
	 *
	 * @return array
	 */
	public function toArray() {
		$columns = static::_columns();
		$arr = array();
		foreach($columns as $col) {
			$arr[$col] = $this->{$col};
		}
		return $arr;
	}

	/*
	 * Turn an array of instances into an array of JSON items
	 *
	 * @return string
	 */
	public static function toJSON($records) {
		$items = array();
		foreach($records as $record) {
			$items[] = $record->toArray();
		}
		return json_encode($items);
	}

	/*
	 * Empty the table.
	 */
	public static function truncate() {
		static::fixate();
		static::$dbh->query("TRUNCATE TABLE `" . static::$table . "`");
	}

	public static function raw($query, $data=array()) {
		static::fixate();
		$sth = static::$dbh->prepare($query);
		$result = $sth->execute($data);
		return $sth->fetchAll();
	}
}
