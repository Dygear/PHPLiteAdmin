<?php
namespace phpLiteAdmin;
use \PDO;
use \DateTime;
use \Exception;
use \PDOStatement;
session_start();

/**
 * @global string VERSIONclear
 */
const VERSION = '2.0.0-alpha1';

/**
 * @global array $Users
 * 
 * An array of users that should be able to login to the site.
 */
$Users = [
];

/**
 * @global array $Database
 * 
 * Contains the name of the database as the $key and it's location on the file system as the value.
 */
$Database = [
];

/**
 * Contains all of the logic for building our page.
 */
class Page
{
    /**
     * @property bool $emitted - Has the page header been sent?
     */
    public bool $emitted = false;

    /**
     * @param string $title - HTML Page Title.
     * @param bool $contain - Should the contain class be used to contain the HTML body.
     */
    public function __construct(
        public string $title = 'phpLiteAdmin',
        public bool $contain = true,
    ) {
        global $Users;

        // Reset sessions if there is an empty Users array.
        if (isset($_GET['signout']) OR empty($Users) AND isset($_SESSION))
        {
            unset($_SESSION);
        }

        // Before we do anything or allow anything, we make sure the client is authenticated with the page.
        if (!Access::granted())
        {
            header('HTTP/1.0 401 Unauthorized');
            $this->title = 'Login';
            $this->contain = false;
            $this->emit();
            echo Access::authenticate();
            $this->__destruct();
            exit();
        }
    }

    /**
     * Emits the page header and start of the body into the stream.
     */
    public function emit()
    {
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>{$this->title}</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
            </head>
            <body>

        HTML;

        if ($this->contain)
        {
            echo <<<HTML
                    <main class="container">

            HTML;
        }

        $this->emitted = true;
    }

    /**
     * Page footer, only emits when the body has actualy been sent to the client.
     */
    public function __destruct()
    {
        if (!$this->emitted)
            return;

        if ($this->contain)
        {
            echo <<<HTML
                    </main>

            HTML;
        }

        echo <<<HTML
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
            </body>
        </html>
        HTML;
    }
}

/**
 * Access control class.
 * 
 * A users authentication goes though here.
 * It also has the methods for editing the global $Users array.
 */
