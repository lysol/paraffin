<?php

require_once('../vendor/lastcraft/simpletest/autorun.php');
require_once('../src/Paraffin/SPDOStatement.php');
require_once('../src/Paraffin/Paraffin.php');

$config = parse_ini_file('test.ini');

class User extends Paraffin\Paraffin {
	public static $table = 'users';
}

class Vehicle extends Paraffin\Paraffin {
	public static $table = 'vehicles';
	public static $id_name = 'vin';
}

class TestParaffin extends \UnitTestCase {

	function setUp() {
		global $config;
		User::setPDOConnstring($config['db_connstring'], $config['db_user'], $config['db_password']);
		Vehicle::setPDOConnstring($config['db_connstring'], $config['db_user'], $config['db_password']);
		$this->dbh = new \PDO($config['db_connstring'], $config['db_user'], $config['db_password']);
		$this->dbh->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))");
		$this->dbh->exec("CREATE TABLE vehicles (vin VARCHAR(17) PRIMARY KEY, make VARCHAR(255), model VARCHAR(255))");
	}

	function testCreate() {
		$user = User::create(array('name' => 'Derek'));
		$this->assertTrue($user);
		$this->assertTrue($user->name == 'Derek');
		$user->delete();
	}

	function testSave() {
		$user = User::create(array('name' => 'Derek'));
		$user->name = 'Arnold';
		$user->save();
		$this->assertTrue($user->name = 'Arnold');
		$newuser = User::get($user->id);
		$this->assertTrue($newuser && $newuser->name == 'Arnold');
	}

	function testAlternate() {
		$user = User::create(array('name' => 'Derek'));
		$vehicle = Vehicle::create(array('vin' => '12345678901234567', 'make' => 'Ford', 'model' => 'Focus'));
		$derek = User::get($user->id);
		$this->assertTrue(get_class($derek) == 'User');
		$focus = Vehicle::get('12345678901234567');
		$this->assertTrue(get_class($focus) == 'Vehicle');
	}

	function tearDown() {
		$sth = $this->dbh->prepare("DROP TABLE users;");
		$sth->execute();
		$sth = $this->dbh->prepare("DROP TABLE vehicles;");
		$sth->execute();
	}

}