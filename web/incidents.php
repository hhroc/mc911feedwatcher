<?php
	require_once("_header.php");
?>
	
	<?php

		//
		// Sanity Check Inputs
		//
		
		require_once("./tools/UtilityManager.class.php");
		
		$util = new UtilityManager();
	
		// get the posted data variable
		if( isset($_GET['date']) )
			$date = $_GET['date'];
		else
			$date = date("Y-m-d");

		// see if we got a date passed in, or if we should be use todays date
		if( $date == "" )
		{
			$date = date("Y-m-d");
		}
		
		// check for none-case ... we handle as the current date later in code
		if( $date != "" )
		{
		
			// check that the date is valid
			if( $util->IsValidDate($date) == 0 || $util->IsValidDate($date) == False )
			{
				// not a valid date

				echo '<script>';
				echo 'window.location = "./index.php"';
				echo '</script>';
			}
			else
			{
				// move on to rest of page, no need to do anything
			}
			
		}
		
	?>

	
	<?php
	
		//require_once("./tools/Database.class.php");
		
		require_once("./tools/IncidentManager.class.php");
		require_once("./tools/Incident.class.php");
		require_once("./tools/AgencyManager.class.php");
		require_once("./tools/Agency.class.php");
		
		// calculate tomorrow
		$tomorrowtime = strtotime ('+1 day', strtotime($date)) ;
		$tommorrow = date('Y-m-d', $tomorrowtime);
		
		// calculate yesterday
		$yesterdaytime = strtotime ('-1 day', strtotime($date)) ;
		$yesterday = date('Y-m-d', $yesterdaytime);
	
		// get all of the incidents for the date passed in by the user
		$incidentManager = new IncidentManager();
		$incidents = $incidentManager->GetIncidentsByDay($date);
	
		// to handle all agency related querys
		$agencyManager = new AgencyManager();
	
		// display links to go to previous day and next day
		
		echo '<div class="yesterdaylink">';
		echo '<a href="incidents.php?date=' . $yesterday . '">Incidents for ' . date("l F j, Y",strtotime($yesterday)) . '</a>';
		echo '</div>';
		
		if( $date != date("Y-m-d") )
		{
			echo '<div class="tomorrowlink">';
			echo '<a href="incidents.php?date=' . $tommorrow . '">Incidents for ' . date("l F j, Y",strtotime($tommorrow)) . '</a>';
			echo '</div>';				
		}

		echo '<br><br>';

		echo '<div>';

		echo '<br>';

		echo '<center><h2>Incidents for ' . date("l F j, Y",strtotime($date)) . '</h2></center>';

		echo '<center>';
		echo '<br>';
		echo '<a href="stats.php?date=' . $date . '">See Stats For ' . date("l F j, Y",strtotime($date)) . '</a>';
		echo '</center>';
	
		echo '</div>';
	
		//
		// MAP
		// 
		echo '</br>';
		
		echo '<div id="mapwrapper" class="mapwrapper">';
		
		echo '<div id="map" class="map" style="width: 500px; height: 400px;"></div>';
		echo '<div id="mapsettings" class="mapsettings"></div>';
		echo '<div class="clear"></div>';
		echo '</div>';
	
	
		echo '<div>';
		if( count($incidents) == 0 )
		{
			echo "<br>";
			echo "<h3>No incidents were found for day: " . $date . "</h3>";
			echo "<br>";
		}
		else
		{
			echo"<br><br>";
			echo "Total number of incidents today:<b>" . count($incidents) . "</b><br><br>";
		
			echo '<div class="incidents">';
			echo '<table>';
			echo '<tr>';
			echo '<td><b><font size="4">Time</font></b></th>';
			echo '<td><b><font size="4">Event</font></b></th>';
			echo '<td><b><font size="4">Address</font></b></th>';
			echo '<td><b><font size="4">Responding Agency</font></b></th>';
			echo '<td><b><font size="4">Event ID</font></b></th>';
			echo '</tr>';
		
			// generate dictionaries so we don't have to query the DB every time.
			$longNameDict = $agencyManager->GetAgencyLongNameDictionary();
			$shortNameDict = $agencyManager->GetAgencyShortNameDictionary();
		
			// print the events to the page
			foreach($incidents as $incident)
			{
				
				// print out the row
				echo '<tr>';
				//echo '<a name="' . $incident->itemid . '"></a>';
				echo '<td width="100">' . $incident->pubtime . '</td>';
				echo '<td width="400">' . $incident->event . '</td>';
				if( $incident->lat == "" || $incident->lng == "" )
					echo '<td width="300">' . $incident->address . '</td>';
				else
					echo '<td width="300"><a href="https://maps.google.com/maps?z=16&t=m&q=loc:' . $incident->lat . "+" . $incident->lng . '">' . $incident->address . '</a></td>';
				echo '<td width="250"><a href="viewagency.php?agency=' . $shortNameDict[$incident->agencyid] . '">' . $longNameDict[$incident->agencyid] . '</a></td>';
				echo '<td width="100">' . $incident->itemid . '</td>';
				echo '</tr>';
			}
		
			echo '</table>';
			echo '</div>';
		}
		
		echo '</div>';
		
	?>	

	<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
	<script src="http://maps.google.com/maps/api/js?sensor=false" type="text/javascript"></script>
	<script type="text/javascript">

	//
	//
	// create all global vars

	var mapdiv = document.getElementById('map');

	var markerArray = [];

    var map = new google.maps.Map(mapdiv, {
        zoom: 10,
        center: new google.maps.LatLng(43.1547, -77.6158),
        mapTypeId: google.maps.MapTypeId.ROADMAP
    });

    var currentinfowindow = new google.maps.InfoWindow();

	//
	// page js functions
	// 

	function createcheckboxes()
	{
		var html = '<div class="left">';
	
		url = "./api/counts.php?date=<?php echo $date; ?>&type=dailycounts";
		$.getJSON(url, function (response) {
			
			// create check boxes
			for(n=0; n<response.length; n++)
			{
				html += '<input class="checkbox" type="checkbox" name="' + response[n].incidentname + '" value="' + response[n].id + '">' + response[n].incidentname + '</br>';
			}
			
			// add clear button
			html += '</br><button type="button" id="btnclearmap" name="btnclearmap">Clear Map</button>';
			
			html += '</div>';
			$("#mapsettings").html(html);
			
			$("#btnclearmap").click( function()
			{
				// clear the map
				clearmarkers();
				
				// clear the check boxes
				$(":checked").each( function() {
					this.checked = false;
				});
			});
			
			$(".checkbox").change(function() {

				if(this.checked == true) {
					$(":checked").each(
						function(i,data){
							var url = "./api/getgeo.php?date=<?php echo $date; ?>&typeid=" + $(data).val();
							$.getJSON(url, function (response) { 
								var n;
								for(n=0; n<response.length; n++)
								{
									
									// decode json data
									var lat = response[n].lat;
									var lng = response[n].lng;
									var incident = response[n].incident;
									var itemid = response[n].itemid;
									var fulladdress = response[n].fulladdress;
									
									// create marker from json data
									var myLatLng = new google.maps.LatLng(lat,lng);
									var marker = new google.maps.Marker({
										position: myLatLng,
										//shadow: shadow,
										//icon:image,
										map: map,
										title: incident,
										zIndex: 1
									});
									
									createpopup(marker,'<b>' + incident + '</b></br>' + itemid + '</br>' + fulladdress + '</br>' + lat + ', ' + lng + '</br>');
									
									// push the marker to the array of markers on the map
									markerArray.push(marker);
									
									//marker = "";
								}   
							});
						}
					);
				}
				else
				{
					// clear map of check box type
					if (markerArray) {
						for (i in markerArray) {
							if( markerArray[i].title == this.name )
							{
								markerArray[i].setMap(null);
								
								// TODO: remove item from the array ... becaues this is an empic memory leak
							}
						}
						//markerArray.length = 0;
					}
				}
			});
			
			// check all check boxes
			$(".checkbox").each( function()
			{
				$(this).attr('checked', true);
				$(this).trigger('change');
			});
		});
		
	}

	function createpopup(marker, contentstring)
	{
		// add pop-up listener to marker
		google.maps.event.addListener(marker, 'click', function() {
			if( currentinfowindow )
			{
				currentinfowindow.close();
				currentinfowindow = new google.maps.InfoWindow({
					content: contentstring
				});
				currentinfowindow.open(map, marker);
			}
		});
	}

	function clearmarkers()
	{
		// clear map of check box type
		if (markerArray) {
			for (i in markerArray) {				
				markerArray[i].setMap(null);
				
				//
				// TODO: remove item from the array ... becaues this is an empic memory leak
				//
			}
			//markerArray.length = 0;
		}
	}

	function checkallboxes()
	{
		// check all of the check boxes
		
	}

    //
	// page primary function
	//
		
	// create all the map settings html
	createcheckboxes();
	
	</script>
			
<?php
	require_once("_footer.php");
?>