class Access
{
    const CSS =<<<CSS
            html, body {
                height: 100%;
            }
            body {
                display: flex;
                align-items: center;
                padding-top: 40px;
                padding-bottom: 40px;
                color: #FFF;
                background: #203040;
                background: repeating-linear-gradient(#203040, #243c4f 2px, #213445 2px);
            }

            .form-signin {
                width: 100%;
                max-width: 330px;
                padding: 15px;
                margin: auto;
            }
            .form-signin .checkbox {
                font-weight: 400;
            }
            .form-signin .form-control {
                position: relative;
                box-sizing: border-box;
                height: auto;
                padding: 10px;
                font-size: 16px;
            }
            .form-signin .form-control:focus {
                z-index: 2;
            }
            .form-signin input {
                border: 1px SOLID #4080B0;
                color: #FFF;
                background: #193F5C;
            }
            .form-signin input:hover {
                color: #FFF;
                background: #193F5C;
            }
            .form-signin input:hover, .form-signin input:active {
                animation: 1s all ease-in;
                border-color: #FA0;
                color: #FFF;
            }
            .form-signin input:focus {
                outline: none;
                color: #FFF;
                background: #002040;
            }
            .form-signin input:active {
                color: #FFF;
                background: #002040;
            }
            .form-signin input[type="text"] {
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
            }
            .form-signin input[type="password"] {
                border-top-left-radius: 0;
                border-top-right-radius: 0;
            }
            .form-signin input[type="checkbox"] {
                filter: invert(100%) hue-rotate(180deg) brightness(1.5);
            }
CSS;

    /**
     * Asks the question is Access Granted? And finds the answer.
     * 
     * @return TURE when they are logged int, FALSE otherrwise.
     */
    public static function granted(): bool
    {
        global $Users;

        // Check login form and verify.
        if (!empty($_POST['user']) AND !empty($_POST['pass']) AND isset($Users[$_POST['user']]) AND password_verify($_POST['pass'], $Users[$_POST['user']]))
        {
            if (isset($_POST['remember']))
            {
                setcookie(session_name(), session_id(), strtotime('+1 Month'), '/', $_SERVER['HTTP_HOST'], true, true);
            }

            $_SESSION['loggedIn'] = true;

            return true;
        }

        // Check sessions.
        if (isset($_SESSION) AND isset($_SESSION['loggedIn']) AND $_SESSION['loggedIn'] === true)
        {
            return true;
        }

        return false;
    }

    /**
     * Authenticate a user by presenting them a login prompmt.
     */
    public static function authenticate(): void
    {
        if (self::addUser())
        {
            $CSS = self::CSS;
            echo <<<HTML
                    <style>
            {$CSS}
                    </style>
                    <main class="form-signin text-center">
                        <form method="post">
                            <h1 class="h3 mb-3 fw-normal">Please sign in</h1>
                            <label for="inputUser" class="visually-hidden">Username</label>
                            <input type="text" id="inputUser" class="form-control" placeholder="Username" name="user" required autofocus>
                            <label for="inputPassword" class="visually-hidden">Password</label>
                            <input type="password" id="inputPassword" class="form-control" placeholder="Password" name="pass" required>
                            <div class="checkbox mb-3">
                                <label>
                                    <input type="checkbox" name="remember"> Remember me
                                </label>
                            </div>
                            <button class="w-100 btn btn-lg btn-primary" type="submit">Sign in</button>
                        </form>
                    </main>

            HTML;
        }
    }

    /**
     * Adds a user based on $_POST form data.
     * This pulls from the global $Users array to make sure that:
     *   1. The user array is empty, thus should be allowed for first time use.
     *   2. A username is set, but that username is not already in use.
     *   3. The passwords match.
     * If all of these conditions are met, it will add the user to the $Users array.
     * @return TRUE on success or FALSE on failure.
     */
    public static function addUser(): bool
    {
        global $Users;

        if (isset($_POST['user']) AND isset($_POST['pass']) AND isset($_POST['passConfirm']))
        {
            if (!empty($Users) AND !Access::granted())
                throw new Exception('You must be logged in to add a new user.', 1);

            if (isset($Users[$_POST['user']]))
                throw new Exception('User name already taken.', 1);

            if ($_POST['pass'] !== $_POST['passConfirm'])
                throw new Exception('Passwords do not match.', 1);

            self::addUserToArray($_POST['user'], $_POST['pass']);
        }

        if (empty($Users) AND empty($_POST))
        {
            $CSS = self::CSS;
            echo <<<HTML
                    <style>
            {$CSS}
                    </style>
                    <main class="form-signin text-center">
                        <form method="post">
                            <h1 class="h3 mb-3 fw-normal">Add New User</h1>
                            <label for="inputUser" class="visually-hidden">Username</label>
                            <input type="text" id="inputUser" class="form-control" placeholder="Username" name="user" required autofocus>
                            <label for="inputPassword1" class="visually-hidden">Password</label>
                            <input type="password" id="inputPassword1" class="form-control" placeholder="Password" name="pass" required>
                            <label for="inputPassword2" class="visually-hidden">Password</label>
                            <input type="password" id="inputPassword2" class="form-control" placeholder="Password" name="passConfirm" required>
                            <div class="checkbox mb-3">
                                <label>
                                    <input type="checkbox" name="remember"> Remember me
                                </label>
                            </div>
                            <button class="w-100 btn btn-lg btn-primary" type="submit">Sign in</button>
                        </form>
                    </main>

            HTML;
            return false;
        }

        return true;
    }

    /**
     * This is a document mutating function.
     * If this was a rust function, I would mark this as unsafe.
     * 
     * @param string $user - Username
     * @param string $pass - Password
     * @return bool TRUE on Success or FALSE on Failure.
     */
    public static function addUserToArray(string $user, string $pass): bool
    {
        global $Users;

        $document = file_get_contents(__FILE__);
        $userStrStart = strpos($document, '$Users = [' . "\n");
        $userStrEnd = strpos($document, '];', $userStrStart);

        $Users[$user] = password_hash($pass, PASSWORD_DEFAULT);
        $UsersArrayRAW = '';
        foreach ($Users as $user => $hash)
            $UsersArrayRAW = "    '$user' => '$hash'," . "\n";

        $document = substr_replace($document, $UsersArrayRAW, $userStrEnd, 0);

        file_put_contents(__FILE__, $document);

        return true;
    }
}

/**
 * Database
 */
class Database extends PDO
{
    /**
     * Database connection.
     */
    public PDO $db;

