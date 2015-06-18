<?php

include "include/config.ini";
include "include/Agency.php";
include "include/Genome.php";

// Get our Genome
$genome = new Genome($genome_root);

$mysqli = new mysqli($dbname,$dbuser,$dbpass,"alert");

if ($mysqli->connect_error) {

  echo ("Failed to connect to the database " . mysqli_connect_error());
  die;
}

// Define the globals
$agencies = [];
$has_parser = [];
$needs_parser = [];
$abandoned = [];

// Get all our unactivated agencies
$result = $mysqli->query("SELECT id,name,parser_id,parser_args,address,city,state,zip,country,lat,lon,status,trial_expiration,subscription_id FROM agencies WHERE parser_id=0");

foreach ($result as $row){

  $agencies[$row['id']] = new Agency($row['id'],$row['name'],$row['parser_id'],$row['parser_args'],$row['address'],$row['city'],$row['state'],$row['zip'],$row['country'],$row['lat'],$row['lon'],$row['status'],strtotime($row['trial_expiration']),$row['subscription_id']);

}

$result->close();

// We need to know which agencies have received alerts in the last month
$stmt = ("SELECT alerts.agency_id,alerts.id,alerts.received FROM alerts LEFT JOIN agencies on alerts.agency_id = agencies.id WHERE agencies.parser_id = 0 AND alerts.source = '' AND alerts.received >= DATE_ADD(NOW(),INTERVAL -1 MONTH)");

$result = $mysqli->query($stmt);

foreach ($result as $row){

  if (array_key_exists($row['agency_id'],$agencies)){
    // Save the alarm to its agency
    $agencies[$row['agency_id']]->save_alert([

      'id' => $row['id'],
      'received' => $row['received'],
      'received_utc' => strtotime($row['received'])
      ]);

	} 
}

$result->close();

foreach ($agencies as $agency){

  $alerts = $agency->get_alerts();

	// Agency must have some alerts to test
  if (!empty($alerts)){
		
		//Get a comma separated list of alerts and genes for poly
    $alert_string = $agency->get_alerts_to_string();
    $genes = $genome->get_genes_by_key('state',$agency->get_state());

    // If the country is blank, manually check this agency.
    if (strlen($genes) == 0) {

			//echo("Could not find any possible genes, manually check it<br>");
			$needs_parser[] = $agency;

    }else {
			
			// Define the command to check all the parsers against each alarm
      $gene_cmd = "$gene_location scan $alert_string $genes";

		}
		// Run the command, decoding the results into JSON
    ob_start();
    passthru($gene_cmd);
    $gene_return = json_decode(ob_get_contents(),TRUE);
    ob_end_clean();
  
    // Empty result set means we need to build a parser
    if (empty($gene_return)){

			$needs_parser[] = $agency;

    // Otherwise, prioritize results based on returned parser score
    } else {

      foreach ($gene_return as $key => $gene) {

        $number = 0;
				$aggregate_score = 0;
				$total_iterations = count($agency->get_alerts());

        foreach ($gene as $alert){

          // SUCCESS denotes successful parsing
					if (isset($alert['SUCCESS']) && !empty($alert['SUCCESS'])){

						// Zero denotes failure
						if ($alert['SCORE'] != 0){

							$number++;
							$aggregate_score += $alert['SCORE'];
						}

					}
				}

				// Successful parsing puts the parser in suggested_parsers
				if ($number > 0){
					$average_score = $aggregate_score / $number;

					$average_success = ($number / $total_iterations) * 100;

					// Needs to be successful at least 85% of the time to be considered as a parser
					if ($average_success > 65){

						echo("$key was successful >65% of the time ($average_success).\n");
						$agency->save_suggested_parser($key.",Utility/General/Default",$average_score,$alert_string,$average_success);
					} else {
						echo("$key was not successful >65% of the time ($average_success).\n");
					}
				} 

			}

			// Zero suggested parsers also means agency needs a parser
			if (count($agency->get_suggested_parsers()) == 0){

				$needs_parser[] = $agency;

			} else {

				$has_parser[] = $agency;

			}

		}

		// No Tests yet
	} else {
		$abandoned[] = $agency;
	}
}

// Process the has parser group for the email
$message = '';
$message .= "\r\nThere are ".count($has_parser)." agencies that need to be assigned a parser:\r\n";

// Iterate through each agency that has a suggested parser
foreach ($has_parser as $name => $agency){

	$message .= $agency->get_name().", ".$agency->get_id().", in ". $agency->get_city().", ". $agency->get_state()."\r\nSuggested parsers:\r\n";

	foreach($agency->get_suggested_parsers() as $parser => $values){

		$message .=("     ".$parser." has an average success of ".$values['average_success']." and an average score of ".$values['score']."\r\n");
	};

}


// Next up is needs a parser
$message .= "\r\n\r\n\r\n\r\nThere are ".count($needs_parser)." that have started sending messages:\r\n";
foreach ($needs_parser as $name => $agency){

	$message .= $agency->get_name().", ".$agency->get_id().", in ". $agency->get_city().", ".$agency->get_state()."\r\nFound ".count($agency->get_alerts())." alarms\r\n";

	if (count($agency->get_alerts()) > 8){
		$message .=	"List of alarms:\r\n  ".$agency->get_alerts_to_string()."\n\n";
	}
}

// Last are the idle departments
$message .= "\r\nThere are ".count($abandoned)." with no messages:\r\n";
foreach ($abandoned as $name => $agency){

	$message .="Department is idle: ".$agency->get_name().", ".$agency->get_id().", in ". $agency->get_city().", ".$agency->get_state()."\r\n";
}

// Now send an email
$result = send_email($email_to,$email_subject,$message);

if ($result['result'] != 'success'){

	return $result;
}



// Close up shop
$mysqli->close();





/**
 * Send an email to one or more recipients
 *
 * @param array $recipients
 * @param string $subject
 * @param string $body
 * @return PHP object. 'result' => success|error, 'message' => error message or result data
 */
function send_email($recipients, $subject, $body) {

	require_once 'lib/swift/swift_required.php';
	global $email_from_address,$email_password,$smtp,$smtp_port;

	// Create the message
	$message=Swift_Message::newInstance()
		->setSubject($subject)
		->setFrom($email_from_address)
		->setTo($recipients)
		->setBody($body);


	$smtp_ip = gethostbyname($smtp);
	// Create the transport
	$transport = Swift_SmtpTransport::newInstance($smtp_ip, $smtp_port,'ssl')
		->setUsername($email_from_address)
		->setPassword($email_password)
		->setSourceIp('0.0.0.0');

	// Create the mailer
	$mailer = Swift_Mailer::newInstance($transport);

	// Send the message
	$email_result = $mailer->send($message);

	if($email_result > 0) {

		return array('result'=>'success', 'message'=>"$email_result messages sent successfully");

	}

	return array('result'=>'success', 'message'=>'unable to send message');

}


?>
