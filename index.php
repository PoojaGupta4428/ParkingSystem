<?php

include('config.php');

// Public APIs

// Create user
function registerUser($userName,$userEmail,$userType){
	global $mysqli;
	$stmt = $mysqli->prepare("
		INSERT INTO
		user(
			name,
			email,
			category
			) VALUES (
			?,
			?,
			?
			)
	");

	$stmt->bind_param('sss',
		$userName,
		$userEmail,
		$userType
		);
	$stmt->execute();
	$stmt->close();

	return true;
}

// Get all Registered users
function getAllResisteredUser(){
	global $mysqli;
	$rec = "SELECT *
	FROM
	user
	WHERE
	is_active='1'";
	$uRes = mysqli_query($mysqli, $rec);
	$user_array = array();
	while ($uRow = mysqli_fetch_assoc($uRes)) {
		array_push($user_array, $uRow);
	}
	return $user_array;

}

// we are assuming => total occupied lots= actual occupied lots + unoccupied lots under future booking_end_time.
function getAllOccupiedParkingSlot(){
	global $mysqli;
	$pRec =	"SELECT p.pid, p.Is_reserved 
	FROM parkinglot as p LEFT JOIN userbookings as u 
	ON p.pid = u.pid
	where p.Is_occupied='0'
	and( u.booking_valid_time < CURRENT_TIMESTAMP or u.booking_valid_time is NULL)
	Order by p.Is_reserved DESC ";
	$res1 = mysqli_query($mysqli, $prec);
	$OccupiedParkingSlot =array();
	while ($row = mysqli_fetch_row($res1)) {
		array_push($OccupiedParkingSlot, $row['0']);
	}
	return $OccupiedParkingSlot;
}

// Get All free avilable parkingSlot
function getAllAvailableParkingSlot(){
	global $mysqli;
	$prec =	"SELECT p.pid, p.Is_reserved 
	FROM parkinglot as p LEFT JOIN userbookings as u 
	ON p.pid = u.pid
	where p.Is_occupied='0'
	and( u.booking_valid_time < CURRENT_TIMESTAMP and u.bookinng_id=p.last_bookinng_id or u.booking_valid_time is NULL)
	Order by p.Is_reserved DESC
	";
	$res1 = mysqli_query($mysqli, $prec);
	$availableParkingSlot =array();

	while ($row = mysqli_fetch_row($res1)) {
		array_push($availableParkingSlot, $row);
	}
	return $availableParkingSlot;
}

// book a slot for parking
function creatingNewBooking($user_id , $vehicalNumber){
	global $mysqli;

	// Get user category by user id and validate
	$rec = mysqli_query($mysqli,"SELECT category,is_active
		FROM
		user
		WHERE
		id=".$user_id."");
	$row = mysqli_fetch_row($rec);
	if($row==null){
		$msg = "User is not a valid user with id ".$user_id;
		return $msg;
	}
	$user_categoty = $row[0];
	$isActiveUser=$row[1];
	if($isActiveUser!=1)
	{
		$msg = "User is not a active user with ".$user_id;
		return $msg;
	}


	$userExisingBooking = checkIfExistingValidBooking($user_id);
	if($userExisingBooking!= null)
	{
		$boolingDetail['parkingId']=$userExisingBooking[1];
		$boolingDetail['bookingId']=$userExisingBooking[0];
		$boolingDetail['Messgae']="user has exising booking till time ".$userExisingBooking[2];
		print_r($boolingDetail);
		return $boolingDetail;
	}

	$availableParking = getAllAvailableParkingSlot();
	$availableLotsCount = count($availableParking);
	if($availableLotsCount==0)
	{
		return "Parkinglot is full";
	}
	$totalParkingSlot = PARKINGSLOT;

	$PersentageOccupency =100 - ($availableLotsCount/$totalParkingSlot)*100;

	if($user_categoty == GERNAL_CATEGORY)
	{ 
		if($availableParking[$availableLotsCount-1][IS_RESERVED_IDX]==0)
		{
			if($PersentageOccupency<50)
				return bookParking($availableParking[$availableLotsCount-1][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+30 minutes")),$vehicalNumber);
			else
				return bookParking($availableParking[$availableLotsCount-1][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+15 minutes")),$vehicalNumber);
		}
	}elseif($user_categoty == PHYSICAL_HANDICAP or $user_categoty == PREGNANT_WOMAN){

		if($PersentageOccupency<50)
			return bookParking($availableParking[0][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+30 minutes")),$vehicalNumber);
		else
			return bookParking($availableParking[0][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+15 minutes")),$vehicalNumber);
		
	}			

}


function parkVehical($booking_id, $vehicalNumber){
	global $mysqli;
	$Is_occupied = '1';
	// if user booking time is exceed
	$prec = "SELECT pid ,user_id,vehical_number
	FROM userbookings 
	where bookinng_id=$booking_id
	and booking_valid_time > CURRENT_TIMESTAMP";

	$res1 = mysqli_query($mysqli, $prec);
	$bookingInfo = mysqli_fetch_row($res1);
	print_r($bookingInfo);
	if($bookingInfo == null){
		$result['msg'] = "Booking is not valid now. Please try another booking";
		return $result;
	}
	$bookingInfo[2];
	if($bookingInfo[2]!=$vehicalNumber)
	{
		$msg = "This booking is not valid for this vahical number".$vehicalNumber;
		return $msg;
	}
	$pid = $bookingInfo[0];
	$userid = $bookingInfo[1];
	// checl isBookingValid for $pid,$userid
	try{
		$stmt2 = $mysqli->prepare("
			UPDATE
			ParkingLot
			SET
			Is_occupied = ?
			WHERE
			pid = ? 
			AND
			last_user_id = ?");
		$stmt2->bind_param('sss',
			$Is_occupied,
			$pid,
			$userid);
	$stmt2->execute(); // it may not find a entry for 
}catch (Exception $e){
	"error Message:".$e->getMessage();
}
}


function unparkVehical($userId, $pid){

	global $mysqli;
	$Is_occupied = '0';
	$stmt3 = $mysqli->prepare("
		UPDATE
		ParkingLot
		SET
		Is_occupied = ?
		WHERE
		pid = ? 
		AND
		last_user_id = ?");
	$stmt3->bind_param('sss',
		$Is_occupied,
		$pid,
		$userid);
	$stmt3->execute();

}

// private functions
//Does user have some current valid booking which is not expired yet.
function checkIfExistingValidBooking($user_id){
	global $mysqli;
	//Does user have some current valid booking which is not expired yet.
	 $prec =	"SELECT u.bookinng_id,u.pid,u.booking_valid_time
	from userbookings as u
	where u.user_id=$user_id and u.booking_valid_time > CURRENT_TIMESTAMP ";
	$res1 = mysqli_query($mysqli, $prec);

	$userExisingBooking1 = mysqli_fetch_row($res1);
	return $userExisingBooking1;
}


function bookParking($pid,$userId,$bookingStartTime, $bookingEndTime,$vehicalNumber){
	global $mysqli;
	$result = array();
	try{
		$stmt1= mysqli_query($mysqli,
			"INSERT INTO userbookings (pid, Booking_start_time, booking_valid_time,user_id,vehical_number) 
			VALUES ('$pid', '$bookingStartTime', '$bookingEndTime', '$userId','$vehicalNumber');");

		$booking_id = mysqli_insert_id($mysqli);
		$stmt2 = $mysqli->prepare("
			UPDATE
			ParkingLot
			SET
			last_bookinng_id = ?,
			last_user_id = ?
			WHERE
			pid = ?");
		$stmt2->bind_param('sss',
			$booking_id,
			$userId,
			$pid);
		$stmt2->execute();
		$result['parkingId']=$pid;
		$result['bookingId']=$booking_id;
		$result['Messgae']="one Parkinglot is booked till time ".$bookingEndTime;
	} catch(Exception $e){
		 "error Message".$e->getMessage();
	}
	return $result;
}

function createMasterData()
{
	global $mysqli;
	// 
	$obj = mysqli_query($mysqli, "SELECT *
		FROM parkinglot where Is_reserved='1'");
	print_r($obj);
	echo $obj->num_rows;
	echo $reservedSeats = $obj->num_rows;
	for($i=$reservedSeats;$i<24;$i++)
	{

		mysqli_query($mysqli,
			"INSERT INTO parkinglot (Is_reserved) 
			VALUES ('1');");
	}


	$obj = mysqli_query($mysqli, "SELECT *
		FROM parkinglot where Is_reserved='0'");
	echo $unreservedSeats = $obj->num_rows;

	for($i=$unreservedSeats;$i<96;$i++)
	{
		mysqli_query($mysqli,
			"INSERT INTO parkinglot (Is_reserved) 
			VALUES ('0');");
	}

}
//Testting Area

//createMasterData();


$c=16;
$t1="gen";
$t2="ph";
$t3="pw";
//$test = CreatingBooking(2);
registerUser("user".$c,$c."email@pickmysolar.com",$t2);

$bookingInfo = CreatingNewBooking($c+7,"vehical".$c);

//parkVehical($bookingInfo['bookingId'],"vehical".$c);
//unparkVehical(22,$bookingInfo['parkingId']);

// print_r(getAllAvailableParkingSlot());

// https://github.com/PoojaGupta4428/ParkingSystem
?>

