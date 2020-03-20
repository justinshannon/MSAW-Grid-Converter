<?php
#error_reporting(E_ALL);
#ini_set('display_errors', '1');

$debug = false;
$error = "";
$msaw = [];
$parse = false;

if(isset($_POST['submit'])) {
	$magvar = $_POST['magvar'];
	$path = "uploads/";
	$path = $path . md5($_FILES['msawFile']['name'] . microtime()) . ".txt";
	if(move_uploaded_file($_FILES['msawFile']['tmp_name'], $path)) {
		if($file = fopen($path, 'r')) {
			$p = 0;
			$i = 0;
			while(($line = fgets($file)) !== false) {
				if(substr($line, 0, 1) === '&') continue;
				if(substr($line, 0, 1) === '#') {
					if($p == 11) {
						$gridSize = ParseGridSize($line);
					}
					$p++;
				}
				else if(substr($line, 0, 1) === "$") {
					switch($i) {
						case 0:
							$file_name = sprintf("MSAW-%s.xml", trim(str_replace("$", "", substr($line, 0, 40))));
							break;
						case 3:
							$lat = @ToDecimal($line);
							break;
						case 4:
							$lon = @ToDecimal($line);
							break;
						case 5:
							$numCols = GetRowCol($line);
							break;
						case 6:
							$numRows = GetRowCol($line);
							break;
					}
					$i++;
				}
				else {
					$x = explode(' ', $line);
					$msaw[intval($x[1] + 1)][intval($x[2] + 1)] = $x[3];			
				}					
			}
			$parse = true;
			fclose($file);
		}
	}
	
	if(file_exists($path)) {
		unlink($path);
	}
	
	if($parse) {
		$xmlWriter = new XMLWriter();
		$xmlWriter->openMemory();
		$xmlWriter->setIndent(true);
		
		for($col = 1; $col <= $numCols; $col++) {
			for($row = 1; $row <= $numRows; $row++) {
				
				// <SystemVolume>
				$xmlWriter->startElement('SystemVolume');
				
				// <SystemVolume Attributes>
				$xmlWriter->writeAttribute('VolumeType', 'MSAW');
				$xmlWriter->writeAttribute('Name', sprintf('Column %s of %s', $col, $numCols));
				$xmlWriter->writeAttribute('Floor', 0);
				$xmlWriter->writeAttribute('Ceiling', @intval($msaw[$col][$row]));
				$xmlWriter->writeAttribute('Airport', 0);
				$xmlWriter->writeAttribute('VolumeShape', 'Polygon');
				$xmlWriter->writeAttribute('Radius', 0);
				
				// <Center>
				$xmlWriter->startElement('Center');
				$xmlWriter->writeAttribute('Lon', 0);
				$xmlWriter->writeAttribute('Lat', 0);
				$xmlWriter->endElement();
				// </Center>
				
				// <Region>
				$xmlWriter->startElement('Region');
				
				// <Points>
				$xmlWriter->startElement('Points');
				
				if($row == 1) {
					if($col > 1) {
						$lat = $startLat;
						$lon = $startLon;
					}
					$a["lat"] = $lat;
					$a["lon"] = $lon;
					$b = Extrapolate($a["lat"], $a["lon"], 90 + $magvar, $gridSize); // 90
					$c = Extrapolate($a["lat"], $a["lon"], $magvar, $gridSize);  // 360
					$d = Extrapolate($c["lat"], $c["lon"], 90 + $magvar, $gridSize); // 90
					
					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $a["lon"]);
					$xmlWriter->writeAttribute('Lat', $a["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>

					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $b["lon"]);
					$xmlWriter->writeAttribute('Lat', $b["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>				
					
					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $d["lon"]);
					$xmlWriter->writeAttribute('Lat', $d["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>
					
					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $c["lon"]);
					$xmlWriter->writeAttribute('Lat', $c["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>
					
					$startLat = $b["lat"];
					$startLon = $b["lon"];
				}
				else {
					$a = $c;
					$b = Extrapolate($a["lat"], $a["lon"], 90 + $magvar, $gridSize); // 90
					$c = Extrapolate($a["lat"], $a["lon"], $magvar, $gridSize); // 360
					$d = Extrapolate($c["lat"], $c["lon"], 90 + $magvar, $gridSize); // 90

					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $a["lon"]);
					$xmlWriter->writeAttribute('Lat', $a["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>

					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $b["lon"]);
					$xmlWriter->writeAttribute('Lat', $b["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>				
					
					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $d["lon"]);
					$xmlWriter->writeAttribute('Lat', $d["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>
					
					// <WorldPoint>
					$xmlWriter->startElement('WorldPoint');
					$xmlWriter->writeAttribute('Lon', $c["lon"]);
					$xmlWriter->writeAttribute('Lat', $c["lat"]);
					$xmlWriter->endElement();
					// </WorldPoint>
					
					$lat = $b["lat"];
				}
				
				$lon = $b["lon"];
				
				// </Points>
				$xmlWriter->endElement();
				
				// </Region>
				$xmlWriter->endElement();
				
				// </SystemVolume>
				$xmlWriter->endElement();
				
				if($row % 20 == 0) {
					file_put_contents("uploads/".$file_name, $xmlWriter->flush(true), FILE_APPEND);
				}
			}
			
			if($col % 20 == 0) {
				file_put_contents("uploads/".$file_name, $xmlWriter->flush(true), FILE_APPEND);
			}
		}
		
		file_put_contents("uploads/".$file_name, $xmlWriter->flush(true), FILE_APPEND);
		
		// download file
		header('Content-type: text/xml');
		header("Content-Disposition: attachment; filename=\"$file_name\"");
		header('Content-Length: ' . filesize("uploads/".$file_name));
		readfile("uploads/".$file_name);
		
		if(file_exists("uploads/".$file_name)) {
			unlink("uploads/".$file_name);
		}
	}
}

function ToDecimal($coord) {
	$c = explode(':', strstr($coord, '=', true));
	
	$dir = trim(str_replace("$", "", $c[0]));
	$deg = $c[1];
	$min = $c[2];
	$sec = $c[3];
	
	$dd = $deg + ($min / 60) + ($sec / (60*60));
	
	if($dir == "S" || $dir == "W") {
		$dd *= -1;
	}
	
	return round($dd, 6);
}

function GetRowCol($line) {
	return intval(trim(str_replace("$", "", strstr($line, "=", true))));
}

function ParseGridSize($line) {
	return floatval(trim(str_replace("#", "", strstr($line, "=", true))));
}

function NormalizeEast($deg) {
	$flag = $deg <= 0.0;
	if($flag) {
		$deg += 360.0;
	} else {
		$flag2 = $deg > 360.0;
		if($flag2) {
			$deg -= 360.0;
		}
	}
	return $deg;
}

function Extrapolate($lat1,$long1,$angle,$d)
{
	$magvar = 15;
	$magvar = $magvar * (M_PI/180);
	
	# Earth Radious in KM
	$R = 6378.14;

	# Degree to Radian
	$latitude1 = $lat1 * (M_PI/180);
	$longitude1 = $long1 * (M_PI/180);
	$brng = $angle * (M_PI/180);

	# Distance to NM
	$d *= 1.85200;

	$latitude2 = asin(sin($latitude1)*cos($d/$R) + cos($latitude1)*sin($d/$R)*cos($brng));
	$longitude2 = $longitude1 + atan2(sin($brng)*sin($d/$R)*cos($latitude1),cos($d/$R)-sin($latitude1)*sin($latitude2));

	# back to degrees
	$latitude2 = $latitude2 * (180/M_PI);
	$longitude2 = $longitude2 * (180/M_PI);

	$lat2 = round ($latitude2,6);
	$long2 = round ($longitude2,6);

	// Push in array and get back
	$coords["lat"] = $lat2;
	$coords["lon"] = $long2;
	return $coords;
 }
?>