    /**
     * File size of the database in bytes.
     */
    public ?int $size = null;

    /**
     *
     */
    public ?array $schema = [];

    /**
     * Setups the Database class so all properties are set correctly and ready for use.
     *
     * @param string $filePath - Path to the database file.
     * @param string $name - Friendly name of the database.
     */
    public function __construct(
        public string $filePath,
        public ?string $name = NULL,
    )
    {
        $this->filePath = realpath($filePath);

        try {
            parent::__construct('sqlite:' . $filePath);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->query('PRAGMA foreign_keys = ON;');
            $this->getSchema();
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return false;
        }
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $statement
     * This must be a valid SQL statement template for the target database server.
     * @param array $driver_options
     * This array holds one or more `key => value` pairs to set attribute values for the PDOStatement object that this method returns. You would most commonly use this to set the `PDO::ATTR_CURSOR` value to `PDO::CURSOR_SCROLL` to request a scrollable cursor. Some drivers have driver-specific options that may be set at prepare-time.
     */
    public function prepare(string $statement, array $driver_options = []): PDOStatement
    {
        return parent::prepare($statement, $driver_options);
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     * 
     * @param string RAW Statement, must be escaped.
     * @return false on failure or number of rows affected on success.
     */
    public function exec(string $statement): false|int
    {
        if (empty($statement))
            return false;

        $return = parent::exec($statement);
        $this->getSchema();
        return $return;
    }

    /**
     * View the table.
     * 
     * @param string $table - The table name.
     * @param int $limit - The limit to the number of results to return.
     * @param int $offset - The starting offset.
     */
    public function view(string $table, ?int $limit = 129, ?int $offset = 0)
    {
        $statement = $this->prepare("SELECT * FROM $table LIMIT :limit OFFSET :offset;");
        $statement->execute([
            ':limit' => $limit,
            ':offset' => $offset
        ]);
        foreach ($statement->fetchAll() as $row)
        {
            yield $row;
        }
    }

    /**
     * Can be used with query or prepare to convert those statements into HTML table(s).
     * 
     * @param PDOStatement $result Result of a PDO::statement or PDO::query.
     * @return string HTML formatted text in the form of a table.
     */
    public function resultTable(PDOStatement $result): string
    {
        $result->fetchAll();
    }

    /**
     * https://sqlite.org/schematab.html
     */
    public function getSchema()
    {
        foreach ($this->query('SELECT rowid, * FROM sqlite_master WHERE type IN("table", "view") AND name NOT LIKE "sqlite_%" ORDER BY name;') as $row)
        {
            $this->schema[$row['rowid']] = $row;
        }
    }

    /**
     * Gets the file size of the database on disk.
     * 
     * @return Returns the file size.
     */
    public function getSize(): string
    {
         $B =          1;
        $KB =  $B * 1024;
        $MB = $KB * 1024;
        $GB = $MB * 1024;

        $bytes = filesize($this->filePath);

        $units = match (true)
        {
            $bytes >= $GB => ['suffix' => 'GiB', 'base' => $GB, 'css' => 'text-dark'],
            $bytes >= $MB => ['suffix' => 'MiB', 'base' => $MB, 'css' => 'text-light'],
            $bytes >= $KB => ['suffix' => 'KiB', 'base' => $KB, 'css' => 'text-muted'],
            default       => ['suffix' =>   'B', 'base' =>  $B, 'css' => 'text-danger']
        };

        return sprintf('%.3f', $bytes / $units['base']) . $units['suffix'];
    }

    /**
     * Gets the date this database was last modified
     *
     * @return DateTime with time set to the modifed date of the file.
     */
    public function getModifed(): DateTime
    {
        $DateTime = new DateTime();
        $DateTime->createFromFormat('U', filemtime($this->filePath));
        return $DateTime;
    }

    /**
     * Gets the version of the database from the database file.
     *
     * @return string SQLite Version.
     */
    public function getVersion(): string
    {
        $select = $this->prepare('SELECT sqlite_version();');
        $select->execute();
        return $select->fetchColumn();
    }

    /**
     * Is Writable?
     * 
     * @return bool TRUE if we can, FALSE if we can't.
     */
    public function isWriteable(): bool
    {
        return is_writable($this->filePath) AND is_writable(dirname($this->filePath));
    }

    /**
     * Is Readable?
     * 
     * @return bool TRUE if we can, FALSE if we can't.
     */
    public function isReadable(): bool
    {
        return is_readable($this->filePath);
    }

    /**
     * 
     * 
     * @return string Read / Write State as string.
     */
    public function strReadWrite(): string
    {
        $return  = '';
        $return .= ($this->isReadable()) ? 'R' : ' ';
        $return .= ($this->isWriteable()) ? 'W' : ' ';
        return $return;
    }

    /**
     * @return int - The number of records in the table.
     */
    public function getRecords(string $table): ?int
    {
        try {
            $select = $this->prepare("SELECT MAX(_ROWID_) FROM $table LIMIT 1;");
            $select->execute();
        } catch (Exception $e) {
            return null;
        }
        return $select->fetchColumn();
    }

    public function getColums(string $table): array
    {
        $select = $this->prepare("pragma table_info($table);");
        $select->execute();
        return $select->fetchAll();
    }

    public function getCreateStatement(string $table): ?string
    {
        $select = $this->prepare('SELECT sql FROM sqlite_master WHERE name = :table;');
        $select->execute([':table' => $table]);
        return $select->fetch()['sql'] ?? null;
    }

    /**
     * Themeing helpers
     */
    
    /**
     * Is this database or table selected?
     */
    public function selected(?string $table = null): bool
    {
        global $dbs;

        $database = (function() use ($dbs) {
            return (isset($_GET['db']) AND $dbs[$_GET['db']] == $this) ? true : false;
        })();

        if (null === $table)
            return $database;

        return (isset($_GET['table']) AND $_GET['table'] == $table) ? true : false;
    }

    /**
     * This is a document mutating function.
     * If this was a rust function, I would mark this as unsafe.
     * 
     * @param string $dbName - Database Name
     * @param string $dbPath - Database Path
     * @return bool TRUE on Success or FALSE on Failure.
     */
    public static function addDBToArray(string $dbName, string $dbPath): bool
    {
        global $Database;

        $document = file_get_contents(__FILE__);
        $dbStrStart = strpos($document, '$Database = [' . "\n");
        $dbStrEnd = strpos($document, '];', $dbStrStart);

        $dbArrayRAW = '';

        // Get the current list.
        foreach ($Database ?? [] as $name => $path)
            $dbArrayRAW = "    '$name' => '$path'," . "\n";

        // Add to the end of the list.
        $dbArrayRAW = "    '$dbName' => '$dbPath'," . "\n";

        $document = substr_replace($document, $dbArrayRAW, $dbStrEnd, 0);

        file_put_contents(__FILE__, $document);

        return true;
    }

}

$page = new Page();
$page->contain = false;
$page->emit();

/**
 * 
 * Anything after the new Page class is protected by the authentication system automaticlly.
 * You have to be logged in in order to take any action from this point forward.
 * If you're not logged in, it will stop the rest of the page execution and it will give you a login prompt.
 * 
 */

if (isset($_POST['addDatabase']))
{
    Database::addDBToArray($_POST['dbName'], $_POST['dbPath']);
}

static $dbs = [];
foreach ($Database as $name => $path)
{
    $dbs[] = new Database($path, $name); 
}

if (isset($_GET['db']) AND isset($_POST['sql']))
{
    try {
        $resultSet = $dbs[$_GET['db']]->query($_POST['sql']);
    } catch (Exception $e) {
        $resultSet = $e;
    }
}

?>
        <style>
            :root {
                --bs-primary: #375D7A;
            }
            /**
             * Dark Mode
             */
            html, body {
                background: #375D7A;
                color: #FFF;
            }
            a {
                text-decoration: none;
                color: #FA0 !important;
            }
            a:hover {
                color: #FF0 !important;
            }
            input {
                border: 1px SOLID #4080B0 !important;
                color: #FFF !important;
                background: #193F5C !important;
            }
            input:focus {
                outline: none;
                background: #002040 !important;
            }
            input:active {
                background: #002040 !important;
            }
            /**
             * Header
             */
            header {
                background: #002040;
                margin: 0;
                padding: 0;
                line-height: 1em;
            }
            header a {
                color: #FFF;
            }
            header center {
                padding-top: .75rem;
                padding-bottom: .75rem;
            }
            header a {
                font-size: 1rem;
            }
            header .toggler {
                top: .25rem;
                right: 1rem;
            }
            header .form-control {
                padding: .75rem 1rem;
                border-width: 0;
                border-radius: 0;
            }
            /**
             * Sidebar
             */
            aside {
                background: #193F5C;
                color: #FFF;
                position: fixed;
                top: 2.5rem;
                padding-top: 1rem;
                bottom: 0;
                left: 0;
                z-index: 100; /* Behind the navbar */
                box-shadow: inset -1rem 0 1rem rgba(0, 0, 0, .1);
                overflow-y: auto
            }
            aside a {
                color: #000;
            }
            aside dl {
                margin: 0;
                padding: 0;
            }
            aside dl dt {
                margin: 0;
                padding: 0;
                padding-left: 1rem;
            }
            aside dl dt a {
                color: #FF0 !important;
            }
            aside dl dd {
                margin: 0;
                padding: 0;
                padding-left: 2rem;
            }
            aside dl dd a {
                color: #FA0 !important;
            }
            /**
             * Content
             */
            main {
                padding-top: 1rem;
            }
            .null {
                color: rgba(128, 128, 128, 0.25) !important;
            }
            .table {
                color: #FFF;
                border: #4080B0;
                --bs-table-striped-color: #EEE;
                --bs-table-hover-color: #4080B0;
            }
            .table th {
                text-align: center;
            }
            .table-striped > tbody > tr:hover,
            .table > tbody > tr:hover {
                background: rgba(0, 0, 0, .5);
            }
        </style>
        <header class="navbar sticky-top flex-md-nowrap p-0 shadow">
            <center class="col-md-3 col-lg-2 me-0 px-3">
                <a href="<?=$_SERVER['SCRIPT_NAME']?>">phpLiteAdmin <?=VERSION?></a>
            </center>
            <center class="col-md-3 col-lg-2 me-0 px-3">
                <a href="?signout">Sign out</a>
            </center>
        </header>
        <div class="container-fluid">
            <aside class="col-md-3 col-lg-2 d-md-block sidebar collapse">
<?php   foreach ($dbs as $idx => $db): ?>
                <dl class="flex-column">
                    <dt>[<?=$db->strReadWrite()?>] <a class="<?=($db->selected()) ? 'active' : null?>" aria-current="page" href="?db=<?=$idx?>"><?=$db->name?></a></dt>
<?php           if (isset($_GET['db']) AND $_GET['db'] == $idx):   ?>
<?php               foreach ($db->schema as $table):    ?>
                    <dd>[<?=$table['type']?>] <a class="<?=($db->selected($table['name'])) ? 'active' : null?>" aria-current="page" href="?db=<?=$idx?>&table=<?=$table['name']?>"><?=$table['name']?></a></dd>
<?php               endforeach; ?>
<?php           endif;  ?>
                </dl>
<?php   endforeach;  ?>
            </aside>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <label>phpLiteAdmin Version</label> <var><?=VERSION?></var><br />
                <label>PHP Version</label> <var><a href="?phpinfo"><?=phpversion()?></a></var><br />
                <label>SQLite Installed</label> <var><?=\SQLite3::version()['versionString']?></var><br />
                <label>Date Time</label> <var><?=date('Y-m-d H:i:s')?></var><br />
<?php   if (isset($_GET['phpinfo'])):   ?>

                <?php phpinfo(); ?>

<?php   elseif (isset($_GET['db'])): ?>
                <form method="post" class="d-grid gap-2">
                    <style type="text/css" media="screen">
                        #editor { 
                            font-size: 1em;
                            height: 16em;
                        }
                    </style>
                    <div id="editor"><?=$_POST['sql'] ?? NULL?></div>
                    <textarea name="sql" style="display: none;"><?=$_POST['sql'] ?? NULL?></textarea>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js" integrity="sha512-GZ1RIgZaSc8rnco/8CXfRdCpDxRCphenIiZ2ztLy3XQfCbQUSCuk8IudvNHxkRA3oUg6q0qejgN/qqyG1duv5Q==" crossorigin="anonymous"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/theme-twilight.min.js" integrity="sha512-2IqbT6swyxY2XnBLoAIUYyxu2Oj1XoS7AafJE/5q3vl0mmXyKxIIyKqh1jZNqZeNsp8uP8JRNtG2Z6sgoadXOA==" crossorigin="anonymous"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/mode-sql.min.js" integrity="sha512-wsE6/Wq6h/sr67KuoliMHvnS5FGqI1oW/gvbLgYKtWyfCChHi/T6tt8i+qvaIEdA9+JiW+d+51cA5fRdlfjGtw==" crossorigin="anonymous"></script>
                    <script>
                        var editor = ace.edit('editor');
                        var postql = document.querySelector('textarea[name="sql"]');

