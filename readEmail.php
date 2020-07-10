<?php
require_once(__DIR__ . '/vendor/autoload.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use GuzzleHttp\Client;

// Initialize the mailbox first
$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->overload();
$mailbox = new PhpImap\Mailbox(
	getenv('IMAP_SERVER'),
	getenv('IMAP_UNAME'),
	getenv('IMAP_PWORD'), 
	getenv('DOWNLOADS') ? getenv('DOWNLOADS') : __DIR__,
	getenv('IMAP_ENCODING')
);
$mailbox->setAttachmentsIgnore(true);

// Configure the database now
$config = array();
$config["hostname"] = getenv('DB_HOST');
$config["database"] = getenv('DB_NAME');
$config["username"] = getenv('DB_USER');
$config["password"] = getenv('DB_PWD');
$config["port"] = getenv('DB_PORT');
$config["charset"] = getenv('DB_CHARSET');
$config["exit_on_error"] = getenv('DB_EXIT_ON_ERROR');
$config["allow_logging"] = getenv('DB_ALLOW_LOGGING');

$db = new OBJ_mysql($config);

if(!$db){
	die("Database not reachable");
}

// Better be safe than sorry
$db->query("create table if not exists `".getenv('DB_TBL_NAME')."` (
	`id` int(11) unsigned NOT NULL auto_increment,
	`FROM_EMAIL` varchar(255) NOT NULL default '',
	`TO_EMAIL` varchar(255) NOT NULL default '',
	`UID` varchar(200) NOT NULL default'',
	`SENDER_NAME` varchar(255) NOT NULL,
	`AMOUNT` varchar(30) NOT NULL,
	`MEMO` varchar(500),
	`FULL_BODY` text,
	`EMAIL_MESSAGE_ID` varchar(500),
	`EMAIL_DATE` varchar(255) NOT NULL,
	PRIMARY KEY (EMAIL_MESSAGE_ID),
	KEY (id)
)");

// Fetch the emails now
try {
	$mail_ids = $mailbox->searchMailbox('FROM "' . getenv('SEARCH_EMAIL_FROM') . '"');
} catch(PhpImap\Exceptions\ConnectionException $ex) {
	echo "IMAP connection failed: " . $ex . PHP_EOL;
	die();
}

if(!$mail_ids) {
	die('Mailbox is empty');
}

// Start processing
$date = date("Y-m-d H:i:s");
echo "Beginning: $date" . PHP_EOL;
echo "Reading ".$mailbox->countMails() ." mails" . PHP_EOL;
foreach ( $mail_ids as $id ) {
	$mail = $mailbox->getMail($id, true);
	$subject = $mail->subject;
	echo "Email Suject -> " . $subject . PHP_EOL;
	if ( preg_match('/ sent you /',$subject) ) {
		handleChaseQuickPay($mailbox, $mail);
	}
	$mailbox->markMailAsRead($id);
}

echo "Ending: $date" . PHP_EOL;

// Helper functions
function saveDetails($objInsert){
	$save = $db->insert(getenv('DB_TBL_NAME'), array(
		`FROM_EMAIL`  => $objInsert['FROM_EMAIL'],
		`TO_EMAIL` => $objInsert['TO_EMAIL'],
		`UID` => $objInsert['UID'],
		`SENDER_NAME` => $objInsert['SENDER_NAME'],
		`AMOUNT` => $objInsert['AMOUNT'],
		`MEMO` => $objInsert['MEMO'],
		`FULL_BODY` => $objInsert['FULL_BODY'],
		`EMAIL_MESSAGE_ID` => $objInsert['EMAIL_MESSAGE_ID'],
		`date` => $objInsert['EMAIL_DATE']
	));
	if($save){
		echo "Saved in database" . PHP_EOL;;
	}
}

function _get($client,$url) {
	return $client->get($url);
}

function new_client() {
	$client = new Client([
		'timeout' => 20, 
		'verify' => false,
	]);
	return $client;
}

function get_html_from_url($url) {
	$client = new_client(); 
	try {
		$r = _get($client,$url);
		if ( $r->getStatusCode() != 200 ) {
			echo "Failed " . $r->getStatusCode() . ": " . $r->getReasonPhrase() . "\n" . PHP_EOL;
			return false;
		} 
	} catch ( \Exception $e ) {
		echo "Error: " . $e->getMessage() . "\n" . PHP_EOL;
		file_put_contents(__DIR__ . '/failed.log', print_r($e,true) . "\n", FILE_APPEND);
	}
	return $r->getBody();
}

function get_tag( $attr, $value, $xml, $tag=null ) {
	if( is_null($tag) )
		$tag = '\w+';
	else
		$tag = preg_quote($tag);

	$attr = preg_quote($attr);
	$value = preg_quote($value);

	$tag_regex = "/<(".$tag.")[^>]*$attr\s*=\s*".
	"(['\"])$value\\2[^>]*>(.*?)<\/\\1>/";

	preg_match_all($tag_regex,
		$xml,
		$matches,
		PREG_PATTERN_ORDER);

	return $matches[3];
}

function handleChaseQuickPay($mb, $mail){
	echo "_____" . __FUNCTION__ . "______". PHP_EOL;

	$saveDetail = array();

	echo "Subject -> ". $mail->subject . PHP_EOL;
	preg_match('/(?:\d{1,9}[,\.]?)+\d*/', $mail->subject, $match);
	if (!empty($match) && !empty($match[0]) ) {
		$saveDetail['AMOUNT'] = $match[0];
		echo "Got amount as " . $match[0] . PHP_EOL;
	}

	preg_match_all("/\b([A-Z-][\p{L}\pL]+)\b/", $mail->subject, $output_array);
	if (!empty($output_array) ) {
		$senderName = "";
		foreach ($output_array as $key => $value) {
			foreach ($value as $name) {
				$senderName .=  $name . " ";
				echo $name . PHP_EOL;
			}
		}
		$saveDetail['SENDER_NAME'] = $senderName;
	}

	$body = $mb->getMail($mail->id, true);
	if ($body->textHtml ) {
		$body = $body->textHtml;
	} else {
		$body = $body->textPlain;
	}
	$saveDetail['FULL_BODY'] = $body;
	$resp = get_tag("style", "vertical-align:top;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#000000", $body, "td");
	foreach ($resp as $value) {
		$val = strip_tags($value);
		$arVal = explode(":", $val);
		if($arVal && strpos($arVal[0], 'Payment details')){
    		// Payment details, nothing here
		}
		if($arVal && strpos($arVal[0], 'Amount')){
			$saveDetail['AMOUNT'] = $arVal[1];
		}
		if($arVal && strpos($arVal[0], 'Memo')){
			$saveDetail['Memo'] = $arVal[1];
		}
	}

	$newEmail = $mb->getMailsInfo(array($mail->id));
	foreach ($newEmail as $index => &$mail){
    	// uid, from, to, date, subject, message_id
		var_dump($mail);
		$saveDetail['UID'] = $mail->uid;
		$saveDetail['FROM_EMAIL'] = $mail->from;
		$saveDetail['TO_EMAIL'] = $mail->to;
		$saveDetail['EMAIL_DATE'] = $mail->date;
		$saveDetail['EMAIL_MESSAGE_ID'] = $mail->message_id;
	}
	saveDetails($saveDetail);
	echo "_____" . __FUNCTION__ . "______". PHP_EOL;
	return false;
}

?>
