<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="charset=utf-8" />
		<style type="text/css">
			body
			{
				font-size: <?php echo $fontsize; ?>;
			}
			footer 
			{ 
				position: fixed; bottom: -10px; left: 0px; right: 0px; height: 50px; text-align: center; font-size: 8pt; color: gray;
			}
			#items
			{
				border: solid 0.5px black; width:100%; position: relative; table-layout: auto;
			}

			#items th
			{
				border: solid 0.5px black; padding: 2px;
			}

			#items td
			{
				border: solid 0.5px black; padding: 2px;
				<?php 
				if ($breakword == 1) {
					echo 'word-wrap: break-word;';
				}
				?>
			}

		</style>
	</head>
	<body>
	<?php 
		if ($islogo == 1) {


			$img_type = pathinfo($logo, PATHINFO_EXTENSION);
			$img_data = file_get_contents($logo);
			$base64 = 'data:image/'.$img_type.';base64,'.base64_encode($img_data);
			echo "<center><img src = '".$base64."' style='display: block; height: 25mm;'></center>";

			// echo '<img src="';
			// echo $logo;
			// echo '" style="display: block; width: 100%; height: 20mm;">';

		}
	?>
		<table style="border: none; width: 100%;">
			<td style="height: 8mm; width: 70%;"><?php echo $prot_num; echo "-"; echo date('dmY'); ?></td>
			<td style="height: 8mm; width: 30%; text-align: right;"><?php echo $city." "; $date=date('d.m.Y'); echo $date; ?></td>
		</table>
		
		<table style="border:none; width: 100%;">
			<tr>
				<td style="border:none; text-align: center; width: 100%; font-size: 15pt;  height: 15mm;">
				<?php echo $title; ?>
				</td>
			</tr>
		</table>
	<br>
	<table>
		<tr>
			<td style="weight:100%;">
	<?php echo $upper_content; ?>
			</td>
		</tr>
	</table>
	<br>
	<table id="items" cellspacing="0">

	<?php
		// Concatenation for sending the result
		$conca = '';
		$lp = 1;

		//if no comments, there is no comments column
		if (empty(array_filter($comments))) {
			
			//if serial and inventory in different columns
			if ($serial_mode == 1) {
				echo '<tr>
				<th></th>
				<th>'; 
				echo __('Type');
				echo "</th><th>";
				echo __('Manufacturer');
				echo "</th><th>";
				echo __('Model');
				echo "</th><th>";
				echo __('Name');				
				echo "</th><th>"; 
				echo __('Serial number'); 
				echo "</th><th>";
				echo __('Inventory number'); 
				echo "</th></tr>";
				
				
				if (isset($number))
				{
					foreach ($number as $key) {

						$conca = '<tr><td>'. $lp . '</td>';
		
						if (isset($type_name[$key])) {
							$conca .= '<td>' . $type_name[$key] .'</td>';
						}

						// if there is manufacturer
						if(isset($man_name[$key]) && !empty($man_name[$key]))
						{

							if(isset($mod_name[$key]))
							{
								$conca .= '<td>'. $man_name[$key] .'</td><td>'. $mod_name[$key]. '</td>';

							}
							else {
								$conca .= '<td>'. $man_name[$key] .'</td><td></td>';
							}
							
						}
						else {
							$conca .= '<td></td><td>'. $mod_name[$key] .'</td>';
						}
		
						if (isset($item_name[$key])) {
							$conca .= '<td>' .$item_name[$key] .'</td>';
						}
		
						if (isset($serial[$key])) {
							$conca .= '<td>'. $serial[$key] .'</td>';
						}
		
						if (isset($otherserial[$key])) {
							$conca .= '<td>'. $otherserial[$key] .'</td>';
						}
		
						echo $conca .= '</tr>';
		
						$lp++;
					}
				}

			}

			//if serial and inventory in one column
			if ($serial_mode == 2) {
				
				echo "<tr>
					<th></th>
					<th>"; 
					echo __('Type');
					echo "</th><th>";
					echo __('Manufacturer');
					echo "</th><th>";
					echo __('Model'); 
					echo "</th><th>"; 
					echo __('Name'); 
					echo "</th><th>";
					echo __('Serial number');
					echo "</th>
				</tr>";

				if (isset($number))
				{
					foreach ($number as $key) {

						if (empty($serial[$key])) {
							$serial[$key]=$otherserial[$key];
						} //if no serial, get inventory number

						$conca = '<tr><td>'. $lp . '</td>';
		
						if (isset($type_name[$key])) {
							$conca .= '<td>' . $type_name[$key] .'</td>';
						}

						// if there is manufacturer
						if(isset($man_name[$key]) && !empty($man_name[$key]))
						{

							if(isset($mod_name[$key]))
							{
								$conca .= '<td>'. $man_name[$key] .'</td><td>'. $mod_name[$key]. '</td>';

							}
							else {
								$conca .= '<td>'. $man_name[$key] .'</td><td></td>';
							}
							
						}
						else {
							$conca .= '<td></td><td>'. $mod_name[$key] .'</td>';
						}
		
						if (isset($item_name[$key])) {
							$conca .= '<td>' .$item_name[$key] .'</td>';
						}
		
						if (isset($serial[$key])) {
							$conca .= '<td>'. $serial[$key] .'</td>';
						}
		
						echo $conca .= '</tr>';
		
						$lp++;
					}
				}

			}

		}
		else {
			//if at least one comment, there will be comment column
			if ($serial_mode == 1) {
				
				echo "<tr>
					<th></th>
					<th>"; 
					echo __('Type');
					echo "</th><th>";
					echo __('Manufacturer');
					echo "</th><th>"; 
					echo __('Model'); 
					echo "</th><th>"; 
					echo __('Name'); 
					echo "</th><th>"; 
					echo __('Serial number'); 
					echo "</th><th>";
					echo __('Inventory number'); 
					echo "</th><th>";
					echo __('Comments'); 
					echo "</th>
				</tr>";
				
				if (isset($number))
				{
					foreach ($number as $key) {

						$conca = '<tr><td>'. $lp . '</td>';
		
						if (isset($type_name[$key])) {
							$conca .= '<td>' . $type_name[$key] .'</td>';
						}
		
						// if there is manufacturer
						if(isset($man_name[$key]) && !empty($man_name[$key]))
						{

							if(isset($mod_name[$key]))
							{
								$conca .= '<td>'. $man_name[$key] .'</td><td>'. $mod_name[$key]. '</td>';

							}
							else {
								$conca .= '<td>'. $man_name[$key] .'</td><td></td>';
							}
							
						}
						else {
							$conca .= '<td></td><td>'. $mod_name[$key] .'</td>';
						}
		
						if (isset($item_name[$key])) {
							$conca .= '<td>' .$item_name[$key] .'</td>';
						}
		
						if (isset($serial[$key])) {
							$conca .= '<td>'. $serial[$key] .'</td>';
						}
		
						if (isset($otherserial[$key])) {
							$conca .= '<td>'. $otherserial[$key] .'</td>';
						}

						if (isset($comments[$key])) {
							$conca .= '<td>'. $comments[$key] .'</td>';
						}
		
						echo $conca .= '</tr>';
		
						$lp++;
					}
				}
			}

			if ($serial_mode == 2) {
				
				echo "<tr>
					<th></th>
					<th>"; 
					echo __('Type');
					echo "</th><th>"; 
					echo __('Manufacturer');
					echo "</th><th>"; 
					echo __('Model'); 
					echo "</th><th>"; 
					echo __('Name'); 
					echo "</th><th>";
					echo __('Serial number'); 
					echo "</th><th>";
					echo __('Comments'); 
					echo "</th>
				</tr>";
				
				
				if (isset($number))
				{
					foreach ($number as $key) {

						if (empty($serial[$key])) {
							$serial[$key]=$otherserial[$key];
						} //if no serial, get inventory number

						$conca = '<tr><td>'. $lp . '</td>';
		
						if (isset($type_name[$key])) {
							$conca .= '<td>' . $type_name[$key] .'</td>';
						}
		
						// if there is manufacturer
						if(isset($man_name[$key]) && !empty($man_name[$key]))
						{

							if(isset($mod_name[$key]))
							{
								$conca .= '<td>'. $man_name[$key] .'</td><td>'. $mod_name[$key]. '</td>';

							}
							else {
								$conca .= '<td>'. $man_name[$key] .'</td><td></td>';
							}
							
						}
						else {
							$conca .= '<td></td><td>'. $mod_name[$key] .'</td>';
						}
		
						if (isset($item_name[$key])) {
							$conca .= '<td>' .$item_name[$key] .'</td>';
						}
		
						if (isset($serial[$key])) {
							$conca .= '<td>'. $serial[$key] .'</td>';
						}

						if (isset($comments[$key])) {
							$conca .= '<td>'. $comments[$key] .'</td>';
						}
		
						echo $conca .= '</tr>';
		
						$lp++;
					}
				}
			}

		}
			

	?>
	</table>

	<br>

	<table>
		<tr>
			<td style="height: 10mm;"></td>
		</tr>
	</table>

	<table>
		<tr>
			<td style="weight:100%;">
	<?php echo $content; ?>
			</td>
		</tr>
	</table>

	<table>
		<tr>
			<td style="height: 20mm;"></td>
		</tr>
	</table>

	<table style="border-collapse: collapse; width: 100%;">
		<tr>
			<td style="width:50%; border-bottom: 1px solid black;"><strong><?php echo __('Administrator').":"; ?></strong></td>
			<td style="width:50%; border-bottom: 1px solid black;"><strong><?php echo __('User').":"; ?></strong></td>
		</tr>
		<tr>
			<td style="border: 1px solid black; width:50%; vertical-align:top; height: 20mm">
			<?php 
			if($author_state == 2) {
				echo $author_name;
			}
			else {
				echo $author;
			}
			?>
			</td>
			<td style="border: 1px solid black; width:50%; vertical-align:top; height: 20mm">
				<?php echo $owner; ?>
			</td>
		</tr>
	</table>

		<footer>
			<?php echo $footer; ?>
		</footer>
	</body>
</html>
