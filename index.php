<?php
require 'config.php';

//  setup vars we will use
$form = [
 	'code' => null
];

if (!empty($_GET['code'])) {
	$form['code'] = urldecode($_GET['code']);
}

//  form submitted
if (!empty($_POST)) {
	//  keep only the keys we allow
	$data = array_intersect_key($_POST, array_flip(['code', 'g-recaptcha-response']));

	if ($cfg['recaptcha']['enabled']) {
		//  verify not a robot
		require 'libs/php-curl-class/src/Curl/Curl.php';

		$curl = new \Curl\Curl();
		$recaptcha = $curl->post($cfg['recaptcha']['endpoint'], array(
			'secret'   => $cfg['recaptcha']['secret'],
			'response' => $data['g-recaptcha-response'],
			'remoteip' => $_SERVER['REMOTE_ADDR']
		));

		//  found a bot
		if (empty($recaptcha->success) || !$recaptcha->success) {
			error_log('line ' . __LINE__ . ' ' . __FILE__ . ': bot found');
			$_SESSION['msg'] = [
				'text' => 'An error was encountered.',
				'type' => 'danger'
			];
			header ('Location: /postfix-reg/');
			exit;
		}
	}

	//  check if this code is valid
	try {
		$file = file_get_contents('codes.txt');

		$lines = explode("\n", $file);

		if (empty($file) || empty($lines)) {
			error_log('line ' . __LINE__ . ' ' . __FILE__ . ': error opening codes.txt');
			$_SESSION['msg'] = [
				'text' => 'An error was encountered.',
				'type' => 'danger'
			];
			header ('Location: /postfix-reg/');
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
		}

		$_SESSION['data'] = $data;
		header ('Location: /postfix-reg/register/');
		exit;
	} catch (Exception $e) {
		error_log('line ' . __LINE__ . ' ' . __FILE__ . ': ' . $e->getMessage());
		$_SESSION['msg'] = [
			'text' => 'An error was encountered.',
			'type' => 'danger'
		];
		header ('Location: /postfix-reg/');
		exit;
	}
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
			<form method="POST" action="/postfix-reg/">
				<div class="form-group">
					<label for="code">Invite Code</label>
					<input type="text" class="form-control" id="code" name="code" value="<?php echo htmlentities($form['code']); ?>" placeholder="enter the code you received..." />
				</div>

				<?php if ($cfg['recaptcha']['enabled']) { ?>
				<div class="form-group">
					<div class="g-recaptcha" data-sitekey="6LcLdgoTAAAAACxPfRocLkcXbAG8nFsKVVzq2UuX"></div>
				</div>
				<?php } ?>

				<button type="submit" class="btn btn-primary">Activate Email</button>
			</form>
		</div>
	</div>
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="/postfix-reg/js/bootstrap.min.js"></script>
<?php if ($cfg['recaptcha']['enabled']) { ?><script src="//www.google.com/recaptcha/api.js"></script><?php } ?>
</body>
</html>