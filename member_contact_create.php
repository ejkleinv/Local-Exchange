<?php

include_once("includes/inc.global.php");

$p->site_section = 0;

include("includes/inc.forms.php");

//
// First, we define the form
//

$form->addElement("header", null, _("Add Joint Member"));
$form->addElement("html", "<TR></TR>");

if($_REQUEST["mode"] == "admin") {  // Administrator is adding to a member's account
	$cUser->MustBeLevel(1);
	$form->addElement("hidden","mode","admin");
	if(isset($_REQUEST["member_id"])) {
		$form->addElement("hidden","member_id", $_REQUEST["member_id"]);
	} else {
		$ids = new cMemberGroup;
		$ids->LoadMemberGroup();
		$form->addElement("select", "member_id", _("Choose Member to Add Contact to"), $ids->MakeIDArray());
	}
} else {  // Member is adding to own account
	$cUser->MustBeLoggedOn();
	$form->addElement("hidden","member_id", $cUser->member_id);
	$form->addElement("hidden","mode","self");
}

$form->addElement("text", "first_name", _("First Name"), array("size" => 15, "maxlength" => 20));
$form->addElement("text", "mid_name", _("Middle Name"), array("size" => 10, "maxlength" => 20));
$form->addElement("text", "last_name", _("Last Name"), array("size" => 20, "maxlength" => 30));
$form->addElement("static", null, null, null); 

$today=getdate();
$options = array("language"=> _("en"), "format" => "dFY", "maxYear"=>$today["year"], "minYear"=>"1880"); // changed "en" by _("en") by ejkv
$form->addElement("date", "dob", _("Date of Birth"), $options);
$form->addElement("text", "mother_mn", _("Mother's Maiden Name"), array("size" => 20, "maxlength" => 30)); 
$form->addElement("static", null, null, null);
$form->addElement("select","directory_list", _("List this Person's Contact Information in the Directory?"), array("Y"=>_("Yes"), "N"=>_("No")));
$form->addElement("text", "email", _("Email Address"), array("size" => 25, "maxlength" => 40));
$form->addElement("text", "phone1", _("Primary Phone"), array("size" => 20));
$form->addElement("text", "phone2", _("Secondary Phone"), array("size" => 20));
$form->addElement("text", "fax", _("Fax Number"), array("size" => 20));
$form->addElement("static", null, null, null);
$form->addElement("text", "address_street1", _("Address Line 1"), array("size" => 25, "maxlength" => 50));
$form->addElement("text", "address_street2", _("Address Line 2"), array("size" => 25, "maxlength" => 50));
$form->addElement("text", "address_city", _("City"), array("size" => 25, "maxlength" => 50));

// TODO: The State and Country codes should be Select Menus, and choices should be built
// dynamically using an internet database (if such exists).
$state = new cStateList; // added by ejkv
$state_list = $state->MakeStateArray(); // added by ejkv
$state_list[0]="---"; // added by ejkv

// address_state_code textbox replaced by Select menu, and contents filled from Database table states
// $form->addElement("text", "address_state_code", _("State"), array("size" => 25, "maxlength" => 50));
$form->addElement("select", "address_state_code", _("State"), $state_list); // changed by ejkv
$form->addElement("text", "address_post_code", _("Zip Code"), array("size" => 10, "maxlength" => 20));
$form->addElement("text", "address_country", _("Country"), array("size" => 25, "maxlength" => 50));
$form->addElement("static", null, null, null);
$form->addElement('submit', 'btnSubmit', _("Create Contact"));

//
// Define form rules
//
$form->addRule('password', _("Password not long enough"), 'minlength', 7);
$form->addRule('first_name', _("Enter a first name"), 'required');
$form->addRule('last_name', _("Enter a last name"), 'required');
$form->addRule('address_city', _("Enter a")." " . _("City"), 'required');
$form->addRule('address_state_code', _("Enter a")." " . _("State"), 'required');
$form->addRule('address_post_code', _("Enter a")." "._("Zip Code"), 'required');
$form->addRule('address_country', _("Enter a country"), 'required');

$form->registerRule('verify_not_future_date','function','verify_not_future_date');
$form->addRule('dob', _("Birth date cannot be in the future"), 'verify_not_future_date');
$form->registerRule('verify_reasonable_dob','function','verify_reasonable_dob');
$form->addRule('dob', _("A little young, don't you think?"), 'verify_reasonable_dob');
$form->registerRule('verify_valid_email','function', 'verify_valid_email');
$form->addRule('email', _("Not a valid email address"), 'verify_valid_email');
$form->registerRule('verify_phone_format','function','verify_phone_format');
$form->addRule('phone1', _("Phone format invalid"), 'verify_phone_format');
$form->addRule('phone2', _("Phone format invalid"), 'verify_phone_format');
$form->addRule('fax', _("Phone format invalid"), 'verify_phone_format');


