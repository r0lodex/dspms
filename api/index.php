<?php

//load routing library
require('Horus.php');

// instantiate app object
$app = new Horus();

// function to create db connection
$app->dbc = function(){
	$dbuser = 'root';
	$dbpass = '';
	try {
	    $dbh = new PDO("mysql:host=localhost;dbname=dspms", $dbuser, $dbpass);
	    return $dbh;
	}catch(PDOException $e){
	    echo $e->getMessage();
	}
};

//function to validate access
$app->auth = function($token,$uid){

	// query db for token validation
	$sql = 'SELECT id FROM user WHERE access_token=:token AND id=:id LIMIT 1';
	// bind parameter
	$prm = [
		'id' => $uid,
		':token' => $token
	];

	$dbh = $this->dbc();
	$qry = $dbh->prepare($sql);
	$qry->execute($prm);
	$res = $qry->fetch(PDO::FETCH_ASSOC);

	if(empty($res)){
		return false;
	}else{
		return true;
	}
};

//function to output error
$app->out = function($msg,$code){
	if(is_array($msg)){
		$this->end(json_encode($msg),$code);
	}else{
		$this->end(json_encode(['msg' => $msg]),$code);
	}
};

//api for user login ------->  "/api/login"
$app->on('POST /login', function(){

	$username = $this->body['username'];
	$password = $this->body['password'];

	// db sql query to check user and password
	$sql = 'SELECT id FROM user WHERE username=:uname AND password=:upass LIMIT 1';
	// query parameter
	$prm = [
		':uname' => $username,
		':upass' => $password
	];
	// create db connection and executing query
	$dbh = $this->dbc();
	$qry = $dbh->prepare($sql);
	$qry->execute($prm);
	$res = $qry->fetch(PDO::FETCH_ASSOC);

	if(!empty($res)) {
		$token = md5(time());
		// update query to append access token
		$sql = 'UPDATE user SET access_token=:token WHERE id=:id';
		// query parameter
		$prm = [
			':id' => $res['id'],
			':token' => $token
		];
		// create db connection and executing query
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);
	}

	// creating json output
	$out = [
		'id' => $res['id'],
		'token' => $token
	];

	// send output
	$this->out($out,202);
});

//api to get all user ------->  "/api/user"
$app->on('GET /user', function(){
	$token = (isset($this->query->token)) ? $this->query->token: $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid: $this->out('uid is required',400);
	if(!$this->auth($token,$uid) && $uid != 1){
		$this->out('access unauthorized',400);
	}else{
		//select all user from db
		$sql = 'SELECT id,username FROM user WHERE id>1';
		// connect to db and execute
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute();

		//get results
		$res = $qry->fetchAll(PDO::FETCH_ASSOC);

		// send output
		$this->out($res,200);
	}
});

//api for add user ------->  "/api/user"
$app->on('POST /user', function(){
	$token = (isset($this->query->token)) ? $this->query->token: $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid: $this->out('uid is required',400);
	if(!$this->auth($token,$uid)){
		$this->out('access unauthorized',400);
	}else{
		$username = $this->body['username'];
		$password = $this->body['password'];

		//check if user already existed
		$sql = 'SELECT id FROM user WHERE username=:uname LIMIT 1';
		// query parameter
		$prm = [
			':uname' => $username
		];
		// create db connection and executing query
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);
		$res = $qry->fetch(PDO::FETCH_ASSOC);

		if(!empty($res)) {
			$this->out('username already taken.',200);
		}else{
			// db sql query to insert user and password
			$sql = 'INSERT INTO user (username, password) VALUES (:uname, :upass) ';
			// query parameter
			$prm = [
				':uname' => $username,
				':upass' => $password
			];
			// create db connection and executing query
			$dbh = $this->dbc();
			$qry = $dbh->prepare($sql);
			$qry->execute($prm);

			// send output
			$this->end('new user created!',201);
		}
	}
});

//api for update user ------->  "/api/user/{id}"
$app->on('PUT /user/([0-9]+)', function($id){
	$token = (isset($this->query->token)) ? $this->query->token: $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid: $this->out('uid is required',400);
	$password = (isset($this->body['password']) && !empty($this->body['password'])) ? $this->body['password']: $this->out('password is required',400);

	if(!$this->auth($token,$uid)){
		$this->out('access unauthorized',400);
	}else{
		if($uid == 1) {
			// db query to update user for admin
			$sql = 'UPDATE user SET password=:upass WHERE id=:id';
			// bind parameter
			$prm = [
				':id' => $id,
				':upass' => $password
			];
		}else{
			// db query to update user for the user itself
			$sql = 'UPDATE user SET password=:upass WHERE id=:id AND access_token=:token';
			// bind parameter
			$prm = [
				':id' => $id,
				':upass' => $password,
				':token' => $token
			];
		}

		// connect to db and execute query
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);

		// send output
		$this->out('password update successful!',202);
	}
});

