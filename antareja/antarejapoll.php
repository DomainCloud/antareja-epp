<?
/**
 * Copyright (c) 2015, Infinys System Indonesia
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * This code is a fork of the following:
 * @link https://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/wikis/home
 * @version 0.1.0
 *
 **/


# This file brings in a few constants we need
require_once dirname(__FILE__) . '/../../../dbconnect.php';
# Setup include dir
$include_path = ROOTDIR . '/modules/registrars/antareja';
set_include_path($include_path . PATH_SEPARATOR . get_include_path());
# Include EPP stuff we need
require_once 'antareja.php';
# Additional functions we need
require_once ROOTDIR . '/includes/functions.php';
# Include registrar functions aswell
require_once ROOTDIR . '/includes/registrarfunctions.php';

require_once 'Net/EPP/Frame.php';
require_once 'Net/EPP/Frame/Command.php';
require_once 'Net/EPP/ObjectSpec.php';

# Grab module parameters
$params = getregistrarconfigoptions('antareja');

echo("Antareja-EPP Poll Report\n");
echo("---------------------------------------------------\n");

# Request balance from registrar
try {
	$client = _antareja_Client();

	# Loop with message queue
	while (!$last) {
		# Request messages
		$request = $client->request('
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<poll op="req"/>
	</command>
</epp>
		');

		# Decode response
		$doc= new DOMDocument();
		$doc->loadXML($request);

		# Pull off code
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		if ($coderes == 1301 || $coderes == 1300) {
			$msgs = $doc->getElementsByTagName('msg');
			for ($m = 0; $m < $msgs->length; $m++) {
					echo "CODE: $coderes, MESSAGE: '".$msgs->item($m)->textContent."'\n";
			}

			# This is the last one
			if ($coderes == 1300) {
				$last = 1;
			}

			$msgq = $doc->getElementsByTagName('msgQ')->item(0);
			if ($msgq) {
				$msgid = $doc->getElementsByTagName('msgQ')->item(0)->getAttribute('id');
				try {
					$res = _antareja_ackpoll($client,$msgid);
				} catch (Exception $e) {
					echo("ERROR: ".$e->getMessage()."\n");
				}
			}

		} else {
			$msgid = $doc->getElementsByTagName('svTRID')->item(0)->textContent;
			$msgs = $doc->getElementsByTagName('msg');
			for ($m = 0; $m < $msgs->length; $m++) {
				echo "\n";
					echo "UNKNOWN CODE: $coderes, MESSAGE: '".$msgs->item($m)->textContent."', ID: $msgid\n";
				echo $request;
				echo "\n\n";
			}

		}
	}

} catch (Exception $e) {
	echo("ERROR: ".$e->getMessage(). "\n");
	exit;
}





?>
