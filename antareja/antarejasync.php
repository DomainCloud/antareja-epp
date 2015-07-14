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

# Grab module parameters
$params = getregistrarconfigoptions('antareja');

echo("Antareja-EPP Domain Sync Report\n");
echo("---------------------------------------------------\n");

# Request balance from registrar
try {
	$client = _antareja_Client();

	# Pull list of domains which are registered using this module
	$queryresult = mysql_query("SELECT domain FROM tbldomains WHERE registrar = 'antareja'");
	while($data = mysql_fetch_array($queryresult)) {
		$domains[] = trim(strtolower($data['domain']));
	}

	# Loop with each one
	foreach($domains as $domain) {
		sleep(1);

		# Query domain
		$output = $client->request('
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
	');

		$doc= new DOMDocument();
		$doc->loadXML($output);
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		if($coderes == '1000') {
			if( $doc->getElementsByTagName('status')) {
				if($doc->getElementsByTagName('status')->item(0)) {
					$statusres = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
					$createdate = substr($doc->getElementsByTagName('crDate')->item(0)->nodeValue,0,10);
					$nextduedate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
				} else {
					$status = "Domain $domain not registered!";
					continue;
				}
			}
		} else {
			echo "Domain check on $domain not successful: "._antareja_message($coderes)." (File a bug report here: registrar@isi.co.id)";
			continue;
		}


		# This is the template we going to use below for our updates
		$querytemplate = "UPDATE tbldomains SET status = '%s', registrationdate = '%s', expirydate = '%s', nextduedate = '%s' WHERE domain = '%s'";

		# Check status and update
		if ($statusres == "ok") {
			mysql_query(sprintf($querytemplate,"Active",
					mysql_real_escape_string($createdate),
					mysql_real_escape_string($nextduedate),
					mysql_real_escape_string($nextduedate),
					mysql_real_escape_string($domain)
			));
			echo "Updated $domain expiry to $nextduedate\n";

		} elseif ($statusres == "serverHold") {

		} elseif ($statusres == "expired" || $statusres == "pendingDelete" || $statusres == "inactive") {
			mysql_query(sprintf($querytemplate,"Expired",
					mysql_real_escape_string($createdate),
					mysql_real_escape_string($nextduedate),
					mysql_real_escape_string($nextduedate),
					mysql_real_escape_string($domain)
			));
			echo "Domain $domain is EXPIRED (Registration: $createdate, Expiry: $nextduedate)\n";

		} else {
			echo "Domain $domain has unknown status '$statusres'\n";
		}
	}

} catch (Exception $e) {
	echo("ERROR: ".$e->getMessage()."\n");
	exit;
}