//api for delete user ------->  "/api/user/{id}"
$app->on('DELETE /user/([0-9]+)', function($id){
	$token = (isset($this->query->token)) ? $this->query->token: $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid: $this->out('uid is required',400);
	if(!$this->auth($token,$uid) || $uid != 1){
		$this->out('access unauthorized',400);
	}elseif($id == 1){
		$this->out('Cant delete default user',401);
	}else{
		//db query to delete user
		$sql = 'DELETE FROM user WHERE id=:id';
		//set parameter
		$prm = [
			':id'=>$id
		];
		//connect db and execute
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);

		// send output
		$this->out('user deleted!',202);
	}
});




/****************
*   PARCEL API  *
*****************/

//api for all parcel ------> "/api/parcel"
$app->on('GET /parcel', function(){
	$sql = 'SELECT * FROM parcel';
	$dbh = $this->dbc();
	$qry = $dbh->prepare($sql);
	$qry->execute();

	$res = $qry->fetchAll(PDO::FETCH_ASSOC);

	//send output
	$this->out($res,200);
});

//api for parcel based on status ------> "/api/parcel/{parcel_status}"
$app->on('GET /parcel/([0-9]+)', function($state){
	$sql = 'SELECT * FROM parcel WHERE status = :a';
	$prm = [':a' => $state];
	$dbh = $this->dbc();
	$qry = $dbh->prepare($sql);
	$qry->execute($prm);

	$res = $qry->fetchAll(PDO::FETCH_ASSOC);

	//send output
	$this->out($res,200);
});

//api for add parcel ------->  "/api/parcel"
$app->on('POST /parcel', function(){
	$token = (isset($this->query->token)) ? $this->query->token: $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid: $this->out('uid is required',400);
	if(!$this->auth($token,$uid) || $uid != 1){
		$this->out('access unauthorized',400);
	}else{

		$parcel = (isset($this->body['parcel'])) ? $this->body['parcel'] : $this->out('parcel is required',400);
		$data = json_decode($parcel);

		// get data
		$data['student_name'] = (isset($this->body['student_name'])) ? $this->body['student_name']:null;
		$data['student_id'] = (isset($this->body['student_id'])) ? $this->body['student_id']:null;
		$data['parcel_id'] = (isset($this->body['parcel_id'])) ? $this->body['parcel_id']:null;

		// db sql query to add parcel
		$sql = 'INSERT INTO parcel (student_id, student_name, parcel_id, date_in, status) VALUES (:sid, :sname, :parcel, :date_in, :status) ';
		// query parameter
		$prm = [
			':sid' => $data['student_id'],
			':sname' => $data['student_name'],
			':parcel' => $data['parcel_id'],
			':date_in' => time(),
			':status' => ParcelStatus::RECEIVED
		];

		// create db connection and executing query
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);

		// send output
		$this->end('parcel added!',201);

	}
});

//api for update parcel ------->  "/api/parcel/{id}"
$app->on('PUT /parcel/([0-9]+)', function($pid) {
	$token = (isset($this->query->token)) ? $this->query->token : $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid : $this->out('uid is required',400);

	$data = $this->body;

	if(!$this->auth($token,$uid) || $uid != 1){
		$this->out('access unauthorized',400);
	}else{
		$prm = [];
		$sql = 'UPDATE parcel SET ';
		foreach($data as $k => $v) {
			$sql.= $k.'=:'.$k.', ';
			$prm[':'.$k] = $v;
		}
		$sql = substr($sql, 0, -2);
		$sql.= ' WHERE id=:id';
		$prm[':id'] = (int)$pid;

		// connect to db and execute query
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);

		//send output
		$this->out('parcel updated',202);
	}
});

//api for delete parcel ------->  "/api/parcel/{id}"
$app->on('DELETE /parcel/([0-9]+)', function($pid){
	$token = (isset($this->query->token)) ? $this->query->token: $this->out('token is required',400);
	$uid = (isset($this->query->uid)) ? $this->query->uid: $this->out('uid is required',400);
	if(!$this->auth($token,$uid) || $uid != 1){
		$this->out('access unauthorized',400);
	}else{
		//db query to soft delete parcel
		$sql = 'UPDATE parcel SET status=:state WHERE id=:id';
		//set parameter
		$prm = [
			':state'=>ParcelStatus::CLAIMED,
			':id'=>$pid
		];
		//connect db and execute
		$dbh = $this->dbc();
		$qry = $dbh->prepare($sql);
		$qry->execute($prm);

		//send output
		$this->out('parcel deleted!',202);
	}
});

//default route
$app->on('/:*', function(){
	$out = json_encode([
		'err' => true,
		'msg' => 'cannot find any matched route !'
	]);
    $this->end($out,404);
});


// constant for parcel status
class ParcelStatus{
	const UNKNOWN     = 0;
	const RECEIVED    = 1;
	const CLAIMED     = 2;
}