<?php
require '../config.php';

//  holds the code from the previous form
$data = $_SESSION['data'];

//  regulate the available form fields
$fields = ['name', 'email', 'username', 'password', 'domain'];

$form = [];

//  prepopulate the form
foreach ($fields as $field) {
	$form[$field] = ((isset($_SESSION['form'][$field])) ? $_SESSION['form'][$field] : '');
}

$form['domains'] = [];  //  filed on line 51

//  form submitted
if (!empty($_POST)) {
	//  keep only the keys we allow
	$data = array_intersect_key($_POST, array_flip($fields));

	//  all fields are required
	foreach ($fields as $field) {
		if (empty($data[$field])) {
			$_SESSION['msg'] = [
				'text' => 'All fields are required.',
				'type' => 'danger'
			];
			header ('Location: /postfix-reg/register/');
			exit;
		}
	}

	//  open a db connection
	require '../libs/sparrow/sparrow.php';
	$db = new Sparrow();
	$db->setDb($cfg['db']);

	//  check if already claimed
	$exists = $db->from('mailbox')->where(['local_part' => $data['username'], 'domain' => $data['domain']])->one();
	if (!empty($exists)) {
		$_SESSION['msg'] = [
			'text' => $data['username'] . '@' . $data['domain'] . ' is not available.',
			'type' => 'danger'
		];
		unset($data['password']);
		$_SESSION['form'] = $data;
		header ('Location: /postfix-reg/register/');
		exit;
	}

	$out = $db->from('mailbox')->insert([
		'username'   => $data['username'] . '@' . $data['domain'],
		'password'   => '',
		'name'       => $data['name'],
		'maildir'    => $data['domain'] . '/' . $data['username'],
		'quota'      => 0,
		'local_part' => $data['username'],
		'domain'     => $data['domain'],
		'created'    => date('Y-m-d H:i:s'),
		'modified'   => date('Y-m-d H:i:s'),
		'active'     => 1
	])->execute();

	$out = $db->sql(sprintf('UPDATE mailbox SET password = encrypt(%s) WHERE username = %s', $db->quote($data['password']), $db->quote($data['username'] . '@' . $data['domain'])))->execute();

	echo'<pre>';print_r($out);exit;
}

//  check if this code is valid
try {
	$file = file_get_contents('../codes.txt');

	$lines = explode("\n", $file);

	if (empty($file) || empty($lines)) {
		error_log('line ' . __LINE__ . ' ' . __FILE__ . ': error opening codes.txt');
		$_SESSION['msg'] = [
			'text' => 'An error was encountered.',
			'type' => 'danger'
		];
		header ('Location: /postfix-reg/register/');
		exit;
	}

	$codes = [];
	foreach ($lines as $line) {
		list($code, $domains) = explode(':', $line);

		$codes[trim($code)] = array_map('trim', explode(' ', trim(str_replace(';', '', $domains))));
	}

	//  check that this code exists
	if (empty($codes[$data['code']])) {
		$_SESSION['msg'] = [
			'text' => 'That code is no longer valid.',
			'type' => 'danger'
		];
		header ('Location: /postfix-reg/');
		exit;
	} else {
		$form['domains'] = $codes[$data['code']];
	}
} catch (Exception $e) {
	error_log('line ' . __LINE__ . ' ' . __FILE__ . ': ' . $e->getMessage());
	$_SESSION['msg'] = [
		'text' => 'An error was encountered.',
		'type' => 'danger'
	];
	header ('Location: /postfix-reg/register/');
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>Registration</title>

	<link href="/postfix-reg/css/bootstrap.min.css" rel="stylesheet" />
	<link href="/postfix-reg/css/app.css" rel="stylesheet" />

	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
	<script src="//oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
	<script src="//oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
</head>
<body>
<div class="container">
	<div class="row">
		<div class="col col-xs-6 col-xs-offset-3">
			<?php if (!empty($_SESSION['msg'])) { ?>
			<div class="alert alert-<?php echo $_SESSION['msg']['type']; ?>"><?php echo $_SESSION['msg']['text']; ?></div>
			<?php unset($_SESSION['msg']);} ?>
			<form method="POST" action="/postfix-reg/register/">
				<div class="form-group">
					<label for="name">Name</label>
					<input type="text" name="name" id="name" class="form-control" placeholder="your name" required="required" />
				</div>
				<div class="form-group">
					<label for="email">Current Email</label>
					<input type="email" name="email" id="email" class="form-control" placeholder="your current email" required="required" />
				</div>
				<div class="form-group">
					<label for="username">Desired Username</label>
					<input type="text" name="username" id="username" class="form-control" placeholder="username@domain.com" required="required" />
				</div>
				<div class="form-group">
					<label for="password">Password</label>
					<input type="password" name="password" id="password" class="form-control" required="required" />
				</div>
				<div class="form-group">
					<label for="domain">Domain</label>
					<select name="domain" id="domain" class="form-control" required="required">
						<option></option>
						<?php foreach ($form['domains'] as $domain) { ?>
						<option value="<?php echo $domain; ?>"><?php echo $domain; ?></option>
						<?php } ?>
					</select>
				</div>

				<button type="submit" class="btn btn-primary">Register</button>
			</form>
		</div>
	</div>
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="/postfix-reg/js/bootstrap.min.js"></script>
</body>
</html>