<?php

$domain ="domain.com";
$name = "nuseed"; //subdomain e.g. name.domain.com 
$number_of_records = 10; //maximum n A records with $name... 
$user = "emailofcloudflareaccount"; //user name
$key = "yourapikey"; //key for cloudflare api found in account settings
$seed_dump = "/path/to/dnsseed.dump"; //absolute path to dnsseed.dump in the nubits-seeder root directory 

/*
############################################################################################################
				The magic starts here - Don't alter anything below, unless you know what you're doing
############################################################################################################
*/

error_reporting(E_ERROR | E_WARNING | E_PARSE);

include_once("class_cloudflare.php"); //include the cloudflare php API by vexxhost

$type = "A"; //set A records only
$i5 = 0; //counter
$i4 = 0; //counter
$i3 = 0; //counter
$i2 = 1; //counter, 1 (line 0 is a header)
$i = 0; //counter
$ip_array=array();

if (!file_exists($seed_dump))
{
echo "Didn't find seed_dump at $seed_dump\n";
}

else{
	$cf = new cloudflare_api($user, $key);
	$response_object = $cf->rec_load_all($domain); //ALL dns entries of domain
	$response_json=json_encode($response_object); //make objects to JSON
	$response_array=json_decode($response_json, true); //make JSON to array, thanks php
	
	if(isset($response_array["err_code"]))
	{
		if($response_array["err_code"]=="E_UNAUTH")
		{
		echo "Auth failed, check your email and key!";
		}
	}

//write IPs into array 
	$ip_raw = file($seed_dump); //read seed_dump into array
	$ip_numbers_in_file = count($ip_raw);
	
	while ($i2 < $ip_numbers_in_file) {
		$ip_array_line = $ip_raw[$i2]; //read line 
		$ip_array_split = explode(":",$ip_array_line); //explode string at ":"
		$ip = $ip_array_split[0]; // [0] is the ip address of the line

		$ip_array_split = str_replace("7890", "", $ip_array_split[1]); //remove port
		$ip_array_split = trim($ip_array_split); //remove spaces at the start
		$ip_array_split = substr($ip_array_split,0,1); //get first charakter == the GOOD parameter in the dump file /
		$good =  $ip_array_split;
	
		$ip_array_while = array(
		'ip' => $ip,
		'good' => $good	
		);
	
		if ($good==1){
			array_push($ip_array, $ip_array_while);
		}
		
		$i2++;
	}

//check for number of CF $name.$domain entries

	$number_of_name_domain_records = count ($response_array["response"]["recs"]["objs"]); //count dns entries with $name.$domain
	$entries=0;  //entry counter
	while ($i3 < $number_of_name_domain_records)
	{
		if($response_array["response"]["recs"]["objs"][$i3]["name"] == "$name.$domain" && $response_array["response"]["recs"]["objs"][$i3]["type"]==$type)
		 {
			$entries++;
		 }
	$i3++;
	}
	
//create needed entries if $entries differs from $number_of_records (initial create of zone file)

	$difference=$number_of_records-$entries;
	while($i4 < $difference)
	{
		$ip_new=$ip_array[$i5]["ip"];
		$good_new=$ip_array[$i5]["good"];

		if ($good_new == 1) //only re-write the dns entry if good == 1
			{
			$content_new=$ip_new;
			$write_new = $cf->rec_new($domain, $type, $name, $content_new);
			}
	
		//error handling if IP is already in table
		$new_response_array=get_object_vars($write_new); 
		$new_response_array_retry=$new_response_array;
		
		while($new_response_array_retry["msg"]=="The record already exists.")
		{
				$i5++; //move file pointer +1
				$ip_new_retry=$ip_array[$i5]["ip"];
				$good_new_retry=$ip_array[$i5]["good"];

				if ($good_new_retry == 1) //only re-write the dns entry if good == 1
					{
					$content_new_retry=$ip_new_retry;
					$write_new_retry = $cf->rec_new($domain, $type, $name, $content_new_retry);
					}
				
				$new_response_array_retry=get_object_vars($write_new_retry); 
		
		}
			
		$i5++; //move file pointer!		
		$i4++; //check next missing element
	}
	 
	
//make CF edits
	while ($i < $number_of_records)
	{
		$id=$response_array["response"]["recs"]["objs"][$i]["rec_id"]; //get the DNS table row IDs 

		$ip_edit=$ip_array[$i]["ip"];
		$good_edit=$ip_array[$i]["good"];

		if ($good_edit == 1) //only re-write the dns entry if good == 1
			{
			$content_edit=$ip_edit;
			$write_edit = $cf->rec_edit($domain, $type, $id, $name, $content_edit);
			}
		$i++;
	}
}
?>