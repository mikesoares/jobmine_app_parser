<?php

/*
	JobMine Application Parser
	Created by Michael Soares (mikesoares.com)

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// quest/nexus username & password
$user = "username";
$pass = "password";

// grab page and parse it
$app_page = jobMineMe($user, $pass);

if(!$app_page) {
	print "Can't login - wrong username and/or password.";
	exit;
}

$jobs = parsePage($app_page);

// write everything to a csv file
$fp = fopen('jobmine_export.csv', 'w');

// headers
$headers = array (
    'job_id',
	'title',
	'employer',
	'job_status',
	'application_status'
);

fputcsv($fp, $headers);

// applications
foreach($jobs as $key => $value) {
	$row = array(
		$key,
		$value['title'],
		$value['employer'],
		$value['job_status'],
		$value['app_status']
	);
		
	fputcsv($fp, $row);
}

fclose($fp);

/////////////////////////////////////////////////
/////////////////// FUNCTIONS ///////////////////
/////////////////////////////////////////////////

function getUrl($url, $cookie, $fields = NULL) {
	$options = array(
		CURLOPT_URL => $url,
		CURLOPT_COOKIEJAR => $cookie,
		CURLOPT_COOKIEFILE => $cookie,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; U; Linux i686; pl-PL; rv:1.9.0.2) Gecko/20121223 Ubuntu/9.25 (jaunty) Firefox/3.8', // jobmine won't authenticate you without supplying a user agent (this can be anything)
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYPEER => false
	);
	
	if(isset($fields)) {
		//url-ify the data for the POST
		foreach($fields as $key=>$value) { 
			$fields_string .= $key.'='.$value.'&';
		}
		rtrim($fields_string,'&');
		
		$options[CURLOPT_POST] = count($fields);
		$options[CURLOPT_POSTFIELDS] = $fields_string;
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch);
	
	return $result;
}

function jobMineMe($userid, $password) {
	// create a temporary cookie file for the user - om nom nom nom nom nom nom
	$cookie = $userid.'_session';
	$fh = fopen($cookie, 'w') or die('Can\'t temporarily store session.');
	fclose($fh);

	// set base URL and POST variables
	$jm_base_url = 'https://jobmine.ccol.uwaterloo.ca/servlets/iclientservlet/SS/';
	$login_fields = array(
		"cmd" => urlencode("login"), 
		"languageCd" => urlencode("ENG"), 
		"sessionId" => urlencode(""),
		"httpPort" => urlencode(""), 
		"timezoneOffset" => urlencode("0"), 
		"userid" => urlencode($userid), 
		"pwd" => urlencode($password),
		"submit" => urlencode("Submit")
	);

	// initialize cookies
	getUrl($jm_base_url, $cookie);

	// log in to jobmine
	getUrl($jm_base_url, $cookie, $login_fields);

	// browse to application page
	$app_pg = getUrl($jm_base_url."?ICType=Panel&Menu=UW_CO_STUDENTS&Market=GBL&PanelGroupName=UW_CO_APP_SUMMARY&RL=&target=main0&navc=5170", $cookie);

	// if login unsuccessful
	if(!strstr($app_pg, "<TITLE>Student App Summary</TITLE>")) {
		unlink($cookie);
		return false;
	}
	
	// log out of jobmine
	getUrl($jm_base_url."?cmd=logout", $cookie);

	// delete the cookie file
	unlink($cookie);
	
	return $app_pg;
}

function parsePage($page_html) {
	// load the page into the DOM
	$jobs = array();
	$dom = new domDocument();
	@$dom->loadHTML($page_html);
	$dom->preserveWhiteSpace = false;
	
	$table = $dom->getElementsByTagName('table')->item(8); 
	$rows = $table->getElementsByTagName('tr');
	
	$i = true;
	foreach($rows as $row) {
		// hack to skip headers
		if($i == true) { $i = false; continue; }

		$cols = $row->getElementsByTagName('td');
		$jobs[(int)$cols->item(0)->nodeValue] = array(
			'title'		=> str_replace('"', "", trim($cols->item(1)->nodeValue)),
			'employer'	=> str_replace('"', "", trim($cols->item(2)->nodeValue)),
			'job_status'=> str_replace('"', "", trim($cols->item(5)->nodeValue)),
			'app_status'=> str_replace('"', "", trim($cols->item(6)->nodeValue))
		);
	}
	
	return $jobs;
}

?>
