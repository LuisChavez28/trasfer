<?php //https://www.youtube.com/watch?v=cjs39P7FR3s
require 'Slim/Slim.php';
require 'config.php';

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

$app->post('/', 'registerToken');
$app->get('/sendOneSMS', 'sendOneSMS');
$app->get('/sendGroupSMS', 'sendGroupSMS');
$app->post('/deleteToken','deleteToken');
$app->get('/testDB', 'testDB');
$app->run();

function registerToken() {
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    // Save Token
    $app = $data->app;
	$id = 'id_'.$data->id;
	$grupo = $data->grupo;
	$token = $data->token;
	$name = $data->name;
	if(empty($app) || !isset($app) || empty($id) || !isset($id) || empty($grupo) || !isset($grupo) ||empty($token) || !isset($token) ||empty($name) || !isset($name)){
		die('Faltan parametros');
	}
	$user = array($app => [
		$id => [
			'Grupo' => [
				$grupo
			],
			'Token' => [
				$token
			],
			'nombre' => $name
		]
	]);
	saveUsers($user,$app,$id);
	registerGroup($token, $grupo);
}

function testDB(){
	$app = "basica";
	$id = "id_10";
	$grupo = "HAckers";
	$token = "Token 2";
	$name = "jorge";

	$user = array($app => [
		$id => [
			'Token' => [
				$token
			],
			'nombre' => $name
		]
	]);
	$grupos = array('Groups' =>[
		$app => [
			$grupo => [
				'user_id' => $id
			]
		]
	]);
	// saveUsers($user, $app, $id);
	// registerGroup($grupos, $app, $id, $grupo);

	deleteToken();
}
	
function getUser(string $app, string $id){
	if(empty($app) || !isset($app) || empty($id) || !isset($id)){ return FALSE;}
	$db = getDB();

	if($db->getReference($app)->getSnapshot()->hasChild($id)){
		return $db->getReference($app)->getChild($id)->getValue();
	}else{
		return FALSE;
	}
}

function saveUsers(array $users, string $app, string $id){
	if(empty($users) || !isset($users) ||
		empty($app) || !isset($app) || empty($id) || !isset($id)){return FALSE;}
	$db = getDB();
	$data = json_decode(json_encode($users));

	if($user = getUser($app,$id)){
		foreach ($user as $key => $value) {
			if($key == "Token"){
				if(is_array($value)){
					if(!(in_array($data->$app->$id->Token[0],$value))){
						echo "El token no lo tiene en base<br>";
						array_push($value,$data->$app->$id->Token[0]);
						$db->getReference($app)->getChild($id)->getChild('Token')->set($value);
					}
				}
			}
			if($key == "nombre"){
				if(!($value == $data->$app->$id->nombre)){
					echo "El nombre cambia<br>";
					$db->getReference($app)->getChild($id)->getChild('nombre')->set($data->$app->$id->nombre);
				}
			}
		}
		echo "Ususario Actualizado";
	}else{
		$db->getReference($app)->getChild($id)->set($data->$app->$id);
		echo "Ususario Guardado";
	}
}

function registerGroup(string $token, string $grupo){
	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => "https://iid.googleapis.com/iid/v1/".$token."/rel/topics/".$grupo,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => "",
	  CURLOPT_HTTPHEADER => array(
	    "Authorization: key=",
	    "Content-Type: application/json"
	  ),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);
	$http = curl_getinfo($curl);
	curl_close($curl);

	if ($err) {
	   echo "cURL Error #:" . $err;
	} else {
		if($http['http_code'] === 200){
			echo " - GRUPO Correctamente registrado - ";
		}
	}
}

function deleteToken(){
	$request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    // Save Token
    $app = $data->app;
	$id = 'id_'.$data->id;
	$token = $data->token;

	if(empty($app) || !isset($app) ||
		empty($token) || !isset($token) || empty($id) || !isset($id)){return FALSE;}

	$db = getDB();
	try{
		$tokensDB = $db->getReference($app)->getChild($id)->getChild('Token')->getValue();
	
		if(count($tokensDB) != 1){
			$db->getReference($app)->getChild($id)->getChild('Token')->getChild(array_search($token, $tokensDB))->remove();
		}
	}catch (ApiException $e) {
	    echo $e;
	    die('Error 00057');
	}catch (ErrorException $ee){
		echo $ee;
		die('Error 00058');
	}
	echo "Token Borrado ! ------------------------------";
}

function sendOneSMS(){
	$request = \Slim\Slim::getInstance()->request();
	$app = $request->params('app');
    $id = $request->params('id');
    $title = $request->params('title');
    $body = $request->params('body');
    $icon = $request->params('icon');
    $to = getUser($app, 'id_'.$id);
    
    //echo "App: $app <br>para: $id<br> msg: $title <br>body: $body<br>";
    try{
		$to = json_encode($to['Token']);
		//echo "<br>TOKEN(s) : ".$to."<br>";
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_SSL_VERIFYPEER => false,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "{\n\t\"registration_ids\": ".$to.",\n\t\"notification\":{\n\t\t\"title\":\"".$title."\",\n\t\t\"body\":\"".$body."\",\n\t\t\"icon\":\"".$icon."\"\n\t}\n\t\n}",
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: key=",
	    "Content-Type: application/json"
		  ),
		));

		$response = json_decode(curl_exec($curl));
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
		  echo "cURL Error #555C";
		} else {
			//echo "<br> Sucess: ".$response->success." failure: ".$response->failure."<br>------------------------------<br>";
		}
    }catch(ApiException $ae){
    	//echo $ae;
    	//die('Error 00055');
	}catch(ErrorException $ee){
		//echo $ee;
		//die('Error 00056');
	}finally{
		//die();
	}
}

function sendGroupSMS(){
	$request = \Slim\Slim::getInstance()->request();
	$grupo = $request->params('grupo');
    $title = $request->params('title');
    $body = $request->params('body');
    $icon = $request->params('icon');

	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => "{\n\t\"to\": \"/topics/".$grupo."\",\n\t\"priority\" : \"high\",\n\t\"notification\":{\n\t\t\"title\":\"".$title."\",\n\t\t\"body\":\"".$body."\",\n\t\t\"icon\":\"".$icon."\"\n\t}\n\t\n}",
	  CURLOPT_HTTPHEADER => array(
	    "Authorization: key=",
	    "Content-Type: application/json"
	  ),
	));

	curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  //echo "cURL Error #:" . $err;
	} else {
	  //echo $response;
	}
}

?>