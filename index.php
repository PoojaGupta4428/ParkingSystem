<?php

include('config.php');

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
	and( u.booking_valid_time < CURRENT_TIMESTAMP or u.booking_valid_time is NULL)
	Order by p.Is_reserved DESC
	";
	$res1 = mysqli_query($mysqli, $prec);
	$availableParkingSlot =array();

	while ($row = mysqli_fetch_row($res1)) {
		array_push($availableParkingSlot, $row);
	}
	return $availableParkingSlot;
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

// book a slot for parking
function CreatingBooking($user_id){
	global $mysqli;
	$userExisingBooking = checkIfExistingValidBooking($user_id);
     if($userExisingBooking!= null)
      {
      	$boolingDetail['parkingId']=$userExisingBooking[1];
		$boolingDetail['bookingId']=$userExisingBooking[0];
		$boolingDetail['Messgae']="user has exising booking till time ".$userExisingBooking[2];
		print_r($boolingDetail);
		return $boolingDetail;
      }

	// Get user category by user id
	$rec = mysqli_query($mysqli,"SELECT category
		FROM
		user
		WHERE
		id=".$user_id."");
	while ($row = mysqli_fetch_row($rec)) {
		$user_categoty = $row[0];
	}

	$availableParking = getAllAvailableParkingSlot();
	$availableLotsCount = count($availableParking);
	$totalParkingSlot = PARKINGSLOT;

	$PersentageOccupency =100 - ($availableLotsCount/$totalParkingSlot)*100;

	if($user_categoty == GERNAL_CATEGORY)
	{ 
		if($availableParking[$availableLotsCount-1][IS_RESERVED_IDX]==0)
		{
			if($PersentageOccupency<50)
				return bookSeat($availableParking[$availableLotsCount-1][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+30 minutes")));
			else
				return bookSeat($availableParking[$availableLotsCount-1][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+15 minutes")));
		}
	}elseif($user_categoty == PHYSICAL_HANDICAP or $user_categoty == PREGNANT_WOMAN){

		if($PersentageOccupency<50)
			return bookSeat($availableParking[0][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+30 minutes")));
		else
			return bookSeat($availableParking[0][PARKING_ID_IDX],$user_id,date("Y-m-d H:i:s"),date("Y-m-d H:i:s", strtotime("+15 minutes")));
		
	}			

}


function Vehical_parked($booking_id){
	global $mysqli;
	$Is_occupied = '1';
	echo "Cas";
	// if user booking time is exceed
	echo $prec = "SELECT pid ,user_id
	FROM userbookings 
	where bookinng_id=$booking_id
	and booking_valid_time > CURRENT_TIMESTAMP";

	$res1 = mysqli_query($mysqli, $prec);
	$row = mysqli_fetch_row($res1);
	if($row == null){
		$result['msg'] = "Booking is not valid now. Please try another booking";
		return $result;
	}
	$pid = $row[0];
	$userid = $row[1];
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
		echo "error Message:".$e->getMessage();
	}
}

function VehicalUnparked($userId, $pid){

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

//Does user have some current valid booking which is not expired yet.
function checkIfExistingValidBooking($user_id){
	global $mysqli;
	//Does user have some current valid booking which is not expired yet.
	echo $prec =	"SELECT u.bookinng_id,u.pid,u.booking_valid_time
	from userbookings as u
	where u.user_id=$user_id and u.booking_valid_time > CURRENT_TIMESTAMP ";
	$res1 = mysqli_query($mysqli, $prec);

     $userExisingBooking1 = mysqli_fetch_row($res1);
     return $userExisingBooking1;
}


function bookSeat($pid,$userId,$bookingStartTime, $bookingEndTime){
	global $mysqli;
	$result = array();
	try{
		$stmt1= mysqli_query($mysqli,"INSERT INTO userbookings (pid, Booking_start_time, booking_valid_time,user_id) VALUES ('$pid', '$bookingStartTime', '$bookingEndTime', '$userId');");

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
		echo "error Message".$e->getMessage();
	}
	return $result;
}

// Create user
function register_user($user_name,$user_email,$user_type){
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
		$user_name,
		$user_email,
		$user_type
		);
	$stmt->execute();
	$stmt->close();

	return true;
}

$c=3;
$t1="gen";
$t2="ph";
$t3="pw";
//$test = CreatingBooking(2);
//register_user("user".$c,$c."email@pickmysolar.com",$t1);
//CreatingBooking("10");

?>