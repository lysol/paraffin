PARAFFIN
--------

Full-fledged ORM is a gnarly beast. This is a pretty simple library, that
provides some work-alike features and a pattern for writing object-oriented
database code. Joins, subqueries, and arbitrary queries are written within
static methods which return instances of the surrounding class.

Only MySQL is supported at the moment, Postgres support coming someday.

Usage
-----

Assume `some_table` is a table with the columns `id int`, `name varchar(128)`, and `active tinyint`.

    class TheTable extends Paraffin {
        public static $id_name = 'id';  // this is also the default
        public static $table = 'the_table';
    }
  
    TheTable::setPDOConnString("mysql:host=localhost;dbname=some_db")
  
    $someRow = TheTable::get(2);  // get record with id 2
    $someRow->name = 'AnotherValue';
    $someRow->save();
  
    $anotherRow = TheTable::create(array(
        'name' => 'friend',
        'active' => 1
        ));
  
    $anotherRow->delete();
  
    $moreRecords = TheTable::getMany(array(1,2,3,4));
  
    $allOfTheRecords = TheTable::all();
  
    $people = TheTable::where(array('name' => 'person'));
  
    $people[0]->update(array('name' => 'smart'));

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
  
        public function makeYetAnother() {
			$sth = $this->dbh->prepare("UPDATE `" . static::$table . "`" .
  	  	  		" SET `type` = 'yet_another' WHERE `id` = :id");
			$sth->bindValue(':id', $this->id);
			$sth->execute();
		}
  
		public function friends() {
  			return YetAnotherTable::where(array('another_id' => $this->id));
		}  
    }
  
    class YetAnotherTable extends Paraffin {
    	public static $table = 'yet_another_table';
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