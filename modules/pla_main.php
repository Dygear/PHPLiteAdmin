<?php
try {
	$PLA = new PHPLiteAdmin('sqlite::memory:');
} catch (PDOException $e) {
	print_r($e);
}
class PHPLiteAdmin extends PDO
{
	const NAME = 'PHPLiteAdmin';
	const VERSION = '0.Alpha.0';
}
?>