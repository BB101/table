<?php
require("inc/common.php");

//fetch the role of the staff member with an id of 1
$role = table("authentication_roles")->get("users.staff_members.id=1");
echo $role."<br/>";

//we're given a table_view back, this is iterable, countable, etc
echo "count of roles with staff member id 1: ".count($role)."<br/>";
//despite getting a table_view back, the system knows it's unique, so ->name resolves peacefully to the first entry
echo $role->name." users:<br/>";
//now loop through the users that have active staff_member profiles with this role and output their firstnames ordered by last_login
foreach ($role->users as $user) {
  echo $user->first_name." ".$user->last_name."<br />";
}
?>