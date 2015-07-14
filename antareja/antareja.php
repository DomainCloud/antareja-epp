<?php
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


# Configuration array
function antareja_getConfigArray() {
	$configarray = array(
		"Username" => array( "Type" => "text", "Size" => "20", "Description" => "Enter your username here" ),
		"Password" => array( "Type" => "password", "Size" => "20", "Description" => "Enter your password here" ),
		"Server" => array( "Type" => "text", "Size" => "20", "Description" => "Enter EPP Server Address" ),
		"Port" => array( "Type" => "text", "Size" => "20", "Description" => "Enter EPP Server Port" ),
		"SSL" => array( "Type" => "yesno" ),
		"Certificate" => array( "Type" => "text", "Description" => "Path of certificate .pem" )
	);
	return $configarray;
}

function antareja_AdminCustomButtonArray() {
  $buttonarray = array(
      "Approve Transfer" => "ApproveTransfer",
      "Cancel Transfer Request" => "CancelTransferRequest",
      "Reject Transfer" => "RejectTransfer",
      "Restore Domain" => "RestoreDomain",
      );
  return $buttonarray;
}


# Function to return current nameservers
function antareja_GetNameservers($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = "$sld.$tld";


	# Get client instance
	try {
		$client = _antareja_Client();

		# Get list of nameservers for domain
		$result = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($result);
		logModuleCall('AntareJa', 'GetNameservers', $xml, $result);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check the result is ok
		if($coderes != '1000') {
			$values["error"] = "GetNameservers/domain-info($domain): Code ($coderes) $msg";
			return $values;
		}

		# Grab hostname array
		$ns = $doc->getElementsByTagName('hostObj');
		# Extract nameservers & build return result
		$i = 1;	$values = array();
		foreach ($ns as $nn) {
			$values["ns{$i}"] = $nn->nodeValue;
			$i++;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'GetNameservers/EPP: '.$e->getMessage();
		return $values;
	}


	return $values;
}



# Function to save set of nameservers
function antareja_SaveNameservers($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];


	# Generate XML for nameservers
	$nameservers = array();
	if ($nameserver1 = $params["ns1"]) {
		$nameservers[] = $nameserver1;
		$add_hosts = '<domain:hostObj>'.$nameserver1.'</domain:hostObj>';
	}
	if ($nameserver2 = $params["ns2"]) {
		$nameservers[] = $nameserver2;
		$add_hosts .= '<domain:hostObj>'.$nameserver2.'</domain:hostObj>';
	}
	if ($nameserver3 = $params["ns3"]) {
		$nameservers[] = $nameserver3;
		$add_hosts .= '<domain:hostObj>'.$nameserver3.'</domain:hostObj>';
	}
	if ($nameserver4 = $params["ns4"]) {
		$nameservers[] = $nameserver4;
		$add_hosts .= '<domain:hostObj>'.$nameserver4.'</domain:hostObj>';
	}
	if ($nameserver5 = $params["ns5"]) {
		$nameservers[] = $nameserver5;
		$add_hosts .= '<domain:hostObj>'.$nameserver5.'</domain:hostObj>';
	}
	$errors = array_filter($nameservers);
	if (empty($errors)) {
		$add_hosts = '
			<domain:hostObj>'.$default_nameserver1.'</domain:hostObj>
			<domain:hostObj>'.$default_nameserver2.'</domain:hostObj>
		';
	}

	# Get client instance
	try {
		$client = _antareja_Client();

		# Grab list of current nameservers
		$request = $client->request( $xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$sld.'.'.$tld.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'SaveNameservers', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check if result is ok
		if($coderes != '1000') {
			$values["error"] = "SaveNameservers/domain-info($sld.$tld): Code ($coderes) $msg";
			return $values;
		}
		$values["status"] = $msg;

		# Generate list of nameservers to remove
		$hostlist = $doc->getElementsByTagName('hostObj');
		foreach ($hostlist as $host) {
			$rem_hosts .= '<domain:hostObj>'.$host->nodeValue.'</domain:hostObj>';
		}

		# Build request
		$request = $client->request($xml = '
		<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
				xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:coiddomain="http://co.za/epp/extensions/coiddomain-1-0"
				xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
			<command>
				<update>
					<domain:update>
						<domain:name>'.$sld.'.'.$tld.'</domain:name>
						<domain:rem>
							<domain:ns>'.$rem_hosts.'</domain:ns>
						</domain:rem>
					</domain:update>
				</update>
			</command>
		</epp>
		');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		$rem_coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$rem_msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		$add_coderes = "";

		if ($rem_coderes == '1000') {
			$request = $client->request($xml = '
			<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
					xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:coiddomain="http://co.za/epp/extensions/coiddomain-1-0"
					xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
				<command>
					<update>
						<domain:update>
							<domain:name>'.$sld.'.'.$tld.'</domain:name>
							<domain:add>
								<domain:ns>'.$add_hosts.'</domain:ns>
							</domain:add>
						</domain:update>
					</update>
				</command>
			</epp>
			');

			# Parse XML result
			$doc= new DOMDocument();
			$doc->loadXML($request);
			logModuleCall('Antareja', 'SaveNameservers', $xml, $request);

			# Pull off status
			$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
			$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		} else {
			$values["error"] = "SaveNameservers/domain-update($sld.$tld): Code ($rem_coderes) $rem_msg";
			return $values;
		}

		# Check if result is ok
		if($coderes == '1000') {
			$values["error"] = "SaveNameservers/domain-update($sld.$tld): Code ($coderes) $msg";
			return $values;
		}
	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


function antareja_GetRegistrarLock($params) {

	try {
		$client = _antareja_Client();
	
		$sld = $params["sld"];
		$tld = $params["tld"];

		$request = $client->request( $xml = '
		<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
			<command>
				<info>
					<domain:info xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
						<domain:name hosts="all">'.$sld . '.' . $tld.'</domain:name>
					</domain:info>
				</info>
			</command>
		</epp>
		');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		if ($coderes == '1000') {
			$lock_transfer = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
			$lock =  $lock_transfer == 'clientTransferProhibited' ? "1" : "0";

			if ($lock == "1") {
				$lockstatus = "locked";
			} else {
				$lockstatus = "unlocked";
			}

			return $lockstatus;
		} else {
			$values["error"] = "GetRegistrarLock/domain-info($contactid): Code ($coderes) $msg";
			return $values;
		}
	} catch (Exception $e) {
		$values["error"] = 'GetRegistrarLock/EPP: '.$e->getMessage();
		return $values;
	}
}


function antareja_SaveRegistrarLock($params) {

	try {
		$client = _antareja_Client();
	
		$sld = $params["sld"];
		$tld = $params["tld"];

		$request = $client->request( $xml = '
		<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
			<command>
				<info>
					<domain:info xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
						<domain:name hosts="all">'.$sld . '.' . $tld.'</domain:name>
					</domain:info>
				</info>
				<clTRID>ABC-12346</clTRID>
			</command>
		</epp>
		');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		if ($coderes == '1000') {
			$lock_transfer = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
			$lock =  $lock_transfer == 'clientTransferProhibited' ? "1" : "0";

			if ($lock == "0") {
				$args = "
					<domain:add>
						<domain:status s=\"clientTransferProhibited\"/>
					</domain:add>
				";
			} else {
				$args = "
					<domain:rem>
						<domain:status s=\"clientTransferProhibited\"/>
					</domain:rem>
				";
			}

			# Update Registrar Lock
			$request = $client->request($xml = '
			<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
				<command>
					<update>
						<domain:update>
							<domain:name>'.$sld.'.'.$tld.'</domain:name>
							'.$args.'
						</domain:update>
					</update>
				</command>
			</epp>
			');

			# Parse XML result
			$doc= new DOMDocument();
			$doc->loadXML($request);

			# Pull off status
			$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
			$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
			# Check if result is ok
			if($coderes != '1001') {
				$values["error"] = "SaveRegistrarLock/domain-update($sld.$tld): Code ($coderes) $msg";
				return $values;
			}

			$values['status'] = $msg;
		} else {
			$values["error"] = "SaveRegistrarLock/domain-update($contactid): Code ($coderes) $msg";
			return $values;
		}
	} catch (Exception $e) {
		$values["error"] = 'SaveRegistrarLock/EPP: '.$e->getMessage();
		return $values;
	}
	return $values;
}

# Function to restore domain
function antareja_RestoreDomain($params) {
	$domainid = $params['domainid'];
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = $sld . '.' . $tld;

	try {
		$client = _antareja_Client();

		$request = $client->request( $xml = '
		<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
			<command>
				<update>
					<domain:update xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
						<domain:name>'.$domain.'</domain:name>
					</domain:update>
				</update>
				<extension>
					<rgp:update xmlns:rgp="urn:ietf:params:xml:ns:rgp-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:rgp-1.0 rgp-1.0.xsd">
						<rgp:restore op="request"/>
					</rgp:update>
				</extension>
			</command>
		</epp>
		');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RestoreDomain', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		if($coderes != '1000') {
			$values["error"] = "RestoreDomain/domain-info: Code ($coderes) $msg";
			return $values;
		}

		if ($coderes == '1000') {
			$request = $client->request($xml = '
			<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
				<command>
					<info>
						<domain:info xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
							<domain:name hosts="all">'.$domain.'</domain:name>
						</domain:info>
					</info>
				</command>
			</epp>
			');
			$doc = new DOMDocument();
			$doc->loadXML($request);
			$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
			# Get and reformat expiry date.
			$exdate = $doc->getElementsByTagName('exDate')->item(0)->nodeValue;
			$expirydate = date("Y-m-d", strtotime($exdate));

			if ($coderes == '1000') {
				$values = array(
					"expirydate"=>$expirydate,
					"nextduedate"=>$expirydate,
					"status"=>'Active'
					);
				$query = update_query( "tbldomains", $values, array("id"=>$domainid) );
			} else {
				$values['error'] = "RestoreDomain/update-expiry-date: Code ($coderes) $msg";
			}
		}
		$values['error'] = "RestoreDomain/domain-info: : Code ($coderes) $msg";
	} catch (Exception $e) {
		$values["error"] = 'RestoreDomain: '.$e->getMessage();
		return $values;
	}
	return $values;
}

# Function to register domain
function antareja_RegisterDomain($params) {
	# Grab varaibles
	$sld = $params["sld"];
	$tld = $params["tld"];
	$regperiod = $params["regperiod"];

	# Get registrant details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantOrg = $params["companyname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["fullphonenumber"];

	# Our details
	$contactid = strtolower(substr(md5($sld . '.' . $tld), 0,15));
	$dcadmid = '77124a249a66a50';
	$adminid = !empty($params['resellerid']) ? $params['resellerid'] : $dcadmid;

	# Generate XML for namseverss
	if ($nameserver1 = $params["ns1"]) {
		$add_hosts = '<domain:hostObj>'.$nameserver1.'</domain:hostObj>';
	}
	if ($nameserver2 = $params["ns2"]) {
		$add_hosts .= '<domain:hostObj>'.$nameserver2.'</domain:hostObj>';
	}
	if ($nameserver3 = $params["ns3"]) {
		$add_hosts .= '<domain:hostObj>'.$nameserver3.'</domain:hostObj>';
	}
	if ($nameserver4 = $params["ns4"]) {
		$add_hosts .= '<domain:hostObj>'.$nameserver4.'</domain:hostObj>';
	}
	if ($nameserver5 = $params["ns5"]) {
		$add_hosts .= '<domain:hostObj>'.$nameserver5.'</domain:hostObj>';
	}

	# Get client instance
	try {
		$client = _antareja_Client();

		# Send registration
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<create>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$contactid.'</contact:id>
				<contact:postalInfo type="loc">
					<contact:name>'.$RegistrantFirstName.' '.$RegistrantLastName.'</contact:name>
					<contact:org>'.$RegistrantOrg.'</contact:org>
					<contact:addr>
						<contact:street>'.$RegistrantAddress1.'</contact:street>
						<contact:street>'.$RegistrantAddress2.'</contact:street>
						<contact:city>'.$RegistrantCity.'</contact:city>
						<contact:sp>'.$RegistrantStateProvince.'</contact:sp>
						<contact:pc>'.$RegistrantPostalCode.'</contact:pc>
						<contact:cc>'.$RegistrantCountry.'</contact:cc>
					</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$RegistrantPhone.'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$RegistrantEmailAddress.'</contact:email>
				<contact:authInfo>
					<contact:pw>AxA8AjXbAH'.rand().rand().'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</create>
	</command>
</epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RegisterDomain', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes == '1000') {
			$values['contact'] = 'Contact Created';
		} else if($coderes == '2302') {
			$values['contact'] = 'Contact Already exists';
		} else {
			$values["error"] = "RegisterDomain/contact-create($contactid): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;
		$seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()');
		shuffle($seed);
		$epp_code = '';
		foreach (array_rand($seed, 10) as $k) $epp_code .= $seed[$k];

		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
	xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:coiddomain="http://co.za/epp/extensions/coiddomain-1-0"
	xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<create>
			<domain:create xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name>'.$sld.'.'.$tld.'</domain:name>
				<domain:ns>'.$add_hosts.'</domain:ns>
				<domain:registrant>'.$contactid.'</domain:registrant>
				<domain:authInfo>
					<domain:pw>'.$epp_code.'</domain:pw>
				</domain:authInfo>
				<domain:contact type="admin">'.$adminid.'</domain:contact>
				<domain:contact type="tech">'.$adminid.'</domain:contact>
				<domain:contact type="billing">'.$adminid.'</domain:contact>
			</domain:create>
		</create>
	</command>
</epp>
');
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RegisterDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RegisterDomain/domain-create($sld.$tld): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;
	} catch (Exception $e) {
		$values["error"] = 'RegisterDomain/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



# Function to transfer a domain
function antareja_TransferDomain($params) {
	# Grab variables
	$testmode = $params["TestMode"];
	$sld = $params["sld"];
	$tld = $params["tld"];

	# Domain info
	$regperiod = $params["regperiod"];
	$transfersecret = $params["transfersecret"];
	$nameserver1 = $params["ns1"];
	$nameserver2 = $params["ns2"];
	# Registrant Details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["fullphonenumber"];
	# Admin details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminphonenumber"];
	# Our details
	$contactid = substr(md5($sld . '.' . $tld), 0,15);

	# Get client instance
	try {
		$client = _antareja_Client();

		# Initiate transfer
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<command>
		<transfer op="request">
			<domain:transfer>
				<domain:name>'.$sld.'.'.$tld.'</domain:name>
				<domain:period unit="y">'.$regperiod.'</domain:period>
				<domain:authInfo>
					<domain:pw>'.$transfersecret.'</domain:pw>
				</domain:authInfo>
			</domain:transfer>
		</transfer>
	</command>
</epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'TransferDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# We should get a 1001 back
		if($coderes != '1001') {
			$values["error"] = "TransferDomain/domain-transfer($sld.$tld): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		# Create contact details
		# @revils. 20052015. Not sure if these lines of code is necessary.
		/*
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<create>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$contactid.'</contact:id>
				<contact:postalInfo type="loc">
					<contact:name>'.$RegistrantFirstName.' '.$RegistrantLastName.'</contact:name>
					<contact:addr>
						<contact:street>'.$RegistrantAddress1.'</contact:street>
						<contact:street>'.$RegistrantAddress2.'</contact:street>
						<contact:city>'.$RegistrantCity.'</contact:city>
						<contact:sp>'.$RegistrantStateProvince.'</contact:sp>
						<contact:pc>'.$RegistrantPostalCode.'</contact:pc>
						<contact:cc>'.$RegistrantCountry.'</contact:cc>
					</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$RegistrantPhone.'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$RegistrantEmailAddress.'</contact:email>
				<contact:authInfo>
					<contact:pw>AxA8AjXbAH'.rand().rand().'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</create>
	</command>
</epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'TransferDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes == '1000') {
			$values['contact'] = 'Contact Created';
		} else if($coderes == '2302') {
			$values['contact'] = 'Contact Already exists';
		} else {
			$values["error"] = "TransferDomain/contact-create($contactid): Code ($coderes) $msg";
			return $values;
		}
		*/
	} catch (Exception $e) {
		$values["error"] = 'TransferDomain/EPP: '.$e->getMessage();
		return $values;
	}

	$values["status"] = $msg;

	return $values;
}



# Function to renew domain
function antareja_RenewDomain($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$regperiod = $params["regperiod"];


	# Get client instance
	try {
		$client = _antareja_Client();

		# Send renewal request
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$sld.'.'.$tld.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RenewDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RenewDomain/domain-info($sld.$tld)): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		# Sanitize expiry date
		$expdate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
		if (empty($expdate)) {
			$values["error"] = "RenewDomain/domain-info($sld.$tld): Domain info not available";
			return $values;
		}

		# Send request to renew
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<command>
		<renew>
			<domain:renew>
				<domain:name>'.$sld.'.'.$tld.'</domain:name>
				<domain:curExpDate>'.$expdate.'</domain:curExpDate>
				<domain:period unit="y">'.$regperiod.'</domain:period>
			</domain:renew>
		</renew>
	</command>
</epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RenewDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RenewDomain/domain-renew($sld.$tld,$expdate): Code (".$coderes.") ".$msg;
			return $values;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RenewDomain/EPP: '.$e->getMessage();
		return $values;
	}

	# If error, return the error message in the value below
	return $values;
}



# Function to grab contact details
function antareja_GetContactDetails($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];


	# Get client instance
	try {
		$client = _antareja_Client();

		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$sld.'.'.$tld.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'GetContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if($coderes != '1000') {
			$values["error"] = "GetContactDetails/domain-info($sld.$tld): Code (".$coderes.") ".$msg;
			return $values;
		}

		# Grab contact info
		$registrant = $doc->getElementsByTagName('registrant')->item(0)->nodeValue;
		if (empty($registrant)) {
			$values["error"] = "GetContactDetails/domain-info($sld.$tld): Registrant info not available";
			return $values;
		}

		# Grab contact info
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
	<command>
		<info>
			<contact:info>
				<contact:id>'.$registrant.'</contact:id>
			</contact:info>
		</info>
	</command>
</epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'GetContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if($coderes != '1000') {
			$values["error"] = "GetContactDetails/contact-info($registrant): Code (".$coderes.") ".$msg;
			return $values;
		}

		$nodes = $doc->getElementsByTagName('postalInfo');
		for ($i = 0; ($i < $nodes->length); $i++) {
			if ($nodes->item($i)->getAttributeNode('type')->nodeValue == 'int') {
				$childNodes = $nodes->item($i);
				$results["Registrant"]["Contact Name"] = $childNodes->getElementsByTagName('name')->item(0)->nodeValue;
				$results["Registrant"]["Organisation"] = $childNodes->getElementsByTagName('org')->item(0)->nodeValue;
				$results["Registrant"]["Address line 1"] = $childNodes->getElementsByTagName('street')->item(0)->nodeValue;
				$results["Registrant"]["Address line 2"] = $childNodes->getElementsByTagName('street')->item(1)->nodeValue;
				$results["Registrant"]["TownCity"] = $childNodes->getElementsByTagName('city')->item(0)->nodeValue;
				$results["Registrant"]["State"] = $childNodes->getElementsByTagName('sp')->item(0)->nodeValue;
				$results["Registrant"]["Zip code"] = $childNodes->getElementsByTagName('pc')->item(0)->nodeValue;
				$results["Registrant"]["Country Code"] = $childNodes->getElementsByTagName('cc')->item(0)->nodeValue;
			}
		}

		$results["Registrant"]["Phone"] = $doc->getElementsByTagName('voice')->item(0)->nodeValue;
		$results["Registrant"]["Email"] = $doc->getElementsByTagName('email')->item(0)->nodeValue;

	} catch (Exception $e) {
		$values["error"] = 'GetContactDetails/EPP: '.$e->getMessage();
		return $values;
	}


	# What we going to do here is make sure all the attributes we return back are set
	# If we don't do this WHMCS won't display the options for editing
	foreach (
			array("Contact Name","Organisation","Address line 1","Address line 2","TownCity","State","Zip code","Country Code","Phone","Email")
			as $item
	) {
		# Check if the item is set
		if ($results["Registrant"][$item] == "") {
			# Just set it to -
			$values["Registrant"][$item] = "-";
		} else {
			# We setting this here so we maintain the right order, else we get the set
			# things first and all the unsets second, which looks crap
			$values["Registrant"][$item] = $results["Registrant"][$item];
		}
	}

	return $values;
}



# Function to save contact details
function antareja_SaveContactDetails($params) {
	# Grab variables
	$tld = $params["tld"];
	$sld = $params["sld"];
	# Registrant details
	$registrant_name = $params["contactdetails"]["Registrant"]["Contact Name"];
	$registrant_org = $params["contactdetails"]["Registrant"]["Organisation"];
	$registrant_address1 =  $params["contactdetails"]["Registrant"]["Address line 1"];
	$registrant_address2 = $params["contactdetails"]["Registrant"]["Address line 2"];
	$registrant_town = $params["contactdetails"]["Registrant"]["TownCity"];
	$registrant_state = $params["contactdetails"]["Registrant"]["State"];
	$registrant_zipcode = $params["contactdetails"]["Registrant"]["Zip code"];
	$registrant_countrycode = isset($params["contactdetails"]["Registrant"]["Country Code"]) ? $params["contactdetails"]["Registrant"]["Country Code"] : "ID";
	$registrant_phone = $params["contactdetails"]["Registrant"]["Phone"];
	#$registrant_fax = '',
	$registrant_email = $params["contactdetails"]["Registrant"]["Email"];

	# Get client instance
	try {
		$client = _antareja_Client();

		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$sld.'.'.$tld.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');
		# Parse XML	result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'SaveContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "SaveContactDetails/domain-info($sld.$tld): Code (".$coderes.") ".$msg;
			return $values;
		}

		$values["status"] = $msg;

		# Time to do the update
		$registrant = $doc->getElementsByTagName('registrant')->item(0)->nodeValue;

		# Save contact details
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
	<command>
		<update>
			<contact:update>
				<contact:id>'.$registrant.'</contact:id>
				<contact:chg>
					<contact:postalInfo type="loc">
						<contact:name>'.$registrant_name.'</contact:name>
						<contact:org>'.$registrant_org.'</contact:org>
						<contact:addr>
							<contact:street>'.$registrant_address1.'</contact:street>
							<contact:street>'.$registrant_address2.'</contact:street>
							<contact:city>'.$registrant_town.'</contact:city>
							<contact:sp>'.$registrant_state.'</contact:sp>
							<contact:pc>'.$registrant_zipcode.'</contact:pc>
							<contact:cc>'.$registrant_countrycode.'</contact:cc>
						</contact:addr>
						</contact:postalInfo>
						<contact:voice>'.$registrant_phone.'</contact:voice>
						<contact:fax></contact:fax>
						<contact:email>'.$registrant_email.'</contact:email>
				</contact:chg>
			</contact:update>
		</update>
	</command>
</epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'SaveContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1001') {
			$values["error"] = "SaveContactDetails/contact-update($registrant): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveContactDetails/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

function antareja_GetEPPCode($params) {

	# Grab varaibles
	$sld = $params["sld"];
	$tld = $params["tld"];

	# Grab client instance
	try {
		$client = _antareja_Client();

		$request = $client->request( $xml = '
	<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
		<command>
			<info>
				<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
					<domain:name hosts="all">'. $sld . '.' . $tld .'</domain:name>
				</domain:info>
			</info>
		</command>
	</epp>
	');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		$result_code = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($result_code != '1000') {
			$values["error"] = "GetEPPCode/domain-info($sld.$tld): Code ($result_code) $msg";
			return $values;
		}

		$values["status"] = $msg;

		$eppcode = $doc->getElementsByTagName('pw')->item(0)->nodeValue;
		$values['eppcode'] = $eppcode;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

# Function to register nameserver
function antareja_RegisterNameserver($params) {
	# Grab varaibles
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$sld = $params["sld"];
	$tld = $params["tld"];
	$nameserver = $params["nameserver"];
	$ipaddress = $params["ipaddress"];


	# Grab client instance
	try {
		$client = _antareja_Client();

		# Register nameserver
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<command>
		<create>
			<host:create xmlns:host="urn:ietf:params:xml:ns:host-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:host-1.0 host-1.0.xsd">
				<host:name>'.$nameserver.'</host:name>
				<host:addr ip="v4">'.$ipaddress.'</host:addr>
			</host:create>
		</create>
	</command>
</epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RegisterNameserver', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "RegisterNameserver/domain-update($sld.$tld): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}


	return $values;
}



# Modify nameserver
function antareja_ModifyNameserver($params) {
	# Grab variables
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$nameserver = $params["nameserver"];
	$currentipaddress = $params["currentipaddress"];
	$newipaddress = $params["newipaddress"];


	# Grab client instance
	try {
		$client = _antareja_Client();

		# Modify nameserver
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<command>
		<update>
			<host:update xmlns:host="urn:ietf:params:xml:ns:host-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:host-1.0 host-1.0.xsd">
				<host:name>'.$nameserver.'</host:name>
				<host:add>
					<host:addr ip="v4">'.$newipaddress.'</host:addr>
				</host:add>
				<host:rem>
					<host:addr ip="v4">'.$currentipaddress.'</host:addr>
				</host:rem>
			</host:update>
		</update>
	</command>
</epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'ModifyNameserver', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "ModifyNameserver/domain-update($sld.$tld): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


# Delete nameserver
function antareja_DeleteNameserver($params) {
	# Grab variables
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$nameserver = $params["nameserver"];


	# Grab client instance
	try {
		$client = _antareja_Client();

		# If we were given   hostname.  blow away all of the stuff behind it and allow us to remove hostname
		$nameserver = preg_replace('/\.\.\S+/','',$nameserver);

		# Delete nameserver
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
  <command>
    <update>
      <domain:update>
		<domain:name>'.$sld.'.'.$tld.'</domain:name>
        <domain:rem>
          <domain:ns>
			<domain:hostObj>'.$nameserver.'</domain:hostObj>
          </domain:ns>
        </domain:rem>
      </domain:update>
    </update>
  </command>
</epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'DeleteNameserver', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "DeleteNameserver/domain-update($sld.$tld): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


# Function to return meaningful message from response code
function _antareja_message($code) {
	return "Code $code";
}


# Ack a POLL message
function _antareja_ackpoll($client,$msgid) {
	# Ack poll message
	$request = $client->request($xml = '
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<poll op="ack" msgID="'.$msgid.'"/>
	</command>
</epp>
');

	# Decipher XML
	$doc = new DOMDocument();
	$doc->loadXML($request);
	logModuleCall('Antareja', 'ackpoll', $xml, $request);

	$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
	$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

	# Check result
	if($coderes != '1301' && $coderes != '1300') {
		throw new Exception("ackpoll/poll-ack($id): Code ($coderes) $msg");
	}
}

# Function to create internal .ID EPP request
function _antareja_Client() {
	# Setup include dir
	$include_path = ROOTDIR . '/modules/registrars/antareja';
	set_include_path($include_path . PATH_SEPARATOR . get_include_path());
	# Include EPP stuff we need
	require_once 'Net/EPP/Client.php';
	require_once 'Net/EPP/Protocol.php';
	require 'antarejaconfig.php';

	# Grab module parameters
	$params = getregistrarconfigoptions('antareja');
	# Check if module parameters are sane
	if (empty($params['Username']) || empty($params['Password'])) {
		throw new Exception('System configuration error(1), please contact your provider');
	}
	if ($params['Server'] != $server_addr) {
		throw new Exception('System configuration error(2), please contact your provider');
	}

	# Create SSL context
	$context = stream_context_create();
	# Are we using ssl?
	$use_ssl = false;
	if (!empty($params['SSL']) && $params['SSL'] == 'on') {
		$use_ssl = true;
	}
	# Set certificate if we have one
	if ($use_ssl && !empty($params['Certificate'])) {
		if (!file_exists($params['Certificate'])) {
			throw new Exception("System configuration error(3), please contact your provider");
		}
		# Set client side certificate
		stream_context_set_option($context, 'ssl', 'local_cert', $params['Certificate']);
	}

	# Create EPP client
	$client = new Net_EPP_Client();

	# Connect
	$res = $client->connect($params['Server'], $params['Port'], 15, $use_ssl, $context);

	# Perform login
	$request = $client->request($xml = '
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<login>
			<clID>'.$params['Username'].'</clID>
			<pw>'.$params['Password'].'</pw>
			<options>
			<version>1.0</version>
			<lang>en</lang>
			</options>
			<svcs>
				<objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>
				<objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>
			</svcs>
		</login>
	</command>
</epp>
');
	logModuleCall('Antareja', 'Connect', $xml, $request);

	return $client;
}

function antareja_TransferSync($params) {
	$domainid = $params['domainid'];
	$domain = $params['domain'];
	$sld = $params['sld'];
	$tld = $params['tld'];
	$registrar = $params['registrar'];
	$regperiod = $params['regperiod'];
	$status = $params['status'];
	$dnsmanagement = $params['dnsmanagement'];
	$emailforwarding = $params['emailforwarding'];
	$idprotection = $params['idprotection'];

	# Other parameters used in your _getConfigArray() function would also be available for use in this function

	# Grab domain info
	try {
		$client = _antareja_Client();
		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');

		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'TransferSync', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if($coderes != '1000') {
			$values['error'] = "TransferSync/domain-info($domain): Code("._antareja_message($coderes).") $msg";
			return $values;
		}

		# Check if we can get a status back
		if($doc->getElementsByTagName('status')->item(0)) {
			$statusres = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
			$createdate = substr($doc->getElementsByTagName('crDate')->item(0)->nodeValue,0,10);
			$nextduedate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
		} else {
			$values['error'] = "TransferSync/domain-info($domain): Domain not found";
			return $values;
		}

		$values['status'] = $msg;

		# Check status and update
		if ($statusres == "ok") {
			$values['completed'] = true;

		} else {
			$values['error'] = "TransferSync/domain-info($domain): Unknown status code '$statusres' (File a bug report here: registrar@isi.co.id)";
		}

		$values['expirydate'] = $nextduedate;

	} catch (Exception $e) {
		$values["error"] = 'TransferSync/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

function antareja_Sync($params) {
	$domainid = $params['domainid'];
	$domain = $params['domain'];
	$sld = $params['sld'];
	$tld = $params['tld'];
	$registrar = $params['registrar'];
	$regperiod = $params['regperiod'];
	$status = $params['status'];
	$dnsmanagement = $params['dnsmanagement'];
	$emailforwarding = $params['emailforwarding'];
	$idprotection = $params['idprotection'];

	# Other parameters used in your _getConfigArray() function would also be available for use in this function

	# Grab domain info
	try {
		$client = _antareja_Client();
		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</info>
	</command>
</epp>
');

		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'Sync', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if($coderes != '1000') {
			$values['error'] = "Sync/domain-info($domain): Code("._antareja_message($coderes).") $msg";
			return $values;
		}

		# Check if we can get a status back
		if($doc->getElementsByTagName('status')->item(0)) {
			$statusres = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
			$createdate = substr($doc->getElementsByTagName('crDate')->item(0)->nodeValue,0,10);
			$nextduedate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
		} else {
			$values['error'] = "Sync/domain-info($domain): Domain not found";
			return $values;
		}

		$values['status'] = $msg;

		# Check status and update
		if ($statusres == "ok" || $statusres == "clientTransferProhibited") {
			$values['active'] = true;
		} elseif ($statusres == "serverHold") {
			$values['error'] = "Sync/domain-info($domain): Domain currently on '$statusres' Please contact Pandi for further information.";
		} elseif ($statusres == "inactive") {
			$values['error'] = "Sync/domain-info($domain): Domain currently '$statusres'. Please check ns records of $domain.";
		} elseif ($statusres == "expired" /*|| $statusres == "pendingDelete"*/) {
			$values['expired'] = true;
		} else {
			$values['error'] = "Sync/domain-info($domain): Unknown status code '$statusres' (File a bug report here: registrar@isi.co.id)";
		}

		$values['expirydate'] = $nextduedate;

	} catch (Exception $e) {
		$values["error"] = 'Sync/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


function antareja_RequestDelete($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];

	# Grab domain info
	try {
		$client = _antareja_Client();

		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
xmlns:domain="urn:ietf:params:xml:ns:domain-1.0"
xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<command>
		<delete>
			<domain:delete xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name>'.$sld.'.'.$tld.'</domain:name>
			</domain:delete>
		</delete>
	</command>
</epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RequestDelete', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1001') {
			$values['error'] = 'RequestDelete/domain-info('.$sld.'.'.$tld.'): Code('._antareja_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RequestDelete/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

function antareja_ApproveTransfer($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];

	# Grab domain info
	try {
		$client = _antareja_Client();

		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
  <command>
    <transfer op="approve">
      <domain:transfer>
        <domain:name>'.$sld.'.'.$tld.'</domain:name>
      </domain:transfer>
    </transfer>
  </command>
</epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'ApproveTransfer', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values['error'] = 'ApproveTransfer/domain-info('.$sld.'.'.$tld.'): Code('._antareja_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'ApproveTransfer/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


function antareja_CancelTransferRequest($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];

	# Grab domain info
	try {
		$client = _antareja_Client();

		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
  <command>
    <transfer op="cancel">
      <domain:transfer>
        <domain:name>'.$sld.'.'.$tld.'</domain:name>
      </domain:transfer>
    </transfer>
  </command>
</epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'CancelTransferRequest', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values['error'] = 'CancelTransferRequest/domain-info('.$sld.'.'.$tld.'): Code('._antareja_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'CancelTransferRequest/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


function antareja_RejectTransfer($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];

	# Grab domain info
	try {
		$client = _antareja_Client();

		# Grab domain info
		$request = $client->request($xml = '
<epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
  <command>
    <transfer op="reject">
      <domain:transfer>
        <domain:name>'.$sld.'.'.$tld.'</domain:name>
      </domain:transfer>
    </transfer>
  </command>
</epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('Antareja', 'RejectTransfer', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values['error'] = 'RejectTransfer/domain-info('.$sld.'.'.$tld.'): Code('._antareja_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RejectTransfer/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

?>