                        editor.setTheme('ace/theme/twilight');
                        editor.session.setMode('ace/mode/sql');
                        editor.session.on('change', (delta) => {
                            console.log(delta);
                            postql.innerHTML = editor.session.getValue();
                        });
                    </script>
                    <button type="submit" class="btn btn-primary">Execute Statement</button>
                </form>

<?php       if (isset($resultSet)): ?>
<?php           if ($resultSet instanceof \Exception):  ?>
                <div class="alert alert-warning"><?=$resultSet->getMessage();?></div>
                <pre><?=print_r($resultSet, true)?></pre>
<?php           else:   ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
<?php           for ($i = 0, $j = $resultSet->columnCount(); $i < $j; $i++): ?>
                            <th><?=$resultSet->getColumnMeta($i)['name']?></th>
<?php           endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
<?php           foreach ($resultSet->fetchAll() as $row):   ?>
                        <tr>
<?php               foreach ($row as $col): ?>
                            <td><?=(NULL === $col) ? '<span class="null">NULL</span>' : $col?></td>
<?php               endforeach; ?>
                        </tr>
<?php           endforeach; ?>
                    </tbody>
                </table>
<?php           endif;  ?>
<?php       endif;  ?>

<?php       if (isset($_GET['table'])): ?>

                <br />
                <pre><?=$dbs[$_GET['db']]->getCreateStatement($_GET['table'])?></pre>
                <br />

