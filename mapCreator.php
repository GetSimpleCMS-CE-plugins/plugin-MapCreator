<?php

# get correct id for plugin
$thisfile = basename(__FILE__, ".php");

# add in this plugin's language file
i18n_merge('mapCreator') || i18n_merge('mapCreator', 'en_US');

# register plugin
register_plugin(
	$thisfile,
	'MapCreator',
	'6.1',
	'Multicolor',
	'https://discord.gg/vkySHPxpg2',
	i18n_r('mapCreator/LANG_Description'),
	'plugins',
	'mapCreator'
);

# Register local Leaflet styles & scripts (1.9.4)
register_style('leaflet-css', $SITEURL . 'plugins/mapCreator/css/leaflet.css', '1.9.4', 'all');
register_script('leaflet-js', $SITEURL . 'plugins/mapCreator/js/leaflet.js', '1.9.4', false);

# Register MarkerCluster styles & scripts
register_style('leaflet-cluster-css', $SITEURL . 'plugins/mapCreator/css/MarkerCluster.css', '1.5.3', 'all');
register_style('leaflet-cluster-default-css', $SITEURL . 'plugins/mapCreator/css/MarkerCluster.Default.css', '1.5.3', 'all');
register_script('leaflet-cluster-js', $SITEURL . 'plugins/mapCreator/js/leaflet.markercluster.js', '1.5.3', false);

# Enqueue for both frontend and admin backend
queue_style('leaflet-css', GSFRONT);
queue_script('leaflet-js', GSFRONT);
queue_style('leaflet-cluster-css', GSFRONT);
queue_style('leaflet-cluster-default-css', GSFRONT);
queue_script('leaflet-cluster-js', GSFRONT);

queue_style('leaflet-css', GSBACK);
queue_script('leaflet-js', GSBACK);
queue_style('leaflet-cluster-css', GSBACK);
queue_style('leaflet-cluster-default-css', GSBACK);
queue_script('leaflet-cluster-js', GSBACK);

# Admin sidebar navigation
add_action('plugins-sidebar', 'createSideMenu', array($thisfile, i18n_r('mapCreator/LANG_Settings')));

