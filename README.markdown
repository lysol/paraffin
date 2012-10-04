PARAFFIN
--------

Full-fledged ORM is a gnarly beast. This is a pretty simply library, that
just provides some work-alike features and a pattern for writing object-oriented
database code. Joins, subqueries, and arbitrary queries are written within
static methods which return instances of the surrounding class.

Only MySQL is supported at the moment, Postgres support coming someday.

Usage
-----

Assume `some_table` is a table with the columns `id int`, `name varchar(128)`, and `active tinyint`.

    class SomeTable extends Paraffin {
        public static $id_name = 'id';  // this is also the default
        public static $table = 'some_table';
    }
  
    SomeTable::setPDOConnString("mysql:host=localhost;dbname=some_db")
  
    $someRow = SomeTable::get(2);  // get record with id 2
    $someRow->name = 'AnotherValue';
    $someRow->save();
  
    $anotherRow = SomeTable::create(array(
        'name' => 'friend',
        'active' => 1
        ));
  
    $anotherRow->delete();
  
    $moreRecords = SomeTable::getMany(array(1,2,3,4));
  
    $allOfTheRecords = SomeTable::all();
  
    $stupids = SomeTable::where(array('name' => 'stupid'));
  
    $stupids[0]->update(array('name' => 'smart'));

Well that's nice. If you need to do more advanced logic, don't try to force some annoying construct onto it, just write SQL.

    class AnotherTable extends Paraffin {
        public static $table = 'another_table';
  
        public static function smartPeople() {
            $dbh = static::getInstance();
            $sth = $dbh->prepare("SELECT * FROM `" . static::$table . "`" .
                  " WHERE `type` = 'smart'");
            $sth->execute();
            return $sth->fetchAll();
        }
  
        public function makeDumb() {
			$sth = $this->dbh->prepare("UPDATE `" . static::$table . "`" .
  	  	  		" SET `type` = 'dumb' WHERE `id` = :id");
			$sth->bindValue(':id', $this->id);
			$sth->execute();
		}
  
		public function friends() {
  			return DumbTable::where(array('another_id' => $this->id));
		}  
    }
  
    class DumbTable extends Paraffin {
    	public static $table = 'dumb_table';
    }

Just write PDO for the rest. The best reference for this library is the library itself.

Caveats
-------

Because by default all PDO queries in class methods return instances of itself, in order to return arrays you'll need to set your fetch mode to FETCH_ASSOC first:

    public function howMany() {
        $sth = $this->dbh->prepare("SELECT count() FROM `" . static::$table . "`");
        $sth->execute();
        $sth->setFetchMode(PDO::FETCH_ASSOC);
        ...
    }