                <table class="table table-striped table-hover table-responsive">
                    <thead>
                        <tr>
<?php       foreach ($dbs[$_GET['db']]->getColums($_GET['table']) as ['name' => $name, 'type' => $type]):  ?>
                            <th><?=$name?></th>
<?php       endforeach;   ?>
                        </tr>
                    </thead>
                    <tbody>
<?php       foreach ($dbs[$_GET['db']]->view($_GET['table'], $_GET['limit'] ?? 128, $_GET['offset'] ?? 0) as $row): ?>
                        <tr>
<?php           foreach ($row as $col):    ?>
                            <td><?=(NULL === $col) ? '<span class="null">NULL</span>' : $col?></td>
<?php           endforeach; ?>
                        </tr>
<?php       endforeach; ?>
                    </tbody>
                </table>

<?php       endif;  ?>

<?php   elseif (isset($_GET['db'])):   ?>
                <label>Database name</label> <var><?=$dbs[$_GET['db']]->name?></var><br />
                <label>Path to database</label> <var><?=$dbs[$_GET['db']]->filePath?></var><br />
                <label>Size of database</label> <var><?=$dbs[$_GET['db']]->getSize()?></var><br />
                <label>Database last modified</label> <var><?=$dbs[$_GET['db']]->getModifed()->format('Y-m-d H:i:s')?></var><br />
                <label>SQLite version</label> <var><?=$dbs[$_GET['db']]->getVersion()?></var><br />

