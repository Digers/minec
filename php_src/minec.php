<?php
require "config.php";
/* setup */
$db = new PDO('sqlite:../minec.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
* Authorization
*/
class Security
{
  private $con, $name, $key, $key_id;
  function __construct($con)
  {
    $this->con = $con;
    $key = $_GET['apkey'];
    $this->validate($key);
  }

  public function get_name()
  {
    return $this->name;
  }

  public function is_master()
  {
    return $this->master;
  }

  public function get_key_id()
  {
    return $this->key_id;
  }

  private function validate($key)
  {
    global $MASTER_KEY;
    if($key == $MASTER_KEY)
    {
      $this->master = true;
      $this->name = 'MASTER';
      $key = $_GET['tkey'];
      if ($key)
        $this->key = $key;
      return;
    } else {
      $this->master = false;
    }

    $keys = $this->con->prepare('SELECT key_id, key, key_name FROM Keys WHERE key = ?');
    $keys->execute(array($key));

    if($data = $keys->fetch(PDO::FETCH_ASSOC))
    {
      $this->key = $data['key'];
      $this->key_id = $data['key_id'];
      $this->name = $data['key_name'];
    }
    else
    {
      die('key');
    }
  }
}

/* Functions */
function execDBWithParams($con, $query, $params)
{
  $trans = $con->prepare(
    $query,
    array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY)
    );
  if($trans == false) die("Could not prepare statement");
  if($trans->execute($params) == false)
  {
    die('Execution failed');
  }
}

