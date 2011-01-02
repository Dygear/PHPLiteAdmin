<?php require_once('modules/pla_main.php'); ?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title>PHPLiteAdmin Version <?php echo PHPLiteAdmin::VERSION; ?></title>
		<link rel="stylesheet" href="theme/original/style.css" />
	</head>
	<body>
		<header>
			<hgroup>
				<h1><?php echo PHPLiteAdmin::NAME; ?></h1>
				<h2>A HTML SQLite PHP Admin interface for SQLite.</h2>
			</hgroup>
		</header>
		<nav>
			<ul>
				<li><a href="#">New Db</a></li>
				<li><a href="#">Del Db</a></li>
			</ul>
		</nav>
		<footer>
			<p>&copy; <time datetime="2011-01-01">2011</time> Mark 'Dygear' Tomlin</p>
		</footer>
	</body>
</html>