                <table class="table table-striped table-hover table-responsive">
                    <thead>
                        <tr>
                            <td>Type</td>
                            <td>Name</td>
                            <td colspan="10">Actions</td>
                            <td>Records</td>
                        </tr>
                    </thead>
                    <tbody>
<?php           foreach ($dbs[$_GET['db']]->schema as $table):    ?>
                        <tr>
                            <th><?=$table['type']?></th>
                            <td><a href="?db=<?=$_GET['db']?>&table=<?=$table['name']?>"><?=$table['name']?></a></td>
                            <td>Browse</td>
                            <td>Structure</td>
                            <td>SQL</td>
                            <td>Search</td>
                            <td>Insert</td>
                            <td>Export</td>
                            <td>Import</td>
                            <td>Rename</td>
                            <td>Empty</td>
                            <td>Drop</td>
                            <td><?=$dbs[$_GET['db']]->getRecords($table['name'])?></td>
                        </tr>
<?php           endforeach; ?>
                    </tbody>
                </table>

<?php   else:   ?>

                <table class="table table-striped table-hover table-responsive">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Modifed</th>
                            <th>File Path</th>
                            <th>File Size</th>
                            <th>R/W</th>
                        </tr>
                    </thead>
                    <tbody>
<?php       foreach ($dbs as $idx => $db): ?>
                        <tr>
                            <th><?=$idx?></th>
                            <td><?=$db->name?></td>
                            <td><?=$db->getModifed()->format('Y-m-d H:i:s')?></td>
                            <td><?=$db->filePath?></td>
                            <td><?=$db->getSize()?></td>
                            <td><?=$db->strReadWrite()?></td>
                        </tr>
<?php       endforeach; ?>
                    </tbody>
                    <tfoot>
                        <form method="post" target="_self">
                            <tr>
                                <th>
                                    <button class="btn btn-block btn-primary" type="submit" name="addDatabase">+</button>
                                </th>
                                <th colspan="2">
                                    <input class="form-control" type="text" name="dbName" placeholder="DB Name (SQL Database)" />
                                </th>
                                <th colspan="3">
                                    <input class="form-control" type="text" name="dbPath" placeholder="DB Path (../database.db)" />
                                </th>
                            </tr>
                        </form>
                    </tfoot>
                </table>

<?php   endif;  ?>
            </main>
        </div>