//
// Check if we are processing a submission or just displaying the form
//
if ($form->validate()) { // Form is validated so processes the data
   $form->freeze();
 	$form->process("process_data", false);
} else {
	$today = getdate();
	$current_date = array("Y"=>$today["year"], "F"=>$today["mon"], "d"=>$today["mday"]);
	$defaults = array("dob"=>$current_date, "address_state_code"=>DEFAULT_STATE, "address_country"=>DEFAULT_COUNTRY, "directory_list"=>"Y");
	$form->setDefaults($defaults);
   $p->DisplayPage($form->toHtml());  // just display the form
}

//
// The form has been submitted with valid data, so process it   
//
function process_data ($values) {
	global $p, $cUser,$cErr, $today;
	$list = "";

	$values['primary_member'] = "N"; 

	// [chris] fixed problem with passing an Array to htmlspecialchars()
	$date = $values['dob'];
	
	$values['dob'] = htmlspecialchars($date['Y'] . '/' . $date['F'] . '/' . $date['d']);
	
	if($values['dob'] == $today['year']."/".$today['mon']."/".$today['mday'])
		$values['dob'] = ""; // if birthdate was left as default, set to null
	
	$phone = new cPhone_uk($values['phone1']);
	$values['phone1_area'] = $phone->area;
	$values['phone1_number'] = $phone->SevenDigits();
	$values['phone1_ext'] = $phone->ext;
	$phone = new cPhone_uk($values['phone2']);
	$values['phone2_area'] = $phone->area;
	$values['phone2_number'] = $phone->SevenDigits();
	$values['phone2_ext'] = $phone->ext;	
	$phone = new cPhone_uk($values['fax']);
	$values['fax_area'] = $phone->area;
	$values['fax_number'] = $phone->SevenDigits();
	$values['fax_ext'] = $phone->ext;	

    // XSS guard
    foreach($values as $key => $value) {
        $values[$key] = htmlspecialchars($value);
    }

	$new_person = new cPerson($values);
	$created = $new_person->SaveNewPerson();
	
	$member = new cMember();
	$member->LoadMember($_REQUEST["member_id"]);
	
	if($created and $member->account_type == "S") {
		$member->account_type = "J";  // Now it's a Joint account
		$member->SaveMember();
	}	

	if($created) {
		$list .= _("Joint member created. Would you like to")." <A HREF=member_contact_create.php?mode=". $_REQUEST["mode"] ."&member_id=". $values["member_id"] .">"._("add another")."</A>?<P>";
	} else {
		$cErr->Error(_("There was an error saving the joint member.")." "._("Please try again later."));
	}
   $p->DisplayPage($list);
}
//
// The following functions verify form data
//

// TODO: All my validation functions should go into a new cFormValidation class
		
function verify_reasonable_dob($element_name,$element_value) {
	global $today;
	$date = $element_value;
	$date_str = $date['Y'] . '/' . $date['F'] . '/' . $date['d'];
//	echo $date_str ."=".$today['year']."/".$today['mon']."/".$today['mday'];

	if ($date_str == $today['year']."/".$today['mon']."/".$today['mday']) 
		// date wasn't changed by user, so no need to verify it
		return true;
	elseif ($today['year'] - $date['Y'] < 3)  // A little young to be trading, presumably a mistake
		return false;
	else
		return true;
}

function verify_not_future_date ($element_name,$element_value) {
	$date = $element_value;
	$date_str = $date['Y'] . '/' . $date['F'] . '/' . $date['d'];

	if (strtotime($date_str) > strtotime("now"))
		return false;
	else
		return true;
}

// TODO: This simplistic function should ultimately be replaced by this class method on Pear:
// 		http://pear.php.net/manual/en/package.mail.mail-rfc822.intro.php
function verify_valid_email ($element_name,$element_value) {
	if ($element_value=="")
		return true;		// Currently not planning to require this field
	if (strstr($element_value,"@") and strstr($element_value,"."))
		return true;	
	else
		return false;
	
}

function verify_phone_format ($element_name,$element_value) {
	$phone = new cPhone_uk($element_value);
	
	if($phone->prefix)
		return true;
	else
		return false;
}

?>