/*
add permissions for a state.
permissions can be either of the following: "", "R", "W", "RW"
*/
function bindStateWithKey($con, $key_id, $state, $permissions)
{
  $readable = false;
  $writeable = false;
  switch ($permissions) {
    case '':
    $trans = $con->prepare('DELETE FROM Keystates WHERE key_id = CAST(? as integer) AND state = ?');
    $trans->execute(array($key_id, $state));
    return;
    case 'RW':
    $readable = true;
    $writeable = true;
    break;
    case 'R':
    $readable = true;
    break;
    case 'W':
    $writeable = true;
    break;
    default:
    die('invalid_permission');
    return;
  }
  $trans = $con->prepare('INSERT OR REPLACE INTO Keystates
    (key_id, state, readable, writeable)
    VALUES (CAST(? as integer), ?, CAST(? as integer), CAST(? as integer))');
  $trans->execute(array($key_id, $state, $readable, $writeable));
}

/*
add permissions for a file.
as_name is an alias for the specific key
permissions can be either of the following: "R", "W", "RW"
*/
function bindFileWithKey($con, $key_id, $file_id, $alias, $permissions)
{
  $readable = false;
  $writeable = false;
  if($permissions == '' && $alias == ''){
    $trans = $con->prepare(
      'DELETE FROM Keyfiles
      WHERE key_id = CAST(? as integer)
      AND file_id = CAST(? as integer)');
    $trans->execute(array($key_id, $file_id));
    return;
  }
  switch ($permissions) {
    case '':
    break;
    case 'RW':
    $readable = true;
    $writeable = true;
    break;
    case 'R':
    $readable = true;
    break;
    case 'W':
    $writeable = true;
    break;
    default:
    die('invalid_permission');
    return;
  }
  $trans = $con->prepare('INSERT OR REPLACE INTO Keyfiles
    (key_id, file_id, readable, writeable, alias)
    VALUES (CAST(? as integer), CAST(? as integer), CAST(? as integer), CAST(? as integer), ?)');
  $trans->execute(array($key_id, $file_id, $readable, $writeable, $alias));
}

/*
list all files and their relations, suitable for administration
*/
function listFiles($con)
{
  $transaction = $con->prepare("SELECT file_id, name FROM Files");
  $transaction->execute();
  return $transaction->fetchAll(PDO::FETCH_ASSOC);
}

/*
list all states and their relations, suitable for administration
*/
function listStates($con)
{
  $transaction = $con->prepare("SELECT * FROM States");
  $transaction->execute();
  return $transaction->fetchAll(PDO::FETCH_ASSOC);
}

/*
list all keys
*/
function listKeys($con)
{
  $transaction = $con->prepare("SELECT * FROM Keys");
  $transaction->execute();
  return $transaction->fetchAll(PDO::FETCH_ASSOC);
}

function listKeyFileRelation($con)
{
  $transaction = $con->prepare("SELECT * FROM Keyfiles");
  $transaction->execute();
  return $transaction->fetchAll(PDO::FETCH_ASSOC);
}

function listKeyStateRelation($con)
{
  $transaction = $con->prepare("SELECT * FROM Keystates");
  $transaction->execute();
  return $transaction->fetchAll(PDO::FETCH_ASSOC);
}

/*
create a new unattached state.
To add permissions use the function bindStateWithKey()
*/
function createState($con, $state_name, $initial_value)
{
  $transaction = $con->prepare(
    'INSERT INTO States (state, value) VALUES (:state, :value)'
    );

  $transaction->execute(
    array(':state' => $state_name, ':value' => $initial_value)
    );
}

function getStatePermission($con, $key, $state)
{
  $transaction = $con->prepare(
    'SELECT readable, writeable FROM Keystates AS st
    JOIN Keys USING(key_id)
    JOIN States USING(state)
    WHERE st.state = :st AND key = :ke'
    );
  $transaction->execute(array(':st' => $state, ':ke' => $key));
  return $transaction->fetch(PDO::FETCH_ASSOC);
}

function getState($con, $key_id, $state)
{
  $transaction = $con->prepare(
    'SELECT value FROM States
    JOIN Keystates USING (state)
    WHERE (readable = 1 AND key_id = CAST(:key as integer) AND state = :state)');
  $transaction->execute(array(':key' => $key_id, ':state' => $state));
  return $transaction->fetchColumn();
}

function setState($con, $key_id, $state, $value)
{
  $transaction = $con->prepare(
    'UPDATE States
    SET value = :value
    WHERE state IN
    (SELECT state FROM Keystates
      WHERE writeable = 1
      AND key_id = CAST(:key as integer)
      AND state = :state
      )'
  );
  $transaction->execute(array(':value' => $value, ':key' => $key_id, ':state' => $state));
  return $transaction->fetchColumn();
}

function masterSetState($con, $state, $value)
{
  $transaction = $con->prepare(
    'UPDATE States SET value = :value WHERE state = :state'
    );
  $transaction->execute(array(':value' => $value, ':state' => $state));
  return $transaction->fetchColumn();
}

function getFile($con, $key_id, $name)
{
  $transaction = $con->prepare(
    "SELECT content FROM Files
    JOIN Keyfiles USING(file_id)
    WHERE (
      (name = :name AND (alias = '' OR alias IS NULL)) OR
      (alias = :name)
      ) AND readable = 1 and key_id = CAST(:key as integer) LIMIT 1"
  );
  $transaction->execute(array(':key' => $key_id, ':name' => $name));
  return $transaction->fetchColumn();
}

function setFile($con, $key_id, $name)
{

}

/* =========================================================
   =                                                       =
   =  Main execution                                       =
   =                                                       =
   =========================================================
*/

/* Special case for bootstrap */
if($_GET['a'] == 'bootstrap'){
  $transaction = $db->prepare("SELECT content FROM Files WHERE name = 'bootstrap'");
  $transaction->execute();
  print($transaction->fetchColumn());
  die();
}

/* Check key */
$key = new Security($db);

$k = $_GET['k'];

switch($_GET['a'])
{
  /*Debugging*/
  case 'testPermission':
  print_r(getStatePermission($db, $key, $k));
  break;

  case 'insert':
  $value = $_GET['v'];
  if(!$key->master)
    die('not master');
  createState($db, $k, $value);
  print('OK');
  break;

  case 'get':
  print(getState($db, $key->get_key_id(), $k));
  break;

  case 'set':
  $value = $_GET['v'];
  if($key->master)
    masterSetState($db, $k, $value);
  else
    setState($db, $key->get_key_id(), $k, $value);
  print("set");
  break;

  case 'put_file':
  $arguments = array('name' => $k, 'content' => $_POST['file']);
  $transaction = $db->prepare("SELECT 1 FROM Files WHERE name = ?");
  $transaction->execute(array($k));
  $exists = $transaction->fetchColumn();
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
  print('OK ');
  break;

  case 'get_file':
  $name = $_GET['v'];
  print(getFile($db, $key->get_key_id(), $name));
  break;

  case 'wait_event':
  print('start');
  sleep(25);
  print('stop');
  break;

  case 'list_files':
  if(!$key->is_master())
    die('list_files denied');
  print json_encode(listFiles($db));
  break;

  case 'list_keys':
  if(!$key->is_master())
    die('list_keys denied');
  print json_encode(listKeys($db));
  break;

  case 'list_states':
  if(!$key->is_master())
    die('list_states denied');
  print json_encode(listStates($db));
  break;

  case 'list_key_states':
  if(!$key->is_master())
    die('list_key_states denied');
  print json_encode(listKeyStateRelation($db));
  break;

  case 'list_key_files':
  if(!$key->is_master())
    die('list_key_files denied');
  print json_encode(listKeyFileRelation($db));
  break;

  case 'set_key_state':
  if(!$key->is_master())
    die('set_key_state denied');
  bindStateWithKey($db, $_REQUEST['key'],
    $_REQUEST['state'], $_REQUEST['permissions']);
  break;

  case 'set_key_file':
  if(!$key->is_master())
    die('set_key_state denied');
  bindFileWithKey($db, $_REQUEST['key'],
    $_REQUEST['file'], $_REQUEST['alias'], $_REQUEST['permissions']);
  break;

  case 'gui_sync':
  if(!$key->is_master())
    die('list_key_files denied');
  $everything = array(
    'Files'      => listFiles($db),
    'Keys'       => listKeys($db),
    'States'     => listStates($db),
    'Key-States' => listKeyStateRelation($db),
    'Key-Files'  => listKeyFileRelation($db)
    );
  print json_encode($everything);
  break;
}
if($_GET['a'] != 'GUI' || !$key->is_master())
  die()
/* =========================================================
   =                                                       =
   =  HTML Code                                            =
   =                                                       =
   =========================================================
*/
?>
<html>
<head>
  <title>MineC Adminstration</title>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
  <script src="knockout-2.2.1.js"></script>
  <link rel="stylesheet" type="text/css" href="mcstyle.css">
</head>
<body>
  <div id="main">
    <div id="upper">
      <h1>MineC administration util</h1>
      <h2 class="right">Logged in as <?php echo $key->get_name(); ?></h2>
      <div id="debug" style="display:none">
        <h3>debug options</h3>
        minec.php?key=<span>&amp;k=<input id="k_arg" type="text"></span>
      </div>
    </div>
    <script type="text/html" id="fileModifiers">
    <span data-bind="if: showFileModifiers">
    <a class="tagbtn2" href="" data-bind="css: fileRead, click: toggleReadFile">R</a>
    <a class="tagbtn2" href="" data-bind="css: fileWrite, click: toggleWriteFile">W</a>
    Alias: <a href="" data-bind="click: popupAlias, text:fileAlias">None</a>
    </span>
    </script>
    <script type="text/html" id="stateModifiers">
    <span data-bind="if: showStateModifiers">
    <a class="tagbtn2" href="" data-bind="css: stateRead, click: toggleReadState">R</a>
    <a class="tagbtn2" href="" data-bind="css: stateWrite, click: toggleWriteState">W</a>
    </span>
    </script>
    <div id="tabs">
      <a href="#" class="selectedTab">Control</a> <a href="#">Debug</a>
    </div>
    <div id="control" class="tab">
      <div class="column">
        <div class="rowHeader">Stored files<span class="actions">
          <a href="" data-bind="click: addFile">Add</a></span>
        </div>
        <span data-bind="foreach: files">
          <div class="row" data-bind="css: isSelected">
            <a href="#" data-bind="click: $root.selectItem">
              <span data-bind="text: name"></span>
            </a>
            <span class="itemActions" data-bind="template: {name: 'fileModifiers'}">
            </span>
          </div><a class="outOfBorderAction" data-bind="visible: isSelected" href="#">Delete</a><br>
        </span>
      </div>
      <div class="column">
        <div class="rowHeader">Stored keys<span class="actions">
          <a href="" data-bind="click: addKey">Add</a></span>
        </div>
        <span data-bind="foreach: keys">
          <div class="row" data-bind="css: isSelected">
            <a href="#" data-bind="click: $root.selectItem">
              <span data-bind="text: name"></span>
            </a>
            Key:<span data-bind="text: key"></span>
            <span class="itemActions" data-bind="template: {name: 'fileModifiers'}"></span>
            <span class="itemActions" data-bind="template: {name: 'stateModifiers'}"></span>
          </div><a class="outOfBorderAction" data-bind="visible: isSelected" href="#">Delete</a><br>
        </span>
      </div>
      <div class="column">
        <div class="rowHeader">Stored states<span class="actions">
          <a href="" data-bind="click: addState">Add</a></span>
        </div>
        <span data-bind="foreach: states">
          <div class="row" data-bind="css: isSelected">
            <strong><a href="#" data-bind="click: $root.selectItem, text: state"></a></strong>
            <a href="" data-bind="click: updateState">State:<span data-bind="text: value"></span></a>
            <span class="itemActions" data-bind="template: {name: 'stateModifiers'}">
            </div><a class="outOfBorderAction" data-bind="visible: isSelected" href="#">Delete</a><br>
          </span>
        </div>
      </div>
    </div>
    <div id="bottom">Statistics</div>
    <!--
       =========================================================
       =                                                       =
       =  Java script                                          =
       =                                                       =
       =========================================================
     -->
     <script>
      //$('#data').html('Hello world')
      function selectType(value){
        if(value)
          return 'selected'
        return ''
      }

      function send_cmd(cmd, args){
        return $.get("?", $.extend({'apkey': 'm1nK3y', 'a': cmd}, args))
      }

      function toggleStateHelper(model, key_item, state_item, toggleRead, toggleWrite){
        m = ko.utils.arrayFirst(model.keystates(), function(item){
          if(key_item.id != item.id) return false
            return item.state == state_item.state()
        })

      var new_relation = null
      if(m === null){ /* R = 0 & W = 0*/
        new_relation = new KeyStateRelation(model,
          key_item.id,
          state_item.state(),
          (toggleRead ? '1' : '0'),
          (toggleWrite ? '1' : '0')
          )
        model.keystates.push(new_relation)
      }else { /* R != 0 | W != 0*/
        if(toggleRead)
          m.readable(m.readable() == '0' ? '1' : '0')
        if(toggleWrite)
          m.writeable(m.writeable() == '0' ? '1' : '0')
      }
      f = m ? m : new_relation
      /* Update backbone */
      $.get("?", {'apkey': 'm1nK3y',
        'a': 'set_key_state', 'key': key_item.id,
        'state': state_item.state(),
        'permissions': (f.readable() == '1' ? 'R' : '') +
        (f.writeable() == '1' ? 'W' : '') },
        function(data) {
          if(data != '')
            alert("Data Loaded: " + data)
        })
        /* Remove element */
        if(f.readable() == '0' && f.writeable() == '0'){
          model.keystates.remove(m)
        }
      }

      function toggleFileHelper(model, key_item, file_obj, toggleRead, toggleWrite, alias){
        m = ko.utils.arrayFirst(model.keyfiles(), function(item){
          if(key_item.id != item.id) return false
            return item.file_id == file_obj.id
        })
        var new_relation = null
        if(m === null){ /* R = 0 & W = 0*/
          new_relation = new KeyFileRelation(model,
            key_item.id,
            file_obj.id,
            (toggleRead ? '1' : '0'),
            (toggleWrite ? '1' : '0'),
            (alias == null ? '' : alias)
            )
          model.keyfiles.push(new_relation)
        }else { /* R != 0 | W != 0*/
          if(toggleRead)
            m.readable(m.readable() == '0' ? '1' : '0')
          if(toggleWrite)
            m.writeable(m.writeable() == '0' ? '1' : '0')
          if(alias != null)
            m.alias(alias)
        }
        f = m ? m : new_relation
        /* Update backbone */
        $.get("?", {'apkey': 'm1nK3y',
          'a': 'set_key_file',
          'key': key_item.id,
          'file': file_obj.id,
          'alias': f.alias(),
          'permissions': (f.readable() == '1' ? 'R' : '') +
          (f.writeable() == '1' ? 'W' : '') },
          function(data) {
            if(data != '')
              alert("Data Loaded: " + data)
          })
        /* Remove element */
        if(f.readable() == '0' && f.writeable() == '0' && f.alias() == ''){
          model.keyfiles.remove(m)
        }
      }

      function getKeyStateRelation(self, model, state_obj, key_obj, type){
        if (!self.showStateModifiers())
          return 'gray'
        m = ko.utils.arrayFirst(model.keystates(), function(item){
          if(key_obj.id != item.id) return false
            return item.state == state_obj.state()
        })
        if(m === null) return 'off'
          if(type == 'R')
            return (m.readable() == '1') ? 'on' : 'off'
          if(type == 'W')
            return (m.writeable() == '1') ? 'on' : 'off'
        }

      function getKeyFileRelation(self, model, file_obj, key_obj, type){
        if (!self.showFileModifiers())
          return 'gray'
        m = ko.utils.arrayFirst(model.keyfiles(), function(item){
          if(key_obj.id != item.id) return false
            return item.file_id == file_obj.id
        })
        if(m === null) return 'off'
          if(type == 'R')
            return (m.readable() == '1') ? 'on' : 'off'
          if(type == 'W')
            return (m.writeable() == '1') ? 'on' : 'off'
          if(type == 'ALIAS')
            return (m.alias() != '') ? m.alias() : 'off'
        }

        var model = null

        function keyStateModelBindings(parrent, model, key_id, state_id){

        }

      /*
      ===============
      Models
      ===============
      */
      var File = function(model, id, name){
        var self = this
        this.type = 'File'
        this.id = id
        this.name = ko.observable(name)
        self.isSelected = ko.computed(function() {
          return selectType(model.selected_obj() == self)
        })

        self.showFileModifiers = ko.computed(function(){
          m = model.selected_obj()
          if(m === undefined)
            return false
          return m.type === 'Key'
        })

        self.fileRead = ko.computed(function(){
          return getKeyFileRelation(self, model, self, model.selected_obj(), 'R')
        })

        self.fileWrite = ko.computed(function(){
          return getKeyFileRelation(self, model, self, model.selected_obj(), 'W')
        })
        self.toggleReadFile = function(){
          toggleFileHelper(model, model.selected_obj(), self, true, false, null)
        }
        self.toggleWriteFile = function(){
          toggleFileHelper(model, model.selected_obj(), self, false, true, null)
        }
        self.fileAlias = ko.computed(function(){
          return getKeyFileRelation(self, model, self, model.selected_obj(), 'ALIAS')
        })
        self.popupAlias = function(){
          alias = window.prompt("Alias",
            getKeyFileRelation(self, model, self, model.selected_obj(),
              'ALIAS'
              )
            );
          if(alias != null)
            toggleFileHelper(model, model.selected_obj(), self, false, false, alias)
        }
      }

      var Key = function(model, id, name, key){
        var self = this
        this.type = 'Key'
        this.id = id
        this.name = ko.observable(name)
        this.key = ko.observable(key)
        this.remoteSelected = ko.computed(function(){
          return true;
        })
        this.associated_states = ko.observableArray()
        self.isSelected = ko.computed(function() {
          return selectType(model.selected_obj() == self)
        })
        this.connectState = function(state){
          model.keystates
        }
        self.showFileModifiers = ko.computed(function(){
          m = model.selected_obj()
          if(m === undefined)
            return false
          return m.type === 'File'
        })
        self.showStateModifiers = ko.computed(function(){
          m = model.selected_obj()
          if(m === undefined)
            return false
          return m.type === 'State'
        })

        self.stateRead = ko.computed(function (){
          return getKeyStateRelation(self, model, model.selected_obj(), self, 'R')}
          )
        self.stateWrite = ko.computed(function (){
          return getKeyStateRelation(self, model, model.selected_obj(), self, 'W')}
          )
        self.toggleReadState = function(){
          toggleStateHelper(model, self, model.selected_obj(), true, false)
        }
        self.toggleWriteState = function(){
          toggleStateHelper(model, self, model.selected_obj(), false, true)
        }
        self.fileRead = ko.computed(function(){
          return getKeyFileRelation(self, model, model.selected_obj(), self, 'R')
        })
        self.fileWrite = ko.computed(function(){
          return getKeyFileRelation(self, model, model.selected_obj(), self, 'W')
        })
        self.toggleReadFile = function(){
          toggleFileHelper(model, self, model.selected_obj(), true, false, null)
        }
        self.toggleWriteFile = function(){
          toggleFileHelper(model, self, model.selected_obj(), false, true, null)
        }
        self.fileAlias = ko.computed(function(){
          return getKeyFileRelation(self, model, model.selected_obj(), self, 'ALIAS')
        })
        self.popupAlias = function(){
          alias = window.prompt("Alias",
            getKeyFileRelation(self, model, model.selected_obj(),
              self, 'ALIAS'
              )
            );
          if (alias != null)
            toggleFileHelper(model, self, model.selected_obj(), false, false, alias)
        }
      }

      var State = function(model, name, value){
        var self = this
        this.type = 'State'
        this.state = ko.observable(name)
        this.value = ko.observable(value)
        this.associated_keys = ko.observableArray()
        self.isSelected = ko.computed(function() {
          return selectType(model.selected_obj() == self)
        })
        self.showStateModifiers = ko.computed(function(){
          m = model.selected_obj()
          if(m === undefined)
            return false
          return m.type === 'Key'
        })

        self.stateRead = ko.computed(function (){
          return getKeyStateRelation(self, model, self, model.selected_obj(), 'R')}
          )
        self.stateWrite = ko.computed(function (){
          return getKeyStateRelation(self, model, self, model.selected_obj(), 'W')}
          )
        self.toggleReadState = function(){
          toggleStateHelper(model, model.selected_obj(), self,  true, false)
        }
        self.toggleWriteState = function(){
          toggleStateHelper(model, model.selected_obj(), self, false, true)
        }
        self.updateState = function(){
          value = window.prompt("State",
            self.value()
            );
          if (value != null){
            self.value(value)
            send_cmd('set', {'k': self.state(), 'v': value})
          }
        }
      }

      var KeyStateRelation = function(model, id, state, readable, writeable){
        var self = this
        this.id = id
        this.state = state
        this.readable = ko.observable(readable)
        this.writeable = ko.observable(writeable)
      }

      var KeyFileRelation = function(model, key_id, file_id, readable, writeable, alias){
        var self = this
        this.id = key_id
        this.file_id = file_id
        this.readable = ko.observable(readable)
        this.writeable = ko.observable(writeable)
        this.alias = ko.observable(alias)
      }

      var ViewModel = function(){
        var self = this
        this.selected_obj = ko.observable()
        this.files = ko.observableArray()
        this.keys = ko.observableArray()
        this.states = ko.observableArray()
        this.keystates = ko.observableArray()
        this.keyfiles = ko.observableArray()
        this.selectItem = function(item) {
          if(item === self.selected_obj())
            self.selected_obj("null")
          else
            self.selected_obj(item)
        }
        this.addFile = function() {
          alert("Not implemented")
        }
        this.addKey = function() {
          alert("Not implemented")
        }
        this.addState = function() {
          console.log("Create new state")
          value = window.prompt("State name"
            );
          if (value != null){
            send_cmd('insert', {'k': value, 'v': ''})
            self.states.push(new State(self, value, ''))
          }
        }
      }

      model = new ViewModel()
      ko.applyBindings(model)

      $.getJSON("?apkey=m1nK3y&a=gui_sync", function(data){
        $.each(data['Keys'], function(row, content){
          model.keys.push(new Key(model, content['key_id'], content['key_name'], content['key']))
        })
        $.each(data['Files'], function(row, content){
          model.files.push(new File(model, content['file_id'], content['name']))
        })
        $.each(data['States'], function(row, content){
          model.states.push(new State(model, content['state'], content['value']))
        })
        $.each(data['Key-States'], function(row, content){
          model.keystates.push(new KeyStateRelation(model, content['key_id'],
            content['state'],
            content['readable'],
            content['writeable']
            ))
        })
        $.each(data['Key-Files'], function(row, content){
          model.keyfiles.push(new KeyFileRelation(model, content['key_id'],
            content['file_id'],
            content['readable'],
            content['writeable'],
            content['alias']
            ))
        })
      })
    </script>
  </body>
</html>