function getSvgMarkerScript() {
	return "
	function createColorIcon(colorHex) {
		const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 44' width='32' height='44'>
			<defs>
				<filter id='dropShadow' x='-20%' y='-20%' width='150%' height='150%'>
					<feDropShadow dx='1' dy='3' stdDeviation='2' flood-color='#000000' flood-opacity='0.4'/>
				</filter>
			</defs>
			<path d='M16 2 C8.82 2 3 7.82 3 15 C3 24.5 16 40 16 40 S29 24.5 29 15 C29 7.82 23.18 2 16 2 Z' 
				  fill='` + colorHex + `' stroke='#ffffff' stroke-width='1.5' filter='url(#dropShadow)'/>
			<circle cx='16' cy='15' r='5' fill='#ffffff'/>
		</svg>`;
		return L.icon({
			iconUrl: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
			iconSize: [32, 44],
			iconAnchor: [16, 40],
			popupAnchor: [0, -36]
		});
	}";
}

function renderMapHtmlAndScript($mapId, $mapData) {
	$heightVal = htmlspecialchars($mapData['height']);
	$aspectRatio = isset($mapData['aspectRatio']) && $mapData['aspectRatio'] !== 'none' ? $mapData['aspectRatio'] : '';
	$center = !empty($mapData['center']) ? $mapData['center'] : '51.505, -0.09';
	$zoom = intval($mapData['zoom']);
	$scroll = ($mapData['mousescroll'] === 'true' || $mapData['mousescroll'] === true) ? 'true' : 'false';
	$useClustering = (!isset($mapData['clustering']) || $mapData['clustering'] === 'true' || $mapData['clustering'] === true);

	$containerStyle = "width:100%;";

	if (!empty($aspectRatio)) {
		$containerStyle .= "aspect-ratio:" . str_replace(':', '/', $aspectRatio) . ";";
	} else {
		// Fallback for legacy % heights to prevent collapsed containers
		if (substr($heightVal, -1) === '%') {
			$heightVal = '400px';
		}
		$containerStyle .= "height:" . $heightVal . ";";
	}

	$jsonPoints = json_encode(isset($mapData['points']) ? $mapData['points'] : array());

	$output = "<div id='" . $mapId . "' style='" . $containerStyle . "'></div>\n";
	$output .= "<script>\n";
	$output .= "document.addEventListener('DOMContentLoaded', function() {\n";
	$output .= getSvgMarkerScript() . "\n";
	$output .= "    var mapInstance = L.map('" . $mapId . "', {\n";
	$output .= "        center: [" . $center . "],\n";
	$output .= "        zoom: " . $zoom . ",\n";
	$output .= "        scrollWheelZoom: " . $scroll . "\n";
	$output .= "    });\n";
	$output .= "    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {\n";
	$output .= "        maxZoom: 19,\n";
	$output .= "        attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>'\n";
	$output .= "    }).addTo(mapInstance);\n";

	if ($useClustering) {
		$output .= "    var markerGroup = L.markerClusterGroup();\n";
	} else {
		$output .= "    var markerGroup = mapInstance;\n";
	}

	$output .= "    var points = " . $jsonPoints . ";\n";
	$output .= "    var autoOpenMarkers = [];\n";
	$output .= "    points.forEach(function(pt) {\n";
	$output .= "        if (!pt.coords) return;\n";
	$output .= "        var coords = pt.coords.split(',').map(Number);\n";
	$output .= "        var markerOptions = {};\n";
	$output .= "        if (pt.color) { markerOptions.icon = createColorIcon(pt.color); }\n";
	$output .= "        var marker = L.marker(coords, markerOptions).addTo(markerGroup);\n";
	$output .= "        if (pt.popup) {\n";
	$output .= "            marker.bindPopup(pt.popup, { autoClose: false, closeOnClick: false });\n";
	$output .= "            if (pt.showPopup === true || pt.showPopup === 'true' || pt.showPopup === '1' || pt.showPopup === 1) {\n";
	$output .= "                autoOpenMarkers.push(marker);\n";
	$output .= "            }\n";
	$output .= "        }\n";
	$output .= "    });\n";

	if ($useClustering) {
		$output .= "    mapInstance.addLayer(markerGroup);\n";
	}

	$output .= "    setTimeout(function() {\n";
	$output .= "        mapInstance.invalidateSize();\n";
	$output .= "        autoOpenMarkers.forEach(function(m) { m.openPopup(); });\n";
	$output .= "    }, 250);\n";
	$output .= "});\n";
	$output .= "</script>\n";

	return $output;
}

function parseLegacyMapTxt($content) {
	$data = array(
		'height' => '400px',
		'aspectRatio' => 'none',
		'zoom' => 13,
		'center' => '51.505, -0.09',
		'mousescroll' => 'false',
		'clustering' => 'true',
		'points' => array()
	);

	if (preg_match('/height:\s*([^;}]+)/i', $content, $m)) {
		$val = trim($m[1]);
		$data['height'] = (substr($val, -1) === '%') ? '400px' : $val;
	}
	if (preg_match('/center:\s*\[([^\]]+)\]/i', $content, $m)) {
		$data['center'] = trim($m[1]);
	}
	if (preg_match('/zoom:\s*([0-9]+)/i', $content, $m)) {
		$data['zoom'] = intval($m[1]);
	}
	if (preg_match('/scrollWheelZoom:\s*(true|false)/i', $content, $m)) {
		$data['mousescroll'] = $m[1];
	}

	preg_match_all('/L\.marker\(\[([^\]]+)\]\)(?:\.addTo\([^)]+\))?(?:\.bindPopup\((.*?)(?:,\s*\{[^}]*\})?\))?(\.openPopup\(\))?/is', $content, $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$popupHtml = '';
		if (!empty($match[2])) {
			$raw = trim($match[2]);
			if (substr($raw, 0, 1) === '`' && substr($raw, -1) === '`') {
				$popupHtml = substr($raw, 1, -1);
			} else {
				$popupHtml = json_decode($raw, true) ?: trim($raw, "\"'");
			}
		}
		$isOpen = !empty($match[3]);
		$data['points'][] = array(
			'coords' => trim($match[1]),
			'popup' => $popupHtml,
			'showPopup' => $isOpen,
			'color' => '#2A81CB'
		);
	}

	return $data;
}

function mapCreator()
{
	global $SITEURL;
	$folder = GSDATAOTHERPATH . '/mapCreator/';
	if (!file_exists($folder)) {
		mkdir($folder, 0755, true);
	}

	# Save/Update handler
	if (isset($_POST['save_map'])) {
		$mapName = preg_replace('/[^a-zA-Z0-9_]/', '', lcfirst(ucwords($_POST['namemap'])));
		$heightChoice = $_POST['height_preset'];
		$aspectRatio = 'none';
		$finalHeight = '400px';

		if (strpos($heightChoice, 'ratio:') === 0) {
			$aspectRatio = str_replace('ratio:', '', $heightChoice);
		} else {
			$numeric = is_numeric($_POST['height_val']) ? $_POST['height_val'] : '400';
			$unit = in_array($heightChoice, array('px', 'vh')) ? $heightChoice : 'px';
			$finalHeight = $numeric . $unit;
		}

		$points = array();
		if (isset($_POST['point_uids']) && is_array($_POST['point_uids'])) {
			foreach ($_POST['point_uids'] as $uid) {
				if (!isset($_POST['point_coords'][$uid])) continue;
				$coord = $_POST['point_coords'][$uid];
				$cleaned = preg_replace('/[^0-9.,\- ]/', '', $coord);
				if (empty($cleaned)) continue;

				$showPopup = isset($_POST['point_showpopup'][$uid]) && $_POST['point_showpopup'][$uid] === '1';

				$points[] = array(
					'coords' => $cleaned,
					'color' => isset($_POST['point_color'][$uid]) ? $_POST['point_color'][$uid] : '#2A81CB',
					'showPopup' => $showPopup,
					'popup' => isset($_POST['point_popup'][$uid]) ? $_POST['point_popup'][$uid] : ''
				);
			}
		}

		$mapPayload = array(
			'name' => $mapName,
			'center' => preg_replace('/[^0-9.,\- ]/', '', $_POST['localization']),
			'zoom' => intval($_POST['zoom']),
			'height' => $finalHeight,
			'aspectRatio' => $aspectRatio,
			'mousescroll' => ($_POST['mousescroll'] === 'true') ? 'true' : 'false',
			'clustering' => ($_POST['clustering'] === 'true') ? 'true' : 'false',
			'points' => $points
		);

		if (file_exists($folder . $mapName . '.txt')) {
			unlink($folder . $mapName . '.txt');
		}

		file_put_contents($folder . $mapName . '.json', json_encode($mapPayload, JSON_PRETTY_PRINT));
		echo "<meta http-equiv='refresh' content='0'>";
		exit;
	}

	# Delete Map
	if (isset($_POST['deletemap'])) {
		$target = $_POST['map_file'];
		if (file_exists($target)) {
			unlink($target);
		}
		echo "<meta http-equiv='refresh' content='0'>";
		exit;
	}

	# Edit selection
	$editingMap = null;
	$editingData = null;
	if (isset($_GET['edit'])) {
		$editingMap = basename($_GET['edit']);
		$jsonPath = $folder . $editingMap . '.json';
		$txtPath = $folder . $editingMap . '.txt';

		if (file_exists($jsonPath)) {
			$editingData = json_decode(file_get_contents($jsonPath), true);
		} elseif (file_exists($txtPath)) {
			$editingData = parseLegacyMapTxt(file_get_contents($txtPath));
			$editingData['name'] = $editingMap;
		}
	}

	echo '<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
	<style>
		.mc-container { background: #fafafa; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; box-sizing: border-box; }
		.mc-input { padding: 6px; width: 100%; box-sizing: border-box; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 3px; }
		.mc-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
		.mc-card { position: relative; background: #fff; padding: 12px; border: 1px solid #e0e0e0; margin-top: 10px; }
		.mc-badge { background: #003865; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; }

		/* Autocomplete Dropdown Styles */
		.mc-autocomplete-wrapper { position: relative; flex: 1; }
		.mc-autocomplete-list { 
			position: absolute; 
			top: 100%; 
			left: 0; 
			right: 0; 
			background: #fff; 
			border: 1px solid #ccc; 
			border-top: none; 
			z-index: 1000; 
			max-height: 200px; 
			overflow-y: auto; 
			display: none; 
			box-shadow: 0 4px 8px rgba(0,0,0,0.15); 
		}
		.mc-autocomplete-item { 
			padding: 8px 10px; 
			font-size: 12px; 
			cursor: pointer; 
			border-bottom: 1px solid #f0f0f0; 
			line-height: 1.4;
		}
		.mc-autocomplete-item:hover, .mc-autocomplete-item.active { background: #003865; color: #fff; }
	</style>';

	echo '<h3>' . ($editingData ? i18n_r('mapCreator/LANG_Edit_Map') . ': ' . htmlspecialchars($editingMap) : i18n_r('mapCreator/LANG_Create_Map')) . ' 📍</h3>';
	?>

	<!-- Visual Coordinate Picker -->
	<div class="mc-container">
		<h4 style="margin: 0 0 8px 0;"><?php echo i18n_r('mapCreator/LANG_Visual_Picker_Title'); ?></h4>
		<p style="font-size:12px; margin: 0 0 10px 0; color:#555;"><?php echo i18n_r('mapCreator/LANG_Visual_Picker_Desc'); ?></p>
		
		<!-- Address Geocoding Search Bar with Country Code Prioritization & Autocomplete -->
		<div style="display:flex; gap:6px; margin-bottom:6px; align-items:center;">
			<div class="mc-autocomplete-wrapper">
				<input type="text" id="pickerSearchInput" placeholder="<?php echo addslashes(i18n_r('mapCreator/LANG_Search_Address_Placeholder')); ?>" class="mc-input" style="margin-bottom:0;" autocomplete="off">
				<div id="pickerAutocompleteList" class="mc-autocomplete-list"></div>
			</div>
			<button type="button" id="btnPickerSearch" style="padding: 6px 14px; cursor:pointer; background:#003865; color:#fff; border:none; border-radius:3px;">
				<i class="uil uil-search"></i> <?php echo i18n_r('mapCreator/LANG_Search_Btn'); ?>
			</button>
		</div>

		<div style="margin-bottom:10px; display:flex; align-items:center; gap:8px;">
			<label style="font-size:11px; color:#555;"><?php echo i18n_r('mapCreator/LANG_Search_Country_Filter'); ?></label>
			<input type="text" id="pickerCountryCode" placeholder="es" maxlength="10" style="padding:4px 6px; width:70px; font-size:11px; border:1px solid #ccc; border-radius:3px; text-transform:lowercase;">
		</div>

		<div id="visualPickerMap" style="width:100%; height:260px; border:1px solid #ccc;"></div>
		<div style="margin-top: 8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
			<span id="pickerCoordsLabel" style="font-size: 13px; font-weight: bold; color: #D61C4E;"><?php echo i18n_r('mapCreator/LANG_Picker_Click_Map'); ?></span>
			<button type="button" id="btnUseCenter" style="padding: 5px 10px; cursor:pointer;"><?php echo i18n_r('mapCreator/LANG_Use_As_Center'); ?></button>
			<button type="button" id="btnDropPoint" style="padding: 5px 10px; cursor:pointer;"><?php echo i18n_r('mapCreator/LANG_Add_As_Point'); ?></button>
			<button type="button" id="btnLocateGps" style="padding: 5px 10px; cursor:pointer; background:#003865; color:#fff; border:none; border-radius:3px;">
				<i class="uil uil-location-pin-alt"></i> <?php echo i18n_r('mapCreator/LANG_Locate_Me'); ?>
			</button>
		</div>
	</div>

	<!-- Map Configuration Form -->
	<form method="POST" action="load.php?id=mapCreator" class="mc-container">
		<input type="hidden" name="save_map" value="1">
		
		<label><b><?php echo i18n_r('mapCreator/LANG_Map_Name'); ?></b></label>
		<input type="text" name="namemap" required value="<?php echo htmlspecialchars($editingData ? $editingData['name'] : ''); ?>" class="mc-input" <?php echo $editingData ? 'readonly' : ''; ?>>

		<div class="mc-row">
			<div style="flex: 2;">
				<label><b><?php echo i18n_r('mapCreator/LANG_Map_Center'); ?></b></label>
				<input type="text" id="mapCenterInput" name="localization" required value="<?php echo htmlspecialchars($editingData ? $editingData['center'] : '51.505, -0.09'); ?>" class="mc-input">
			</div>
			<div style="flex: 1;">
				<label><b><?php echo i18n_r('mapCreator/LANG_Map_Zoom'); ?></b></label>
				<input type="number" id="mapZoomInput" name="zoom" min="1" max="19" required value="<?php echo htmlspecialchars($editingData ? $editingData['zoom'] : 13); ?>" class="mc-input">
			</div>
		</div>

		<div class="mc-row">
			<div style="flex: 1;">
				<label><b><?php echo i18n_r('mapCreator/LANG_Height_Preset'); ?></b></label>
				<select name="height_preset" id="height_preset" class="mc-input" onchange="toggleHeightInput(this.value)">
					<?php 
						$aspect = isset($editingData['aspectRatio']) ? $editingData['aspectRatio'] : 'none';
						$currHeight = isset($editingData['height']) ? $editingData['height'] : '400px';
						$currUnit = preg_replace('/[0-9]/', '', $currHeight) ?: 'px';
						if (!in_array($currUnit, array('px', 'vh'))) {
							$currUnit = 'px';
						}
					?>
					<option value="px" <?php echo ($aspect === 'none' && $currUnit === 'px') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Fixed_Pixels'); ?></option>
					<option value="vh" <?php echo ($aspect === 'none' && $currUnit === 'vh') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Viewport_Height'); ?></option>
					<option value="ratio:16:9" <?php echo ($aspect === '16:9') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Ratio_16_9'); ?></option>
					<option value="ratio:4:3" <?php echo ($aspect === '4:3') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Ratio_4_3'); ?></option>
					<option value="ratio:1:1" <?php echo ($aspect === '1:1') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Ratio_1_1'); ?></option>
				</select>
			</div>
			<div style="flex: 1;" id="heightValContainer">
				<label><b><?php echo i18n_r('mapCreator/LANG_Height_Magnitude'); ?></b></label>
				<input type="number" name="height_val" id="height_val" value="<?php echo preg_replace('/[^0-9]/', '', $currHeight) ?: '400'; ?>" class="mc-input">
			</div>
			<div style="flex: 1;">
				<label><b><?php echo i18n_r('mapCreator/LANG_Mouse_Scroll'); ?></b></label>
				<select name="mousescroll" class="mc-input">
					<option value="true" <?php echo (isset($editingData['mousescroll']) && $editingData['mousescroll'] === 'true') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Enabled'); ?></option>
					<option value="false" <?php echo (!isset($editingData['mousescroll']) || $editingData['mousescroll'] === 'false') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Disabled'); ?></option>
				</select>
			</div>
			<div style="flex: 1;">
				<label><b><?php echo i18n_r('mapCreator/LANG_Clustering'); ?></b></label>
				<select name="clustering" class="mc-input">
					<option value="true" <?php echo (!isset($editingData['clustering']) || $editingData['clustering'] === 'true') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Enabled'); ?></option>
					<option value="false" <?php echo (isset($editingData['clustering']) && $editingData['clustering'] === 'false') ? 'selected' : ''; ?>><?php echo i18n_r('mapCreator/LANG_Disabled'); ?></option>
				</select>
			</div>
		</div>

		<hr style="border:0; border-top:1px solid #e0e0e0; margin:15px 0;">

		<div style="display:flex; justify-content:space-between; align-items:center;">
			<b><?php echo i18n_r('mapCreator/LANG_Map_Points'); ?></b>
			<button type="button" class="addnewpoint" style="background:#FFAD33; color:#fff; border:none; padding:6px 12px; cursor:pointer; border-radius:4px;">
				<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="1.2em" height="1.2em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M9 11.5A2.5 2.5 0 0 0 11.5 9A2.5 2.5 0 0 0 9 6.5A2.5 2.5 0 0 0 6.5 9A2.5 2.5 0 0 0 9 11.5M9 2c3.86 0 7 3.13 7 7c0 5.25-7 13-7 13S2 14.25 2 9a7 7 0 0 1 7-7m6 15h3v-3h2v3h3v2h-3v3h-2v-3h-3z"/></svg> <?php echo i18n_r('mapCreator/LANG_Add_Point'); ?>
			</button>
		</div>

		<div class="nextpoint" id="pointsContainer"></div>

		<div style="margin-top: 20px;">
			<button type="submit" style="background:#4CAF50; color:#fff; padding:10px 18px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; border-radius:4px; font-weight:600;">
				<?php if ($editingData): ?>
					<!-- Update icon: pencil -->
					<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="1.5em" height="1.5em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M7.5 2.717L2 5.926V21.5l5.5-2.267zm2 16.193l1 .59v-.955l4-4V5.09l-5-2.953zM22 2.5v8.97l-2.213-2.212l-3.287 3.287V4.767zm1.914 13.714l-4.127-4.128l-7.287 7.287l.001 4.127h4.127zm-6.463 1.036l1.299 1.3l-2.95 2.95h-1.3v-1.3z"/></svg>
				<?php else: ?>
					<!-- Update icon: add -->
					<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="1.5em" height="1.5em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M7.5 2.717L2 5.926V21.5l5.5-2.267zm2 16.193l3.245 1.916A6.47 6.47 0 0 1 11.5 17a6.5 6.5 0 0 1 3-5.478V5.09l-5-2.953zM22 2.5v9.376a6.52 6.52 0 0 0-5.5-1.202V4.767z"/><path fill="currentColor" d="M19 16v-4h-2v4h-4v2h4v4h2v-4h4v-2z"/></svg>
				<?php endif; ?>

				<?php echo $editingData
					? i18n_r('mapCreator/LANG_Update_Map')
					: i18n_r('mapCreator/LANG_Create_Map'); ?>
			</button>
			<?php if ($editingData): ?>
				<a href="load.php?id=mapCreator" style="margin-left: 10px; color:#555; text-decoration:none;"><?php echo i18n_r('mapCreator/LANG_Cancel_Edit'); ?></a>
			<?php endif; ?>
		</div>
	</form>

	<script src="<?php echo $SITEURL; ?>admin/template/js/ckeditor/ckeditor.js"></script>
	<script>
		let activeCoords = null;

		function toggleHeightInput(val) {
			document.getElementById('heightValContainer').style.display = val.startsWith('ratio:') ? 'none' : 'block';
		}
		toggleHeightInput(document.getElementById('height_preset').value);

		// Admin Visual Picker initialization
		document.addEventListener('DOMContentLoaded', function() {
			const initialCenter = [51.505, -0.09];

			const pickerMap = L.map('visualPickerMap').setView(initialCenter, 5);
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(pickerMap);

			let clickMarker = null;

			function updatePickerCoords(latlng) {
				const lat = latlng.lat.toFixed(5);
				const lng = latlng.lng.toFixed(5);
				activeCoords = lat + ', ' + lng;
				document.getElementById('pickerCoordsLabel').innerText = '<?php echo addslashes(i18n_r('mapCreator/LANG_Picker_Selected')); ?>: ' + activeCoords;
			}

			function setOrMoveMarker(latlng) {
				updatePickerCoords(latlng);
				if (clickMarker) {
					clickMarker.setLatLng(latlng);
				} else {
					clickMarker = L.marker(latlng, { draggable: true }).addTo(pickerMap);
					clickMarker.on('dragend', function(e) {
						updatePickerCoords(e.target.getLatLng());
					});
				}
			}

			// Single-click placement
			pickerMap.on('click', function(e) {
				setOrMoveMarker(e.latlng);
			});

			// Autocomplete & Search Logic with Regional Prioritization
			const searchInput = document.getElementById('pickerSearchInput');
			const countryCodeInput = document.getElementById('pickerCountryCode');
			const autocompleteList = document.getElementById('pickerAutocompleteList');
			let debounceTimer = null;
			let currentResults = [];

			function closeAutocomplete() {
				autocompleteList.style.display = 'none';
				autocompleteList.innerHTML = '';
			}

			function selectResult(item) {
				const lat = parseFloat(item.lat);
				const lng = parseFloat(item.lon);
				const latlng = L.latLng(lat, lng);
				pickerMap.setView(latlng, 15);
				setOrMoveMarker(latlng);
				searchInput.value = item.display_name;
				closeAutocomplete();
			}

			function buildNominatimUrl(query) {
				let url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(query);
				
				// Optional country filter
				const cc = countryCodeInput.value.trim().toLowerCase();
				if (cc) {
					url += '&countrycodes=' + encodeURIComponent(cc);
				}

				// Prioritize current viewport bounds (soft bias: bounded=0)
				const bounds = pickerMap.getBounds();
				const viewbox = [
					bounds.getWest().toFixed(5),
					bounds.getNorth().toFixed(5),
					bounds.getEast().toFixed(5),
					bounds.getSouth().toFixed(5)
				].join(',');
				url += '&viewbox=' + viewbox + '&bounded=0';

				return url;
			}

			function performFetch(query, callback) {
				const url = buildNominatimUrl(query);
				fetch(url)
					.then(res => res.json())
					.then(data => callback(data || []))
					.catch(() => callback([]));
			}

			// Debounced input watcher for suggestions
			searchInput.addEventListener('input', function() {
				const val = this.value.trim();
				clearTimeout(debounceTimer);
				if (val.length < 3) {
					closeAutocomplete();
					return;
				}

				debounceTimer = setTimeout(() => {
					performFetch(val, function(results) {
						currentResults = results;
						autocompleteList.innerHTML = '';
						if (results.length === 0) {
							const noRes = document.createElement('div');
							noRes.className = 'mc-autocomplete-item';
							noRes.style.color = '#888';
							noRes.innerText = '<?php echo addslashes(i18n_r('mapCreator/LANG_Search_No_Results')); ?>';
							autocompleteList.appendChild(noRes);
						} else {
							results.forEach((item) => {
								const div = document.createElement('div');
								div.className = 'mc-autocomplete-item';
								div.innerText = item.display_name;
								div.addEventListener('click', () => selectResult(item));
								autocompleteList.appendChild(div);
							});
						}
						autocompleteList.style.display = 'block';
					});
				}, 350);
			});

			// Dismiss autocomplete when clicking outside
			document.addEventListener('click', function(e) {
				if (!searchInput.contains(e.target) && !autocompleteList.contains(e.target)) {
					closeAutocomplete();
				}
			});

			// Manual search button / Enter key fallback
			function submitManualSearch() {
				const query = searchInput.value.trim();
				if (!query) return;
				performFetch(query, function(results) {
					if (results.length > 0) {
						selectResult(results[0]);
					} else {
						alert('<?php echo addslashes(i18n_r('mapCreator/LANG_Search_No_Results')); ?>');
					}
				});
			}

			document.getElementById('btnPickerSearch').addEventListener('click', submitManualSearch);
			searchInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					submitManualSearch();
				}
			});

			// Browser Geolocation
			document.getElementById('btnLocateGps').addEventListener('click', function() {
				if (!navigator.geolocation) {
					alert('<?php echo addslashes(i18n_r('mapCreator/LANG_Geolocation_Fail')); ?>');
					return;
				}
				navigator.geolocation.getCurrentPosition(function(pos) {
					const latlng = L.latLng(pos.coords.latitude, pos.coords.longitude);
					pickerMap.setView(latlng, 15);
					setOrMoveMarker(latlng);
				}, function(err) {
					alert('<?php echo addslashes(i18n_r('mapCreator/LANG_Geolocation_Fail')); ?>');
				}, { enableHighAccuracy: true, timeout: 8000 });
			});

			document.getElementById('btnUseCenter').addEventListener('click', function() {
				if (activeCoords) {
					document.getElementById('mapCenterInput').value = activeCoords;
					document.getElementById('mapZoomInput').value = pickerMap.getZoom();
				}
			});

			document.getElementById('btnDropPoint').addEventListener('click', function() {
				if (activeCoords) {
					appendPointRow({ coords: activeCoords, color: '#2A81CB', showPopup: true, popup: '' });
				}
			});

			const preloadedPoints = <?php echo json_encode(isset($editingData['points']) ? $editingData['points'] : array()); ?>;
			if (preloadedPoints.length > 0) {
				preloadedPoints.forEach(pt => appendPointRow(pt));
			}
		});

		function appendPointRow(data = {}) {
			const uid = 'pt_' + Math.random().toString(36).substr(2, 9);
			const coords = data.coords || '';
			const color = data.color || '#2A81CB';
			const showPopup = (data.showPopup === true || data.showPopup === 'true' || data.showPopup === 1 || data.showPopup === '1') ? 'checked' : '';
			const popup = data.popup || '';

			const colors = [
				{ name: '<?php echo addslashes(i18n_r('mapCreator/LANG_Color_Default_Blue')); ?>', val: '#2A81CB' },
				{ name: '<?php echo addslashes(i18n_r('mapCreator/LANG_Color_Crimson_Red')); ?>', val: '#D61C4E' },
				{ name: '<?php echo addslashes(i18n_r('mapCreator/LANG_Color_Forest_Green')); ?>', val: '#2E7D32' },
				{ name: '<?php echo addslashes(i18n_r('mapCreator/LANG_Color_Vibrant_Orange')); ?>', val: '#EF6C00' },
				{ name: '<?php echo addslashes(i18n_r('mapCreator/LANG_Color_Royal_Violet')); ?>', val: '#6A1B9A' },
				{ name: '<?php echo addslashes(i18n_r('mapCreator/LANG_Color_Dark_Slate')); ?>', val: '#37474F' }
			];

			let colorOptions = '';
			colors.forEach(c => {
				colorOptions += `<option value="${c.val}" ${c.val === color ? 'selected' : ''}>${c.name}</option>`;
			});

			const rowHtml = `
				<div class='mc-card' id='point_box_${uid}'>
					<input type='hidden' name='point_uids[]' value='${uid}'>
					<div style="margin-bottom:20px"><button class='closeitem' style='position:absolute; right:10px; top:10px; background:#D61C4E; color:#fff; border:none; padding:4px 8px; cursor:pointer; border-radius:4px;' onclick='event.preventDefault(); document.getElementById("point_box_${uid}").remove();'>&times;</button></div>
					<div class='mc-row'>
						<div style='flex:2;'>
							<label><?php echo addslashes(i18n_r('mapCreator/LANG_Marker_Coordinates')); ?>:</label>
							<input type='text' name='point_coords[${uid}]' value='${coords}' required class='mc-input' style='margin-bottom:0;'>
						</div>
						<div style='flex:1;'>
							<label><?php echo addslashes(i18n_r('mapCreator/LANG_Pin_Color')); ?>:</label>
							<select name='point_color[${uid}]' class='mc-input' style='margin-bottom:0;'>
								${colorOptions}
							</select>
						</div>
					</div>
					<div style='margin: 8px 0;'>
						<label><input type='checkbox' name='point_showpopup[${uid}]' value='1' ${showPopup}> <b><?php echo addslashes(i18n_r('mapCreator/LANG_Show_Popup_Default')); ?></b> <?php echo addslashes(i18n_r('mapCreator/LANG_Popup_Hint')); ?></label>
					</div>
					<label><?php echo addslashes(i18n_r('mapCreator/LANG_Popup_Content')); ?>:</label>
					<textarea name='point_popup[${uid}]' id='popup_editor_${uid}'>${popup}</textarea>
				</div>`;

			document.getElementById('pointsContainer').insertAdjacentHTML('beforeend', rowHtml);

			CKEDITOR.replace('popup_editor_' + uid, {
				filebrowserBrowseUrl: 'filebrowser.php?type=all',
				filebrowserImageBrowseUrl: 'filebrowser.php?type=images',
				toolbar: 'basic',
				height: 100
			});
		}

		document.querySelector('.addnewpoint').addEventListener('click', e => {
			e.preventDefault();
			appendPointRow();
		});
	</script>

	<!-- Saved Maps List & Live Preview -->
	<h3 style="margin-top: 30px;"><?php echo i18n_r('mapCreator/LANG_Saved_Maps'); ?></h3>
	<?php
	$files = array_merge(glob($folder . '*.json'), glob($folder . '*.txt'));
	$processed = array();

	if (!empty($files)) {
		foreach ($files as $filepath) {
			$filename = pathinfo($filepath, PATHINFO_FILENAME);
			if (in_array($filename, $processed)) continue;
			$processed[] = $filename;

			$isJson = file_exists($folder . $filename . '.json');
			if ($isJson) {
				$mdata = json_decode(file_get_contents($folder . $filename . '.json'), true);
			} else {
				$mdata = parseLegacyMapTxt(file_get_contents($folder . $filename . '.txt'));
				$mdata['name'] = $filename;
			}

			echo "<div class='mc-container'>";
			echo "<div style='display:flex; justify-content:space-between; align-items:center;'>";
			echo "<h3 style='margin:0;'>" . htmlspecialchars($filename) . " " . ($isJson ? "<span class='mc-badge'>JSON</span>" : "<span class='mc-badge' style='background:#f57c00;'>Legacy TXT</span>") . "</h3>";
			echo "<div>";
			echo "<a href='load.php?id=mapCreator&edit=" . urlencode($filename) . "' style='font-size:14px; background:#FFAD33; color:#fff; padding:6px 10px; text-decoration:none; margin-right:5px; border-radius:4px;'><svg xmlns='http://www.w3.org/2000/svg' style='vertical-align:middle' width='1.2em' height='1.2em' viewBox='0 0 24 24'><path d='M0 0h24v24H0z' fill='none'/><path fill='currentColor' d='M7.5 2.717L2 5.926V21.5l5.5-2.267zm2 16.193l1 .59v-.955l4-4V5.09l-5-2.953zM22 2.5v8.97l-2.213-2.212l-3.287 3.287V4.767zm1.914 13.714l-4.127-4.128l-7.287 7.287l.001 4.127h4.127zm-6.463 1.036l1.299 1.3l-2.95 2.95h-1.3v-1.3z'/></svg> " . i18n_r('mapCreator/LANG_Edit') . "</a>";
			echo "<form method='POST' action='load.php?id=mapCreator' style='display:inline;' onsubmit='return confirm(\"" . addslashes(i18n_r('mapCreator/LANG_Are_You_Sure')) . "\");'>";
			echo "<input type='hidden' name='map_file' value='" . htmlspecialchars($filepath) . "'>";
			echo "<button type='submit' name='deletemap' value='1' style='font-size:14px; background:#D61C4E!important; color:#fff; border:none; padding:6px 10px; cursor:pointer; border-radius:4px; display:inline-flex; align-items:center; gap:6px;'><svg xmlns='http://www.w3.org/2000/svg' style='vertical-align:middle'  width='1.2em' height='1.2em' viewBox='0 0 24 24'><path d='M0 0h24v24H0z' fill='none'/><path fill='currentColor' d='M6.5 2.717L1 5.926V21.5l5.5-2.267zm2 16.193l5 2.953v-.173a6.48 6.48 0 0 1-2-4.69a6.48 6.48 0 0 1 2-4.69V5.09l-5-2.952zM21 2.5v8.732a6.5 6.5 0 0 0-3-.732a6.5 6.5 0 0 0-2.5.498v-6.23zm-.171 10.257L18 15.586l-2.828-2.829l-1.414 1.415L16.586 17l-2.828 2.828l1.414 1.415L18 18.414l2.829 2.829l1.414-1.415L19.415 17l2.828-2.828z'/></svg>" . i18n_r('mapCreator/LANG_Delete_Map') . "</button>";
			echo "</form>";
			echo "</div>";
			echo "</div>";

			echo "<p style='margin: 8px 0;'>" . i18n_r('mapCreator/LANG_Add') . " <code style='color:blue;'>&lt;?php getMapCreator('" . $filename . "'); ?&gt;</code> " . i18n_r('mapCreator/LANG_In_Template') . " <code style='color:green'>[% mapcreator=" . $filename . " %]</code> " . i18n_r('mapCreator/LANG_In_Editor') . ".</p>";

			echo "<div style='border:1px solid #ccc; margin-top:10px; overflow:hidden;'>";
			echo renderMapHtmlAndScript("preview_" . $filename, $mdata);
			echo "</div>";
			echo "</div>";
		}
	} else {
		echo "<p>" . i18n_r('mapCreator/LANG_No_Maps') . "</p>";
	}

	echo '
		<form action="https://www.paypal.com/cgi-bin/webscr" class="moneyshot" method="post" target="_top" style="display:flex; flex-direction:column; padding: 5px 0 15px; border-radius:4px; box-sizing:border-box; width:100%; align-items:center; justify-content:space-between; background:#D61C4E; color:#fff;">
			<p style="margin:0; padding:10px;">' . i18n_r('mapCreator/LANG_PayPal') . '</p>
			<input type="hidden" name="cmd" value="_s-xclick" />
			<input type="hidden" name="hosted_button_id" value="KFZ9MCBUKB7GL" />
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
			<img alt="" border="0" src="https://www.paypal.com/en_PL/i/scr/pixel.gif" width="1" height="1" />
		</form>
		<div style="width:100%; margin: 15px 0 -25px 0; opacity: 0.5; text-align:center;">
			<p>' . i18n_r('mapCreator/LANG_Based_On') . ' <a href="https://leafletjs.com/" style="text-decoration:none;" target="_blank">leaflet.js</a></p>
		</div>
		';
}

add_action('theme-header', 'pageBeginMapCreator');
function pageBeginMapCreator()
{
	global $content;
	$content = preg_replace_callback('/\[% mapcreator=([a-zA-Z0-9_\-]+) %\]/i', 'getMapCreatorShortcode', $content);
}

function getMapCreator($name)
{
	echo renderMapById($name);
}

function getMapCreatorShortcode($matches)
{
	return renderMapById($matches[1]);
}

function renderMapById($rawName)
{
	$name = basename($rawName);
	$folder = GSDATAOTHERPATH . '/mapCreator/';
	$jsonFile = $folder . $name . '.json';
	$txtFile = $folder . $name . '.txt';

	if (file_exists($jsonFile)) {
		$data = json_decode(file_get_contents($jsonFile), true);
		return renderMapHtmlAndScript("map_" . $name, $data);
	} elseif (file_exists($txtFile)) {
		$legacyData = parseLegacyMapTxt(file_get_contents($txtFile));
		return renderMapHtmlAndScript("map_" . $name, $legacyData);
	}
	return '';
}