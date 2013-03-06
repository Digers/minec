<?php
function execDBWithParams($con, $query, $params)
{
    $trans = $con->prepare($query);
    if($trans == false) die("Could not prepare statement");
    foreach ($params as $id => $value) {
        if($trans->bindValue(':' . $id, $value) == false){
            die("Could not bind value $value on $id");
        }

    }
    if($trans->execute() == false)
    {
        die('Execution failed');
    }
}
    $db = new SQLite3('../minec.db');

    /* Special case for bootstrap */
    if($_GET['a'] == 'bootstrap'){
        $query = $db->querySingle("SELECT content FROM Files WHERE name = 'bootstrap'");
        print($query);
        die();
    }

    /* Check key */
    if($_GET['apkey'] != 'm1nK3y')
    {
        die('key');
    }

    #$db->exec("INSERT INTO Keys (key, value) VALUES ('A', 'hello')");

    $key = $_GET['k'];

    switch($_GET['a'])
    {
        case 'insert':
            $value = $_GET['v'];
            $db->exec("INSERT INTO Keys (key, value) VALUES ('" . $key . "', '" . $value . "')");
            print('OK');
            break;

        case 'get':
            $query = $db->querySingle("SELECT value FROM Keys WHERE key = '" . $key . "'");
            print($query);
            break;

        case 'set':
            $value = $_GET['v'];
            $db->exec("UPDATE Keys SET value = '" . $value . "' WHERE key ='" . $key . "'");
            print("set");
            break;

        case 'put_file':
            $arguments = array('name' => $key, 'content' => $_POST['file']);
            $exists = $db->querySingle("SELECT 1 FROM Files WHERE name = '" . $key . "'") == true;
            $query = '';
            if($exists)
            {
                $query = "UPDATE Files SET content = :content WHERE name = :name";
            }
            else
            {
                $query = "INSERT INTO Files (name, content) VALUES (:name, :content)";
            }
            execDBWithParams($db, $query, $arguments);
            print('OK - ' . $arguments['content']);
            break;

        case 'get_file':
            $name = $_GET['v'];
            $query = $db->querySingle("SELECT content FROM Files WHERE name = '$name'");
            print($query);
            break;

        case 'wait_event':
            print('start');
            sleep(25);
            print('stop');
            break;
    }
?>
